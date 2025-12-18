<?php
/**
 * PARTE 2 - CONTINÚA DEL ARCHIVO ANTERIOR
 * Agregar este código después de comandoAyuda() en bot_imei_corregido_fixed.php
 */

// ============================================
// FUNCIONES DE PAGO
// ============================================

function comandoComprarCreditosNuevo($chatId, $telegramId, $sistemaPagos) {
    $respuesta = "╔═══════════════════════════╗\n";
    $respuesta .= "║  🛒 TIENDA DE CRÉDITOS   ║\n";
    $respuesta .= "╚═══════════════════════════╝\n\n";
    
    $paquetes = $sistemaPagos->obtenerPaquetes();
    
    foreach ($paquetes as $id => $paquete) {
        $respuesta .= $paquete['emoji'] . " *" . strtoupper($id) . "*\n";
        $respuesta .= "💎 {$paquete['creditos']} créditos\n";
        $respuesta .= "💵 {$paquete['moneda']} {$paquete['precio']}\n\n";
    }
    
    $respuesta .= "Selecciona tu paquete 👇";
    
    $teclado = getTecladoPaquetes();
    enviarMensaje($chatId, $respuesta, 'Markdown', $teclado);
}

function comandoSeleccionarPaquete($chatId, $telegramId, $paqueteId, $sistemaPagos, $estados) {
    $paquete = $sistemaPagos->obtenerPaquete($paqueteId);
    
    if (!$paquete) {
        enviarMensaje($chatId, "❌ Paquete no válido");
        return;
    }
    
    $estados->establecerEstado($chatId, 'esperando_metodo_pago', [
        'paquete_id' => $paqueteId
    ]);
    
    $respuesta = "✅ Has seleccionado:\n\n";
    $respuesta .= "{$paquete['emoji']} *Paquete " . strtoupper($paqueteId) . "*\n";
    $respuesta .= "💎 {$paquete['creditos']} créditos\n";
    $respuesta .= "💵 {$paquete['moneda']} {$paquete['precio']}\n\n";
    $respuesta .= "💳 *Selecciona método de pago:*";
    
    $teclado = getTecladoMetodosPago();
    enviarMensaje($chatId, $respuesta, 'Markdown', $teclado);
}

function comandoProcesarMetodoPago($chatId, $telegramId, $metodoPago, $sistemaPagos, $estados) {
    $estado = $estados->obtenerEstado($chatId);
    
    if ($estado === false || !isset($estado['datos']['paquete_id'])) {
        enviarMensaje($chatId, "❌ Sesión expirada. Inicia nuevamente desde /start");
        $estados->limpiarEstado($chatId);
        return;
    }
    
    $paqueteId = $estado['datos']['paquete_id'];
    
    $orden = $sistemaPagos->crearOrdenPago($telegramId, $paqueteId, $metodoPago);
    
    if (!$orden) {
        enviarMensaje($chatId, "❌ Error al crear la orden. Intenta de nuevo.");
        $estados->limpiarEstado($chatId);
        return;
    }
    
    $estados->establecerEstado($chatId, 'esperando_comprobante', [
        'orden_id' => $orden['orden_id'],
        'codigo_orden' => $orden['codigo_orden']
    ]);
    
    $mensajePago = $sistemaPagos->generarMensajePago($orden, $metodoPago);
    enviarMensaje($chatId, $mensajePago, 'Markdown');
    
    if ($metodoPago == 'yape') {
        $ordenData = $sistemaPagos->obtenerOrden($orden['orden_id']);
        $qrUrl = GeneradorQR::generarQROrden($ordenData);
        enviarFoto($chatId, $qrUrl, "📱 Escanea este QR con tu app Yape");
    }
    
    if (PAGO_NOTIFICAR_ADMIN) {
        notificarNuevaOrden($orden, $telegramId);
    }
}

