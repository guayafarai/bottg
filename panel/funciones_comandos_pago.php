<?php
/**
 * ═══════════════════════════════════════════════════════════════
 * FUNCIONES DE COMANDOS DE PAGO
 * ═══════════════════════════════════════════════════════════════
 * 
 * Agregar estas funciones al archivo bot_imei_corregido.php
 * INSERTAR DESPUÉS DE LA LÍNEA 949 (después de comandoAyuda)
 * 
 */

// ============================================
// COMANDO: COMPRAR CRÉDITOS (NUEVO)
// ============================================

function comandoComprarCreditosNuevo($chatId, $telegramId, $sistemaPagos) {
    $respuesta = "╔═══════════════════════════╗\n";
    $respuesta .= "║  🛒 TIENDA DE CRÉDITOS   ║\n";
    $respuesta .= "╚═══════════════════════════╝\n\n";
    
    $respuesta .= PAGO_MENSAJE_BIENVENIDA . "\n\n";
    $respuesta .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $respuesta .= "💎 *PAQUETES DISPONIBLES*\n";
    $respuesta .= "━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    $paquetes = $sistemaPagos->obtenerPaquetes();
    
    foreach ($paquetes as $id => $paquete) {
        $respuesta .= $paquete['emoji'] . " *" . strtoupper($id) . "*";
        
        if ($paquete['popular']) {
            $respuesta .= " 🔥 POPULAR";
        }
        
        $respuesta .= "\n";
        $respuesta .= "├ 💎 {$paquete['creditos']} créditos\n";
        $respuesta .= "├ 💵 {$paquete['moneda']} {$paquete['precio']}\n";
        
        if ($paquete['ahorro'] > 0) {
            $respuesta .= "├ 🎁 Ahorra {$paquete['ahorro']}%\n";
        }
        
        $valorPorCredito = $paquete['precio'] / $paquete['creditos'];
        $respuesta .= "└ 📊 S/ " . number_format($valorPorCredito, 2) . " por crédito\n\n";
    }
    
    $respuesta .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $respuesta .= "💳 *MÉTODOS DE PAGO*\n";
    $respuesta .= "━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    $metodos = $sistemaPagos->obtenerMetodosPago();
    
    foreach ($metodos as $id => $metodo) {
        $respuesta .= "{$metodo['emoji']} {$metodo['nombre']}\n";
    }
    
    $respuesta .= "\n━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $respuesta .= "📝 *CÓMO COMPRAR*\n";
    $respuesta .= "━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    $respuesta .= "Usa los botones de abajo\n";
    $respuesta .= "para seleccionar tu paquete 👇";
    
    // Teclado con paquetes
    $teclado = getTecladoPaquetes();
    
    enviarMensaje($chatId, $respuesta, 'Markdown', $teclado);
}

// ============================================
// COMANDO: SELECCIONAR PAQUETE
// ============================================

function comandoSeleccionarPaquete($chatId, $telegramId, $paqueteId, $sistemaPagos, $estados) {
    $paquete = $sistemaPagos->obtenerPaquete($paqueteId);
    
    if (!$paquete) {
        enviarMensaje($chatId, "❌ Paquete no válido");
        return;
    }
    
    // Guardar paquete seleccionado en el estado
    $estados->establecerEstado($chatId, 'esperando_metodo_pago', [
        'paquete_id' => $paqueteId
    ]);
    
    $respuesta = "✅ Has seleccionado:\n\n";
    $respuesta .= "{$paquete['emoji']} *Paquete " . strtoupper($paqueteId) . "*\n";
    $respuesta .= "💎 {$paquete['creditos']} créditos\n";
    $respuesta .= "💵 {$paquete['moneda']} {$paquete['precio']}\n\n";
    
    if ($paquete['ahorro'] > 0) {
        $respuesta .= "🎁 ¡Ahorras {$paquete['ahorro']}%!\n\n";
    }
    
    $respuesta .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $respuesta .= "💳 *Selecciona método de pago:*\n";
    $respuesta .= "━━━━━━━━━━━━━━━━━━━━━━━━";
    
    $teclado = getTecladoMetodosPago();
    
    enviarMensaje($chatId, $respuesta, 'Markdown', $teclado);
}

