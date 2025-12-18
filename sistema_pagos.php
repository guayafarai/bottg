<?php
/**
 * ═══════════════════════════════════════════════════════════════
 * SISTEMA DE PAGOS - CLASE PRINCIPAL (CORREGIDO)
 * ═══════════════════════════════════════════════════════════════
 */

// Cargar configuración de pagos
if (file_exists(__DIR__ . '/config_pagos.php')) {
    require_once(__DIR__ . '/config_pagos.php');
} else {
    // Configuración por defecto si no existe el archivo
    if (!defined('PAGO_YAPE_NUMERO')) define('PAGO_YAPE_NUMERO', '924780239');
    if (!defined('PAGO_YAPE_NOMBRE')) define('PAGO_YAPE_NOMBRE', 'Victor Aguilar');
    if (!defined('PAGO_PLIN_NUMERO')) define('PAGO_PLIN_NUMERO', '924780239');
    if (!defined('PAGO_PLIN_NOMBRE')) define('PAGO_PLIN_NOMBRE', 'Victor Aguilar');
    if (!defined('PAGO_TIEMPO_EXPIRACION')) define('PAGO_TIEMPO_EXPIRACION', 86400);
    if (!defined('PAGO_REQUIERE_COMPROBANTE')) define('PAGO_REQUIERE_COMPROBANTE', true);
    if (!defined('PAGO_NOTIFICAR_ADMIN')) define('PAGO_NOTIFICAR_ADMIN', true);
    if (!defined('PAGO_CANAL_NOTIFICACIONES')) define('PAGO_CANAL_NOTIFICACIONES', null);
}

class SistemaPagos {
    private $db;
    private $paquetes;
    private $metodos;
    
    public function __construct($database) {
        $this->db = $database;
        $this->inicializarPaquetes();
        $this->inicializarMetodos();
    }
    
    private function inicializarPaquetes() {
        // Paquetes predefinidos
        $this->paquetes = [
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
    }
    
    private function inicializarMetodos() {
        // Métodos de pago disponibles
        $this->metodos = [
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
                'banco' => 'BCP',
                'cuenta' => '123-456789-0-12',
                'cci' => '00212300045678901234',
                'titular' => 'F4 Mobile'
            ]
        ];
    }
    
    public function obtenerPaquetes() {
        return $this->paquetes;
    }
    
    public function obtenerPaquete($id) {
        return isset($this->paquetes[$id]) ? $this->paquetes[$id] : false;
    }
    
    public function obtenerMetodosPago() {
        return array_filter($this->metodos, function($metodo) {
            return $metodo['activo'];
        });
    }
    
    public function obtenerMetodoPago($id) {
        return isset($this->metodos[$id]) ? $this->metodos[$id] : false;
    }
    
    public function crearOrdenPago($telegramId, $paqueteId, $metodoPago) {
        $paquete = $this->obtenerPaquete($paqueteId);
        $metodo = $this->obtenerMetodoPago($metodoPago);
        
        if (!$paquete || !$metodo) {
            return false;
        }
        
        $codigoOrden = $this->generarCodigoOrden();
        
        $sql = "INSERT INTO ordenes_pago 
                (telegram_id, codigo_orden, paquete_id, creditos, monto, moneda, 
                 metodo_pago, estado, fecha_expiracion)
                VALUES 
                (:telegram_id, :codigo_orden, :paquete_id, :creditos, :monto, :moneda,
                 :metodo_pago, 'pendiente', DATE_ADD(NOW(), INTERVAL :expiracion SECOND))";
        
        try {
            $stmt = $this->db->conn->prepare($sql);
            $stmt->execute([
                ':telegram_id' => $telegramId,
                ':codigo_orden' => $codigoOrden,
                ':paquete_id' => $paqueteId,
                ':creditos' => $paquete['creditos'],
                ':monto' => $paquete['precio'],
                ':moneda' => $paquete['moneda'],
                ':metodo_pago' => $metodoPago,
                ':expiracion' => PAGO_TIEMPO_EXPIRACION
            ]);
            
            return [
                'orden_id' => $this->db->conn->lastInsertId(),
                'codigo_orden' => $codigoOrden,
                'paquete' => $paquete,
                'metodo' => $metodo
            ];
            
        } catch(PDOException $e) {
            error_log("Error al crear orden: " . $e->getMessage());
            return false;
        }
    }
    
    private function generarCodigoOrden() {
        return 'ORD-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 10));
    }
    