function comandoRecibirComprobante($chatId, $telegramId, $message, $sistemaPagos, $estados, $db) {
    $estado = $estados->obtenerEstado($chatId);
    
    if ($estado === false || $estado['estado'] != 'esperando_comprobante') {
        return false;
    }
    
    $ordenId = $estado['datos']['orden_id'];
    $codigoOrden = $estado['datos']['codigo_orden'];
    
    if (!isset($message['photo'])) {
        enviarMensaje($chatId, "❌ Por favor envía una *foto* del comprobante.");
        return true;
    }
    
    $photos = $message['photo'];
    $photo = end($photos);
    $fileId = $photo['file_id'];
    
    if ($sistemaPagos->adjuntarComprobante($ordenId, $fileId, 'photo')) {
        $estados->limpiarEstado($chatId);
        
        $respuesta = "✅ *¡Comprobante recibido!*\n\n";
        $respuesta .= "🔖 Código: `{$codigoOrden}`\n\n";
        $respuesta .= "Tu pago está siendo revisado.\n";
        $respuesta .= "Te notificaremos en breve. ⏱️";
        
        enviarMensaje($chatId, $respuesta, 'Markdown');
        
        if (PAGO_NOTIFICAR_ADMIN) {
            notificarComprobanteRecibido($ordenId, $telegramId);
        }
        
        return true;
    } else {
        enviarMensaje($chatId, "❌ Error al procesar el comprobante.");
        return true;
    }
}

function comandoMisOrdenes($chatId, $telegramId, $sistemaPagos) {
    $ordenes = $sistemaPagos->obtenerHistorialUsuario($telegramId, 10);
    
    if (empty($ordenes)) {
        enviarMensaje($chatId, "📋 No tienes órdenes de compra aún.");
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
            'rechazada' => '❌'
        ];
        
        $emoji = $estadoEmoji[$orden['estado']] ?? '❓';
        
        $respuesta .= "{$emoji} *Orden #{$orden['id']}*\n";
        $respuesta .= "🔖 `{$orden['codigo_orden']}`\n";
        $respuesta .= "💎 {$orden['creditos']} créditos\n";
        $respuesta .= "📊 Estado: *" . ucfirst($orden['estado']) . "*\n\n";
    }
    
    enviarMensaje($chatId, $respuesta);
}

function comandoRevisarPagosPendientes($chatId, $sistemaPagos) {
    $ordenes = $sistemaPagos->obtenerOrdenesPendientes(20);
    
    if (empty($ordenes)) {
        enviarMensaje($chatId, "✅ No hay pagos pendientes.");
        return;
    }
    
    $respuesta = "👁️ *PAGOS PENDIENTES*\n\n";
    $respuesta .= "Total: *" . count($ordenes) . " órdenes*\n\n";
    
    foreach ($ordenes as $orden) {
        $nombreUsuario = $orden['first_name'];
        
        $respuesta .= "🆔 *Orden #{$orden['id']}*\n";
        $respuesta .= "👤 {$nombreUsuario}\n";
        $respuesta .= "💎 {$orden['creditos']} créditos\n";
        $respuesta .= "💵 {$orden['moneda']} {$orden['monto']}\n";
        $respuesta .= "🔖 `{$orden['codigo_orden']}`\n\n";
        $respuesta .= "`/ver_orden {$orden['id']}`\n";
        $respuesta .= "`/aprobar {$orden['id']}`\n\n";
    }
    
    enviarMensaje($chatId, $respuesta);
}

