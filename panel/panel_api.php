<?php
/**
 * ═══════════════════════════════════════════════════════════════
 * API DEL PANEL - ENDPOINTS AJAX
 * ═══════════════════════════════════════════════════════════════
 */

require_once(__DIR__ . '/../config_bot.php');
require_once(__DIR__ . '/../config_pagos.php');
require_once(__DIR__ . '/config_panel.php');
require_once(__DIR__ . '/panel_auth.php');
require_once(__DIR__ . '/../sistema_pagos.php');

class PanelAPI {
    private $db;
    private $sistemaPagos;
    
    public function __construct() {
        $this->db = new Database();
        $this->sistemaPagos = new SistemaPagos($this->db);
    }
    
    /**
     * Maneja las peticiones API
     */
    public function manejarPeticion() {
        // Verificar autenticación
        PanelAuth::iniciarSesion();
        if (!PanelAuth::estaAutenticado()) {
            $this->responderJSON(['error' => 'No autenticado'], 401);
            return;
        }
        
        $accion = $_GET['action'] ?? $_POST['action'] ?? '';
        
        switch ($accion) {
            // ÓRDENES
            case 'obtener_ordenes':
                $this->obtenerOrdenes();
                break;
            case 'obtener_orden':
                $this->obtenerOrden();
                break;
            case 'aprobar_orden':
                $this->aprobarOrden();
                break;
            case 'rechazar_orden':
                $this->rechazarOrden();
                break;
                
            // USUARIOS
            case 'obtener_usuarios':
                $this->obtenerUsuarios();
                break;
            case 'obtener_usuario':
                $this->obtenerUsuario();
                break;
            case 'actualizar_creditos':
                $this->actualizarCreditos();
                break;
            case 'bloquear_usuario':
                $this->bloquearUsuario();
                break;
            case 'hacer_premium':
                $this->hacerPremium();
                break;
                
            // ESTADÍSTICAS
            case 'obtener_estadisticas':
                $this->obtenerEstadisticas();
                break;
            case 'obtener_grafico_ventas':
                $this->obtenerGraficoVentas();
                break;
                
            // CONFIGURACIÓN
            case 'guardar_paquete':
                $this->guardarPaquete();
                break;
            case 'eliminar_paquete':
                $this->eliminarPaquete();
                break;
            case 'guardar_metodo_pago':
                $this->guardarMetodoPago();
                break;
            case 'guardar_config_general':
                $this->guardarConfigGeneral();
                break;
                
            // PROMOCIONES
            case 'crear_promocion':
                $this->crearPromocion();
                break;
            case 'obtener_promociones':
                $this->obtenerPromociones();
                break;
            case 'activar_promocion':
                $this->activarPromocion();
                break;
                
            // NOTIFICACIONES
            case 'contar_pendientes':
                $this->contarPendientes();
                break;
                
            default:
                $this->responderJSON(['error' => 'Acción no válida'], 400);
        }
    }
    
    // ═══════════════════════════════════════════════════════════
    // MÉTODOS DE ÓRDENES
    // ═══════════════════════════════════════════════════════════
    
    private function obtenerOrdenes() {
        $estado = $_GET['estado'] ?? 'revision';
        $limite = intval($_GET['limite'] ?? 50);
        
        $sql = "SELECT o.*, u.username, u.first_name, u.last_name
                FROM ordenes_pago o
                LEFT JOIN usuarios u ON o.telegram_id = u.telegram_id";
        
        if ($estado != 'todas') {
            $sql .= " WHERE o.estado = :estado";
        }
        
        $sql .= " ORDER BY o.fecha_creacion DESC LIMIT :limite";
        
        try {
            $stmt = $this->db->conn->prepare($sql);
            
            if ($estado != 'todas') {
                $stmt->bindValue(':estado', $estado, PDO::PARAM_STR);
            }
            
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            
            $ordenes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->responderJSON(['success' => true, 'ordenes' => $ordenes]);
            
        } catch (PDOException $e) {
            $this->responderJSON(['error' => $e->getMessage()], 500);
        }
    }
    
    private function obtenerOrden() {
        $id = intval($_GET['id'] ?? 0);
        
        $orden = $this->sistemaPagos->obtenerOrden($id);
        
        if ($orden) {
            $this->responderJSON(['success' => true, 'orden' => $orden]);
        } else {
            $this->responderJSON(['error' => 'Orden no encontrada'], 404);
        }
    }
    
    private function aprobarOrden() {
        $id = intval($_POST['id'] ?? 0);
        $adminId = $_SESSION['admin_telegram_id'] ?? null;
        
        if ($this->sistemaPagos->aprobarOrden($id, $adminId)) {
            $this->responderJSON(['success' => true, 'mensaje' => 'Orden aprobada']);
        } else {
            $this->responderJSON(['error' => 'Error al aprobar orden'], 500);
        }
    }
    
