<?php
/**
 * ═══════════════════════════════════════════════════════════════
 * TECLADOS PARA SISTEMA DE PAGOS
 * ═══════════════════════════════════════════════════════════════
 * 
 * Agregar estas funciones después de la función getTecladoPrincipal()
 * en bot_imei_corregido.php
 * 
 */

/**
 * Teclado para seleccionar paquetes de créditos
 */
function getTecladoPaquetes() {
    $teclado = [
        'keyboard' => [
            [
                ['text' => '📦 Básico - 50 créditos'],
                ['text' => '🎁 Estándar - 100 créditos']
            ],
            [
                ['text' => '💎 Premium - 250 créditos'],
                ['text' => '👑 VIP - 500 créditos']
            ],
            [
                ['text' => '📋 Mis Órdenes'],
                ['text' => '🔙 Volver']
            ]
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => false
    ];
    
    return json_encode($teclado);
}

/**
 * Teclado para seleccionar método de pago
 */
function getTecladoMetodosPago() {
    $teclado = [
        'keyboard' => [
            [
                ['text' => '💜 Pagar con Yape'],
                ['text' => '🟣 Pagar con Plin']
            ],
            [
                ['text' => '🏦 Transferencia Bancaria']
            ],
            [
                ['text' => '❌ Cancelar Compra']
            ]
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => true
    ];
    
    return json_encode($teclado);
}

/**
 * Teclado durante proceso de pago
 */
function getTecladoProcesoPago() {
    $teclado = [
        'keyboard' => [
            [
                ['text' => '📸 Ya envié el comprobante']
            ],
            [
                ['text' => '❌ Cancelar Orden'],
                ['text' => '❓ Ayuda con Pago']
            ]
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => false
    ];
    
    return json_encode($teclado);
}

/**
 * Teclado modificado del menú principal - CON PAGOS
 * REEMPLAZAR la función getTecladoPrincipal() existente con esta:
 */
function getTecladoPrincipal($esAdmin = false) {
    $botones = [
        [
            ['text' => '📱 Generar IMEI'],
            ['text' => '💳 Mis Créditos']
        ],
        [
            ['text' => '💰 Comprar Créditos'], // NUEVO
            ['text' => '📋 Mis Órdenes']       // NUEVO
        ],
        [
            ['text' => '📊 Mi Perfil'],
            ['text' => '📜 Historial']
        ],
        [
            ['text' => '❓ Ayuda']
        ]
    ];
    
    // Botón de admin si corresponde
    if ($esAdmin) {
        $botones[] = [['text' => '👑 Panel Admin']];
    }
    
    $teclado = [
        'keyboard' => $botones,
        'resize_keyboard' => true,
        'one_time_keyboard' => false
    ];
    
    return json_encode($teclado);
}

/**
 * Teclado de administración - CON PAGOS
 * REEMPLAZAR la función getTecladoAdmin() existente con esta:
 */
function getTecladoAdmin() {
    $teclado = [
        'keyboard' => [
            [
                ['text' => '📊 Estadísticas'],
                ['text' => '👥 Top Usuarios']
            ],
            [
                ['text' => '💸 Pagos Pendientes'],   // NUEVO
                ['text' => '✅ Aprobar Pagos']       // NUEVO
            ],
            [
                ['text' => '➕ Agregar Créditos'],
                ['text' => '🚫 Bloquear Usuario']
            ],
            [
                ['text' => '⭐ Hacer Premium'],
                ['text' => '📱 Gestionar Modelos']
            ],
            [
                ['text' => '📡 Stats API'],
                ['text' => '💰 Reporte Ventas']      // NUEVO
            ],
            [
                ['text' => '🔙 Volver al Menú']
            ]
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => false
    ];
    
    return json_encode($teclado);
}

/**
 * Teclado inline para acciones rápidas de órdenes (opcional)
 */
function getTecladoInlineOrden($ordenId) {
    $teclado = [
        'inline_keyboard' => [
            [
                [
                    'text' => '✅ Aprobar',
                    'callback_data' => "aprobar_orden_{$ordenId}"
                ],
                [
                    'text' => '❌ Rechazar',
                    'callback_data' => "rechazar_orden_{$ordenId}"
                ]
            ],
            [
                [
                    'text' => '📋 Ver Detalles',
                    'callback_data' => "ver_orden_{$ordenId}"
                ]
            ]
        ]
    ];
    
    return json_encode($teclado);
}

/**
 * Teclado inline para confirmación de acciones
 */
function getTecladoConfirmacion($accion, $id) {
    $teclado = [
        'inline_keyboard' => [
            [
                [
                    'text' => '✅ Sí, confirmar',
                    'callback_data' => "confirmar_{$accion}_{$id}"
                ],
                [
                    'text' => '❌ No, cancelar',
                    'callback_data' => "cancelar_{$accion}_{$id}"
                ]
            ]
        ]
    ];
    
    return json_encode($teclado);
}

?>