function comandoVerOrden($chatId, $texto, $sistemaPagos) {
    $partes = explode(' ', $texto);
    
    if (count($partes) < 2) {
        enviarMensaje($chatId, "❌ Uso: `/ver_orden [ID]`");
        return;
    }
    
    $ordenId = intval($partes[1]);
    $orden = $sistemaPagos->obtenerOrden($ordenId);
    
    if (!$orden) {
        enviarMensaje($chatId, "❌ Orden no encontrada");
        return;
    }
    
    $respuesta = "🔍 *ORDEN #{$ordenId}*\n\n";
    $respuesta .= "🔖 `{$orden['codigo_orden']}`\n";
    $respuesta .= "👤 `{$orden['telegram_id']}`\n";
    $respuesta .= "💎 {$orden['creditos']} créditos\n";
    $respuesta .= "💵 {$orden['moneda']} {$orden['monto']}\n";
    $respuesta .= "📊 Estado: *{$orden['estado']}*\n\n";
    $respuesta .= "`/aprobar {$ordenId}` - Aprobar\n";
    $respuesta .= "`/rechazar {$ordenId}` - Rechazar";
    
    enviarMensaje($chatId, $respuesta);
    
    if (!empty($orden['comprobante_file_id'])) {
        enviarFoto($chatId, $orden['comprobante_file_id'], "Comprobante");
    }
}

function comandoAprobarPagoAdmin($chatId, $texto, $adminId, $sistemaPagos, $db) {
    $partes = explode(' ', $texto);
    
    if (count($partes) < 2) {
        enviarMensaje($chatId, "❌ Uso: `/aprobar [ORDEN_ID]`");
        return;
    }
    
    $ordenId = intval($partes[1]);
    $orden = $sistemaPagos->obtenerOrden($ordenId);
    
    if (!$orden) {
        enviarMensaje($chatId, "❌ Orden no encontrada");
        return;
    }
    
    if ($sistemaPagos->aprobarOrden($ordenId, $adminId)) {
        $respuesta = "✅ *ORDEN APROBADA*\n\n";
        $respuesta .= "🆔 Orden #{$ordenId}\n";
        $respuesta .= "💎 Créditos acreditados automáticamente";
        
        enviarMensaje($chatId, $respuesta);
        
        $mensajeUsuario = "🎉 *¡PAGO APROBADO!*\n\n";
        $mensajeUsuario .= "✅ Tu pago ha sido verificado\n";
        $mensajeUsuario .= "💎 Se han agregado *{$orden['creditos']} créditos*\n\n";
        $mensajeUsuario .= "¡Ya puedes usar tus créditos! 🚀";
        
        enviarMensaje($orden['telegram_id'], $mensajeUsuario);
    } else {
        enviarMensaje($chatId, "❌ Error al aprobar la orden");
    }
}

function comandoRechazarPagoAdmin($chatId, $texto, $sistemaPagos) {
    $partes = explode(' ', $texto, 3);
    
    if (count($partes) < 2) {
        enviarMensaje($chatId, "❌ Uso: `/rechazar [ORDEN_ID] [motivo]`");
        return;
    }
    
    $ordenId = intval($partes[1]);
    $motivo = isset($partes[2]) ? $partes[2] : 'No especificado';
    
    $orden = $sistemaPagos->obtenerOrden($ordenId);
    
    if (!$orden) {
        enviarMensaje($chatId, "❌ Orden no encontrada");
        return;
    }
    
    if ($sistemaPagos->rechazarOrden($ordenId, $motivo)) {
        enviarMensaje($chatId, "❌ *ORDEN RECHAZADA*");
        
        $mensajeUsuario = "❌ *PAGO RECHAZADO*\n\n";
        $mensajeUsuario .= "📝 Motivo: {$motivo}\n\n";
        $mensajeUsuario .= "Si crees que es un error, contacta\n";
        $mensajeUsuario .= "con soporte: @CHAMOGSM";
        
        enviarMensaje($orden['telegram_id'], $mensajeUsuario);
    } else {
        enviarMensaje($chatId, "❌ Error al rechazar la orden");
    }
}

function notificarNuevaOrden($orden, $telegramId) {
    foreach (ADMIN_IDS as $adminId) {
        $mensaje = "🔔 *NUEVA ORDEN DE PAGO*\n\n";
        $mensaje .= "🆔 Orden #{$orden['orden_id']}\n";
        $mensaje .= "👤 Usuario: `{$telegramId}`\n";
        $mensaje .= "💎 Créditos: {$orden['paquete']['creditos']}\n";
        $mensaje .= "💵 Monto: {$orden['paquete']['moneda']} {$orden['paquete']['precio']}\n";
        $mensaje .= "🔖 `{$orden['codigo_orden']}`\n\n";
        $mensaje .= "⏳ Esperando comprobante...";
        
        enviarMensaje($adminId, $mensaje);
    }
}

