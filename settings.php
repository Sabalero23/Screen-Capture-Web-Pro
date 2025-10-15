<?php
/**
 * Página de Configuración
 * Screen Capture Pro v2.0
 */

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
$db = getDB();

$error = '';
$success = '';

// Obtener configuración actual del usuario
$configFile = DATA_DIR . 'user_config_' . $currentUser['id'] . '.json';
$defaultConfig = [
    'video_quality' => '1080',
    'frame_rate' => '30',
    'bitrate' => '5000000',
    'include_audio' => true,
    'include_microphone' => false,
    'auto_stop' => false,
    'auto_stop_time' => '600',
    'capture_mode' => 'full',
    'theme' => 'light',
    'notifications' => true,
    'auto_save' => true
];

// Cargar configuración existente o usar defaults
if (file_exists($configFile)) {
    $userConfig = json_decode(file_get_contents($configFile), true);
    $userConfig = array_merge($defaultConfig, $userConfig);
} else {
    $userConfig = $defaultConfig;
}

// Procesar actualización de configuración
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_settings') {
        $newConfig = [
            'video_quality' => $_POST['video_quality'] ?? '1080',
            'frame_rate' => $_POST['frame_rate'] ?? '30',
            'bitrate' => $_POST['bitrate'] ?? '5000000',
            'include_audio' => isset($_POST['include_audio']),
            'include_microphone' => isset($_POST['include_microphone']),
            'auto_stop' => isset($_POST['auto_stop']),
            'auto_stop_time' => $_POST['auto_stop_time'] ?? '600',
            'capture_mode' => $_POST['capture_mode'] ?? 'full',
            'theme' => $_POST['theme'] ?? 'light',
            'notifications' => isset($_POST['notifications']),
            'auto_save' => isset($_POST['auto_save'])
        ];
        
        if (file_put_contents($configFile, json_encode($newConfig, JSON_PRETTY_PRINT))) {
            $success = '✅ Configuración guardada correctamente';
            $userConfig = $newConfig;
        } else {
            $error = 'Error al guardar la configuración';
        }
    } elseif ($_POST['action'] === 'reset_settings') {
        if (file_exists($configFile)) {
            unlink($configFile);
        }
        $userConfig = $defaultConfig;
        $success = '✅ Configuración restaurada a valores por defecto';
    }
}

// Obtener estadísticas del usuario
$recordingsDir = RECORDINGS_DIR;
$userFiles = glob($recordingsDir . '*.{mp4,webm,mkv}', GLOB_BRACE);
$totalFiles = count($userFiles);
$totalSize = 0;
foreach ($userFiles as $file) {
    if (is_file($file)) {
        $totalSize += filesize($file);
    }
}

function formatBytes($size, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, $precision) . ' ' . $units[$i];
}

