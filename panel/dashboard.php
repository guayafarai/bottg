<?php
require_once(__DIR__ . '/../config_bot.php');
require_once(__DIR__ . '/../config_pagos.php');
require_once(__DIR__ . '/config_panel.php');
require_once(__DIR__ . '/panel_auth.php');
require_once(__DIR__ . '/../sistema_pagos.php');

// Requerir autenticación
PanelAuth::requerirAuth();

$db = new Database();
$sistemaPagos = new SistemaPagos($db);

// Obtener estadísticas
$sql = "SELECT 
        (SELECT COUNT(*) FROM usuarios) as total_usuarios,
        (SELECT COUNT(*) FROM usuarios WHERE es_premium = 1) as usuarios_premium,
        (SELECT COUNT(*) FROM ordenes_pago WHERE estado = 'revision') as ordenes_pendientes,
        (SELECT COUNT(*) FROM ordenes_pago WHERE estado = 'aprobada' AND DATE(fecha_creacion) = CURDATE()) as ventas_hoy,
        (SELECT SUM(monto) FROM ordenes_pago WHERE estado = 'aprobada' AND DATE(fecha_creacion) = CURDATE()) as ingresos_hoy,
        (SELECT SUM(monto) FROM ordenes_pago WHERE estado = 'aprobada') as ingresos_total";

$stmt = $db->conn->query($sql);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener últimas órdenes pendientes
$ordenesPendientes = $sistemaPagos->obtenerOrdenesPendientes(10);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Panel de Administración</title>
    <?php include 'includes/head.php'; ?>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'includes/header.php'; ?>
        
        <div class="content-wrapper">
            <!-- Header del Dashboard -->
            <div class="page-header">
                <h1>📊 Dashboard</h1>
                <p>Resumen general del sistema</p>
            </div>
            
            <!-- Tarjetas de Estadísticas -->
            <div class="stats-grid">
                <!-- Total Usuarios -->
                <div class="stat-card" style="border-color: #667eea;">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        👥
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['total_usuarios']); ?></h3>
                        <p>Usuarios Registrados</p>
                        <small><?php echo $stats['usuarios_premium']; ?> Premium</small>
                    </div>
                </div>
                
                <!-- Órdenes Pendientes -->
                <div class="stat-card alert-warning" style="border-color: #ffc107;">
                    <div class="stat-icon" style="background: #ffc107;">
                        ⏳
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['ordenes_pendientes']; ?></h3>
                        <p>Órdenes Pendientes</p>
                        <small>Requieren revisión</small>
                    </div>
                </div>
                
                <!-- Ventas de Hoy -->
                <div class="stat-card" style="border-color: #28a745;">
                    <div class="stat-icon" style="background: #28a745;">
                        💰
                    </div>
                    <div class="stat-content">
                        <h3><?php echo MONEDA_SIMBOLO . ' ' . number_format($stats['ingresos_hoy'] ?? 0, 2); ?></h3>
                        <p>Ingresos Hoy</p>
                        <small><?php echo $stats['ventas_hoy'] ?? 0; ?> ventas</small>
                    </div>
                </div>
                
                <!-- Ingresos Totales -->
                <div class="stat-card" style="border-color: #17a2b8;">
                    <div class="stat-icon" style="background: #17a2b8;">
                        📈
                    </div>
                    <div class="stat-content">
                        <h3><?php echo MONEDA_SIMBOLO . ' ' . number_format($stats['ingresos_total'] ?? 0, 2); ?></h3>
                        <p>Ingresos Totales</p>
                        <small>Todas las ventas</small>
                    </div>
                </div>
            </div>
            
            <!-- Gráfico de Ventas -->
            <div class="card">
                <div class="card-header">
                    <h3>📈 Ventas de los Últimos 7 Días</h3>
                    <select id="dias-grafico" class="form-select-sm">
                        <option value="7">7 días</option>
                        <option value="15">15 días</option>
                        <option value="30">30 días</option>
                    </select>
                </div>
                <div class="card-body">
                    <canvas id="graficoVentas" height="80"></canvas>
                </div>
            </div>
            
            <!-- Tabla de Órdenes Pendientes -->
            <div class="card">
                <div class="card-header">
                    <h3>⏳ Órdenes Pendientes de Revisión</h3>
                    <a href="ordenes.php" class="btn btn-sm btn-primary">Ver Todas</a>
                </div>
                <div class="card-body">
                    <?php if (empty($ordenesPendientes)): ?>
                        <div class="empty-state">
                            <p>✅ No hay órdenes pendientes de revisión</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Usuario</th>
                                        <th>Paquete</th>
                                        <th>Monto</th>
                                        <th>Método</th>
                                        <th>Fecha</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ordenesPendientes as $orden): ?>
                                        <tr>
                                            <td>#<?php echo $orden['id']; ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($orden['first_name']); ?>
                                                <?php if ($orden['username']): ?>
                                                    <br><small>@<?php echo htmlspecialchars($orden['username']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $orden['creditos']; ?> créditos</td>
                                            <td><?php echo MONEDA_SIMBOLO . ' ' . $orden['monto']; ?></td>
                                            <td><?php echo ucfirst($orden['metodo_pago']); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($orden['fecha_creacion'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-success" onclick="aprobarOrden(<?php echo $orden['id']; ?>)">
                                                    ✅ Aprobar
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="rechazarOrden(<?php echo $orden['id']; ?>)">
                                                    ❌ Rechazar
                                                </button>
                                                <button class="btn btn-sm btn-info" onclick="verOrden(<?php echo $orden['id']; ?>)">
                                                    👁️ Ver
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    <?php include 'includes/scripts.php'; ?>
    
    <script>
        // Cargar gráfico de ventas
        cargarGraficoVentas(7);
        
        function cargarGraficoVentas(dias) {
            fetch('panel_api.php?action=obtener_grafico_ventas&dias=' + dias)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        renderizarGrafico(data.datos);
                    }
                });
        }
        
        function renderizarGrafico(datos) {
            const ctx = document.getElementById('graficoVentas').getContext('2d');
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: datos.map(d => d.fecha),
                    datasets: [{
                        label: 'Ventas',
                        data: datos.map(d => d.total_ventas),
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: true
                        }
                    }
                }
            });
        }
        
        // Cambio de período del gráfico
        document.getElementById('dias-grafico').addEventListener('change', function() {
            cargarGraficoVentas(this.value);
        });
        
        // Actualizar automáticamente cada 30 segundos
        setInterval(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
