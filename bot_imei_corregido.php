<?php
/**
 * ═══════════════════════════════════════════════════════════════
 * BOT TELEGRAM - GENERADOR DE IMEI CON SISTEMA DE PAGOS COMPLETO
 * ═══════════════════════════════════════════════════════════════
 * 
 * VERSIÓN: 2.0 CORREGIDA - Con Sistema de Pagos Yape/Plin Integrado
 * FECHA: Diciembre 2024
 * 
 * ═══════════════════════════════════════════════════════════════
 */

// ============================================
// CONFIGURACIÓN - ARCHIVOS REQUERIDOS
// ============================================

require_once(__DIR__ . '/config_bot.php');
require_once(__DIR__ . '/config_imeidb.php');
require_once(__DIR__ . '/imeidb_api.php');
require_once(__DIR__ . '/config_pagos.php');
require_once(__DIR__ . '/sistema_pagos.php');
require_once(__DIR__ . '/generador_qr.php');

define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');

// Configuración del sistema de créditos
define('CREDITOS_REGISTRO', 10);
define('COSTO_GENERACION', 1);
define('ADMIN_IDS', [7334970766]);

class Database {
    public $conn;  // Público para acceso desde IMEIDbAPI
    
    public function __construct() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch(PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }
    
    // ═══════════════════════════════════════
    // GESTIÓN DE USUARIOS
    // ═══════════════════════════════════════
    