// ============================================
// COMANDO: PROCESAR MÉTODO DE PAGO
// ============================================

function comandoProcesarMetodoPago($chatId, $telegramId, $metodoPago, $sistemaPagos, $estados) {
    $estado = $estados->obtenerEstado($chatId);
    
    if ($estado === false || !isset($estado['datos']['paquete_id'])) {
        enviarMensaje($chatId, "❌ Sesión expirada. Inicia nuevamente desde /start");
        $estados->limpiarEstado($chatId);
        return;
    }
    
    $paqueteId = $estado['datos']['paquete_id'];
    
    // Crear orden de pago
    $orden = $sistemaPagos->crearOrdenPago($telegramId, $paqueteId, $metodoPago);
    
    if (!$orden) {
        enviarMensaje($chatId, "❌ Error al crear la orden. Intenta de nuevo.");
        $estados->limpiarEstado($chatId);
        return;
    }
    
    // Guardar orden en el estado para recibir comprobante
    $estados->establecerEstado($chatId, 'esperando_comprobante', [
        'orden_id' => $orden['orden_id'],
        'codigo_orden' => $orden['codigo_orden']
    ]);
    
    // Generar mensaje de pago
    $mensajePago = $sistemaPagos->generarMensajePago($orden, $metodoPago);
    
    enviarMensaje($chatId, $mensajePago, 'Markdown');
    
    // Si es Yape, enviar QR
    if ($metodoPago == 'yape') {
        require_once(__DIR__ . '/generador_qr.php');
        
        $ordenData = $sistemaPagos->obtenerOrden($orden['orden_id']);
        $qrUrl = GeneradorQR::generarQROrden($ordenData);
        
        enviarFoto($chatId, $qrUrl, "📱 Escanea este QR con tu app Yape");
    }
    
    // Notificar a administradores
    if (PAGO_NOTIFICAR_ADMIN) {
        notificarNuevaOrden($orden, $telegramId);
    }
}

// ============================================
// COMANDO: RECIBIR COMPROBANTE
// ============================================

function comandoRecibirComprobante($chatId, $telegramId, $message, $sistemaPagos, $estados, $db) {
    $estado = $estados->obtenerEstado($chatId);
    
    if ($estado === false || $estado['estado'] != 'esperando_comprobante') {
        return false; // No está esperando comprobante
    }
    
    $ordenId = $estado['datos']['orden_id'];
    $codigoOrden = $estado['datos']['codigo_orden'];
    
    // Verificar si es una foto
    if (!isset($message['photo'])) {
        enviarMensaje($chatId, "❌ Por favor envía una *foto* del comprobante de pago.\n\nAsegúrate de que se vea clara.");
        return true;
    }
    
    // Obtener el file_id de la foto (la de mejor calidad)
    $photos = $message['photo'];
    $photo = end($photos); // Última es la de mejor calidad
    $fileId = $photo['file_id'];
    
    // Adjuntar comprobante a la orden
    if ($sistemaPagos->adjuntarComprobante($ordenId, $fileId, 'photo')) {
        $estados->limpiarEstado($chatId);
        
        $respuesta = "✅ *¡Comprobante recibido!*\n\n";
        $respuesta .= "🔖 Código de orden: `{$codigoOrden}`\n\n";
        $respuesta .= "Tu pago está siendo revisado por nuestro equipo.\n";
        $respuesta .= "Te notificaremos en breve. ⏱️\n\n";
        $respuesta .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $respuesta .= "⏰ *Tiempo de verificación:*\n";
        $respuesta .= "Generalmente de 5 a 30 minutos\n\n";
        $respuesta .= PAGO_MENSAJE_AGRADECIMIENTO;
        
        enviarMensaje($chatId, $respuesta, 'Markdown');
        
        // Notificar a administradores
        if (PAGO_NOTIFICAR_ADMIN) {
            notificarComprobanteRecibido($ordenId, $telegramId);
        }
        
        return true;
    } else {
        enviarMensaje($chatId, "❌ Error al procesar el comprobante. Intenta nuevamente.");
        return true;
    }
}

// ============================================
// COMANDO: MIS ÓRDENES
// ============================================

