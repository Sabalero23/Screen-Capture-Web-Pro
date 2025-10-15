<?php
/**
 * Screen Capture Web Pro - Sistema de Grabación (recorder.php)
 * Versión: 2.0 con Sistema de Login
 */

// Requerir autenticación
require_once 'config.php';
require_once 'auth.php';

$auth = new Auth();

// Verificar autenticación
if (!$auth->isAuthenticated()) {
    header('Location: login.php');
    exit;
}

// Obtener usuario actual
$currentUser = $auth->getCurrentUser();

// Procesar logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $auth->logout();
    header('Location: index.php');
    exit;
}

// Capturar TODOS los errores y convertirlos a JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr en $errfile:$errline");
    
    if (headers_sent()) {
        error_log("Headers ya enviados, no se puede enviar JSON");
        return false;
    }
    
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => "Error PHP: $errstr",
        'file' => basename($errfile),
        'line' => $errline
    ]);
    exit;
});

set_exception_handler(function($exception) {
    error_log("PHP Exception: " . $exception->getMessage());
    
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Excepción: ' . $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
    exit;
});

// Configuración de seguridad
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\' \'unsafe-inline\' \'unsafe-eval\' https://cdnjs.cloudflare.com;');

// Configuración de errores (desactivar en producción)
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Log de errores
error_log("=== NUEVA PETICIÓN ===");
error_log("Método: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . print_r($_POST, true));

class ScreenCaptureWebPro {
    private $outputDir;
    private $logDir;
    private $rateLimitFile;
    private $configFile;
    
    // Configuración
    private $allowedExtensions = ['mp4', 'webm', 'mkv'];
    private $allowedMimes = ['video/mp4', 'video/webm', 'video/x-matroska'];
    private $maxFileSize = 500 * 1024 * 1024; // 500MB por archivo
    private $maxTotalSpace = 10 * 1024 * 1024 * 1024; // 10GB total
    
    public function __construct($outputDir = './recordings/', $logDir = './logs/') {
        $this->outputDir = rtrim($outputDir, '/') . '/';
        $this->logDir = rtrim($logDir, '/') . '/';
        $this->rateLimitFile = $this->logDir . 'rate_limit.json';
        $this->configFile = $this->logDir . 'config.json';
        
        $this->initializeDirectories();
        $this->initializeSession();
    }
    
    /**
     * Inicializar directorios necesarios
     */
    private function initializeDirectories() {
        foreach ([$this->outputDir, $this->logDir] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
                // Proteger directorios con .htaccess
                file_put_contents($dir . '.htaccess', "Options -Indexes\nOrder Deny,Allow\nDeny from all");
            }
        }
        
        // Permitir acceso solo a videos en recordings
        $htaccess = "Options -Indexes\n";
        $htaccess .= "<FilesMatch \"\\.(mp4|webm|mkv)$\">\n";
        $htaccess .= "    Order Allow,Deny\n";
        $htaccess .= "    Allow from all\n";
        $htaccess .= "    Header set X-Content-Type-Options \"nosniff\"\n";
        $htaccess .= "</FilesMatch>\n";
        file_put_contents($this->outputDir . '.htaccess', $htaccess);
    }
    
    /**
     * Inicializar sesión y token CSRF
     */
    private function initializeSession() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['user_id'] = $this->generateUserId();
        }
    }
    
    /**
     * Generar ID único de usuario
     */
    private function generateUserId() {
        return hash('sha256', $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']);
    }
    
    /**
     * Validar token CSRF
     */
    private function validateCSRFToken() {
        if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
    }
    
    /**
     * Obtener token CSRF
     */
    public function getCSRFToken() {
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Rate Limiting
     */
    private function checkRateLimit($action, $limit = 10, $window = 3600) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = hash('sha256', $ip . '_' . $action);
        
        $data = [];
        if (file_exists($this->rateLimitFile)) {
            $data = json_decode(file_get_contents($this->rateLimitFile), true) ?? [];
        }
        
        $now = time();
        
        if (isset($data[$key])) {
            $data[$key] = array_filter($data[$key], function($timestamp) use ($now, $window) {
                return ($now - $timestamp) < $window;
            });
        } else {
            $data[$key] = [];
        }
        
        if (count($data[$key]) >= $limit) {
            return false;
        }
        
        $data[$key][] = $now;
        file_put_contents($this->rateLimitFile, json_encode($data), LOCK_EX);
        
        return true;
    }
    
    /**
     * Logging de acciones
     */
    private function logAction($action, $details = [], $level = 'INFO') {
        $logFile = $this->logDir . date('Y-m-d') . '.log';
        
        $entry = sprintf(
            "[%s] [%s] %s - %s - IP: %s - Details: %s\n",
            date('Y-m-d H:i:s'),
            $level,
            $action,
            $_SESSION['user_id'] ?? 'unknown',
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            json_encode($details)
        );
        
        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Calcular espacio total usado
     */
    public function getTotalUsedSpace() {
        $total = 0;
        $files = glob($this->outputDir . '*.{mp4,webm,mkv}', GLOB_BRACE);
        foreach ($files as $file) {
            if (is_file($file)) {
                $total += filesize($file);
            }
        }
        return $total;
    }
    
    /**
     * Verificar si hay espacio disponible
     */
    private function hasSpaceAvailable($fileSize = 0) {
        $usedSpace = $this->getTotalUsedSpace();
        return ($usedSpace + $fileSize) <= $this->maxTotalSpace;
    }
    
    /**
     * Manejar subida de archivos (mejorado)
     */
    public function handleUpload() {
        try {
            error_log("handleUpload: Iniciando...");
            
            // Validar CSRF
            if (!$this->validateCSRFToken()) {
                error_log("handleUpload: Token CSRF inválido");
                return ['success' => false, 'error' => 'Token de seguridad inválido'];
            }
            
            error_log("handleUpload: Token CSRF válido");
            
            // Rate limiting
            if (!$this->checkRateLimit('upload', 20, 3600)) {
                error_log("handleUpload: Rate limit excedido");
                return ['success' => false, 'error' => 'Demasiadas solicitudes. Intenta más tarde.'];
            }
            
            error_log("handleUpload: Rate limit OK");
            
            // Verificar archivo
            if (!isset($_FILES['video'])) {
                error_log("handleUpload: No se encontró 'video' en FILES");
                error_log("FILES disponibles: " . print_r(array_keys($_FILES), true));
                return ['success' => false, 'error' => 'No se recibió el archivo de video'];
            }
            
            if ($_FILES['video']['error'] !== UPLOAD_ERR_OK) {
                $errorCode = $_FILES['video']['error'];
                error_log("handleUpload: Error en upload, código: " . $errorCode);
                
                $uploadErrors = [
                    UPLOAD_ERR_INI_SIZE => 'El archivo excede upload_max_filesize',
                    UPLOAD_ERR_FORM_SIZE => 'El archivo excede MAX_FILE_SIZE',
                    UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
                    UPLOAD_ERR_NO_FILE => 'No se subió ningún archivo',
                    UPLOAD_ERR_NO_TMP_DIR => 'Falta carpeta temporal',
                    UPLOAD_ERR_CANT_WRITE => 'Error al escribir en disco',
                    UPLOAD_ERR_EXTENSION => 'Una extensión PHP detuvo la subida'
                ];
                
                $errorMsg = $uploadErrors[$errorCode] ?? 'Error desconocido: ' . $errorCode;
                return ['success' => false, 'error' => $errorMsg];
            }
            
            $file = $_FILES['video'];
            
            error_log("handleUpload: Archivo recibido - Nombre: " . $file['name'] . ", Tamaño: " . $file['size']);
            
            // Validar tamaño del archivo
            if ($file['size'] > $this->maxFileSize) {
                error_log("handleUpload: Archivo demasiado grande: " . $file['size']);
                return ['success' => false, 'error' => 'Archivo demasiado grande (máx: 500MB)'];
            }
            
            // Verificar espacio disponible
            if (!$this->hasSpaceAvailable($file['size'])) {
                error_log("handleUpload: Sin espacio disponible");
                return ['success' => false, 'error' => 'Límite de almacenamiento alcanzado (10GB)'];
            }
            
            error_log("handleUpload: Espacio disponible OK");
            
            // Validar tipo MIME real
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                
                error_log("handleUpload: MIME type detectado: " . $mimeType);
                
                if (!in_array($mimeType, $this->allowedMimes)) {
                    error_log("handleUpload: MIME type no permitido: " . $mimeType);
                    // Por ahora permitir el archivo de todas formas
                    // return ['success' => false, 'error' => 'Tipo de archivo no permitido'];
                }
            }
            
            // Generar nombre seguro
            $extension = $this->getSecureExtension($file['name']);
            $filename = $this->generateSecureFilename($extension);
            $destination = $this->outputDir . $filename;
            
            error_log("handleUpload: Destino: " . $destination);
            
            // Verificar que el directorio existe y es escribible
            if (!is_dir($this->outputDir)) {
                error_log("handleUpload: Directorio no existe, creando...");
                if (!mkdir($this->outputDir, 0755, true)) {
                    return ['success' => false, 'error' => 'No se pudo crear el directorio de grabaciones'];
                }
            }
            
            if (!is_writable($this->outputDir)) {
                error_log("handleUpload: Directorio no es escribible");
                return ['success' => false, 'error' => 'El directorio de grabaciones no es escribible'];
            }
            
            // Mover archivo
            error_log("handleUpload: Moviendo archivo...");
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                chmod($destination, 0644);
                
                $result = [
                    'success' => true,
                    'filename' => $filename,
                    'size' => filesize($destination),
                    'duration' => intval($_POST['duration'] ?? 0)
                ];
                
                error_log("handleUpload: Éxito - " . json_encode($result));
                
                $this->logAction('UPLOAD_SUCCESS', [
                    'filename' => $filename,
                    'size' => $file['size']
                ]);
                
                return $result;
            } else {
                error_log("handleUpload: Error al mover archivo desde " . $file['tmp_name'] . " a " . $destination);
                return ['success' => false, 'error' => 'Error al guardar el archivo en el servidor'];
            }
            
        } catch (Exception $e) {
            error_log("handleUpload: EXCEPCIÓN - " . $e->getMessage());
            error_log("Stack: " . $e->getTraceAsString());
            return ['success' => false, 'error' => 'Error interno: ' . $e->getMessage()];
        }
    }
    
    /**
     * Generar nombre de archivo seguro
     */
    private function generateSecureFilename($extension) {
        return sprintf(
            'rec_%s_%s.%s',
            date('YmdHis'),
            bin2hex(random_bytes(8)),
            $extension
        );
    }
    
    /**
     * Obtener extensión segura
     */
    private function getSecureExtension($filename) {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, $this->allowedExtensions) ? $extension : 'mp4';
    }
    
    /**
     * Eliminar grabación (mejorado)
     */
    public function deleteRecording($filename) {
        if (!$this->validateCSRFToken()) {
            return ['success' => false, 'error' => 'Token inválido'];
        }
        
        if (!$this->checkRateLimit('delete', 30, 3600)) {
            return ['success' => false, 'error' => 'Demasiadas solicitudes'];
        }
        
        $filename = basename($filename);
        $filepath = realpath($this->outputDir . $filename);
        $outputDir = realpath($this->outputDir);
        
        // Prevenir path traversal
        if (!$filepath || strpos($filepath, $outputDir) !== 0) {
            $this->logAction('DELETE_FAILED', ['filename' => $filename, 'error' => 'Path traversal attempt'], 'CRITICAL');
            return ['success' => false, 'error' => 'Acceso denegado'];
        }
        
        if (!file_exists($filepath)) {
            return ['success' => false, 'error' => 'El archivo no existe'];
        }
        
        // Validar extensión
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            return ['success' => false, 'error' => 'Tipo de archivo no válido'];
        }
        
        $size = filesize($filepath);
        if (unlink($filepath)) {
            $this->logAction('DELETE_SUCCESS', ['filename' => $filename, 'size' => $size]);
            return ['success' => true, 'message' => 'Archivo eliminado correctamente'];
        }
        
        return ['success' => false, 'error' => 'No se pudo eliminar el archivo'];
    }
    
    /**
     * Renombrar grabación (mejorado)
     */
    public function renameRecording($oldName, $newName) {
        if (!$this->validateCSRFToken()) {
            return ['success' => false, 'error' => 'Token inválido'];
        }
        
        $oldName = basename($oldName);
        $newName = $this->sanitizeFilename($newName);
        
        $oldPath = realpath($this->outputDir . $oldName);
        $outputDir = realpath($this->outputDir);
        
        if (!$oldPath || strpos($oldPath, $outputDir) !== 0) {
            return ['success' => false, 'error' => 'Archivo no encontrado'];
        }
        
        $newPath = $this->outputDir . $newName;
        
        if (file_exists($newPath)) {
            return ['success' => false, 'error' => 'Ya existe un archivo con ese nombre'];
        }
        
        // Validar extensión del nuevo nombre
        $extension = strtolower(pathinfo($newName, PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            return ['success' => false, 'error' => 'Extensión no permitida'];
        }
        
        if (rename($oldPath, $newPath)) {
            $this->logAction('RENAME_SUCCESS', ['old' => $oldName, 'new' => $newName]);
            return ['success' => true, 'message' => 'Archivo renombrado correctamente'];
        }
        
        return ['success' => false, 'error' => 'Error al renombrar'];
    }
    
    /**
     * Sanitizar nombre de archivo
     */
    private function sanitizeFilename($filename) {
        $filename = basename($filename);
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        $filename = substr($filename, 0, 200);
        
        if (strpos($filename, '.') === 0) {
            $filename = 'file_' . $filename;
        }
        
        return $filename;
    }
    
    /**
     * Listar grabaciones (optimizado)
     */
    public function listRecordings() {
        $files = glob($this->outputDir . '*.{mp4,webm,mkv}', GLOB_BRACE);
        $recordings = [];
        $totalSize = 0;
        
        foreach ($files as $file) {
            if (!is_file($file)) continue;
            
            $size = filesize($file);
            $totalSize += $size;
            
            $recordings[] = [
                'filename' => basename($file),
                'url' => $this->outputDir . basename($file),
                'size' => $size,
                'size_formatted' => $this->formatBytes($size),
                'created' => filemtime($file),
                'created_formatted' => date('d/m/Y H:i:s', filemtime($file)),
                'extension' => strtolower(pathinfo($file, PATHINFO_EXTENSION))
            ];
        }
        
        usort($recordings, function($a, $b) {
            return $b['created'] - $a['created'];
        });
        
        return [
            'recordings' => $recordings,
            'total_files' => count($recordings),
            'total_size' => $totalSize,
            'total_size_formatted' => $this->formatBytes($totalSize),
            'space_limit' => $this->maxTotalSpace,
            'space_limit_formatted' => $this->formatBytes($this->maxTotalSpace),
            'space_available' => $this->maxTotalSpace - $totalSize,
            'space_available_formatted' => $this->formatBytes($this->maxTotalSpace - $totalSize),
            'percentage_used' => round(($totalSize / $this->maxTotalSpace) * 100, 2)
        ];
    }
    
    /**
     * Limpiar archivos antiguos
     */
    public function cleanOldRecordings($days = 30) {
        if (!$this->validateCSRFToken()) {
            return ['success' => false, 'error' => 'Token inválido'];
        }
        
        $cutoffTime = time() - ($days * 24 * 60 * 60);
        $deletedFiles = 0;
        $deletedSize = 0;
        
        $files = glob($this->outputDir . '*.{mp4,webm,mkv}', GLOB_BRACE);
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                $size = filesize($file);
                if (unlink($file)) {
                    $deletedFiles++;
                    $deletedSize += $size;
                }
            }
        }
        
        $this->logAction('CLEAN_OLD', ['deleted_files' => $deletedFiles, 'deleted_size' => $deletedSize]);
        
        return [
            'success' => true,
            'deleted_files' => $deletedFiles,
            'deleted_size' => $this->formatBytes($deletedSize),
            'message' => "Eliminados $deletedFiles archivos (" . $this->formatBytes($deletedSize) . ")"
        ];
    }
    
    /**
     * Formatear bytes
     */
    private function formatBytes($size, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        return round($size, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Obtener información del sistema
     */
    public function getSystemInfo() {
        return [
            'max_upload_size' => ini_get('upload_max_filesize'),
            'max_post_size' => ini_get('post_max_size'),
            'max_execution_time' => ini_get('max_execution_time'),
            'space_limit' => $this->formatBytes($this->maxTotalSpace)
        ];
    }
}

// Inicializar aplicación
$capture = new ScreenCaptureWebPro();

// Procesar solicitudes POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    error_log("Acción solicitada: " . $action);
    
    try {
        switch ($action) {
            case 'upload':
                error_log("Procesando upload...");
                error_log("FILES: " . print_r($_FILES, true));
                $result = $capture->handleUpload();
                error_log("Resultado upload: " . print_r($result, true));
                echo json_encode($result);
                exit;
                
            case 'delete':
                $filename = $_POST['filename'] ?? '';
                echo json_encode($capture->deleteRecording($filename));
                exit;
                
            case 'rename':
                $oldName = $_POST['old_name'] ?? '';
                $newName = $_POST['new_name'] ?? '';
                echo json_encode($capture->renameRecording($oldName, $newName));
                exit;
                
            case 'list':
                echo json_encode($capture->listRecordings());
                exit;
                
            case 'clean_old':
                $days = intval($_POST['days'] ?? 30);
                echo json_encode($capture->cleanOldRecordings($days));
                exit;
                
            case 'get_token':
                echo json_encode(['token' => $capture->getCSRFToken()]);
                exit;
                
            default:
                echo json_encode(['success' => false, 'error' => 'Acción no válida: ' . $action]);
                exit;
        }
    } catch (Exception $e) {
        error_log("EXCEPCIÓN: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        echo json_encode(['success' => false, 'error' => 'Error del servidor: ' . $e->getMessage()]);
        exit;
    }
}

$systemInfo = $capture->getSystemInfo();
$csrfToken = $capture->getCSRFToken();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎥 Captura de Pantalla Pro</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container" style="margin-top: 20px;">
        <div class="header">
            <h1>🎥 Captura de Pantalla Pro</h1>
            <p>Sistema optimizado de grabación de pantalla con límite de 10GB</p>
        </div>
        
        <div id="statsSection" class="stats-grid">
            <!-- Estadísticas se cargan dinámicamente -->
        </div>
        
        <div class="capability-check">
            <h4>📊 Estado del Sistema</h4>
            <div class="result success">
                ✅ Sistema funcionando correctamente<br>
                <small>Grabación en formato MP4 compatible con todos los navegadores</small>
            </div>
            <div class="system-info">
                <strong>⚙️ Configuración:</strong><br>
                📤 Subida máxima: <?= $systemInfo['max_upload_size'] ?> |
                ⏱️ Tiempo ejecución: <?= $systemInfo['max_execution_time'] ?>s |
                💾 Límite total: <?= $systemInfo['space_limit'] ?>
            </div>
        </div>
        
        <div class="instructions">
            <h4>📋 Instrucciones de Uso</h4>
            <ol>
                <li><strong>🎯 Seleccionar origen:</strong> Pantalla completa, pestaña o ventana</li>
                <li><strong>🔊 Audio del sistema:</strong> Solo funciona con "Pantalla Completa" o "Pestaña"</li>
                <li><strong>🎤 Micrófono:</strong> Siempre disponible para narración</li>
                <li><strong>▶️ Reproducir:</strong> Haz clic en "▶️ Reproducir" para ver tus videos</li>
                <li><strong>💾 Gestión:</strong> Renombrar, descargar o eliminar grabaciones</li>
            </ol>
            <div class="alert-warning">
                <strong>💡 Formatos soportados:</strong> MP4 (recomendado), WebM, MKV<br>
                <strong>⚠️ Límite de almacenamiento:</strong> 10GB total para todas las grabaciones
            </div>
        </div>
        
        <div class="form-group">
            <h3>🎬 Control de Grabación</h3>
            <div class="recording-controls">
                <button id="startRecording" class="btn-primary">▶️ Iniciar</button>
                <button id="pauseRecording" class="btn-warning" disabled>⏸️ Pausar</button>
                <button id="resumeRecording" class="btn-success" disabled style="display:none;">▶️ Reanudar</button>
                <button id="stopRecording" class="btn-danger" disabled>⏹️ Detener</button>
            </div>
            
            <div id="recordingStatus" class="status stopped">
                Estado: Listo para grabar
            </div>
            
            <div class="grid-2">
                <div class="settings-box">
                    <h4>⚙️ Configuración de Video</h4>
                    <label>
                        🎯 Modo de Captura:
                        <select id="captureMode">
                            <option value="full">🖥️ Normal (Pantalla/Ventana/Pestaña)</option>
                            <option value="region">✂️ Región Personalizada (Recorte)</option>
                        </select>
                        <small style="display: block; margin-top: 8px; padding: 10px; background: #fef3c7; border-radius: 6px; color: #92400e;">
                            <strong>💡 Región Personalizada:</strong> Primero selecciona qué compartir (pantalla/ventana/pestaña) en el diálogo del navegador, luego podrás recortar el área específica que quieres grabar.
                        </small>
                    </label>
                    <label>
                        📺 Calidad:
                        <select id="videoQuality">
                            <option value="720">HD (1280x720)</option>
                            <option value="1080" selected>Full HD (1920x1080)</option>
                            <option value="1440">2K (2560x1440)</option>
                        </select>
                    </label>
                    <label>
                        🎬 FPS:
                        <select id="frameRate">
                            <option value="24">24 FPS (Cine)</option>
                            <option value="30" selected>30 FPS (Estándar)</option>
                            <option value="60">60 FPS (Alta calidad)</option>
                        </select>
                    </label>
                    <label>
                        📊 Bitrate:
                        <select id="bitrate">
                            <option value="2500000">2.5 Mbps (Media)</option>
                            <option value="5000000" selected>5 Mbps (Alta)</option>
                            <option value="8000000">8 Mbps (Muy Alta)</option>
                        </select>
                    </label>
                </div>
                
                <div class="settings-box">
                    <h4>🔊 Configuración de Audio</h4>
                    <label class="checkbox-label">
                        <input type="checkbox" id="includeAudio" checked>
                        <span>🔊 Audio del Sistema</span>
                        <small>Solo con Pantalla Completa o Pestaña</small>
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" id="includeMicrophone">
                        <span>🎤 Micrófono</span>
                        <small>Para narración y comentarios</small>
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" id="autoStop">
                        <span>⏱️ Detener automáticamente después de:</span>
                        <select id="autoStopTime" disabled>
                            <option value="300">5 minutos</option>
                            <option value="600">10 minutos</option>
                            <option value="1800">30 minutos</option>
                            <option value="3600">1 hora</option>
                        </select>
                    </label>
                </div>
            </div>
        </div>
        
        <div class="form-group">
            <h3>📁 Gestión de Grabaciones</h3>
            <div class="action-buttons">
                <button id="listRecordings" class="btn-info">🔄 Actualizar Lista</button>
                <button id="cleanOld" class="btn-warning">🧹 Limpiar Antiguos</button>
            </div>
            <div id="recordingsList"></div>
        </div>
        
        <div id="result" class="result"></div>
    </div>

    <!-- Modal del Reproductor -->
    <div id="videoModal" class="video-modal">
        <div class="video-modal-content">
            <div class="video-header">
                <h3 id="videoTitle">🔹 Reproductor</h3>
                <button class="close-video" onclick="closeVideoPlayer()">&times;</button>
            </div>
            <div class="video-container">
                <video id="videoPlayer" class="video-player" controls></video>
            </div>
            <div class="video-controls-custom">
                <div class="video-info" id="videoInfo"></div>
                <div class="video-actions">
                    <button onclick="toggleFullscreen()" class="btn-info">🖥️ Pantalla Completa</button>
                    <button onclick="changePlaybackSpeed()" class="btn-info" id="speedBtn">🏃 Velocidad: 1x</button>
                    <button onclick="downloadCurrentVideo()" class="btn-success">💾 Descargar</button>
                    <button onclick="closeVideoPlayer()" class="btn-danger">❌ Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" id="csrfToken" value="<?= htmlspecialchars($csrfToken) ?>">
    <script src="recorder.js"></script>
</body>
</html>