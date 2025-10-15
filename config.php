<?php
/**
 * Configuración del Sistema
 * Screen Capture Pro v2.0
 */

// Configuración de la base de datos SQLite
define('DB_FILE', __DIR__ . '/data/users.db');
define('DATA_DIR', __DIR__ . '/data/');

// Configuración de seguridad
define('SESSION_LIFETIME', 3600 * 24); // 24 horas
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutos

// Configuración de grabaciones
define('RECORDINGS_DIR', __DIR__ . '/recordings/');
define('MAX_STORAGE_GB', 10);
define('MAX_FILE_SIZE_MB', 500);

// Inicializar base de datos si no existe
function initDatabase() {
    // Crear directorio de datos si no existe
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0755, true);
    }
    
    // Crear directorio de grabaciones si no existe
    if (!is_dir(RECORDINGS_DIR)) {
        mkdir(RECORDINGS_DIR, 0755, true);
    }
    
    // Conectar a SQLite
    try {
        $db = new PDO('sqlite:' . DB_FILE);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Crear tabla de usuarios
        $db->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                email TEXT,
                full_name TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_login DATETIME,
                is_active INTEGER DEFAULT 1
            )
        ");
        
        // Crear tabla de intentos de login
        $db->exec("
            CREATE TABLE IF NOT EXISTS login_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL,
                ip_address TEXT NOT NULL,
                attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP,
                success INTEGER DEFAULT 0
            )
        ");
        
        // Crear tabla de sesiones
        $db->exec("
            CREATE TABLE IF NOT EXISTS sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                session_token TEXT UNIQUE NOT NULL,
                ip_address TEXT,
                user_agent TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");
        
        // Verificar si existe el usuario admin
        $stmt = $db->query("SELECT COUNT(*) FROM users WHERE username = 'admin'");
        $count = $stmt->fetchColumn();
        
        // Crear usuario admin por defecto si no existe
        if ($count == 0) {
            $adminPassword = password_hash('admin123', PASSWORD_BCRYPT);
            $db->exec("
                INSERT INTO users (username, password, email, full_name) 
                VALUES ('admin', '$adminPassword', 'admin@example.com', 'Administrador')
            ");
        }
        
        return $db;
        
    } catch (PDOException $e) {
        error_log("Error inicializando base de datos: " . $e->getMessage());
        die("Error de configuración del sistema");
    }
}

// Función para obtener conexión a la base de datos
function getDB() {
    static $db = null;
    
    if ($db === null) {
        $db = initDatabase();
    }
    
    return $db;
}

// Inicializar en la primera carga
initDatabase();
?>