-- ═══════════════════════════════════════════════════════════════
-- TABLAS PARA SISTEMA DE PAGOS
-- ═══════════════════════════════════════════════════════════════

-- Tabla de órdenes de pago
CREATE TABLE IF NOT EXISTS ordenes_pago (
    id INT AUTO_INCREMENT PRIMARY KEY,
    telegram_id BIGINT NOT NULL,
    codigo_orden VARCHAR(50) UNIQUE NOT NULL,
    paquete_id VARCHAR(50) NOT NULL,
    creditos INT NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    moneda VARCHAR(10) DEFAULT 'PEN',
    metodo_pago VARCHAR(50) NOT NULL,
    estado ENUM('pendiente', 'revision', 'aprobada', 'rechazada', 'cancelada', 'expirada') DEFAULT 'pendiente',
    comprobante_file_id VARCHAR(255),
    comprobante_tipo VARCHAR(50),
    admin_id BIGINT,
    motivo_rechazo TEXT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_expiracion TIMESTAMP,
    fecha_aprobacion TIMESTAMP NULL,
    notas TEXT,
    INDEX idx_telegram_id (telegram_id),
    INDEX idx_codigo_orden (codigo_orden),
    INDEX idx_estado (estado),
    INDEX idx_fecha (fecha_creacion),
    FOREIGN KEY (telegram_id) REFERENCES usuarios(telegram_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de promociones/descuentos
CREATE TABLE IF NOT EXISTS promociones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) UNIQUE NOT NULL,
    tipo ENUM('porcentaje', 'creditos_extra', 'descuento_fijo') DEFAULT 'porcentaje',
    valor DECIMAL(10,2) NOT NULL,
    creditos_minimos INT DEFAULT 0,
    usos_maximos INT DEFAULT 0,
    usos_actuales INT DEFAULT 0,
    fecha_inicio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_fin TIMESTAMP NULL,
    activo BOOLEAN DEFAULT TRUE,
    descripcion TEXT,
    INDEX idx_codigo (codigo),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de uso de promociones
CREATE TABLE IF NOT EXISTS uso_promociones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    promocion_id INT NOT NULL,
    telegram_id BIGINT NOT NULL,
    orden_id INT NOT NULL,
    creditos_extra INT DEFAULT 0,
    descuento DECIMAL(10,2) DEFAULT 0,
    fecha_uso TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (promocion_id) REFERENCES promociones(id) ON DELETE CASCADE,
    FOREIGN KEY (telegram_id) REFERENCES usuarios(telegram_id) ON DELETE CASCADE,
    FOREIGN KEY (orden_id) REFERENCES ordenes_pago(id) ON DELETE CASCADE,
    INDEX idx_telegram_id (telegram_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de estadísticas de pagos
CREATE TABLE IF NOT EXISTS estadisticas_pagos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATE NOT NULL,
    total_ordenes INT DEFAULT 0,
    ordenes_aprobadas INT DEFAULT 0,
    ordenes_rechazadas INT DEFAULT 0,
    ordenes_pendientes INT DEFAULT 0,
    total_recaudado DECIMAL(10,2) DEFAULT 0,
    creditos_vendidos INT DEFAULT 0,
    metodo_mas_usado VARCHAR(50),
    UNIQUE KEY unique_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de notificaciones de pago (para tracking)
CREATE TABLE IF NOT EXISTS notificaciones_pago (
    id INT AUTO_INCREMENT PRIMARY KEY,
    orden_id INT NOT NULL,
    tipo ENUM('nueva_orden', 'comprobante_recibido', 'aprobada', 'rechazada') NOT NULL,
    admin_notificado BIGINT,
    usuario_notificado BIGINT,
    fecha_notificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (orden_id) REFERENCES ordenes_pago(id) ON DELETE CASCADE,
    INDEX idx_orden (orden_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════
-- ÍNDICES ADICIONALES PARA OPTIMIZACIÓN
-- ═══════════════════════════════════════════════════════════════

-- Índices compuestos para consultas frecuentes
CREATE INDEX idx_ordenes_estado_fecha ON ordenes_pago(estado, fecha_creacion);
CREATE INDEX idx_ordenes_usuario_estado ON ordenes_pago(telegram_id, estado);

-- ═══════════════════════════════════════════════════════════════
-- VISTAS ÚTILES
-- ═══════════════════════════════════════════════════════════════

-- Vista de órdenes pendientes con datos de usuario
CREATE OR REPLACE VIEW vista_ordenes_pendientes AS
SELECT 
    o.id,
    o.codigo_orden,
    o.telegram_id,
    u.username,
    u.first_name,
    u.last_name,
    o.paquete_id,
    o.creditos,
    o.monto,
    o.moneda,
    o.metodo_pago,
    o.estado,
    o.comprobante_file_id,
    o.fecha_creacion,
    o.fecha_expiracion,
    TIMESTAMPDIFF(HOUR, o.fecha_creacion, NOW()) as horas_desde_creacion
FROM ordenes_pago o
LEFT JOIN usuarios u ON o.telegram_id = u.telegram_id
WHERE o.estado = 'revision'
ORDER BY o.fecha_creacion DESC;

-- Vista de estadísticas por usuario
CREATE OR REPLACE VIEW vista_estadisticas_usuario AS
SELECT 
    u.telegram_id,
    u.username,
    u.first_name,
    COUNT(o.id) as total_ordenes,
    SUM(CASE WHEN o.estado = 'aprobada' THEN 1 ELSE 0 END) as ordenes_aprobadas,
    SUM(CASE WHEN o.estado = 'aprobada' THEN o.monto ELSE 0 END) as total_gastado,
    SUM(CASE WHEN o.estado = 'aprobada' THEN o.creditos ELSE 0 END) as total_creditos_comprados,
    MAX(o.fecha_creacion) as ultima_compra
FROM usuarios u
LEFT JOIN ordenes_pago o ON u.telegram_id = o.telegram_id
GROUP BY u.telegram_id, u.username, u.first_name;

-- ═══════════════════════════════════════════════════════════════
-- TRIGGERS
-- ═══════════════════════════════════════════════════════════════

-- Trigger para actualizar estadísticas cuando se aprueba una orden
DELIMITER //

CREATE TRIGGER after_orden_aprobada
AFTER UPDATE ON ordenes_pago
FOR EACH ROW
BEGIN
    IF NEW.estado = 'aprobada' AND OLD.estado != 'aprobada' THEN
        -- Actualizar estadísticas del día
        INSERT INTO estadisticas_pagos (fecha, ordenes_aprobadas, total_recaudado, creditos_vendidos)
        VALUES (CURDATE(), 1, NEW.monto, NEW.creditos)
        ON DUPLICATE KEY UPDATE
            ordenes_aprobadas = ordenes_aprobadas + 1,
            total_recaudado = total_recaudado + NEW.monto,
            creditos_vendidos = creditos_vendidos + NEW.creditos;
    END IF;
END//

DELIMITER ;

-- ═══════════════════════════════════════════════════════════════
-- DATOS INICIALES - PROMOCIONES DE EJEMPLO
-- ═══════════════════════════════════════════════════════════════

-- Promoción de bienvenida
INSERT INTO promociones (codigo, tipo, valor, creditos_minimos, usos_maximos, descripcion, activo)
VALUES 
('BIENVENIDA', 'creditos_extra', 10, 50, 100, 'Promoción de bienvenida: 10 créditos extra en tu primera compra', TRUE),
('PRIMERACOMPRA', 'porcentaje', 20, 0, 1000, '20% de descuento en tu primera compra', TRUE)
ON DUPLICATE KEY UPDATE id=id;

-- ═══════════════════════════════════════════════════════════════
-- PROCEDIMIENTOS ALMACENADOS ÚTILES
-- ═══════════════════════════════════════════════════════════════

DELIMITER //

-- Procedimiento para limpiar órdenes expiradas
CREATE PROCEDURE limpiar_ordenes_expiradas()
BEGIN
    UPDATE ordenes_pago 
    SET estado = 'expirada'
    WHERE estado = 'pendiente' 
    AND fecha_expiracion < NOW();
    
    SELECT ROW_COUNT() as ordenes_expiradas;
END//

-- Procedimiento para obtener reporte de ventas
CREATE PROCEDURE reporte_ventas(IN fecha_inicio DATE, IN fecha_fin DATE)
BEGIN
    SELECT 
        DATE(fecha_creacion) as fecha,
        COUNT(*) as total_ordenes,
        SUM(CASE WHEN estado = 'aprobada' THEN 1 ELSE 0 END) as aprobadas,
        SUM(CASE WHEN estado = 'aprobada' THEN monto ELSE 0 END) as total_recaudado,
        SUM(CASE WHEN estado = 'aprobada' THEN creditos ELSE 0 END) as creditos_vendidos,
        metodo_pago
    FROM ordenes_pago
    WHERE DATE(fecha_creacion) BETWEEN fecha_inicio AND fecha_fin
    GROUP BY DATE(fecha_creacion), metodo_pago
    ORDER BY fecha DESC;
END//

DELIMITER ;

-- ═══════════════════════════════════════════════════════════════
-- CONSULTAS ÚTILES PARA ADMINISTRACIÓN
-- ═══════════════════════════════════════════════════════════════

-- Ver órdenes pendientes de revisión
-- SELECT * FROM vista_ordenes_pendientes;

-- Ver estadísticas de un usuario
-- SELECT * FROM vista_estadisticas_usuario WHERE telegram_id = ?;

-- Obtener reporte de ventas del mes
-- CALL reporte_ventas('2024-01-01', '2024-01-31');

-- Limpiar órdenes expiradas
-- CALL limpiar_ordenes_expiradas();

-- Ver top usuarios por compras
-- SELECT * FROM vista_estadisticas_usuario ORDER BY total_gastado DESC LIMIT 10;