    public function registrarUsuario($telegramId, $username, $firstName, $lastName) {
        $sql = "INSERT INTO usuarios (telegram_id, username, first_name, last_name, creditos)
                VALUES (:telegram_id, :username, :first_name, :last_name, :creditos)
                ON DUPLICATE KEY UPDATE 
                    username = :username2,
                    first_name = :first_name2,
                    last_name = :last_name2,
                    ultima_actividad = CURRENT_TIMESTAMP";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $creditos = CREDITOS_REGISTRO;
            
            $stmt->execute([
                ':telegram_id' => $telegramId,
                ':username' => $username,
                ':first_name' => $firstName,
                ':last_name' => $lastName,
                ':creditos' => $creditos,
                ':username2' => $username,
                ':first_name2' => $firstName,
                ':last_name2' => $lastName
            ]);
            
            if ($stmt->rowCount() > 0) {
                $this->registrarTransaccion($telegramId, 'registro', $creditos, 'Créditos de bienvenida');
                return true;
            }
            return false;
        } catch(PDOException $e) {
            return false;
        }
    }
    
    public function getUsuario($telegramId) {
        $sql = "SELECT * FROM usuarios WHERE telegram_id = :telegram_id";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':telegram_id' => $telegramId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            return false;
        }
    }
    
    public function actualizarCreditos($telegramId, $cantidad, $operacion = 'add') {
        if ($operacion == 'add') {
            $sql = "UPDATE usuarios SET creditos = creditos + :cantidad WHERE telegram_id = :telegram_id";
        } else {
            $sql = "UPDATE usuarios SET creditos = creditos - :cantidad WHERE telegram_id = :telegram_id AND creditos >= :cantidad";
        }
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':cantidad' => $cantidad,
                ':telegram_id' => $telegramId
            ]);
            return $stmt->rowCount() > 0;
        } catch(PDOException $e) {
            return false;
        }
    }
    
    public function incrementarGeneraciones($telegramId) {
        $sql = "UPDATE usuarios SET total_generaciones = total_generaciones + 1 WHERE telegram_id = :telegram_id";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':telegram_id' => $telegramId]);
            return true;
        } catch(PDOException $e) {
            return false;
        }
    }
    
    public function bloquearUsuario($telegramId, $bloquear = true) {
        $sql = "UPDATE usuarios SET bloqueado = :bloqueado WHERE telegram_id = :telegram_id";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':bloqueado' => $bloquear ? 1 : 0,
                ':telegram_id' => $telegramId
            ]);
            return $stmt->rowCount() > 0;
        } catch(PDOException $e) {
            return false;
        }
    }
    
    public function setPremium($telegramId, $premium = true) {
        $sql = "UPDATE usuarios SET es_premium = :premium WHERE telegram_id = :telegram_id";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':premium' => $premium ? 1 : 0,
                ':telegram_id' => $telegramId
            ]);
            return $stmt->rowCount() > 0;
        } catch(PDOException $e) {
            return false;
        }
    }
    
    // ═══════════════════════════════════════
    // TRANSACCIONES Y HISTORIAL
    // ═══════════════════════════════════════
    
    public function registrarTransaccion($telegramId, $tipo, $cantidad, $descripcion, $adminId = null) {
        $sql = "INSERT INTO transacciones (telegram_id, tipo, cantidad, descripcion, admin_id)
                VALUES (:telegram_id, :tipo, :cantidad, :descripcion, :admin_id)";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':telegram_id' => $telegramId,
                ':tipo' => $tipo,
                ':cantidad' => $cantidad,
                ':descripcion' => $descripcion,
                ':admin_id' => $adminId
            ]);
            return true;
        } catch(PDOException $e) {
            return false;
        }
    }
    
    public function registrarUso($telegramId, $tac, $modelo) {
        $sql = "INSERT INTO historial_uso (telegram_id, tac, modelo, creditos_usados)
                VALUES (:telegram_id, :tac, :modelo, :creditos_usados)";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':telegram_id' => $telegramId,
                ':tac' => $tac,
                ':modelo' => $modelo,
                ':creditos_usados' => COSTO_GENERACION
            ]);
            return true;
        } catch(PDOException $e) {
            return false;
        }
    }
    
    public function getHistorialUsuario($telegramId, $limite = 10) {
        $sql = "SELECT * FROM historial_uso 
                WHERE telegram_id = :telegram_id 
                ORDER BY fecha DESC 
                LIMIT :limite";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':telegram_id', $telegramId, PDO::PARAM_INT);
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            return [];
        }
    }
    
    // ═══════════════════════════════════════
    // TAC Y MODELOS
    // ═══════════════════════════════════════
    
    public function guardarModelo($tac, $modelo, $marca = '', $fuente = 'usuario') {
        $sql = "INSERT INTO tac_modelos (tac, modelo, marca, fuente, veces_usado) 
                VALUES (:tac, :modelo, :marca, :fuente, 1)
                ON DUPLICATE KEY UPDATE 
                    modelo = :modelo2,
                    marca = :marca2,
                    veces_usado = veces_usado + 1,
                    ultima_consulta = CURRENT_TIMESTAMP";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':tac' => $tac,
                ':modelo' => $modelo,
                ':marca' => $marca,
                ':fuente' => $fuente,
                ':modelo2' => $modelo,
                ':marca2' => $marca
            ]);
            return true;
        } catch(PDOException $e) {
            return false;
        }
    }
    
    public function buscarModelo($tac) {
        $sql = "SELECT * FROM tac_modelos WHERE tac = :tac";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':tac' => $tac]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            return false;
        }
    }
    
    public function eliminarModelo($tac) {
        $sql = "DELETE FROM tac_modelos WHERE tac = :tac";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $resultado = $stmt->execute([':tac' => $tac]);
            return $resultado && $stmt->rowCount() > 0;
        } catch(PDOException $e) {
            return false;
        }
    }
    
    // ═══════════════════════════════════════
    // ESTADÍSTICAS
    // ═══════════════════════════════════════
    
    public function getEstadisticasGenerales() {
        $stats = [];
        
        try {
            $stmt = $this->conn->query("SELECT COUNT(*) as total FROM usuarios");
            $stats['total_usuarios'] = $stmt->fetch()['total'];
            
            $stmt = $this->conn->query("SELECT SUM(creditos) as total FROM usuarios");
            $stats['total_creditos'] = $stmt->fetch()['total'];
            
            $stmt = $this->conn->query("SELECT SUM(total_generaciones) as total FROM usuarios");
            $stats['total_generaciones'] = $stmt->fetch()['total'];
            
            $stmt = $this->conn->query("SELECT COUNT(*) as total FROM usuarios WHERE DATE(ultima_actividad) = CURDATE()");
            $stats['usuarios_hoy'] = $stmt->fetch()['total'];
            
            $stmt = $this->conn->query("SELECT COUNT(*) as total FROM ordenes_pago WHERE estado = 'revision'");
            $stats['pagos_pendientes'] = $stmt->fetch()['total'];
            
            $stmt = $this->conn->query("SELECT COUNT(*) as total FROM usuarios WHERE es_premium = 1");
            $stats['usuarios_premium'] = $stmt->fetch()['total'];
            
            return $stats;
        } catch(PDOException $e) {
            return [];
        }
    }
    
    public function getTopUsuarios($limite = 10) {
        $sql = "SELECT telegram_id, username, first_name, creditos, total_generaciones 
                FROM usuarios 
                ORDER BY total_generaciones DESC 
                LIMIT :limite";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            return [];
        }
    }
}