function comandoMisOrdenes($chatId, $telegramId, $sistemaPagos) {
    $ordenes = $sistemaPagos->obtenerHistorialUsuario($telegramId, 10);
    
    if (empty($ordenes)) {
        enviarMensaje($chatId, "📋 No tienes órdenes de compra aún.\n\nUsa *💰 Comprar Créditos* para realizar tu primera compra.");
        return;
    }
    
    $respuesta = "╔═══════════════════════════╗\n";
    $respuesta .= "║   📋 MIS ÓRDENES         ║\n";
    $respuesta .= "╚═══════════════════════════╝\n\n";
    
    foreach ($ordenes as $orden) {
        $estadoEmoji = [
            'pendiente' => '⏳',
            'revision' => '👁️',
            'aprobada' => '✅',
            'rechazada' => '❌',
            'cancelada' => '🚫',
            'expirada' => '⏰'
        ];
        
        $emoji = $estadoEmoji[$orden['estado']] ?? '❓';
        
        $respuesta .= "{$emoji} *Orden #{$orden['id']}*\n";
        $respuesta .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $respuesta .= "🔖 Código: `{$orden['codigo_orden']}`\n";
        $respuesta .= "💎 Créditos: {$orden['creditos']}\n";
        $respuesta .= "💵 Monto: {$orden['moneda']} {$orden['monto']}\n";
        $respuesta .= "💳 Método: " . ucfirst($orden['metodo_pago']) . "\n";
        $respuesta .= "📅 Fecha: " . date('d/m/Y H:i', strtotime($orden['fecha_creacion'])) . "\n";
        $respuesta .= "📊 Estado: *" . ucfirst($orden['estado']) . "*\n";
        
        if ($orden['estado'] == 'rechazada' && !empty($orden['motivo_rechazo'])) {
            $respuesta .= "📝 Motivo: {$orden['motivo_rechazo']}\n";
        }
        
        $respuesta .= "\n";
    }
    
    $respuesta .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $respuesta .= "💡 *Leyenda:*\n";
    $respuesta .= "⏳ Pendiente | 👁️ En revisión\n";
    $respuesta .= "✅ Aprobada | ❌ Rechazada";
    
    enviarMensaje($chatId, $respuesta);
}

// ============================================
// ADMIN: REVISAR PAGOS PENDIENTES
// ============================================

function comandoRevisarPagosPendientes($chatId, $sistemaPagos) {
    $ordenes = $sistemaPagos->obtenerOrdenesPendientes(20);
    
    if (empty($ordenes)) {
        enviarMensaje($chatId, "✅ No hay pagos pendientes de revisión.");
        return;
    }
    
    $respuesta = "👁️ *PAGOS PENDIENTES DE REVISIÓN*\n\n";
    $respuesta .= "Total: *" . count($ordenes) . " órdenes*\n\n";
    $respuesta .= "━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    foreach ($ordenes as $orden) {
        $nombreUsuario = $orden['first_name'];
        if (!empty($orden['username'])) {
            $nombreUsuario .= " (@{$orden['username']})";
        }
        
        $horasDesde = $orden['horas_desde_creacion'];
        $tiempoTexto = $horasDesde < 1 ? "Hace " . round($horasDesde * 60) . " min" : "Hace {$horasDesde}h";
        
        $respuesta .= "🆔 *Orden #{$orden['id']}*\n";
        $respuesta .= "👤 Usuario: {$nombreUsuario}\n";
        $respuesta .= "💎 Créditos: {$orden['creditos']}\n";
        $respuesta .= "💵 Monto: {$orden['moneda']} {$orden['monto']}\n";
        $respuesta .= "💳 Método: " . ucfirst($orden['metodo_pago']) . "\n";
        $respuesta .= "⏰ {$tiempoTexto}\n";
        $respuesta .= "🔖 `{$orden['codigo_orden']}`\n\n";
        
        $respuesta .= "*Acciones:*\n";
        $respuesta .= "`/ver_orden {$orden['id']}` - Ver detalles\n";
        $respuesta .= "`/aprobar {$orden['id']}` - Aprobar\n";
        $respuesta .= "`/rechazar {$orden['id']} [motivo]` - Rechazar\n\n";
        $respuesta .= "━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    }
    
    $respuesta .= "💡 Usa los comandos para gestionar";
    
    enviarMensaje($chatId, $respuesta);
}