// Procesar logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $auth->logout();
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración - Screen Capture Pro</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            background: linear-gradient(135deg, #e0f2fe 0%, #ddd6fe 50%, #fce7f3 100%);
        }
        
        .settings-container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .settings-card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            margin-bottom: 25px;
            overflow: hidden;
        }
        
        .settings-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .settings-header h1 {
            margin: 0 0 10px 0;
            font-size: 2em;
        }
        
        .settings-header p {
            margin: 0;
            opacity: 0.9;
        }
        
        .settings-body {
            padding: 30px;
        }
        
        .settings-section {
            margin-bottom: 35px;
        }
        
        .settings-section:last-child {
            margin-bottom: 0;
        }
        
        .section-title {
            font-size: 1.3em;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--gray-200);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--gray-700);
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-group select,
        .form-group input[type="text"],
        .form-group input[type="number"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--gray-200);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .form-group select:focus,
        .form-group input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .checkbox-group {
            background: var(--gray-50);
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .checkbox-item:last-child {
            margin-bottom: 0;
        }
        
        .checkbox-item input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-right: 12px;
            cursor: pointer;
        }
        
        .checkbox-item label {
            margin: 0;
            font-weight: 500;
            cursor: pointer;
            flex: 1;
        }
        
        .checkbox-item small {
            display: block;
            color: var(--gray-500);
            font-size: 0.85em;
            margin-top: 4px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-box {
            background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid var(--primary);
        }
        
        .stat-value {
            font-size: 2em;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9em;
            color: var(--gray-600);
            font-weight: 500;
        }
        
        .btn-save {
            background: var(--primary);
            color: white;
            padding: 14px 35px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-right: 10px;
        }
        
        .btn-save:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        .btn-reset {
            background: var(--warning);
            color: white;
            padding: 14px 35px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-right: 10px;
        }
        
        .btn-reset:hover {
            background: #d97706;
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        .btn-back {
            background: var(--gray-200);
            color: var(--gray-700);
            padding: 14px 35px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .btn-back:hover {
            background: var(--gray-300);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid var(--success);
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid var(--danger);
        }
        
        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border-left: 4px solid var(--info);
        }
        
        .info-box {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid var(--warning);
            margin-top: 20px;
        }
        
        .info-box h4 {
            margin: 0 0 10px 0;
            color: #78350f;
        }
        
        .info-box p {
            margin: 5px 0;
            color: #92400e;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="settings-container">
        <?php if ($error): ?>
            <div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        
        <!-- Header de Configuración -->
        <div class="settings-card">
            <div class="settings-header">
                <h1>⚙️ Configuración del Sistema</h1>
                <p>Personaliza tu experiencia de grabación</p>
            </div>
        </div>
        
        <!-- Estadísticas -->
        <div class="settings-card">
            <div class="settings-body">
                <h3 class="section-title">📊 Estadísticas de Uso</h3>
                <div class="stats-grid">
                    <div class="stat-box">
                        <div class="stat-value"><?= $totalFiles ?></div>
                        <div class="stat-label">Grabaciones Totales</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value"><?= formatBytes($totalSize) ?></div>
                        <div class="stat-label">Espacio Usado</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value"><?= formatBytes(MAX_STORAGE_GB * 1024 * 1024 * 1024 - $totalSize) ?></div>
                        <div class="stat-label">Espacio Disponible</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value"><?= round(($totalSize / (MAX_STORAGE_GB * 1024 * 1024 * 1024)) * 100, 1) ?>%</div>
                        <div class="stat-label">Porcentaje Usado</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Formulario de Configuración -->
        <form method="POST" action="">
            <input type="hidden" name="action" value="update_settings">
            
            <!-- Configuración de Video -->
            <div class="settings-card">
                <div class="settings-body">
                    <div class="settings-section">
                        <h3 class="section-title">🎬 Configuración de Video</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="video_quality">📺 Calidad de Video</label>
                                <select name="video_quality" id="video_quality">
                                    <option value="720" <?= $userConfig['video_quality'] === '720' ? 'selected' : '' ?>>HD (1280x720)</option>
                                    <option value="1080" <?= $userConfig['video_quality'] === '1080' ? 'selected' : '' ?>>Full HD (1920x1080)</option>
                                    <option value="1440" <?= $userConfig['video_quality'] === '1440' ? 'selected' : '' ?>>2K (2560x1440)</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="frame_rate">🎬 Frames por Segundo (FPS)</label>
                                <select name="frame_rate" id="frame_rate">
                                    <option value="24" <?= $userConfig['frame_rate'] === '24' ? 'selected' : '' ?>>24 FPS (Cine)</option>
                                    <option value="30" <?= $userConfig['frame_rate'] === '30' ? 'selected' : '' ?>>30 FPS (Estándar)</option>
                                    <option value="60" <?= $userConfig['frame_rate'] === '60' ? 'selected' : '' ?>>60 FPS (Alta Calidad)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="bitrate">📊 Bitrate de Video</label>
                                <select name="bitrate" id="bitrate">
                                    <option value="2500000" <?= $userConfig['bitrate'] === '2500000' ? 'selected' : '' ?>>2.5 Mbps (Media)</option>
                                    <option value="5000000" <?= $userConfig['bitrate'] === '5000000' ? 'selected' : '' ?>>5 Mbps (Alta)</option>
                                    <option value="8000000" <?= $userConfig['bitrate'] === '8000000' ? 'selected' : '' ?>>8 Mbps (Muy Alta)</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="capture_mode">🎯 Modo de Captura</label>
                                <select name="capture_mode" id="capture_mode">
                                    <option value="full" <?= $userConfig['capture_mode'] === 'full' ? 'selected' : '' ?>>🖥️ Normal (Pantalla/Ventana/Pestaña)</option>
                                    <option value="region" <?= $userConfig['capture_mode'] === 'region' ? 'selected' : '' ?>>✂️ Región Personalizada</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Configuración de Audio -->
            <div class="settings-card">
                <div class="settings-body">
                    <div class="settings-section">
                        <h3 class="section-title">🔊 Configuración de Audio</h3>
                        
                        <div class="checkbox-group">
                            <div class="checkbox-item">
                                <input type="checkbox" name="include_audio" id="include_audio" <?= $userConfig['include_audio'] ? 'checked' : '' ?>>
                                <label for="include_audio">
                                    🔊 Audio del Sistema
                                    <small>Capturar sonido de la pantalla/pestaña</small>
                                </label>
                            </div>
                            
                            <div class="checkbox-item">
                                <input type="checkbox" name="include_microphone" id="include_microphone" <?= $userConfig['include_microphone'] ? 'checked' : '' ?>>
                                <label for="include_microphone">
                                    🎤 Micrófono
                                    <small>Grabar tu voz para narraciones</small>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Configuración Avanzada -->
            <div class="settings-card">
                <div class="settings-body">
                    <div class="settings-section">
                        <h3 class="section-title">🔧 Configuración Avanzada</h3>
                        
                        <div class="checkbox-group">
                            <div class="checkbox-item">
                                <input type="checkbox" name="auto_stop" id="auto_stop" <?= $userConfig['auto_stop'] ? 'checked' : '' ?> onchange="toggleAutoStopTime()">
                                <label for="auto_stop">
                                    ⏱️ Detener Automáticamente
                                    <small>Detener grabación después de un tiempo límite</small>
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-group" style="margin-top: 15px;">
                            <label for="auto_stop_time">Tiempo Límite</label>
                            <select name="auto_stop_time" id="auto_stop_time" <?= !$userConfig['auto_stop'] ? 'disabled' : '' ?>>
                                <option value="300" <?= $userConfig['auto_stop_time'] === '300' ? 'selected' : '' ?>>5 minutos</option>
                                <option value="600" <?= $userConfig['auto_stop_time'] === '600' ? 'selected' : '' ?>>10 minutos</option>
                                <option value="1800" <?= $userConfig['auto_stop_time'] === '1800' ? 'selected' : '' ?>>30 minutos</option>
                                <option value="3600" <?= $userConfig['auto_stop_time'] === '3600' ? 'selected' : '' ?>>1 hora</option>
                            </select>
                        </div>
                        
                        <div class="checkbox-group" style="margin-top: 15px;">
                            <div class="checkbox-item">
                                <input type="checkbox" name="notifications" id="notifications" <?= $userConfig['notifications'] ? 'checked' : '' ?>>
                                <label for="notifications">
                                    🔔 Notificaciones
                                    <small>Recibir alertas del sistema</small>
                                </label>
                            </div>
                            
                            <div class="checkbox-item">
                                <input type="checkbox" name="auto_save" id="auto_save" <?= $userConfig['auto_save'] ? 'checked' : '' ?>>
                                <label for="auto_save">
                                    💾 Guardado Automático
                                    <small>Guardar grabaciones automáticamente al detener</small>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Información del Sistema -->
            <div class="settings-card">
                <div class="settings-body">
                    <h3 class="section-title">ℹ️ Información del Sistema</h3>
                    
                    <div class="info-box">
                        <h4>📋 Límites del Sistema</h4>
                        <p><strong>🎬 Tamaño máximo por archivo:</strong> <?= MAX_FILE_SIZE_MB ?> MB</p>
                        <p><strong>💾 Almacenamiento total:</strong> <?= MAX_STORAGE_GB ?> GB</p>
                        <p><strong>🔒 Tiempo de sesión:</strong> <?= round(SESSION_LIFETIME / 3600) ?> horas</p>
                        <p><strong>🎯 Formatos soportados:</strong> MP4, WebM, MKV</p>
                    </div>
                </div>
            </div>
            
            <!-- Botones de Acción -->
            <div class="settings-card">
                <div class="settings-body">
                    <button type="submit" class="btn-save">💾 Guardar Configuración</button>
                    <button type="button" class="btn-reset" onclick="return confirm('¿Estás seguro de restaurar los valores por defecto?') && document.getElementById('resetForm').submit()">🔄 Restaurar Valores por Defecto</button>
                    <a href="recorder.php" class="btn-back">← Volver a Grabaciones</a>
                </div>
            </div>
        </form>
        
        <!-- Formulario oculto para reset -->
        <form method="POST" action="" id="resetForm" style="display: none;">
            <input type="hidden" name="action" value="reset_settings">
        </form>
    </div>
    
    <script>
        function toggleAutoStopTime() {
            const autoStop = document.getElementById('auto_stop');
            const autoStopTime = document.getElementById('auto_stop_time');
            autoStopTime.disabled = !autoStop.checked;
        }
    </script>
</body>
</html>