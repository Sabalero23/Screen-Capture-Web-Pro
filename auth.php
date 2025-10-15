<?php
/**
 * Sistema de Autenticación
 * Screen Capture Pro v2.0
 */

require_once 'config.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
        $this->startSession();
    }
    
    /**
     * Iniciar sesión segura
     */
    private function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
            session_start();
        }
    }
    
    /**
     * Verificar intentos de login
     */
    private function checkLoginAttempts($username) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $lockoutTime = date('Y-m-d H:i:s', time() - LOCKOUT_TIME);
        
        // Contar intentos fallidos recientes
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM login_attempts 
            WHERE username = ? 
            AND ip_address = ? 
            AND success = 0 
            AND attempt_time > ?
        ");
        $stmt->execute([$username, $ip, $lockoutTime]);
        $attempts = $stmt->fetchColumn();
        
        return $attempts < MAX_LOGIN_ATTEMPTS;
    }
    
    /**
     * Registrar intento de login
     */
    private function logLoginAttempt($username, $success) {
        $ip = $_SERVER['REMOTE_ADDR'];
        
        $stmt = $this->db->prepare("
            INSERT INTO login_attempts (username, ip_address, success) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$username, $ip, $success ? 1 : 0]);
        
        // Limpiar intentos antiguos (más de 24 horas)
        $oldTime = date('Y-m-d H:i:s', time() - 86400);
        $this->db->exec("DELETE FROM login_attempts WHERE attempt_time < '$oldTime'");
    }
    
    /**
     * Iniciar sesión
     */
    public function login($username, $password) {
        // Validar entrada
        if (empty($username) || empty($password)) {
            return ['success' => false, 'error' => 'Usuario y contraseña requeridos'];
        }
        
        // Verificar intentos de login
        if (!$this->checkLoginAttempts($username)) {
            return ['success' => false, 'error' => 'Demasiados intentos fallidos. Intenta en 15 minutos.'];
        }
        
        // Buscar usuario
        $stmt = $this->db->prepare("
            SELECT id, username, password, full_name, is_active 
            FROM users 
            WHERE username = ?
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verificar usuario y contraseña
        if (!$user || !password_verify($password, $user['password'])) {
            $this->logLoginAttempt($username, false);
            return ['success' => false, 'error' => 'Usuario o contraseña incorrectos'];
        }
        
        // Verificar si está activo
        if (!$user['is_active']) {
            return ['success' => false, 'error' => 'Usuario desactivado'];
        }
        
        // Login exitoso
        $this->logLoginAttempt($username, true);
        
        // Crear sesión
        $sessionToken = bin2hex(random_bytes(32));
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['session_token'] = $sessionToken;
        $_SESSION['login_time'] = time();
        
        // Guardar sesión en base de datos
        $expiresAt = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
        $stmt = $this->db->prepare("
            INSERT INTO sessions (user_id, session_token, ip_address, user_agent, expires_at) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user['id'],
            $sessionToken,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $expiresAt
        ]);
        
        // Actualizar último login
        $stmt = $this->db->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        return ['success' => true, 'message' => 'Login exitoso'];
    }
    
    /**
     * Cerrar sesión
     */
    public function logout() {
        if (isset($_SESSION['session_token'])) {
            // Eliminar sesión de la base de datos
            $stmt = $this->db->prepare("DELETE FROM sessions WHERE session_token = ?");
            $stmt->execute([$_SESSION['session_token']]);
        }
        
        // Destruir sesión
        session_unset();
        session_destroy();
        
        return ['success' => true, 'message' => 'Sesión cerrada'];
    }
    
    /**
     * Verificar si el usuario está autenticado
     */
    public function isAuthenticated() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
            return false;
        }
        
        // Verificar si la sesión ha expirado
        if (isset($_SESSION['login_time'])) {
            if (time() - $_SESSION['login_time'] > SESSION_LIFETIME) {
                $this->logout();
                return false;
            }
        }
        
        // Verificar sesión en base de datos
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM sessions 
            WHERE session_token = ? 
            AND expires_at > CURRENT_TIMESTAMP
        ");
        $stmt->execute([$_SESSION['session_token']]);
        $count = $stmt->fetchColumn();
        
        if ($count == 0) {
            session_unset();
            return false;
        }
        
        return true;
    }
    
    /**
     * Obtener información del usuario actual
     */
    public function getCurrentUser() {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'full_name' => $_SESSION['full_name']
        ];
    }
    
    /**
     * Cambiar contraseña
     */
    public function changePassword($oldPassword, $newPassword) {
        if (!$this->isAuthenticated()) {
            return ['success' => false, 'error' => 'No autenticado'];
        }
        
        // Validar nueva contraseña
        if (strlen($newPassword) < 6) {
            return ['success' => false, 'error' => 'La contraseña debe tener al menos 6 caracteres'];
        }
        
        // Verificar contraseña actual
        $stmt = $this->db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $currentHash = $stmt->fetchColumn();
        
        if (!password_verify($oldPassword, $currentHash)) {
            return ['success' => false, 'error' => 'Contraseña actual incorrecta'];
        }
        
        // Actualizar contraseña
        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$newHash, $_SESSION['user_id']]);
        
        return ['success' => true, 'message' => 'Contraseña actualizada'];
    }
    
    /**
     * Registrar nuevo usuario (solo admin puede hacer esto)
     */
    public function register($username, $password, $email, $fullName) {
        // Validar entrada
        if (empty($username) || empty($password)) {
            return ['success' => false, 'error' => 'Usuario y contraseña requeridos'];
        }
        
        if (strlen($password) < 6) {
            return ['success' => false, 'error' => 'La contraseña debe tener al menos 6 caracteres'];
        }
        
        // Verificar si el usuario ya existe
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() > 0) {
            return ['success' => false, 'error' => 'El usuario ya existe'];
        }
        
        // Crear usuario
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->db->prepare("
            INSERT INTO users (username, password, email, full_name) 
            VALUES (?, ?, ?, ?)
        ");
        
        try {
            $stmt->execute([$username, $passwordHash, $email, $fullName]);
            return ['success' => true, 'message' => 'Usuario creado correctamente'];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Error al crear usuario'];
        }
    }
}
?>