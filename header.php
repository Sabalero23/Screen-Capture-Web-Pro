<?php
/**
 * Header Component con Dropdown de Usuario
 * Screen Capture Pro v2.0
 */

// Asegurar que el usuario esté autenticado
if (!isset($currentUser)) {
    require_once 'config.php';
    require_once 'auth.php';
    $auth = new Auth();
    $currentUser = $auth->getCurrentUser();
}
?>
<!DOCTYPE html>
<style>
    /* Estilos del Header */
    .top-header {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 60px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 30px;
    }
    
    .header-logo {
        display: flex;
        align-items: center;
        gap: 12px;
        color: white;
        text-decoration: none;
        font-weight: 700;
        font-size: 1.1em;
        transition: all 0.3s ease;
    }
    
    .header-logo:hover {
        opacity: 0.9;
        transform: scale(1.02);
    }
    
    .header-logo-icon {
        font-size: 1.8em;
    }
    
    .header-user {
        display: flex;
        align-items: center;
        gap: 20px;
    }
    
    .user-dropdown {
        position: relative;
    }
    
    .user-button {
        display: flex;
        align-items: center;
        gap: 10px;
        background: rgba(255, 255, 255, 0.15);
        padding: 8px 16px;
        border-radius: 25px;
        color: white;
        cursor: pointer;
        transition: all 0.3s ease;
        border: 2px solid transparent;
        font-weight: 500;
    }
    
    .user-button:hover {
        background: rgba(255, 255, 255, 0.25);
        border-color: rgba(255, 255, 255, 0.3);
    }
    
    .user-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: linear-gradient(135deg, #f093fb, #f5576c);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 14px;
        border: 2px solid white;
    }
    
    .user-name {
        font-size: 14px;
        max-width: 150px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .dropdown-arrow {
        font-size: 12px;
        transition: transform 0.3s ease;
    }
    
    .user-dropdown.active .dropdown-arrow {
        transform: rotate(180deg);
    }
    
    .dropdown-menu {
        position: absolute;
        top: calc(100% + 10px);
        right: 0;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        min-width: 220px;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        overflow: hidden;
    }
    
    .user-dropdown.active .dropdown-menu {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }
    
    .dropdown-menu::before {
        content: '';
        position: absolute;
        top: -8px;
        right: 20px;
        width: 0;
        height: 0;
        border-left: 8px solid transparent;
        border-right: 8px solid transparent;
        border-bottom: 8px solid white;
    }
    
    .dropdown-header {
        padding: 16px 20px;
        border-bottom: 2px solid #f3f4f6;
        background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
    }
    
    .dropdown-header-name {
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 4px;
        font-size: 15px;
    }
    
    .dropdown-header-email {
        color: #6b7280;
        font-size: 13px;
    }
    
    .dropdown-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 20px;
        color: #374151;
        text-decoration: none;
        transition: all 0.2s ease;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
    }
    
    .dropdown-item:hover {
        background: #f3f4f6;
        color: #667eea;
    }
    
    .dropdown-item-icon {
        font-size: 18px;
        width: 20px;
        text-align: center;
    }
    
    .dropdown-divider {
        height: 1px;
        background: #e5e7eb;
        margin: 4px 0;
    }
    
    .dropdown-item.danger {
        color: #ef4444;
    }
    
    .dropdown-item.danger:hover {
        background: #fee2e2;
        color: #dc2626;
    }
    
    /* Ajuste del contenido principal */
    body.with-header {
        padding-top: 60px;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .top-header {
            padding: 0 15px;
        }
        
        .header-logo-text {
            display: none;
        }
        
        .user-name {
            max-width: 100px;
        }
        
        .dropdown-menu {
            min-width: 200px;
        }
    }
    
    @media (max-width: 480px) {
        .user-name {
            display: none;
        }
        
        .dropdown-arrow {
            display: none;
        }
    }
</style>

<div class="top-header">
    <a href="recorder.php" class="header-logo">
        <span class="header-logo-icon">🎥</span>
        <span class="header-logo-text">Screen Capture Pro</span>
    </a>
    
    <div class="header-user">
        <div class="user-dropdown" id="userDropdown">
            <div class="user-button" onclick="toggleDropdown()">
                <div class="user-avatar">
                    <?php 
                    // Mostrar inicial del nombre
                    echo strtoupper(substr($currentUser['full_name'] ?? 'U', 0, 1)); 
                    ?>
                </div>
                <span class="user-name"><?= htmlspecialchars($currentUser['full_name'] ?? 'Usuario') ?></span>
                <span class="dropdown-arrow">▼</span>
            </div>
            
            <div class="dropdown-menu">
                <div class="dropdown-header">
                    <div class="dropdown-header-name">
                        <?= htmlspecialchars($currentUser['full_name'] ?? 'Usuario') ?>
                    </div>
                    <div class="dropdown-header-email">
                        @<?= htmlspecialchars($currentUser['username']) ?>
                    </div>
                </div>
                
                <a href="profile.php" class="dropdown-item">
                    <span class="dropdown-item-icon">👤</span>
                    Mi Perfil
                </a>
                
                <a href="recorder.php" class="dropdown-item">
                    <span class="dropdown-item-icon">🎬</span>
                    Grabaciones
                </a>
                
                <a href="settings.php" class="dropdown-item">
                    <span class="dropdown-item-icon">⚙️</span>
                    Configuración
                </a>
                
                <div class="dropdown-divider"></div>
                
                <a href="?action=logout" class="dropdown-item danger" onclick="return confirm('¿Estás seguro de cerrar sesión?')">
                    <span class="dropdown-item-icon">🚪</span>
                    Cerrar Sesión
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    // Toggle dropdown
    function toggleDropdown() {
        const dropdown = document.getElementById('userDropdown');
        dropdown.classList.toggle('active');
    }
    
    // Cerrar dropdown al hacer clic fuera
    document.addEventListener('click', function(event) {
        const dropdown = document.getElementById('userDropdown');
        const button = dropdown.querySelector('.user-button');
        
        if (!dropdown.contains(event.target)) {
            dropdown.classList.remove('active');
        }
    });
    
    // Cerrar dropdown con ESC
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            document.getElementById('userDropdown').classList.remove('active');
        }
    });
    
    
    // Agregar clase al body
    document.body.classList.add('with-header');
</script>