function notificarComprobanteRecibido($ordenId, $telegramId) {
    foreach (ADMIN_IDS as $adminId) {
        $mensaje = "📸 *COMPROBANTE RECIBIDO*\n\n";
        $mensaje .= "🆔 Orden #{$ordenId}\n";
        $mensaje .= "👤 Usuario: `{$telegramId}`\n\n";
        $mensaje .= "`/ver_orden {$ordenId}` - Ver detalles\n";
        $mensaje .= "`/aprobar {$ordenId}` - Aprobar";
        
        enviarMensaje($adminId, $mensaje);
    }
}

function procesarTAC($chatId, $texto, $telegramId, $db, $estados) {
    $usuario = $db->getUsuario($telegramId);
    
    if (!$usuario) {
        enviarMensaje($chatId, "❌ No estás registrado. Usa /start");
        return;
    }
    
    if ($usuario['bloqueado']) {
        enviarMensaje($chatId, "🚫 Tu cuenta está suspendida");
        return;
    }
    
    $tac = extraerTAC($texto);
    if (!$tac) {
        $tac = preg_replace('/[^0-9]/', '', $texto);
    }
    
    if (!validarTAC($tac)) {
        $respuesta = "❌ *TAC INVÁLIDO*\n\n";
        $respuesta .= "El TAC debe tener 8 dígitos\n\n";
        $respuesta .= "Ejemplo: `35203310`";
        enviarMensaje($chatId, $respuesta);
        return;
    }
    
    if ($usuario['creditos'] < COSTO_GENERACION && !$usuario['es_premium']) {
        $respuesta = "⚠️ *SIN CRÉDITOS*\n\n";
        $respuesta .= "Tu saldo: *{$usuario['creditos']}*\n";
        $respuesta .= "Necesitas: *" . COSTO_GENERACION . "*\n\n";
        $respuesta .= "🛒 → *💰 Comprar Créditos*";
        enviarMensaje($chatId, $respuesta);
        return;
    }
    
    $modeloData = $db->buscarModelo($tac);
    
    $imeis = generarMultiplesIMEIs($tac, 2);
    
    if (!$usuario['es_premium']) {
        $db->actualizarCreditos($telegramId, COSTO_GENERACION, 'subtract');
        $db->registrarTransaccion($telegramId, 'uso', COSTO_GENERACION, "Generación de IMEIs - TAC: {$tac}");
    }
    
    $db->incrementarGeneraciones($telegramId);
    
    $nombreModelo = $modeloData ? $modeloData['modelo'] : 'Desconocido';
    $db->registrarUso($telegramId, $tac, $nombreModelo);
    
    $respuesta = "╔═══════════════════════════╗\n";
    $respuesta .= "║  ✅ GENERACIÓN EXITOSA    ║\n";
    $respuesta .= "╚═══════════════════════════╝\n\n";
    $respuesta .= "📱 Modelo: *{$nombreModelo}*\n\n";
    $respuesta .= "📋 *2 IMEIS GENERADOS*\n\n";
    
    foreach ($imeis as $index => $imei) {
        $numero = $index + 1;
        $respuesta .= "🔹 IMEI {$numero}:\n";
        $respuesta .= "`{$imei['imei_completo']}`\n\n";
    }
    
    $usuario = $db->getUsuario($telegramId);
    if (!$usuario['es_premium']) {
        $respuesta .= "💰 Restantes: *{$usuario['creditos']}*";
    } else {
        $respuesta .= "⭐ *Usuario Premium*";
    }
    
    enviarMensaje($chatId, $respuesta);
}