// ============================================
// GESTIÓN DE ESTADOS
// ============================================

class EstadosUsuario {
    private $cacheFile = '/tmp/bot_estados.json';
    
    public function setEstado($chatId, $estado, $datos = []) {
        $this->establecerEstado($chatId, $estado, $datos);
    }
    
    public function establecerEstado($chatId, $estado, $datos = []) {
        $estados = $this->cargarEstados();
        $estados[$chatId] = [
            'estado' => $estado,
            'datos' => $datos,
            'timestamp' => time()
        ];
        $this->guardarEstados($estados);
    }
    
    public function getEstado($chatId) {
        return $this->obtenerEstado($chatId);
    }
    
    public function obtenerEstado($chatId) {
        $estados = $this->cargarEstados();
        
        if (isset($estados[$chatId])) {
            if (time() - $estados[$chatId]['timestamp'] > 600) {
                unset($estados[$chatId]);
                $this->guardarEstados($estados);
                return false;
            }
            return $estados[$chatId];
        }
        return false;
    }
    
    public function limpiarEstado($chatId) {
        $estados = $this->cargarEstados();
        unset($estados[$chatId]);
        $this->guardarEstados($estados);
    }
    
    private function cargarEstados() {
        if (file_exists($this->cacheFile)) {
            $contenido = file_get_contents($this->cacheFile);
            return json_decode($contenido, true) ?: [];
        }
        return [];
    }
    
    private function guardarEstados($estados) {
        file_put_contents($this->cacheFile, json_encode($estados));
    }
}

// ============================================
// FUNCIONES IMEI
// ============================================

function validarIMEI($imei) {
    $imei = preg_replace('/[^0-9]/', '', $imei);
    
    if (strlen($imei) != 15 || !ctype_digit($imei)) {
        return false;
    }
    
    if (preg_match('/^(.)\1{14}$/', $imei)) {
        return false;
    }
    
    $suma = 0;
    
    for ($i = 0; $i < 14; $i++) {
        $digito = intval($imei[$i]);
        
        if ($i % 2 === 1) {
            $digito *= 2;
            if ($digito > 9) {
                $digito -= 9;
            }
        }
        
        $suma += $digito;
    }
    
    $checkCalculado = (10 - ($suma % 10)) % 10;
    $checkReal = intval($imei[14]);
    
    return $checkCalculado === $checkReal;
}

function generarSerial() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

function calcularDigitoVerificador($imei14) {
    $suma = 0;
    
    for ($i = 0; $i < 14; $i++) {
        $digito = intval($imei14[$i]);
        
        if ($i % 2 === 1) {
            $digito *= 2;
            if ($digito > 9) {
                $digito -= 9;
            }
        }
        
        $suma += $digito;
    }
    
    return (10 - ($suma % 10)) % 10;
}

function validarTAC($tac) {
    $tac = preg_replace('/[^0-9]/', '', $tac);
    
    if (strlen($tac) != 8 || !ctype_digit($tac)) {
        return false;
    }
    
    if (preg_match('/^(.)\1{7}$/', $tac)) {
        return false;
    }
    
    return true;
}

function generarIMEI($tac) {
    $serial = generarSerial();
    $imei14 = $tac . $serial;
    $digitoVerificador = calcularDigitoVerificador($imei14);
    $imeiCompleto = $imei14 . $digitoVerificador;
    
    return [
        'imei_completo' => $imeiCompleto,
        'tac' => $tac,
        'serial' => $serial,
        'digito_verificador' => $digitoVerificador
    ];
}

function generarMultiplesIMEIs($tac, $cantidad = 2) {
    $imeis = [];
    for ($i = 0; $i < $cantidad; $i++) {
        $imeis[] = generarIMEI($tac);
    }
    return $imeis;
}

function extraerTAC($imei) {
    $imei = preg_replace('/[^0-9]/', '', $imei);
    if (strlen($imei) >= 8) {
        return substr($imei, 0, 8);
    }
    return false;
}

// ============================================
// FUNCIONES TELEGRAM
// ============================================