    private function rechazarOrden() {
        $id = intval($_POST['id'] ?? 0);
        $motivo = $_POST['motivo'] ?? 'No especificado';
        $adminId = $_SESSION['admin_telegram_id'] ?? null;
        
        if ($this->sistemaPagos->rechazarOrden($id, $motivo, $adminId)) {
            $this->responderJSON(['success' => true, 'mensaje' => 'Orden rechazada']);
        } else {
            $this->responderJSON(['error' => 'Error al rechazar orden'], 500);
        }
    }
    
    // ═══════════════════════════════════════════════════════════
    // MÉTODOS DE USUARIOS
    // ═══════════════════════════════════════════════════════════
    
    private function obtenerUsuarios() {
        $sql = "SELECT u.*,
                COUNT(o.id) as total_ordenes,
                SUM(CASE WHEN o.estado = 'aprobada' THEN o.monto ELSE 0 END) as total_gastado
                FROM usuarios u
                LEFT JOIN ordenes_pago o ON u.telegram_id = o.telegram_id
                GROUP BY u.telegram_id
                ORDER BY u.fecha_registro DESC
                LIMIT 100";
        
        try {
            $stmt = $this->db->conn->query($sql);
            $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->responderJSON(['success' => true, 'usuarios' => $usuarios]);
            
        } catch (PDOException $e) {
            $this->responderJSON(['error' => $e->getMessage()], 500);
        }
    }
    
    private function obtenerUsuario() {
        $telegramId = intval($_GET['telegram_id'] ?? 0);
        
        $usuario = $this->db->getUsuario($telegramId);
        
        if ($usuario) {
            $this->responderJSON(['success' => true, 'usuario' => $usuario]);
        } else {
            $this->responderJSON(['error' => 'Usuario no encontrado'], 404);
        }
    }
    
    private function actualizarCreditos() {
        $telegramId = intval($_POST['telegram_id'] ?? 0);
        $cantidad = intval($_POST['cantidad'] ?? 0);
        $operacion = $_POST['operacion'] ?? 'add'; // add o subtract
        
        if ($this->db->actualizarCreditos($telegramId, $cantidad, $operacion)) {
            $this->responderJSON(['success' => true, 'mensaje' => 'Créditos actualizados']);
        } else {
            $this->responderJSON(['error' => 'Error al actualizar créditos'], 500);
        }
    }
    
    private function bloquearUsuario() {
        $telegramId = intval($_POST['telegram_id'] ?? 0);
        $bloquear = boolval($_POST['bloquear'] ?? true);
        
        if ($this->db->bloquearUsuario($telegramId, $bloquear)) {
            $accion = $bloquear ? 'bloqueado' : 'desbloqueado';
            $this->responderJSON(['success' => true, 'mensaje' => "Usuario {$accion}"]);
        } else {
            $this->responderJSON(['error' => 'Error al bloquear usuario'], 500);
        }
    }
    
    private function hacerPremium() {
        $telegramId = intval($_POST['telegram_id'] ?? 0);
        $premium = boolval($_POST['premium'] ?? true);
        
        if ($this->db->setPremium($telegramId, $premium)) {
            $accion = $premium ? 'activado' : 'desactivado';
            $this->responderJSON(['success' => true, 'mensaje' => "Premium {$accion}"]);
        } else {
            $this->responderJSON(['error' => 'Error al cambiar premium'], 500);
        }
    }
    
    // ═══════════════════════════════════════════════════════════
    // MÉTODOS DE ESTADÍSTICAS
    // ═══════════════════════════════════════════════════════════
    
    private function obtenerEstadisticas() {
        $sql = "SELECT 
                (SELECT COUNT(*) FROM usuarios) as total_usuarios,
                (SELECT COUNT(*) FROM usuarios WHERE es_premium = 1) as usuarios_premium,
                (SELECT COUNT(*) FROM ordenes_pago WHERE estado = 'revision') as ordenes_pendientes,
                (SELECT COUNT(*) FROM ordenes_pago WHERE estado = 'aprobada') as ordenes_aprobadas,
                (SELECT SUM(monto) FROM ordenes_pago WHERE estado = 'aprobada') as total_recaudado,
                (SELECT SUM(creditos) FROM ordenes_pago WHERE estado = 'aprobada') as creditos_vendidos";
        
        try {
            $stmt = $this->db->conn->query($sql);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $this->responderJSON(['success' => true, 'estadisticas' => $stats]);
            
        } catch (PDOException $e) {
            $this->responderJSON(['error' => $e->getMessage()], 500);
        }
    }
    
    private function obtenerGraficoVentas() {
        $dias = intval($_GET['dias'] ?? 7);
        
        $sql = "SELECT 
                DATE(fecha_creacion) as fecha,
                COUNT(*) as total_ordenes,
                SUM(CASE WHEN estado = 'aprobada' THEN monto ELSE 0 END) as total_ventas
                FROM ordenes_pago
                WHERE fecha_creacion >= DATE_SUB(NOW(), INTERVAL :dias DAY)
                GROUP BY DATE(fecha_creacion)
                ORDER BY fecha ASC";
        
        try {
            $stmt = $this->db->conn->prepare($sql);
            $stmt->bindValue(':dias', $dias, PDO::PARAM_INT);
            $stmt->execute();
            
            $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->responderJSON(['success' => true, 'datos' => $datos]);
            
        } catch (PDOException $e) {
            $this->responderJSON(['error' => $e->getMessage()], 500);
        }
    }
    