function comandoEstadisticasAdmin($chatId, $db) {
    $stats = $db->getEstadisticasGenerales();
    
    $respuesta = "📊 *ESTADÍSTICAS*\n\n";
    $respuesta .= "👥 Usuarios: {$stats['total_usuarios']}\n";
    $respuesta .= "💰 Créditos: {$stats['total_creditos']}\n";
    $respuesta .= "📱 Generaciones: {$stats['total_generaciones']}\n";
    $respuesta .= "⭐ Premium: {$stats['usuarios_premium']}\n";
    $respuesta .= "💸 Pagos pendientes: {$stats['pagos_pendientes']}";
    
    enviarMensaje($chatId, $respuesta);
}

function comandoTopUsuarios($chatId, $db) {
    $top = $db->getTopUsuarios(10);
    
    if (empty($top)) {
        enviarMensaje($chatId, "No hay usuarios registrados.");
        return;
    }
    
    $respuesta = "👥 *TOP 10 USUARIOS*\n\n";
    
    foreach ($top as $i => $usuario) {
        $pos = $i + 1;
        $emoji = $pos == 1 ? "🥇" : ($pos == 2 ? "🥈" : ($pos == 3 ? "🥉" : "{$pos}."));
        $username = $usuario['username'] ? "@{$usuario['username']}" : $usuario['first_name'];
        
        $respuesta .= "{$emoji} {$username}\n";
        $respuesta .= "   📊 {$usuario['total_generaciones']} generaciones\n\n";
    }
    
    enviarMensaje($chatId, $respuesta);
}

// ============================================
// PROCESAMIENTO DE ACTUALIZACIONES
// ============================================

