<?php
/**
 * Página de Perfil de Usuario
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

// Procesar actualización de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                $fullName = trim($_POST['full_name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                
                if (empty($fullName)) {
                    $error = 'El nombre completo es requerido';
                } else {
                    try {
                        $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
                        $stmt->execute([$fullName, $email, $currentUser['id']]);
                        
                        // Actualizar sesión
                        $_SESSION['full_name'] = $fullName;
                        
                        $success = '✅ Perfil actualizado correctamente';
                        $currentUser['full_name'] = $fullName;
                        $currentUser['email'] = $email;
                    } catch (Exception $e) {
                        $error = 'Error al actualizar el perfil';
                    }
                }
                break;
                
            case 'change_password':
                $currentPassword = $_POST['current_password'] ?? '';
                $newPassword = $_POST['new_password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';
                
                if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                    $error = 'Todos los campos de contraseña son requeridos';
                } elseif ($newPassword !== $confirmPassword) {
                    $error = 'Las contraseñas nuevas no coinciden';
                } elseif (strlen($newPassword) < 6) {
                    $error = 'La contraseña debe tener al menos 6 caracteres';
                } else {
                    $result = $auth->changePassword($currentPassword, $newPassword);
                    if ($result['success']) {
                        $success = '✅ Contraseña actualizada correctamente';
                    } else {
                        $error = $result['error'];
                    }
                }
                break;
        }
    }
}

// Obtener información completa del usuario
$stmt = $db->prepare("SELECT username, email, full_name, created_at, last_login FROM users WHERE id = ?");
$stmt->execute([$currentUser['id']]);
$userInfo = $stmt->fetch(PDO::FETCH_ASSOC);

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
    <title>Mi Perfil - Screen Capture Pro</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            background: linear-gradient(135deg, #e0f2fe 0%, #ddd6fe 50%, #fce7f3 100%);
        }
        
        .profile-container {
            max-width: 900px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .profile-card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            margin-bottom: 25px;
        }
        
        .profile-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #f093fb, #f5576c);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3em;
            font-weight: 700;
            margin: 0 auto 20px;
            border: 4px solid white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .profile-header h1 {
            margin: 0 0 10px 0;
            font-size: 2em;
        }
        
        .profile-header p {
            margin: 0;
            opacity: 0.9;
        }
        
        .profile-body {
            padding: 30px;
        }
        
        .profile-section {
            margin-bottom: 30px;
        }
        
        .profile-section:last-child {
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
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .info-item {
            background: var(--gray-50);
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
        }
        
        .info-label {
            font-size: 0.85em;
            color: var(--gray-500);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 1.1em;
            color: var(--gray-900);
            font-weight: 600;
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
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .btn-save {
            background: var(--primary);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-save:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        .btn-back {
            background: var(--gray-200);
            color: var(--gray-700);
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
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
        
        .password-strength {
            height: 4px;
            background: var(--gray-200);
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0;
            transition: all 0.3s ease;
        }
        
        .strength-weak { width: 33%; background: #ef4444; }
        .strength-medium { width: 66%; background: #f59e0b; }
        .strength-strong { width: 100%; background: #10b981; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="profile-container">
        <?php if ($error): ?>
            <div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        
        <!-- Header del Perfil -->
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <?= strtoupper(substr($userInfo['full_name'], 0, 1)) ?>
                </div>
                <h1><?= htmlspecialchars($userInfo['full_name']) ?></h1>
                <p>@<?= htmlspecialchars($userInfo['username']) ?></p>
            </div>
        </div>
        
        <!-- Información de la Cuenta -->
        <div class="profile-card">
            <div class="profile-body">
                <div class="profile-section">
                    <h2 class="section-title">📊 Información de la Cuenta</h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Usuario</div>
                            <div class="info-value">@<?= htmlspecialchars($userInfo['username']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Fecha de Registro</div>
                            <div class="info-value">
                                <?= date('d/m/Y', strtotime($userInfo['created_at'])) ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Último Acceso</div>
                            <div class="info-value">
                                <?= $userInfo['last_login'] ? date('d/m/Y H:i', strtotime($userInfo['last_login'])) : 'Nunca' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Editar Perfil -->
        <div class="profile-card">
            <div class="profile-body">
                <div class="profile-section">
                    <h2 class="section-title">✏️ Editar Perfil</h2>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-group">
                            <label for="full_name">Nombre Completo *</label>
                            <input 
                                type="text" 
                                id="full_name" 
                                name="full_name" 
                                value="<?= htmlspecialchars($userInfo['full_name']) ?>"
                                required
                            >
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Correo Electrónico</label>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                value="<?= htmlspecialchars($userInfo['email'] ?? '') ?>"
                                placeholder="correo@ejemplo.com"
                            >
                        </div>
                        
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="btn-save">💾 Guardar Cambios</button>
                            <a href="recorder.php" class="btn-back">← Volver</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Cambiar Contraseña -->
        <div class="profile-card">
            <div class="profile-body">
                <div class="profile-section">
                    <h2 class="section-title">🔒 Cambiar Contraseña</h2>
                    <form method="POST" action="" id="passwordForm">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label for="current_password">Contraseña Actual *</label>
                            <input 
                                type="password" 
                                id="current_password" 
                                name="current_password" 
                                required
                                autocomplete="current-password"
                            >
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">Nueva Contraseña * (mínimo 6 caracteres)</label>
                            <input 
                                type="password" 
                                id="new_password" 
                                name="new_password" 
                                required
                                autocomplete="new-password"
                                oninput="checkPasswordStrength(this.value)"
                            >
                            <div class="password-strength">
                                <div class="password-strength-bar" id="strengthBar"></div>
                            </div>
                            <small id="strengthText" style="color: var(--gray-500); font-size: 0.85em; margin-top: 5px; display: block;"></small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirmar Nueva Contraseña *</label>
                            <input 
                                type="password" 
                                id="confirm_password" 
                                name="confirm_password" 
                                required
                                autocomplete="new-password"
                            >
                        </div>
                        
                        <button type="submit" class="btn-save">🔑 Actualizar Contraseña</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function checkPasswordStrength(password) {
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');
            
            let strength = 0;
            
            if (password.length >= 6) strength++;
            if (password.length >= 10) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            strengthBar.className = 'password-strength-bar';
            
            if (password.length === 0) {
                strengthBar.style.width = '0';
                strengthText.textContent = '';
            } else if (strength <= 2) {
                strengthBar.classList.add('strength-weak');
                strengthText.textContent = '⚠️ Contraseña débil';
                strengthText.style.color = '#ef4444';
            } else if (strength <= 4) {
                strengthBar.classList.add('strength-medium');
                strengthText.textContent = '👍 Contraseña media';
                strengthText.style.color = '#f59e0b';
            } else {
                strengthBar.classList.add('strength-strong');
                strengthText.textContent = '✅ Contraseña fuerte';
                strengthText.style.color = '#10b981';
            }
        }
        
        // Validar que las contraseñas coincidan
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPass = document.getElementById('new_password').value;
            const confirmPass = document.getElementById('confirm_password').value;
            
            if (newPass !== confirmPass) {
                e.preventDefault();
                alert('Las contraseñas no coinciden');
            }
        });
    </script>
</body>
</html>