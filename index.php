<?php
require_once 'config.php';
require_once 'auth.php';

$auth = new Auth();

// Si ya está autenticado, redirigir al recorder
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
    <title>Screen Capture Pro - Sistema Profesional de Grabación</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            background-size: 400% 400%;
            animation: gradientFlow 15s ease infinite;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        @keyframes gradientFlow {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        
        /* Navbar */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 15px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        
        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.5em;
            font-weight: 700;
            color: #3b82f6;
        }
        
        .navbar-brand span {
            font-size: 1.3em;
        }
        
        .navbar-actions {
            display: flex;
            gap: 15px;
        }
        
        .btn {
            padding: 10px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }
        
        .btn-outline {
            background: transparent;
            color: #3b82f6;
            border: 2px solid #3b82f6;
        }
        
        .btn-outline:hover {
            background: #3b82f6;
            color: white;
        }
        
        /* Hero Section */
        .hero {
            padding: 120px 5% 60px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .hero-content h1 {
            font-size: 3.5em;
            color: white;
            margin-bottom: 20px;
            font-weight: 800;
            line-height: 1.2;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .hero-content p {
            font-size: 1.3em;
            color: rgba(255,255,255,0.95);
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .hero-features {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 30px;
        }
        
        .feature-badge {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            padding: 15px;
            border-radius: 12px;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .feature-badge span {
            font-size: 1.5em;
        }
        
        /* Login Card */
        .login-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            position: relative;
            overflow: hidden;
        }
        
        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #3b82f6, #8b5cf6, #ec4899);
        }
        
        .login-card h2 {
            color: #1f2937;
            font-size: 1.8em;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .login-card p {
            color: #6b7280;
            margin-bottom: 25px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #374151;
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus {
            border-color: #3b82f6;
            outline: none;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }
        
        .alert {
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        
        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border-left: 4px solid #3b82f6;
        }
        
        .credentials-hint {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            padding: 16px;
            border-radius: 10px;
            margin-top: 20px;
            border-left: 4px solid #f59e0b;
        }
        
        .credentials-hint strong {
            color: #78350f;
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .credentials-hint p {
            margin: 5px 0;
            color: #92400e;
            font-size: 13px;
        }
        
        /* Features Section */
        .features-section {
            background: white;
            padding: 80px 5%;
        }
        
        .section-title {
            text-align: center;
            font-size: 2.5em;
            color: #1f2937;
            margin-bottom: 50px;
            font-weight: 700;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .feature-card {
            background: linear-gradient(135deg, #f9fafb, #f3f4f6);
            padding: 35px;
            border-radius: 16px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-color: #3b82f6;
        }
        
        .feature-icon {
            font-size: 3em;
            margin-bottom: 20px;
            display: block;
        }
        
        .feature-card h3 {
            color: #1f2937;
            font-size: 1.3em;
            margin-bottom: 12px;
            font-weight: 700;
        }
        
        .feature-card p {
            color: #6b7280;
            line-height: 1.6;
        }
        
        /* Stats Section */
        .stats-section {
            background: linear-gradient(135deg, #1e293b, #334155);
            padding: 60px 5%;
            color: white;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 40px;
            max-width: 1200px;
            margin: 0 auto;
            text-align: center;
        }
        
        .stat-item {
            padding: 20px;
        }
        
        .stat-number {
            font-size: 3.5em;
            font-weight: 800;
            display: block;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #60a5fa, #a78bfa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stat-label {
            font-size: 1.1em;
            opacity: 0.9;
        }
        
        /* Footer */
        .footer {
            background: #1f2937;
            color: white;
            padding: 30px 5%;
            text-align: center;
        }
        
        .footer p {
            margin: 5px 0;
            opacity: 0.8;
        }
        
        /* Responsive */
        @media (max-width: 968px) {
            .hero {
                grid-template-columns: 1fr;
                padding-top: 100px;
            }
            
            .hero-content h1 {
                font-size: 2.5em;
            }
            
            .navbar {
                padding: 12px 20px;
            }
            
            .navbar-actions {
                gap: 8px;
            }
            
            .btn {
                padding: 8px 16px;
                font-size: 13px;
            }
            
            .hero-features {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="navbar-brand">
            <span>🎥</span>
            Screen Capture Pro
        </div>
        <div class="navbar-actions">
            <a href="#login" class="btn btn-primary">🔐 Iniciar Sesión</a>
        </div>
    </nav>
    
    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1>Graba tu Pantalla de Forma Profesional</h1>
            <p>Sistema avanzado de captura de pantalla con edición de regiones, audio dual y gestión completa de archivos.</p>
            
            <div class="hero-features">
                <div class="feature-badge">
                    <span>📹</span>
                    <span>Región Personalizada</span>
                </div>
                <div class="feature-badge">
                    <span>🎤</span>
                    <span>Audio Dual</span>
                </div>
                <div class="feature-badge">
                    <span>💾</span>
                    <span>10GB Storage</span>
                </div>
                <div class="feature-badge">
                    <span>🎬</span>
                    <span>Formato MP4</span>
                </div>
            </div>
        </div>
        
        <!-- Login Card -->
        <div class="login-card" id="login">
            <h2>🚀 Acceder al Sistema</h2>
            <p>Ingresa tus credenciales para comenzar</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    ❌ <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <div class="alert alert-info">
                🔐 Inicio de sesión seguro con encriptación
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
                
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 14px; font-size: 16px;">
                    🚀 Iniciar Sesión
                </button>
            </form>
            
            <div class="credentials-hint">
                <strong>📋 Credenciales por Defecto</strong>
                <p><strong>Usuario:</strong> admin</p>
                <p><strong>Contraseña:</strong> admin123</p>
                <p style="margin-top: 8px; font-size: 12px;">⚠️ Cambia tu contraseña después del primer login</p>
            </div>
        </div>
    </section>
    
    <!-- Features Section -->
    <section class="features-section">
        <h2 class="section-title">✨ Características Principales</h2>
        
        <div class="features-grid">
            <div class="feature-card">
                <span class="feature-icon">📐</span>
                <h3>Región Personalizada</h3>
                <p>Selecciona y graba solo el área específica que necesitas. Panel movible para no obstruir tu vista.</p>
            </div>
            
            <div class="feature-card">
                <span class="feature-icon">🎤</span>
                <h3>Audio Dual</h3>
                <p>Captura simultánea de audio del sistema y micrófono. Perfecto para tutoriales y presentaciones.</p>
            </div>
            
            <div class="feature-card">
                <span class="feature-icon">🎬</span>
                <h3>Formato MP4</h3>
                <p>Videos en formato MP4 (H.264) compatible con todos los dispositivos y plataformas.</p>
            </div>
            
            <div class="feature-card">
                <span class="feature-icon">⏸️</span>
                <h3>Control Total</h3>
                <p>Pausa, reanuda y detén tu grabación en cualquier momento. Control completo sobre tu contenido.</p>
            </div>
            
            <div class="feature-card">
                <span class="feature-icon">📁</span>
                <h3>Gestión Completa</h3>
                <p>Reproduce, descarga, renombra y elimina tus grabaciones desde un panel intuitivo.</p>
            </div>
            
            <div class="feature-card">
                <span class="feature-icon">🔒</span>
                <h3>100% Seguro</h3>
                <p>Sistema de autenticación robusto con sesiones seguras y protección contra fuerza bruta.</p>
            </div>
            
            <div class="feature-card">
                <span class="feature-icon">💾</span>
                <h3>10GB de Almacenamiento</h3>
                <p>Espacio dedicado para tus grabaciones con control de límites y estadísticas en tiempo real.</p>
            </div>
            
            <div class="feature-card">
                <span class="feature-icon">🎯</span>
                <h3>HD / Full HD / 2K</h3>
                <p>Múltiples opciones de calidad. Graba en hasta 2560x1440 con 60 FPS.</p>
            </div>
            
            <div class="feature-card">
                <span class="feature-icon">📱</span>
                <h3>Responsive</h3>
                <p>Interfaz adaptada para escritorio, tablet y móvil. Accede desde cualquier dispositivo.</p>
            </div>
        </div>
    </section>
    
    <!-- Stats Section -->
    <section class="stats-section">
        <div class="stats-grid">
            <div class="stat-item">
                <span class="stat-number">10GB</span>
                <span class="stat-label">Almacenamiento Total</span>
            </div>
            <div class="stat-item">
                <span class="stat-number">500MB</span>
                <span class="stat-label">Máx. por Archivo</span>
            </div>
            <div class="stat-item">
                <span class="stat-number">60 FPS</span>
                <span class="stat-label">Frames por Segundo</span>
            </div>
            <div class="stat-item">
                <span class="stat-number">2K</span>
                <span class="stat-label">Resolución Máxima</span>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="footer">
        <p><strong>🎥 Screen Capture Pro v2.0</strong></p>
        <p>Sistema Profesional de Grabación de Pantalla</p>
        <p style="margin-top: 15px; font-size: 0.9em;">© <?= date('Y') ?> - Todos los derechos reservados</p>
    </footer>
</body>
</html>