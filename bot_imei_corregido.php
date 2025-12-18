<?php
/**
 * ═══════════════════════════════════════════════════════════════
 * BOT TELEGRAM - GENERADOR DE IMEI CON SISTEMA DE PAGOS COMPLETO
 * ═══════════════════════════════════════════════════════════════
 * 
 * VERSIÓN: 2.0 CORREGIDA Y OPTIMIZADA
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
        // Usar directorio temporal del sistema
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

// Continúa en el siguiente archivo debido al límite de caracteres...