// ============================================
// ADMIN: VER ORDEN DETALLADA
// ============================================

function comandoVerOrden($chatId, $texto, $sistemaPagos) {
    $partes = explode(' ', $texto);
    
    if (count($partes) < 2) {
        enviarMensaje($chatId, "❌ Uso: `/ver_orden [ID]`\nEjemplo: `/ver_orden 123`");
        return;
    }
    
    $ordenId = intval($partes[1]);
    $orden = $sistemaPagos->obtenerOrden($ordenId);
    
    if (!$orden) {
        enviarMensaje($chatId, "❌ Orden no encontrada");
        return;
    }
    
    $respuesta = "🔍 *DETALLES DE LA ORDEN #{$ordenId}*\n\n";
    $respuesta .= "━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    $respuesta .= "🔖 Código: `{$orden['codigo_orden']}`\n";
    $respuesta .= "👤 Usuario ID: `{$orden['telegram_id']}`\n";
    $respuesta .= "💎 Créditos: {$orden['creditos']}\n";
    $respuesta .= "💵 Monto: {$orden['moneda']} {$orden['monto']}\n";
    $respuesta .= "💳 Método: " . ucfirst($orden['metodo_pago']) . "\n";
    $respuesta .= "📊 Estado: *" . ucfirst($orden['estado']) . "*\n";
    $respuesta .= "📅 Creada: " . date('d/m/Y H:i:s', strtotime($orden['fecha_creacion'])) . "\n";
    
    if ($orden['fecha_aprobacion']) {
        $respuesta .= "✅ Aprobada: " . date('d/m/Y H:i:s', strtotime($orden['fecha_aprobacion'])) . "\n";
    }
    
    if (!empty($orden['motivo_rechazo'])) {
        $respuesta .= "📝 Motivo rechazo: {$orden['motivo_rechazo']}\n";
    }
    
    $respuesta .= "\n━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    $respuesta .= "*Acciones disponibles:*\n";
    $respuesta .= "`/aprobar {$ordenId}` - Aprobar orden\n";
    $respuesta .= "`/rechazar {$ordenId} motivo` - Rechazar\n";
    
    enviarMensaje($chatId, $respuesta);
    
    // Si tiene comprobante, enviarlo
    if (!empty($orden['comprobante_file_id'])) {
        enviarMensaje($chatId, "📎 *Comprobante adjunto:*");
        enviarFoto($chatId, $orden['comprobante_file_id'], "Comprobante de la orden #{$ordenId}");
    }
}

// ============================================
// FUNCIONES AUXILIARES
// ============================================

function notificarNuevaOrden($orden, $telegramId) {
    foreach (ADMIN_IDS as $adminId) {
        $mensaje = "🔔 *NUEVA ORDEN DE PAGO*\n\n";
        $mensaje .= "🆔 Orden #{$orden['orden_id']}\n";
        $mensaje .= "👤 Usuario: `{$telegramId}`\n";
        $mensaje .= "💎 Créditos: {$orden['paquete']['creditos']}\n";
        $mensaje .= "💵 Monto: {$orden['paquete']['moneda']} {$orden['paquete']['precio']}\n";
        $mensaje .= "💳 Método: {$orden['metodo']['nombre']}\n";
        $mensaje .= "🔖 Código: `{$orden['codigo_orden']}`\n\n";
        $mensaje .= "⏳ Esperando comprobante...";
        
        enviarMensaje($adminId, $mensaje);
    }
}

function notificarComprobanteRecibido($ordenId, $telegramId) {
    foreach (ADMIN_IDS as $adminId) {
        $mensaje = "📸 *COMPROBANTE RECIBIDO*\n\n";
        $mensaje .= "🆔 Orden #{$ordenId}\n";
        $mensaje .= "👤 Usuario: `{$telegramId}`\n\n";
        $mensaje .= "*Acciones:*\n";
        $mensaje .= "`/ver_orden {$ordenId}` - Ver detalles\n";
        $mensaje .= "`/aprobar {$ordenId}` - Aprobar\n";
        $mensaje .= "`/rechazar {$ordenId} motivo` - Rechazar";
        
        enviarMensaje($adminId, $mensaje);
    }
}

?>
