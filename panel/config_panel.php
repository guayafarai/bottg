<?php
/**
 * ═══════════════════════════════════════════════════════════════
 * CONFIGURACIÓN DEL PANEL WEB DE ADMINISTRACIÓN
 * ═══════════════════════════════════════════════════════════════
 */

// ═══════════════════════════════════════════════════════════════
// SEGURIDAD - CREDENCIALES DE ACCESO AL PANEL
// ═══════════════════════════════════════════════════════════════

define('ADMIN_WEB_USERNAME', 'admin');
define('ADMIN_WEB_PASSWORD', password_hash('admin123', PASSWORD_BCRYPT)); // CAMBIAR

// Lista de IPs permitidas (opcional - dejar vacío para permitir todas)
$ADMIN_ALLOWED_IPS = [
    // '192.168.1.1',
    // '10.0.0.1'
];

// ═══════════════════════════════════════════════════════════════
// CONFIGURACIÓN DEL PANEL
// ═══════════════════════════════════════════════════════════════

define('PANEL_TITULO', 'Panel de Administración - Bot IMEI');
define('PANEL_LOGO', '🤖'); // Emoji o ruta a imagen
define('PANEL_EMPRESA', 'F4 Mobile');
define('PANEL_VERSION', '1.0.0');

// Configuración de sesión
define('SESSION_TIMEOUT', 3600); // 1 hora en segundos
define('SESSION_NAME', 'bot_imei_admin_session');

// ═══════════════════════════════════════════════════════════════
// CONFIGURACIÓN DE NOTIFICACIONES
// ═══════════════════════════════════════════════════════════════

define('NOTIF_SOUND_ENABLED', true);
define('NOTIF_DESKTOP_ENABLED', true);
define('NOTIF_AUTO_REFRESH', 30); // segundos

// ═══════════════════════════════════════════════════════════════
// CONFIGURACIÓN DE VISUALIZACIÓN
// ═══════════════════════════════════════════════════════════════

define('ITEMS_POR_PAGINA', 20);
define('TIMEZONE', 'America/Lima');
define('FECHA_FORMATO', 'd/m/Y H:i:s');
define('MONEDA_SIMBOLO', 'S/');

// ═══════════════════════════════════════════════════════════════
// PERMISOS Y ROLES
// ═══════════════════════════════════════════════════════════════

$PERMISOS_PANEL = [
    'ver_dashboard' => true,
    'ver_ordenes' => true,
    'aprobar_ordenes' => true,
    'rechazar_ordenes' => true,
    'gestionar_usuarios' => true,
    'gestionar_paquetes' => true,
    'gestionar_metodos_pago' => true,
    'ver_estadisticas' => true,
    'exportar_reportes' => true,
    'configurar_sistema' => true,
    'gestionar_promociones' => true,
    'enviar_mensajes_masivos' => true
];

// ═══════════════════════════════════════════════════════════════
// CONFIGURACIÓN DE REPORTES
// ═══════════════════════════════════════════════════════════════

define('REPORTES_DIR', __DIR__ . '/reportes/');
define('REPORTES_FORMATOS', ['pdf', 'excel', 'csv']);

// ═══════════════════════════════════════════════════════════════
// CONFIGURACIÓN DE BACKUP
// ═══════════════════════════════════════════════════════════════

define('BACKUP_DIR', __DIR__ . '/backups/');
define('BACKUP_AUTO_ENABLED', true);
define('BACKUP_AUTO_INTERVAL', 86400); // 24 horas

// ═══════════════════════════════════════════════════════════════
// TEMAS Y PERSONALIZACIÓN
// ═══════════════════════════════════════════════════════════════

$PANEL_THEME = [
    'color_primary' => '#667eea',
    'color_secondary' => '#764ba2',
    'color_success' => '#28a745',
    'color_danger' => '#dc3545',
    'color_warning' => '#ffc107',
    'color_info' => '#17a2b8',
    'sidebar_bg' => '#2c3e50',
    'sidebar_text' => '#ecf0f1',
    'modo_oscuro' => false
];

// ═══════════════════════════════════════════════════════════════
// CONFIGURACIÓN DE GRÁFICOS
// ═══════════════════════════════════════════════════════════════

$GRAFICOS_CONFIG = [
    'biblioteca' => 'chartjs', // chartjs, apexcharts
    'animaciones' => true,
    'colores' => ['#667eea', '#764ba2', '#f093fb', '#4facfe', '#43e97b'],
    'altura_defecto' => 300
];

?>
