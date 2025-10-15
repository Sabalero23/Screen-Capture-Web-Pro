# 🎥 Screen Capture Pro v2.0

Sistema profesional de grabación de pantalla con autenticación de usuarios, gestión de perfiles y páginas de error personalizadas.

## 📋 Características

- ✅ **Sistema de Login Seguro** - Autenticación con sesiones y SQLite
- ✅ **Gestión de Perfiles** - Editar información y cambiar contraseña
- ✅ **Header Fijo con Dropdown** - Menú de usuario profesional
- ✅ **Grabación de Pantalla** - Pantalla completa o región personalizada
- ✅ **Audio Dual** - Captura audio del sistema y micrófono
- ✅ **Formato MP4** - Compatible con todos los dispositivos
- ✅ **Gestión de Archivos** - Reproducir, renombrar, eliminar
- ✅ **Límite de 10GB** - Control de almacenamiento
- ✅ **Páginas de Error** - 404 y 502 personalizadas
- ✅ **Interfaz Profesional** - Diseño moderno y responsive

## 🚀 Instalación

### Requisitos
- PHP 7.4 o superior
- Extensión PDO SQLite habilitada
- Apache/Nginx con mod_rewrite
- Navegador moderno (Chrome, Firefox, Edge, Safari)

### Pasos de Instalación

1. **Subir archivos al servidor**
```bash
Archivos PHP:
- index.php (página principal)
- login.php (página de login)
- recorder.php (sistema de grabación - renombrar el index.php original)
- profile.php (página de perfil)
- settings.php (página de configuración)
- header.php (componente header)
- config.php (configuración)
- auth.php (autenticación)

Archivos Estáticos:
- styles.css (estilos)
- recorder.js (JavaScript)
- .htaccess (configuración Apache)
- 404.html (página error 404)
- 502.html (página error 502)
```

2. **Crear estructura de directorios**
```bash
mkdir data
mkdir recordings
mkdir logs
chmod 755 data recordings logs
```

3. **Configurar permisos**
```bash
chmod 644 *.php *.html *.css *.js
chmod 755 data/ recordings/ logs/
chmod 644 .htaccess
```

4. **Acceder al sistema**
- Ir a: `http://tu-dominio.com/`
- Ver página de bienvenida
- Click en "Iniciar Sesión"

## 🔐 Credenciales por Defecto

**Usuario:** `admin`  
**Contraseña:** `admin123`

⚠️ **IMPORTANTE:** Cambia la contraseña después del primer login por seguridad.

## 📁 Estructura de Archivos

```
/
├── index.php           # Página principal de bienvenida
├── login.php           # Sistema de autenticación
├── recorder.php        # Sistema de grabación (index.php renombrado)
├── profile.php         # Página de perfil de usuario
├── settings.php        # Página de configuración del sistema
├── header.php          # Componente de header con dropdown
├── config.php          # Configuración general
├── auth.php            # Clase de autenticación
├── styles.css          # Estilos CSS
├── recorder.js         # JavaScript del grabador
├── .htaccess           # Configuración Apache (seguridad y errores)
├── 404.html            # Página de error 404 (no encontrado)
├── 502.html            # Página de error 502 (servidor)
├── data/               # Base de datos SQLite
│   └── users.db       # Base de datos de usuarios (se crea automáticamente)
├── recordings/         # Grabaciones almacenadas
└── logs/              # Logs del sistema
```

## 🎨 Componentes del Sistema

### Header Fijo con Dropdown
- **Logo del sistema** - Clickeable para volver a grabaciones
- **Avatar del usuario** - Muestra inicial del nombre
- **Dropdown de usuario** con:
  - 👤 Mi Perfil (profile.php)
  - 🎬 Grabaciones (recorder.php)
  - ⚙️ Configuración (settings.php)
  - 🚪 Cerrar Sesión
- **Responsive** - Se adapta a móviles
- **Fijo en la parte superior** - Siempre visible

### Página de Perfil
- **Información de la cuenta** - Usuario, fecha de registro, último acceso
- **Editar perfil** - Nombre completo y correo electrónico
- **Cambiar contraseña** - Con indicador de fortaleza
- **Avatar personalizado** - Inicial del nombre en círculo

### Página de Configuración
- **Estadísticas de uso** - Archivos totales, espacio usado/disponible
- **Configuración de video** - Calidad, FPS, bitrate, modo de captura
- **Configuración de audio** - Audio del sistema y micrófono
- **Opciones avanzadas** - Auto-detener, notificaciones, guardado automático
- **Información del sistema** - Límites y capacidades
- **Restaurar valores por defecto** - Reset de configuración
- **Configuración persistente** - Se guarda por usuario en archivos JSON

### Páginas de Error

#### 404.html - Página No Encontrada
- Diseño moderno con gradientes animados
- Icono de búsqueda animado (flotante)
- Código 404 en degradado
- Sugerencias útiles para el usuario
- Botones de acción (Inicio, Volver)
- 100% responsive

