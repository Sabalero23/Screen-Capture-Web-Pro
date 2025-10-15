<?php
require_once 'config.php';
require_once 'auth.php';

$auth = new Auth();

// Si ya está autenticado, redirigir
if ($auth->isAuthenticated()) {
    header('Location: recorder.php');
    exit;
}

$error = '';
$success = '';

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'login') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        $result = $auth->login($username, $password);
        
        if ($result['success']) {
            header('Location: recorder.php');
            exit;
        } else {
            $error = $result['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Screen Capture Pro</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            background-size: 200% 200%;
            animation: gradientShift 15s ease infinite;
        }
        
        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        
        .login-container {
            max-width: 450px;
            width: 90%;
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            padding: 40px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header .logo {
            font-size: 4em;
            margin-bottom: 10px;
        }
        
        .login-header h1 {
            color: var(--primary);
            font-size: 1.8em;
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: var(--gray-500);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--gray-700);
            font-weight: 600;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--gray-200);
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .btn-login {
            width: 100%;
            padding: 14px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid var(--danger);
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid var(--success);
        }
        
        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border-left: 4px solid var(--info);
        }
        
        .login-footer {
            text-align: center;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid var(--gray-200);
        }
        
        .login-footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-footer a:hover {
            text-decoration: underline;
        }
        
        .credentials-box {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            border-left: 4px solid var(--warning);
        }
        
        .credentials-box strong {
            color: #78350f;
            display: block;
            margin-bottom: 8px;
        }
        
        .credentials-box p {
            margin: 0;
            color: #92400e;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo">🎥</div>
            <h1>Iniciar Sesión</h1>
            <p>Screen Capture Pro</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                ❌ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                ✅ <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <div class="alert alert-info">
            🔐 Ingresa tus credenciales para acceder al sistema
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="login">
            
            <div class="form-group">
                <label for="username">👤 Usuario</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    placeholder="Ingresa tu usuario"
                    required 
                    autofocus
                    autocomplete="username"
                >
            </div>
            
            <div class="form-group">
                <label for="password">🔑 Contraseña</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    placeholder="Ingresa tu contraseña"
                    required
                    autocomplete="current-password"
                >
            </div>
            
            <button type="submit" class="btn-login">
                🚀 Iniciar Sesión
            </button>
        </form>
        
        <div class="credentials-box">
            <strong>📋 Credenciales por Defecto:</strong>
            <p>
                <strong>Usuario:</strong> admin<br>
                <strong>Contraseña:</strong> admin123
            </p>
        </div>
        
        <div class="login-footer">
            <a href="index.php">← Volver al inicio</a>
        </div>
    </div>
</body>
</html>