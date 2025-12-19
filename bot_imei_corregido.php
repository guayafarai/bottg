<?php
/**
 * ═══════════════════════════════════════════════════════════════
 * BOT TELEGRAM - GENERADOR DE IMEI CON SISTEMA DE PAGOS COMPLETO
 * ═══════════════════════════════════════════════════════════════
 * 
 * VERSIÓN: 2.0 COMPLETA Y FUNCIONAL
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
    public $conn;
    
    public function __construct() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch(PDOException $e) {
            error_log("Error de conexión BD: " . $e->getMessage());
            die("Error de conexión a la base de datos");
        }
    }
    
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
            error_log("Error al registrar usuario: " . $e->getMessage());
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
            error_log("Error al obtener usuario: " . $e->getMessage());
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
            error_log("Error al actualizar créditos: " . $e->getMessage());
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
            error_log("Error al incrementar generaciones: " . $e->getMessage());
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
            error_log("Error al bloquear usuario: " . $e->getMessage());
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
            error_log("Error al cambiar premium: " . $e->getMessage());
            return false;
        }
    }
    
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
            error_log("Error al registrar transacción: " . $e->getMessage());
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
            error_log("Error al registrar uso: " . $e->getMessage());
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
            error_log("Error al obtener historial: " . $e->getMessage());
            return [];
        }
    }
    
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
            error_log("Error al guardar modelo: " . $e->getMessage());
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
            error_log("Error al buscar modelo: " . $e->getMessage());
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
            error_log("Error al eliminar modelo: " . $e->getMessage());
            return false;
        }
    }
    
    public function getEstadisticasGenerales() {
        $stats = [];
        
        try {
            $stmt = $this->conn->query("SELECT COUNT(*) as total FROM usuarios");
            $stats['total_usuarios'] = $stmt->fetch()['total'];
            
            $stmt = $this->conn->query("SELECT SUM(creditos) as total FROM usuarios");
            $stats['total_creditos'] = $stmt->fetch()['total'] ?? 0;
            
            $stmt = $this->conn->query("SELECT SUM(total_generaciones) as total FROM usuarios");
            $stats['total_generaciones'] = $stmt->fetch()['total'] ?? 0;
            
            $stmt = $this->conn->query("SELECT COUNT(*) as total FROM usuarios WHERE DATE(ultima_actividad) = CURDATE()");
            $stats['usuarios_hoy'] = $stmt->fetch()['total'];
            
            $stmt = $this->conn->query("SELECT COUNT(*) as total FROM ordenes_pago WHERE estado = 'revision'");
            $stats['pagos_pendientes'] = $stmt->fetch()['total'];
            
            $stmt = $this->conn->query("SELECT COUNT(*) as total FROM usuarios WHERE es_premium = 1");
            $stats['usuarios_premium'] = $stmt->fetch()['total'];
            
            return $stats;
        } catch(PDOException $e) {
            error_log("Error al obtener estadísticas: " . $e->getMessage());
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
            error_log("Error al obtener top usuarios: " . $e->getMessage());
            return [];
        }
    }
}

// ============================================
// GESTIÓN DE ESTADOS
// ============================================

class EstadosUsuario {
    private $cacheFile;
    
    public function __construct() {
        $this->cacheFile = sys_get_temp_dir() . '/bot_estados_' . md5(BOT_TOKEN) . '.json';
    }
    
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
            $contenido = @file_get_contents($this->cacheFile);
            if ($contenido) {
                $estados = json_decode($contenido, true);
                return is_array($estados) ? $estados : [];
            }
        }
        return [];
    }
    
    private function guardarEstados($estados) {
        @file_put_contents($this->cacheFile, json_encode($estados));
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
            'content' => json_encode($data),
            'timeout' => 10
        ]
    ];
    
    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    
    if ($result === false) {
        error_log("Error al enviar mensaje a chat $chatId");
    }
    
    return $result;
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
            'content' => json_encode($data),
            'timeout' => 10
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

// ============================================
// COMANDOS DE COMPRA Y PAGOS
// ============================================

function comandoComprarCreditos($chatId, $telegramId, $sistemaPagos) {
    $respuesta = "╔═══════════════════════════╗\n";
    $respuesta .= "║  🛒 TIENDA DE CRÉDITOS   ║\n";
    $respuesta .= "╚═══════════════════════════╝\n\n";
    
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
    $respuesta .= "Usa los botones de abajo\n";
    $respuesta .= "para seleccionar tu paquete 👇";
    
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
    
    if ($paquete['ahorro'] > 0) {
        $respuesta .= "🎁 ¡Ahorras {$paquete['ahorro']}%!\n\n";
    }
    
    $respuesta .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $respuesta .= "💳 *Selecciona método de pago:*\n";
    $respuesta .= "━━━━━━━━━━━━━━━━━━━━━━━━";
    
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
        enviarMensaje($chatId, "❌ Por favor envía una *foto* del comprobante de pago.\n\nAsegúrate de que se vea clara.");
        return true;
    }
    
    $photos = $message['photo'];
    $photo = end($photos);
    $fileId = $photo['file_id'];
    
    if ($sistemaPagos->adjuntarComprobante($ordenId, $fileId, 'photo')) {
        $estados->limpiarEstado($chatId);
        
        $respuesta = "✅ *¡Comprobante recibido!*\n\n";
        $respuesta .= "🔖 Código de orden: `{$codigoOrden}`\n\n";
        $respuesta .= "Tu pago está siendo revisado por nuestro equipo.\n";
        $respuesta .= "Te notificaremos en breve. ⏱️\n\n";
        $respuesta .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $respuesta .= "⏰ *Tiempo de verificación:*\n";
        $respuesta .= "Generalmente de 5 a 30 minutos\n\n";
        $respuesta .= "¡Gracias por tu compra! 🙏";
        
        enviarMensaje($chatId, $respuesta, 'Markdown');
        
        if (PAGO_NOTIFICAR_ADMIN) {
            notificarComprobanteRecibido($ordenId, $telegramId);
        }
        
        return true;
    } else {
        enviarMensaje($chatId, "❌ Error al procesar el comprobante. Intenta nuevamente.");
        return true;
    }
}

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
// FUNCIONES AUXILIARES DE NOTIFICACIÓN
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

// ============================================
// COMANDOS ADMIN
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
    
    if (!empty($orden['comprobante_file_id'])) {
        enviarMensaje($chatId, "📎 *Comprobante adjunto:*");
        enviarFoto($chatId, $orden['comprobante_file_id'], "Comprobante de la orden #{$ordenId}");
    }
}

function comandoAprobarPago($chatId, $texto, $adminId, $sistemaPagos, $db) {
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

function comandoRechazarPago($chatId, $texto, $sistemaPagos) {
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

function comandoEstadisticas($chatId, $db) {
    $stats = $db->getEstadisticasGenerales();
    
    $respuesta = "╔═══════════════════════════╗\n";
    $respuesta .= "║   📊 ESTADÍSTICAS 📊     ║\n";
    $respuesta .= "╚═══════════════════════════╝\n\n";
    $respuesta .= "👥 Usuarios: *{$stats['total_usuarios']}*\n";
    $respuesta .= "💰 Créditos totales: *{$stats['total_creditos']}*\n";
    $respuesta .= "📱 Generaciones: *{$stats['total_generaciones']}*\n";
    $respuesta .= "🌟 Activos hoy: *{$stats['usuarios_hoy']}*\n";
    $respuesta .= "💸 Pagos pendientes: *{$stats['pagos_pendientes']}*\n";
    $respuesta .= "⭐ Premium: *{$stats['usuarios_premium']}*";
    
    enviarMensaje($chatId, $respuesta);
}

function comandoTopUsuarios($chatId, $db) {
    $top = $db->getTopUsuarios(10);
    
    if (empty($top)) {
        enviarMensaje($chatId, "No hay usuarios registrados.");
        return;
    }
    
    $respuesta = "╔═══════════════════════════╗\n";
    $respuesta .= "║   👥 TOP 10 USUARIOS     ║\n";
    $respuesta .= "╚═══════════════════════════╝\n\n";
    
    foreach ($top as $i => $usuario) {
        $pos = $i + 1;
        $emoji = $pos == 1 ? "🥇" : ($pos == 2 ? "🥈" : ($pos == 3 ? "🥉" : "{$pos}."));
        $username = $usuario['username'] ? "@{$usuario['username']}" : $usuario['first_name'];
        
        $respuesta .= "{$emoji} {$username}\n";
        $respuesta .= "   📊 {$usuario['total_generaciones']} generaciones\n";
        $respuesta .= "   💰 {$usuario['creditos']} créditos\n\n";
    }
    
    enviarMensaje($chatId, $respuesta);
}

// ============================================
// PROCESAMIENTO DE TAC
// ============================================

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

// ============================================
// PROCESAMIENTO DE ACTUALIZACIONES
// ============================================

function procesarActualizacion($update, $db, $estados, $sistemaPagos) {
    if (!isset($update['message'])) {
        return;
    }
    
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $telegramId = $message['from']['id'];
    $texto = isset($message['text']) ? trim($message['text']) : '';
    
    $esAdminUser = esAdmin($telegramId);
    
    // Verificar si es una foto (comprobante)
    if (isset($message['photo'])) {
        if (comandoRecibirComprobante($chatId, $telegramId, $message, $sistemaPagos, $estados, $db)) {
            return;
        }
    }
    
    // Comandos principales
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
        comandoComprarCreditos($chatId, $telegramId, $sistemaPagos);
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
    elseif ($texto == '🔙 Volver') {
        $estados->limpiarEstado($chatId);
        enviarMensaje($chatId, "Volviendo al menú principal...", 'Markdown', getTecladoPrincipal($esAdminUser));
    }
    // Comandos Admin
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
        comandoAprobarPago($chatId, $texto, $telegramId, $sistemaPagos, $db);
    }
    elseif (strpos($texto, '/rechazar') === 0 && $esAdminUser) {
        comandoRechazarPago($chatId, $texto, $sistemaPagos);
    }
    elseif ($texto == '🔙 Volver al Menú' && $esAdminUser) {
        enviarMensaje($chatId, "Volviendo...", 'Markdown', getTecladoPrincipal($esAdminUser));
    }
    elseif ($texto == '📊 Estadísticas' && $esAdminUser) {
        comandoEstadisticas($chatId, $db);
    }
    elseif ($texto == '👥 Top Usuarios' && $esAdminUser) {
        comandoTopUsuarios($chatId, $db);
    }
    // Procesamiento de TAC
    else {
        if (!empty($texto) && $texto[0] != '/') {
            procesarTAC($chatId, $texto, $telegramId, $db, $estados);
        }
    }
}

// ============================================
// MODOS DE EJECUCIÓN
// ============================================

function modoWebhook($db, $estados, $sistemaPagos) {
    $content = @file_get_contents("php://input");
    $update = json_decode($content, true);
    
    if ($update) {
        procesarActualizacion($update, $db, $estados, $sistemaPagos);
    }
}

function modoPolling($db, $estados, $sistemaPagos) {
    $offset = 0;
    
    echo "🤖 Bot iniciado en modo polling\n";
    echo "Presiona Ctrl+C para detener\n\n";
    
    while (true) {
        $url = API_URL . "getUpdates?offset=$offset&timeout=30";
        $response = @file_get_contents($url);
        $updates = json_decode($response, true);
        
        if (isset($updates['result'])) {
            foreach ($updates['result'] as $update) {
                procesarActualizacion($update, $db, $estados, $sistemaPagos);
                $offset = $update['update_id'] + 1;
            }
        }
        
        usleep(100000);
    }
}

// ============================================
// PUNTO DE ENTRADA
// ============================================

// Inicializar instancias
$db = new Database();
$estados = new EstadosUsuario();
$sistemaPagos = new SistemaPagos($db);

if (php_sapi_name() == 'cli') {
    // Modo consola (polling)
    if (isset($argv[1]) && $argv[1] == 'polling') {
        modoPolling($db, $estados, $sistemaPagos);
    } else {
        echo "Uso: php bot_imei_corregido.php polling\n";
    }
} else {
    // Modo webhook
    modoWebhook($db, $estados, $sistemaPagos);
}
?>