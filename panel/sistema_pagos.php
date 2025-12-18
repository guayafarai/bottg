<?php
/**
 * ═══════════════════════════════════════════════════════════════
 * SISTEMA DE PAGOS - CLASE PRINCIPAL
 * ═══════════════════════════════════════════════════════════════
 */

require_once(__DIR__ . '/config_pagos.php');

class SistemaPagos {
    private $db;
    private $paquetes;
    private $metodos;
    
    public function __construct($database) {
        global $PAQUETES_CREDITOS, $METODOS_PAGO;
        
        $this->db = $database;
        $this->paquetes = $PAQUETES_CREDITOS;
        $this->metodos = $METODOS_PAGO;
    }
    
    /**
     * Obtiene todos los paquetes disponibles
     */
    public function obtenerPaquetes() {
        return $this->paquetes;
    }
    
    /**
     * Obtiene información de un paquete específico
     */
    public function obtenerPaquete($id) {
        return isset($this->paquetes[$id]) ? $this->paquetes[$id] : false;
    }
    
    /**
     * Obtiene todos los métodos de pago activos
     */
    public function obtenerMetodosPago() {
        return array_filter($this->metodos, function($metodo) {
            return $metodo['activo'];
        });
    }
    
    /**
     * Obtiene información de un método de pago específico
     */
    public function obtenerMetodoPago($id) {
        return isset($this->metodos[$id]) ? $this->metodos[$id] : false;
    }
    
    /**
     * Crea una nueva orden de pago
     */
    public function crearOrdenPago($telegramId, $paqueteId, $metodoPago) {
        $paquete = $this->obtenerPaquete($paqueteId);
        $metodo = $this->obtenerMetodoPago($metodoPago);
        
        if (!$paquete || !$metodo) {
            return false;
        }
        
        // Generar código único de orden
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
    
    /**
     * Genera un código único de orden
     */
    private function generarCodigoOrden() {
        return 'ORD-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 10));
    }
    
    /**
     * Obtiene una orden por su código
     */
    public function obtenerOrdenPorCodigo($codigoOrden) {
        $sql = "SELECT * FROM ordenes_pago WHERE codigo_orden = :codigo_orden";
        
        try {
            $stmt = $this->db->conn->prepare($sql);
            $stmt->execute([':codigo_orden' => $codigoOrden]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            return false;
        }
    }
    
    /**
     * Obtiene una orden por su ID
     */
    public function obtenerOrden($ordenId) {
        $sql = "SELECT * FROM ordenes_pago WHERE id = :orden_id";
        
        try {
            $stmt = $this->db->conn->prepare($sql);
            $stmt->execute([':orden_id' => $ordenId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            return false;
        }
    }
    
    /**
     * Adjunta comprobante a una orden
     */
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
    
    /**
     * Aprobar una orden de pago
     */
    public function aprobarOrden($ordenId, $adminId = null) {
        $orden = $this->obtenerOrden($ordenId);
        
        if (!$orden || $orden['estado'] == 'aprobada') {
            return false;
        }
        
        try {
            // Iniciar transacción
            $this->db->conn->beginTransaction();
            
            // Actualizar estado de la orden
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
            
            // Agregar créditos al usuario
            $this->db->actualizarCreditos($orden['telegram_id'], $orden['creditos'], 'add');
            
            // Registrar transacción
            $this->db->registrarTransaccion(
                $orden['telegram_id'],
                'compra',
                $orden['creditos'],
                "Compra de {$orden['creditos']} créditos por {$orden['moneda']} {$orden['monto']}",
                $adminId
            );
            
            // Confirmar transacción
            $this->db->conn->commit();
            
            return true;
            
        } catch(PDOException $e) {
            $this->db->conn->rollBack();
            error_log("Error al aprobar orden: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Rechazar una orden de pago
     */
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
    
    /**
     * Cancela una orden de pago
     */
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
            return false;
        }
    }
    
    /**
     * Obtiene órdenes pendientes de revisión
     */
    public function obtenerOrdenesPendientes($limite = 50) {
        $sql = "SELECT o.*, u.username, u.first_name, u.last_name
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
            return [];
        }
    }
    
    /**
     * Obtiene el historial de órdenes de un usuario
     */
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
            return [];
        }
    }
    
    /**
     * Limpia órdenes expiradas
     */
    public function limpiarOrdenesExpiradas() {
        $sql = "UPDATE ordenes_pago 
                SET estado = 'expirada'
                WHERE estado = 'pendiente' 
                AND fecha_expiracion < NOW()";
        
        try {
            $stmt = $this->db->conn->query($sql);
            return $stmt->rowCount();
        } catch(PDOException $e) {
            return 0;
        }
    }
    
    /**
     * Genera mensaje de información de pago según el método
     */
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
        
        // Información específica según método de pago
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