function procesarActualizacion($update, $db, $estados) {
    if (!isset($update['message'])) {
        return;
    }
    
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $telegramId = $message['from']['id'];
    $texto = isset($message['text']) ? trim($message['text']) : '';
    
    $usuario = $db->getUsuario($telegramId);
    $esAdminUser = esAdmin($telegramId);
    
    $sistemaPagos = new SistemaPagos($db);
    
    if (isset($message['photo'])) {
        if (comandoRecibirComprobante($chatId, $telegramId, $message, $sistemaPagos, $estados, $db)) {
            return;
        }
    }
    
    if ($texto == '/start') {
        $estados->limpiarEstado($chatId);
        comandoStart($chatId, $message, $db);
    }
    elseif ($texto == '💳 Mis Créditos') {
        comandoMisCreditos($chatId, $telegramId, $db);
    }
    elseif ($texto == '📊 Mi Perfil') {
        comandoPerfil($chatId, $telegramId, $db);
    }
    elseif ($texto == '📜 Historial') {
        comandoHistorial($chatId, $telegramId, $db);
    }
    elseif ($texto == '❓ Ayuda') {
        comandoAyuda($chatId);
    }
    elseif ($texto == '📱 Generar IMEI') {
        $estados->limpiarEstado($chatId);
        enviarMensaje($chatId, "Envía un TAC de 8 dígitos.\n\nEjemplo: `35203310`\n\n💳 Costo: " . COSTO_GENERACION . " crédito");
    }
    elseif ($texto == '💰 Comprar Créditos') {
        comandoComprarCreditosNuevo($chatId, $telegramId, $sistemaPagos);
    }
    elseif ($texto == '📋 Mis Órdenes') {
        comandoMisOrdenes($chatId, $telegramId, $sistemaPagos);
    }
    elseif (strpos($texto, 'Básico') !== false) {
        comandoSeleccionarPaquete($chatId, $telegramId, 'basico', $sistemaPagos, $estados);
    }
    elseif (strpos($texto, 'Estándar') !== false) {
        comandoSeleccionarPaquete($chatId, $telegramId, 'estandar', $sistemaPagos, $estados);
    }
    elseif (strpos($texto, 'Premium') !== false && !strpos($texto, 'Hacer')) {
        comandoSeleccionarPaquete($chatId, $telegramId, 'premium', $sistemaPagos, $estados);
    }
    elseif (strpos($texto, 'VIP') !== false) {
        comandoSeleccionarPaquete($chatId, $telegramId, 'vip', $sistemaPagos, $estados);
    }
    elseif (strpos($texto, 'Pagar con Yape') !== false) {
        comandoProcesarMetodoPago($chatId, $telegramId, 'yape', $sistemaPagos, $estados);
    }
    elseif (strpos($texto, 'Pagar con Plin') !== false) {
        comandoProcesarMetodoPago($chatId, $telegramId, 'plin', $sistemaPagos, $estados);
    }
    elseif (strpos($texto, 'Transferencia') !== false) {
        comandoProcesarMetodoPago($chatId, $telegramId, 'transferencia', $sistemaPagos, $estados);
    }
    elseif ($texto == '❌ Cancelar Compra') {
        $estados->limpiarEstado($chatId);
        enviarMensaje($chatId, "❌ Compra cancelada", 'Markdown', getTecladoPrincipal($esAdminUser));
    }
    elseif ($texto == '👑 Panel Admin' && $esAdminUser) {
        enviarMensaje($chatId, "👑 *PANEL ADMIN*", 'Markdown', getTecladoAdmin());
    }
    elseif ($texto == '💸 Pagos Pendientes' && $esAdminUser) {
        comandoRevisarPagosPendientes($chatId, $sistemaPagos);
    }
    elseif ($texto == '✅ Aprobar Pagos' && $esAdminUser) {
        comandoRevisarPagosPendientes($chatId, $sistemaPagos);
    }
    elseif (strpos($texto, '/ver_orden') === 0 && $esAdminUser) {
        comandoVerOrden($chatId, $texto, $sistemaPagos);
    }
    elseif (strpos($texto, '/aprobar') === 0 && $esAdminUser) {
        comandoAprobarPagoAdmin($chatId, $texto, $telegramId, $sistemaPagos, $db);
    }
    elseif (strpos($texto, '/rechazar') === 0 && $esAdminUser) {
        comandoRechazarPagoAdmin($chatId, $texto, $sistemaPagos);
    }
    elseif ($texto == '🔙 Volver al Menú' && $esAdminUser) {
        enviarMensaje($chatId, "Volviendo...", 'Markdown', getTecladoPrincipal($esAdminUser));
    }
    elseif ($texto == '📊 Estadísticas' && $esAdminUser) {
        comandoEstadisticasAdmin($chatId, $db);
    }
    elseif ($texto == '👥 Top Usuarios' && $esAdminUser) {
        comandoTopUsuarios($chatId, $db);
    }
    else {
        if (!empty($texto) && $texto[0] != '/') {
            procesarTAC($chatId, $texto, $telegramId, $db, $estados);
        }
    }
}

// ============================================
// MODOS DE EJECUCIÓN
// ============================================

function modoWebhook($db, $estados) {
    $content = @file_get_contents("php://input");
    $update = json_decode($content, true);
    
    if ($update) {
        procesarActualizacion($update, $db, $estados);
    }
}

function modoPolling($db, $estados) {
    $offset = 0;
    
    echo "🤖 Bot iniciado\n";
    echo "Presiona Ctrl+C para detener\n\n";
    
    while (true) {
        $url = API_URL . "getUpdates?offset=$offset&timeout=30";
        $response = @file_get_contents($url);
        $updates = json_decode($response, true);
        
        if (isset($updates['result'])) {
            foreach ($updates['result'] as $update) {
                procesarActualizacion($update, $db, $estados);
                $offset = $update['update_id'] + 1;
            }
        }
        
        usleep(100000);
    }
}

// ============================================
// PUNTO DE ENTRADA
// ============================================

if (php_sapi_name() == 'cli') {
    if (isset($argv[1]) && $argv[1] == 'polling') {
        $db = new Database();
        $estados = new EstadosUsuario();
        modoPolling($db, $estados);
    } else {
        echo "Uso: php bot_imei_corregido.php polling\n";
    }
} else {
    $db = new Database();
    $estados = new EstadosUsuario();
    modoWebhook($db, $estados);
}
?>