    // ═══════════════════════════════════════════════════════════
    // MÉTODOS DE CONFIGURACIÓN
    // ═══════════════════════════════════════════════════════════
    
    private function guardarPaquete() {
        $paquete = [
            'id' => $_POST['id'] ?? '',
            'creditos' => intval($_POST['creditos'] ?? 0),
            'precio' => floatval($_POST['precio'] ?? 0),
            'moneda' => $_POST['moneda'] ?? 'PEN',
            'ahorro' => intval($_POST['ahorro'] ?? 0),
            'popular' => boolval($_POST['popular'] ?? false),
            'emoji' => $_POST['emoji'] ?? '📦'
        ];
        
        // Guardar en archivo de configuración
        if ($this->guardarEnConfig('PAQUETES_CREDITOS', $paquete['id'], $paquete)) {
            $this->responderJSON(['success' => true, 'mensaje' => 'Paquete guardado']);
        } else {
            $this->responderJSON(['error' => 'Error al guardar paquete'], 500);
        }
    }
    
    private function guardarConfigGeneral() {
        $config = [
            'PAGO_YAPE_NUMERO' => $_POST['yape_numero'] ?? '',
            'PAGO_YAPE_NOMBRE' => $_POST['yape_nombre'] ?? '',
            'PAGO_PLIN_NUMERO' => $_POST['plin_numero'] ?? '',
            'PAGO_PLIN_NOMBRE' => $_POST['plin_nombre'] ?? '',
            'CREDITOS_REGISTRO' => intval($_POST['creditos_registro'] ?? 10),
            'COSTO_GENERACION' => intval($_POST['costo_generacion'] ?? 1)
        ];
        
        if ($this->actualizarConfigPHP('config_pagos.php', $config)) {
            $this->responderJSON(['success' => true, 'mensaje' => 'Configuración guardada']);
        } else {
            $this->responderJSON(['error' => 'Error al guardar configuración'], 500);
        }
    }
    
    // ═══════════════════════════════════════════════════════════
    // MÉTODOS DE PROMOCIONES
    // ═══════════════════════════════════════════════════════════
    
    private function crearPromocion() {
        $codigo = $_POST['codigo'] ?? '';
        $tipo = $_POST['tipo'] ?? 'porcentaje';
        $valor = floatval($_POST['valor'] ?? 0);
        $descripcion = $_POST['descripcion'] ?? '';
        
        $sql = "INSERT INTO promociones (codigo, tipo, valor, descripcion)
                VALUES (:codigo, :tipo, :valor, :descripcion)";
        
        try {
            $stmt = $this->db->conn->prepare($sql);
            $stmt->execute([
                ':codigo' => $codigo,
                ':tipo' => $tipo,
                ':valor' => $valor,
                ':descripcion' => $descripcion
            ]);
            
            $this->responderJSON(['success' => true, 'mensaje' => 'Promoción creada']);
            
        } catch (PDOException $e) {
            $this->responderJSON(['error' => $e->getMessage()], 500);
        }
    }
    
    private function obtenerPromociones() {
        $sql = "SELECT * FROM promociones ORDER BY fecha_inicio DESC LIMIT 50";
        
        try {
            $stmt = $this->db->conn->query($sql);
            $promociones = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->responderJSON(['success' => true, 'promociones' => $promociones]);
            
        } catch (PDOException $e) {
            $this->responderJSON(['error' => $e->getMessage()], 500);
        }
    }
    
    private function contarPendientes() {
        $sql = "SELECT COUNT(*) as total FROM ordenes_pago WHERE estado = 'revision'";
        
        try {
            $stmt = $this->db->conn->query($sql);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $this->responderJSON(['success' => true, 'total' => $result['total']]);
            
        } catch (PDOException $e) {
            $this->responderJSON(['error' => $e->getMessage()], 500);
        }
    }
    
    // ═══════════════════════════════════════════════════════════
    // MÉTODOS AUXILIARES
    // ═══════════════════════════════════════════════════════════
    
    private function responderJSON($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    private function guardarEnConfig($variable, $key, $value) {
        // Implementar guardado en archivo config_pagos.php
        // Por seguridad, mejor usar una tabla en BD para configuraciones
        return true;
    }
    
    private function actualizarConfigPHP($archivo, $valores) {
        // Implementar actualización de archivo PHP
        // Mejor usar tabla configuracion en BD
        return true;
    }
}

// Ejecutar API si se llama directamente
if (basename($_SERVER['SCRIPT_NAME']) === 'panel_api.php') {
    $api = new PanelAPI();
    $api->manejarPeticion();
}

?>
