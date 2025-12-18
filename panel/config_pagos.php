<?php
/**
 * ═══════════════════════════════════════════════════════════════
 * CONFIGURACIÓN DEL SISTEMA DE PAGOS
 * ═══════════════════════════════════════════════════════════════
 * 
 * Sistema de pagos con Yape, Plin y otros métodos
 * 
 */

// ═══════════════════════════════════════════════════════════════
// DATOS DE PAGO - YAPE/PLIN
// ═══════════════════════════════════════════════════════════════

define('PAGO_YAPE_NUMERO', '924780239');  // Tu número de Yape
define('PAGO_YAPE_NOMBRE', 'Victor Aguilar');  // Nombre que aparece en Yape

define('PAGO_PLIN_NUMERO', '924780239');  // Tu número de Plin
define('PAGO_PLIN_NOMBRE', 'Victor Aguilar');  // Nombre que aparece en Plin

// ═══════════════════════════════════════════════════════════════
// PAQUETES DE CRÉDITOS DISPONIBLES
// ═══════════════════════════════════════════════════════════════

$PAQUETES_CREDITOS = [
    'basico' => [
        'creditos' => 50,
        'precio' => 5.00,
        'moneda' => 'PEN',
        'ahorro' => 0,
        'popular' => false,
        'emoji' => '📦'
    ],
    'estandar' => [
        'creditos' => 100,
        'precio' => 9.00,
        'moneda' => 'PEN',
        'ahorro' => 10,
        'popular' => true,
        'emoji' => '🎁'
    ],
    'premium' => [
        'creditos' => 250,
        'precio' => 20.00,
        'moneda' => 'PEN',
        'ahorro' => 20,
        'popular' => false,
        'emoji' => '💎'
    ],
    'vip' => [
        'creditos' => 500,
        'precio' => 35.00,
        'moneda' => 'PEN',
        'ahorro' => 30,
        'popular' => false,
        'emoji' => '👑'
    ]
];

// ═══════════════════════════════════════════════════════════════
// MÉTODOS DE PAGO DISPONIBLES
// ═══════════════════════════════════════════════════════════════

$METODOS_PAGO = [
    'yape' => [
        'nombre' => 'Yape',
        'activo' => true,
        'emoji' => '💜',
        'instrucciones' => 'Escanea el QR o transfiere al número',
        'verificacion_automatica' => false
    ],
    'plin' => [
        'nombre' => 'Plin',
        'activo' => true,
        'emoji' => '🟣',
        'instrucciones' => 'Transfiere al número indicado',
        'verificacion_automatica' => false
    ],
    'transferencia' => [
        'nombre' => 'Transferencia Bancaria',
        'activo' => true,
        'emoji' => '🏦',
        'instrucciones' => 'Realiza la transferencia a la cuenta indicada',
        'verificacion_automatica' => false,
        // Datos bancarios
        'banco' => 'BCP',
        'cuenta' => '123-456789-0-12',
        'cci' => '00212300045678901234',
        'titular' => 'F4 Mobile'
    ]
];

// ═══════════════════════════════════════════════════════════════
// CONFIGURACIÓN DE PAGOS
// ═══════════════════════════════════════════════════════════════

// Tiempo de expiración para pagos pendientes (en segundos)
define('PAGO_TIEMPO_EXPIRACION', 3600 * 24); // 24 horas

// ¿Requiere comprobante de pago?
define('PAGO_REQUIERE_COMPROBANTE', true);

// ¿Notificar a admin automáticamente?
define('PAGO_NOTIFICAR_ADMIN', true);

// Canal/grupo para notificaciones de pago (opcional)
define('PAGO_CANAL_NOTIFICACIONES', null); // Ejemplo: -1001234567890

// ═══════════════════════════════════════════════════════════════
// MENSAJES PERSONALIZADOS
// ═══════════════════════════════════════════════════════════════

define('PAGO_MENSAJE_BIENVENIDA', '¡Bienvenido a nuestra tienda de créditos! 🎉');
define('PAGO_MENSAJE_INSTRUCCIONES', 'Selecciona un paquete y sigue las instrucciones de pago.');
define('PAGO_MENSAJE_AGRADECIMIENTO', '¡Gracias por tu compra! Tu pago está siendo procesado. 🙏');

?>