    public function obtenerOrdenPorCodigo($codigoOrden) {
        $sql = "SELECT * FROM ordenes_pago WHERE codigo_orden = :codigo_orden";
        
        try {
            $stmt = $this->db->conn->prepare($sql);
            $stmt->execute([':codigo_orden' => $codigoOrden]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error al obtener orden por código: " . $e->getMessage());
            return false;
        }
    }
    
    public function obtenerOrden($ordenId) {
        $sql = "SELECT * FROM ordenes_pago WHERE id = :orden_id";
        
        try {
            $stmt = $this->db->conn->prepare($sql);
            $stmt->execute([':orden_id' => $ordenId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error al obtener orden: " . $e->getMessage());
            return false;
        }
    }
    
    public function adjuntarComprobante($ordenId, $fileId, $tipoArchivo) {
        $sql = "UPDATE ordenes_pago 
                SET comprobante_file_id = :file_id,
                    comprobante_tipo = :tipo,
                    estado = 'revision'
                WHERE id = :orden_id AND estado = 'pendiente'";
        
        try {
            $stmt = $this->db->conn->prepare($sql);
            $stmt->execute([
                ':file_id' => $fileId,
                ':tipo' => $tipoArchivo,
                ':orden_id' => $ordenId
            ]);
            
            return $stmt->rowCount() > 0;
        } catch(PDOException $e) {
            error_log("Error al adjuntar comprobante: " . $e->getMessage());
            return false;
        }
    }
    
    public function aprobarOrden($ordenId, $adminId = null) {
        $orden = $this->obtenerOrden($ordenId);
        
        if (!$orden || $orden['estado'] == 'aprobada') {
            return false;
        }
        
        try {
            $this->db->conn->beginTransaction();
            
            $sql = "UPDATE ordenes_pago 
                    SET estado = 'aprobada',
                        admin_id = :admin_id,
                        fecha_aprobacion = NOW()
                    WHERE id = :orden_id";
            
            $stmt = $this->db->conn->prepare($sql);
            $stmt->execute([
                ':admin_id' => $adminId,
                ':orden_id' => $ordenId
            ]);
            
            $this->db->actualizarCreditos($orden['telegram_id'], $orden['creditos'], 'add');
            
            $this->db->registrarTransaccion(
                $orden['telegram_id'],
                'compra',
                $orden['creditos'],
                "Compra de {$orden['creditos']} créditos por {$orden['moneda']} {$orden['monto']}",
                $adminId
            );
            
            $this->db->conn->commit();
            
            return true;
            
        } catch(PDOException $e) {
            $this->db->conn->rollBack();
            error_log("Error al aprobar orden: " . $e->getMessage());
            return false;
        }
    }
    
    public function rechazarOrden($ordenId, $motivo = null, $adminId = null) {
        $sql = "UPDATE ordenes_pago 
                SET estado = 'rechazada',
                    admin_id = :admin_id,
                    motivo_rechazo = :motivo,
                    fecha_aprobacion = NOW()
                WHERE id = :orden_id AND estado != 'aprobada'";
        
        try {
            $stmt = $this->db->conn->prepare($sql);
            $stmt->execute([
                ':admin_id' => $adminId,
                ':motivo' => $motivo,
                ':orden_id' => $ordenId
            ]);
            
            return $stmt->rowCount() > 0;
        } catch(PDOException $e) {
            error_log("Error al rechazar orden: " . $e->getMessage());
            return false;
        }
    }
    
    public function cancelarOrden($ordenId, $telegramId) {
        $sql = "UPDATE ordenes_pago 
                SET estado = 'cancelada'
                WHERE id = :orden_id 
                AND telegram_id = :telegram_id 
                AND estado = 'pendiente'";
        
        try {
            $stmt = $this->db->conn->prepare($sql);
            $stmt->execute([
                ':orden_id' => $ordenId,
                ':telegram_id' => $telegramId
            ]);
            
            return $stmt->rowCount() > 0;
        } catch(PDOException $e) {
            error_log("Error al cancelar orden: " . $e->getMessage());
            return false;
        }
    }
    
    public function obtenerOrdenesPendientes($limite = 50) {
        $sql = "SELECT o.*, u.username, u.first_name, u.last_name,
                TIMESTAMPDIFF(HOUR, o.fecha_creacion, NOW()) as horas_desde_creacion
                FROM ordenes_pago o
                LEFT JOIN usuarios u ON o.telegram_id = u.telegram_id
                WHERE o.estado = 'revision'
                ORDER BY o.fecha_creacion DESC
                LIMIT :limite";
        
        try {
            $stmt = $this->db->conn->prepare($sql);
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error al obtener órdenes pendientes: " . $e->getMessage());
            return [];
        }
    }
    
    public function obtenerHistorialUsuario($telegramId, $limite = 10) {
        $sql = "SELECT * FROM ordenes_pago
                WHERE telegram_id = :telegram_id
                ORDER BY fecha_creacion DESC
                LIMIT :limite";
        
        try {
            $stmt = $this->db->conn->prepare($sql);
            $stmt->bindValue(':telegram_id', $telegramId, PDO::PARAM_INT);
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error al obtener historial: " . $e->getMessage());
            return [];
        }
    }
    
    public function limpiarOrdenesExpiradas() {
        $sql = "UPDATE ordenes_pago 
                SET estado = 'expirada'
                WHERE estado = 'pendiente' 
                AND fecha_expiracion < NOW()";
        
        try {
            $stmt = $this->db->conn->query($sql);
            return $stmt->rowCount();
        } catch(PDOException $e) {
            error_log("Error al limpiar órdenes expiradas: " . $e->getMessage());
            return 0;
        }
    }
    
    public function generarMensajePago($orden, $metodo) {
        $paquete = $this->obtenerPaquete($orden['paquete_id']);
        $metodoPago = $this->obtenerMetodoPago($metodo);
        
        if (!$paquete || !$metodoPago) {
            return false;
        }
        
        $mensaje = "💳 *INFORMACIÓN DE PAGO*\n\n";
        $mensaje .= "━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        
        $mensaje .= "📦 *Paquete:* {$paquete['creditos']} créditos\n";
        $mensaje .= "💰 *Monto:* {$paquete['moneda']} {$paquete['precio']}\n";
        $mensaje .= "🔖 *Código de Orden:* `{$orden['codigo_orden']}`\n\n";
        
        $mensaje .= "━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        
        if ($metodo == 'yape') {
            $mensaje .= "{$metodoPago['emoji']} *PAGAR CON YAPE*\n\n";
            $mensaje .= "📱 *Número:* `" . PAGO_YAPE_NUMERO . "`\n";
            $mensaje .= "👤 *Nombre:* " . PAGO_YAPE_NOMBRE . "\n";
            $mensaje .= "💵 *Monto:* S/ {$paquete['precio']}\n\n";
            $mensaje .= "📝 *Instrucciones:*\n";
            $mensaje .= "1️⃣ Abre tu app Yape\n";
            $mensaje .= "2️⃣ Yapea a: `" . PAGO_YAPE_NUMERO . "`\n";
            $mensaje .= "3️⃣ Monto exacto: *S/ {$paquete['precio']}*\n";
            $mensaje .= "4️⃣ Toma captura del comprobante\n";
            $mensaje .= "5️⃣ Envía la captura aquí\n\n";
        } 
        elseif ($metodo == 'plin') {
            $mensaje .= "{$metodoPago['emoji']} *PAGAR CON PLIN*\n\n";
            $mensaje .= "📱 *Número:* `" . PAGO_PLIN_NUMERO . "`\n";
            $mensaje .= "👤 *Nombre:* " . PAGO_PLIN_NOMBRE . "\n";
            $mensaje .= "💵 *Monto:* S/ {$paquete['precio']}\n\n";
            $mensaje .= "📝 *Instrucciones:*\n";
            $mensaje .= "1️⃣ Abre tu app bancaria\n";
            $mensaje .= "2️⃣ Selecciona Plin\n";
            $mensaje .= "3️⃣ Envía a: `" . PAGO_PLIN_NUMERO . "`\n";
            $mensaje .= "4️⃣ Monto exacto: *S/ {$paquete['precio']}*\n";
            $mensaje .= "5️⃣ Toma captura del comprobante\n";
            $mensaje .= "6️⃣ Envía la captura aquí\n\n";
        }
        elseif ($metodo == 'transferencia') {
            $mensaje .= "{$metodoPago['emoji']} *TRANSFERENCIA BANCARIA*\n\n";
            $mensaje .= "🏦 *Banco:* {$metodoPago['banco']}\n";
            $mensaje .= "💳 *Cuenta:* `{$metodoPago['cuenta']}`\n";
            $mensaje .= "🔢 *CCI:* `{$metodoPago['cci']}`\n";
            $mensaje .= "👤 *Titular:* {$metodoPago['titular']}\n";
            $mensaje .= "💵 *Monto:* S/ {$paquete['precio']}\n\n";
            $mensaje .= "📝 *Instrucciones:*\n";
            $mensaje .= "1️⃣ Realiza la transferencia\n";
            $mensaje .= "2️⃣ Monto exacto: *S/ {$paquete['precio']}*\n";
            $mensaje .= "3️⃣ Toma captura del comprobante\n";
            $mensaje .= "4️⃣ Envía la captura aquí\n\n";
        }
        
        $mensaje .= "━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $mensaje .= "⏰ *Válido por:* 24 horas\n\n";
        $mensaje .= "⚠️ *IMPORTANTE:*\n";
        $mensaje .= "• Envía el monto exacto\n";
        $mensaje .= "• Incluye el código: `{$orden['codigo_orden']}`\n";
        $mensaje .= "• Envía el comprobante como foto\n\n";
        $mensaje .= "_Tu pago será verificado en minutos_";
        
        return $mensaje;
    }
}

?>