#### 502.html - Error del Servidor
- Diseño con colores rojos (indicando error)
- Icono de advertencia animado (shake)
- Indicador de estado del servidor (pulsante)
- Explicación técnica del error
- Sugerencias de solución
- Botón de recarga automática
- ID de error único con timestamp
- 100% responsive

## 🔧 Configuración

### Modificar límites en `config.php`:

```php
define('MAX_STORAGE_GB', 10);      // Límite total en GB
define('MAX_FILE_SIZE_MB', 500);   // Tamaño máximo por archivo
define('SESSION_LIFETIME', 86400); // 24 horas
```

### Configurar PHP (php.ini):

```ini
upload_max_filesize = 500M
post_max_size = 550M
max_execution_time = 600
memory_limit = 512M
```

### Configuración del .htaccess

El archivo `.htaccess` incluye:

**Seguridad:**
- Headers de seguridad (X-XSS-Protection, X-Frame-Options, CSP)
- Protección de archivos sensibles (.db, .log, .ini)
- Prevención de inyección SQL
- Protección contra hotlinking (opcional)

**Rendimiento:**
- Compresión GZIP
- Cache del navegador
- Límites de upload configurados

**SEO y URLs:**
- Eliminación de .php en URLs
- Redirección a HTTPS (opcional)
- Redirección a www (opcional)

**Páginas de Error:**
```apache
ErrorDocument 404 /404.html
ErrorDocument 502 /502.html
ErrorDocument 500 /502.html
ErrorDocument 503 /502.html
```

## 👥 Gestión de Usuarios

### Crear nuevo usuario (desde PHP):

```php
require_once 'auth.php';
$auth = new Auth();

$result = $auth->register(
    'nuevo_usuario',
    'contraseña_segura',
    'email@ejemplo.com',
    'Nombre Completo'
);
```

### Editar perfil:

Los usuarios pueden editar su perfil desde:
```
http://tu-dominio.com/profile.php
```

Pueden modificar:
- Nombre completo
- Correo electrónico
- Contraseña (con validación de fortaleza)

## 🛡️ Seguridad

- ✅ Contraseñas hasheadas con bcrypt
- ✅ Protección contra fuerza bruta (5 intentos / 15 min)
- ✅ Tokens CSRF en todas las acciones
- ✅ Rate limiting en uploads
- ✅ Validación de tipos MIME
- ✅ Sesiones con timeout automático
- ✅ Logs de actividad
- ✅ Páginas de error personalizadas
- ✅ Headers de seguridad (XSS, Clickjacking, MIME Sniffing)
- ✅ Protección de archivos sensibles
- ✅ Sin listado de directorios
- ✅ Protección contra inyección SQL
- ✅ Compresión GZIP activada
- ✅ Cache del navegador optimizado

## 📊 Base de Datos

El sistema usa **SQLite** que se crea automáticamente en `data/users.db`.

### Tablas:
- `users` - Información de usuarios (username, password, email, full_name)
- `login_attempts` - Registro de intentos de login
- `sessions` - Sesiones activas

## 🎬 Uso del Sistema

### 1. Iniciar Sesión
- Ir a `http://tu-dominio.com/`
- Click en "Iniciar Sesión"
- Ingresar: admin / admin123
- Acceder al sistema

### 2. Actualizar Perfil
- Click en tu nombre (esquina superior derecha)
- Seleccionar "Mi Perfil"
- Editar información
- Cambiar contraseña si lo deseas
- Guardar cambios

### 3. Grabar Pantalla
- Desde el header, ir a "Grabaciones"
- Seleccionar modo de captura (Normal o Región Personalizada)
- Configurar calidad y FPS
- Click en "▶️ Iniciar"
- Seleccionar ventana/pantalla
- Si es región personalizada, recortar área
- Grabar y detener

### 4. Gestionar Grabaciones
- Ver lista de grabaciones
- Reproducir en el reproductor integrado
- Descargar, renombrar o eliminar
- Limpiar archivos antiguos

### 5. Cerrar Sesión
- Click en tu nombre (esquina superior derecha)
- Seleccionar "🚪 Cerrar Sesión"

## ✅ Checklist de Instalación

### Archivos a Subir
- [ ] `index.php` (página principal)
- [ ] `login.php` (sistema de login)
- [ ] `recorder.php` (renombrar tu index.php actual)
- [ ] `profile.php` (página de perfil)
- [ ] `settings.php` (página de configuración)
- [ ] `header.php` (componente header)
- [ ] `config.php` (configuración)
- [ ] `auth.php` (autenticación)
- [ ] `styles.css` (estilos)
- [ ] `recorder.js` (JavaScript)
- [ ] `.htaccess` (configuración Apache)
- [ ] `404.html` (página error 404)
- [ ] `502.html` (página error 502)

### Configuración del Servidor
- [ ] Crear directorio `data/`
- [ ] Crear directorio `recordings/`
- [ ] Crear directorio `logs/`
- [ ] Establecer permisos 755 a directorios
- [ ] Establecer permisos 644 a archivos PHP
- [ ] Verificar que `data/` es escribible