function enviarMensaje($chatId, $texto, $parseMode = 'Markdown', $replyMarkup = null) {
    $url = API_URL . 'sendMessage';
    $data = [
        'chat_id' => $chatId,
        'text' => $texto,
        'parse_mode' => $parseMode
    ];
    
    if ($replyMarkup) {
        $data['reply_markup'] = $replyMarkup;
    }
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => json_encode($data)
        ]
    ];
    
    $context = stream_context_create($options);
    return @file_get_contents($url, false, $context);
}

function enviarFoto($chatId, $photo, $caption = '', $parseMode = 'Markdown', $replyMarkup = null) {
    $url = API_URL . 'sendPhoto';
    
    $data = [
        'chat_id' => $chatId,
        'photo' => $photo,
        'caption' => $caption,
        'parse_mode' => $parseMode
    ];
    
    if ($replyMarkup) {
        $data['reply_markup'] = $replyMarkup;
    }
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => json_encode($data)
        ]
    ];
    
    $context = stream_context_create($options);
    return @file_get_contents($url, false, $context);
}

function crearTeclado($botones) {
    return json_encode([
        'keyboard' => $botones,
        'resize_keyboard' => true,
        'one_time_keyboard' => false
    ]);
}

function getTecladoPrincipal($esAdmin = false) {
    $botones = [
        [
            ['text' => '📱 Generar IMEI'],
            ['text' => '💳 Mis Créditos']
        ],
        [
            ['text' => '💰 Comprar Créditos'],
            ['text' => '📋 Mis Órdenes']
        ],
        [
            ['text' => '📊 Mi Perfil'],
            ['text' => '📜 Historial']
        ],
        [
            ['text' => '❓ Ayuda']
        ]
    ];
    
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

function getTecladoAdmin() {
    $teclado = [
        'keyboard' => [
            [
                ['text' => '📊 Estadísticas'],
                ['text' => '👥 Top Usuarios']
            ],
            [
                ['text' => '💸 Pagos Pendientes'],
                ['text' => '✅ Aprobar Pagos']
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
                ['text' => '🔙 Volver al Menú']
            ]
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => false
    ];
    
    return json_encode($teclado);
}

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

function esAdmin($telegramId) {
    return in_array($telegramId, ADMIN_IDS);
}

// ============================================
// COMANDOS DEL BOT
// ============================================

function comandoStart($chatId, $message, $db) {
    $telegramId = $message['from']['id'];
    $username = $message['from']['username'] ?? '';
    $firstName = $message['from']['first_name'] ?? '';
    $lastName = $message['from']['last_name'] ?? '';
    
    $esNuevo = $db->registrarUsuario($telegramId, $username, $firstName, $lastName);
    $usuario = $db->getUsuario($telegramId);
    $esAdminUser = esAdmin($telegramId);
    
    if ($esNuevo) {
        $respuesta = "╔═══════════════════════════╗\n";
        $respuesta .= "║   🎉 ¡BIENVENIDO! 🎉      ║\n";
        $respuesta .= "╚═══════════════════════════╝\n\n";
        $respuesta .= "👋 Hola *{$firstName}*\n\n";
        $respuesta .= "💎 Has recibido *" . CREDITOS_REGISTRO . " créditos* de regalo\n";
        $respuesta .= "🚀 ¡Ya puedes empezar a generar IMEIs!\n\n";
        $respuesta .= "📱 Presiona *📱 Generar IMEI* para comenzar";
    } else {
        $statusEmoji = $usuario['es_premium'] ? '⭐' : '👤';
        
        $respuesta = "╔═══════════════════════════╗\n";
        $respuesta .= "║  {$statusEmoji} BIENVENIDO DE VUELTA {$statusEmoji}  ║\n";
        $respuesta .= "╚═══════════════════════════╝\n\n";
        $respuesta .= "👋 Hola *{$firstName}*\n\n";
        $respuesta .= "💰 Créditos: *{$usuario['creditos']}*\n";
        $respuesta .= "📊 Generaciones: *{$usuario['total_generaciones']}*\n";
        
        if ($usuario['es_premium']) {
            $respuesta .= "⭐ Estado: *Premium*\n";
        }
        
        $respuesta .= "\n🎯 Selecciona una opción del menú";
    }
    
    enviarMensaje($chatId, $respuesta, 'Markdown', getTecladoPrincipal($esAdminUser));
}

function comandoMisCreditos($chatId, $telegramId, $db) {
    $usuario = $db->getUsuario($telegramId);
    
    if (!$usuario) {
        enviarMensaje($chatId, "❌ Usuario no encontrado. Usa /start");
        return;
    }
    
    $creditos = $usuario['creditos'];
    $iconoCreditos = $creditos > 50 ? '💎' : ($creditos > 20 ? '💰' : ($creditos > 5 ? '🪙' : '⚠️'));
    
    $respuesta = "╔═══════════════════════════╗\n";
    $respuesta .= "║    {$iconoCreditos} TUS CRÉDITOS {$iconoCreditos}     ║\n";
    $respuesta .= "╚═══════════════════════════╝\n\n";
    $respuesta .= "💰 *{$creditos}* créditos\n\n";
    $respuesta .= "🔢 Generaciones restantes: *{$creditos}*\n";
    $respuesta .= "📱 Total generados: *{$usuario['total_generaciones']}*\n";
    
    if ($creditos < 5) {
        $respuesta .= "\n⚠️ *¡SALDO BAJO!*\n";
        $respuesta .= "🛒 → *💰 Comprar Créditos*";
    }
    
    enviarMensaje($chatId, $respuesta);
}

function comandoPerfil($chatId, $telegramId, $db) {
    $usuario = $db->getUsuario($telegramId);
    
    if (!$usuario) {
        enviarMensaje($chatId, "❌ Usuario no encontrado. Usa /start");
        return;
    }
    
    $statusEmoji = $usuario['es_premium'] ? '⭐' : '👤';
    $statusTexto = $usuario['es_premium'] ? 'Premium' : 'Estándar';
    
    $respuesta = "╔═══════════════════════════╗\n";
    $respuesta .= "║   {$statusEmoji} TU PERFIL {$statusEmoji}        ║\n";
    $respuesta .= "╚═══════════════════════════╝\n\n";
    $respuesta .= "👤 *INFORMACIÓN*\n";
    $respuesta .= "━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    $respuesta .= "🆔 ID: `{$usuario['telegram_id']}`\n";
    $respuesta .= "👨 Nombre: {$usuario['first_name']}\n";
    $respuesta .= "💰 Créditos: *{$usuario['creditos']}*\n";
    $respuesta .= "📊 Generaciones: *{$usuario['total_generaciones']}*\n";
    $respuesta .= "{$statusEmoji} Tipo: *{$statusTexto}*\n";
    
    enviarMensaje($chatId, $respuesta);
}

function comandoHistorial($chatId, $telegramId, $db) {
    $historial = $db->getHistorialUsuario($telegramId, 10);
    
    if (empty($historial)) {
        $respuesta = "📭 *Sin historial aún*\n\n";
        $respuesta .= "💡 Genera tu primer IMEI\n";
        $respuesta .= "🎯 → *📱 Generar IMEI*";
        
        enviarMensaje($chatId, $respuesta);
        return;
    }
    
    $respuesta = "╔═══════════════════════════╗\n";
    $respuesta .= "║  📜 TU HISTORIAL 📜       ║\n";
    $respuesta .= "╚═══════════════════════════╝\n\n";
    
    foreach ($historial as $i => $uso) {
        $num = $i + 1;
        $fecha = date('d/m H:i', strtotime($uso['fecha']));
        $modelo = $uso['modelo'] ?: 'Desconocido';
        
        $respuesta .= "🔹 *#{$num} - {$modelo}*\n";
        $respuesta .= "📡 TAC: `{$uso['tac']}`\n";
        $respuesta .= "🕐 {$fecha}\n\n";
    }
    
    enviarMensaje($chatId, $respuesta);
}

function comandoAyuda($chatId) {
    $respuesta = "╔═══════════════════════════╗\n";
    $respuesta .= "║      ❓ AYUDA ❓          ║\n";
    $respuesta .= "╚═══════════════════════════╝\n\n";
    $respuesta .= "🎯 *¿CÓMO USAR EL BOT?*\n\n";
    $respuesta .= "1️⃣ Presiona *📱 Generar IMEI*\n";
    $respuesta .= "2️⃣ Envía un TAC de 8 dígitos\n";
    $respuesta .= "3️⃣ Ejemplo: `35203310`\n\n";
    $respuesta .= "💰 Costo: *" . COSTO_GENERACION . " crédito*\n";
    $respuesta .= "🎁 Registro: *" . CREDITOS_REGISTRO . " créditos* gratis\n\n";
    $respuesta .= "💬 Soporte: @CHAMOGSM";
    
    enviarMensaje($chatId, $respuesta);
}

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
    $content = file_get_contents("php://input");
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