### Verificación Post-Instalación
- [ ] Acceder a la página principal (index.php)
- [ ] Login con admin/admin123 funciona
- [ ] Header visible con dropdown
- [ ] Página de perfil accesible
- [ ] Página de configuración accesible
- [ ] Edición de perfil funciona
- [ ] Cambio de contraseña funciona
- [ ] Guardar configuración funciona
- [ ] Redirección a recorder.php funciona
- [ ] Grabación de pantalla funciona
- [ ] Guardado de videos funciona
- [ ] Reproducción de videos funciona
- [ ] Logout funciona
- [ ] Página 404 funciona (URL inválida)
- [ ] Directorios protegidos (data/, logs/)

### Seguridad Post-Instalación
- [ ] Cambiar contraseña de admin desde el perfil
- [ ] Verificar que .htaccess está activo
- [ ] Probar límite de intentos de login
- [ ] Verificar headers de seguridad
- [ ] Activar HTTPS si está disponible
- [ ] Configurar backups automáticos

## 🧪 Testing

### Probar Páginas de Error

**404:**
```
http://tu-dominio.com/pagina-que-no-existe
```

**502 (simular):**
Crear `test-502.php`:
```php
<?php
http_response_code(502);
include '502.html';
?>
```

### Verificar Seguridad

Intentar acceder a:
- `http://tu-dominio.com/data/` (debe dar 404)
- `http://tu-dominio.com/logs/` (debe dar 404)
- `http://tu-dominio.com/.htaccess` (debe dar 403/404)
- `http://tu-dominio.com/recorder.php` sin login (debe redirigir)

## 🐛 Solución de Problemas

### Error: "Base de datos no se puede crear"
```bash
chmod 755 data/
chown www-data:www-data data/
```

### Error: "No se puede subir archivo"
```bash
chmod 755 recordings/
# Verificar php.ini: upload_max_filesize y post_max_size
```

### Error: "Sesión expirada"
- Verificar que las cookies estén habilitadas
- Aumentar `SESSION_LIFETIME` en config.php

### Header no aparece
- Verificar que `header.php` existe
- Verificar permisos del archivo
- Revisar errores en consola del navegador

### Dropdown no funciona
- Verificar que JavaScript está cargando
- Limpiar caché del navegador
- Revisar consola de errores (F12)

### No se captura audio
- Usar modo "Pantalla Completa" o "Pestaña"
- El modo "Ventana" no captura audio del sistema

## 🔄 Actualización

Para actualizar desde versión anterior:

1. Hacer backup de `recordings/` y `data/users.db`
2. Reemplazar todos los archivos PHP y JS
3. Mantener `data/users.db`
4. Actualizar `.htaccess`
5. Limpiar caché del navegador

## 📝 Changelog

### v2.0 (Actual)
- ✅ Sistema de login con autenticación
- ✅ Base de datos SQLite
- ✅ Gestión de sesiones
- ✅ Múltiples usuarios
- ✅ **Header fijo con dropdown de usuario**
- ✅ **Página de perfil completa**
- ✅ **Página de configuración del sistema**
- ✅ **Edición de datos de usuario**
- ✅ **Cambio de contraseña con indicador de fortaleza**
- ✅ **Configuración personalizable por usuario**
- ✅ Páginas de error personalizadas (404, 502)
- ✅ Configuración Apache avanzada (.htaccess)
- ✅ Mejor seguridad y rendimiento

### v1.0
- Grabación básica de pantalla
- Sin autenticación

## 🎯 URLs del Sistema

- **Página Principal:** `http://tu-dominio.com/`
- **Login:** `http://tu-dominio.com/login.php`
- **Perfil de Usuario:** `http://tu-dominio.com/profile.php`
- **Configuración:** `http://tu-dominio.com/settings.php`
- **Sistema de Grabación:** `http://tu-dominio.com/recorder.php`
- **Página 404:** Cualquier URL inválida
- **Página 502:** Aparece en errores del servidor

## 💡 Características del Header

- **Fijo en la parte superior** - Siempre visible al hacer scroll
- **Logo clickeable** - Vuelve a la página de grabaciones
- **Avatar personalizado** - Muestra la inicial del nombre
- **Dropdown animado** - Se abre con click
- **Cierre automático** - Al hacer click fuera o presionar ESC
- **100% Responsive** - Se adapta a móviles

## 📞 Soporte

Para problemas o consultas:
- Revisar logs en `logs/`
- Verificar permisos de archivos
- Comprobar configuración PHP
- Ver consola del navegador (F12)
- Revisar este README

## 📄 Licencia

Sistema propietario - Uso interno

---

**Screen Capture Pro v2.0**  
Sistema completo de grabación de pantalla con autenticación, gestión de perfiles y páginas de error personalizadas.

Desarrollado con ❤️ para grabación profesional de pantalla# Screen-Capture-Web-Pro
