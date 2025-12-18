<?php
require_once(__DIR__ . '/config_bot.php');
require_once(__DIR__ . '/config_pagos.php');
require_once(__DIR__ . '/config_panel.php');
require_once(__DIR__ . '/panel_auth.php');
require_once(__DIR__ . '/sistema_pagos.php');

PanelAuth::requerirAuth();

$db = new Database();
$sistemaPagos = new SistemaPagos($db);

// Filtros
$estado = $_GET['estado'] ?? 'revision';
$ordenes = $sistemaPagos->obtenerOrdenesPendientes(100);

// Filtrar por estado si no es 'revision'
if ($estado != 'revision') {
    $sql = "SELECT o.*, u.username, u.first_name FROM ordenes_pago o
            LEFT JOIN usuarios u ON o.telegram_id = u.telegram_id
            WHERE o.estado = :estado
            ORDER BY o.fecha_creacion DESC LIMIT 100";
    $stmt = $db->conn->prepare($sql);
    $stmt->execute([':estado' => $estado]);
    $ordenes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Órdenes - Panel</title>
    <?php include 'includes/head.php'; ?>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'includes/header.php'; ?>
        
        <div class="content-wrapper">
            <div class="page-header">
                <h1>💳 Gestión de Órdenes</h1>
                <p>Administra las compras de créditos</p>
            </div>
            
            <!-- Filtros -->
            <div class="card">
                <div class="card-body">
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <a href="?estado=revision" class="btn <?php echo $estado == 'revision' ? 'btn-primary' : ''; ?>">
                            ⏳ En Revisión
                        </a>
                        <a href="?estado=pendiente" class="btn <?php echo $estado == 'pendiente' ? 'btn-warning' : ''; ?>">
                            ⌛ Pendientes
                        </a>
                        <a href="?estado=aprobada" class="btn <?php echo $estado == 'aprobada' ? 'btn-success' : ''; ?>">
                            ✅ Aprobadas
                        </a>
                        <a href="?estado=rechazada" class="btn <?php echo $estado == 'rechazada' ? 'btn-danger' : ''; ?>">
                            ❌ Rechazadas
                        </a>
                        <a href="?estado=todas" class="btn <?php echo $estado == 'todas' ? 'btn-info' : ''; ?>">
                            📋 Todas
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Tabla de Órdenes -->
            <div class="card">
                <div class="card-header">
                    <h3>Lista de Órdenes</h3>
                    <span>Total: <?php echo count($ordenes); ?></span>
                </div>
                <div class="card-body">
                    <?php if (empty($ordenes)): ?>
                        <div class="empty-state">
                            <p>No hay órdenes en este estado</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Código</th>
                                        <th>Usuario</th>
                                        <th>Paquete</th>
                                        <th>Monto</th>
                                        <th>Método</th>
                                        <th>Estado</th>
                                        <th>Fecha</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ordenes as $orden): ?>
                                        <tr>
                                            <td>#<?php echo $orden['id']; ?></td>
                                            <td><code><?php echo $orden['codigo_orden']; ?></code></td>
                                            <td>
                                                <?php echo htmlspecialchars($orden['first_name'] ?? 'N/A'); ?>
                                                <?php if (!empty($orden['username'])): ?>
                                                    <br><small>@<?php echo htmlspecialchars($orden['username']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $orden['creditos']; ?> créditos</td>
                                            <td><?php echo $orden['moneda'] . ' ' . $orden['monto']; ?></td>
                                            <td><?php echo ucfirst($orden['metodo_pago']); ?></td>
                                            <td>
                                                <?php
                                                $badges = [
                                                    'pendiente' => 'badge-warning',
                                                    'revision' => 'badge-info',
                                                    'aprobada' => 'badge-success',
                                                    'rechazada' => 'badge-danger'
                                                ];
                                                $badge = $badges[$orden['estado']] ?? '';
                                                ?>
                                                <span class="badge <?php echo $badge; ?>">
                                                    <?php echo ucfirst($orden['estado']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($orden['fecha_creacion'])); ?></td>
                                            <td>
                                                <div style="display: flex; gap: 5px;">
                                                    <?php if ($orden['estado'] == 'revision'): ?>
                                                        <button class="btn btn-sm btn-success" onclick="aprobarOrden(<?php echo $orden['id']; ?>)">
                                                            ✅
                                                        </button>
                                                        <button class="btn btn-sm btn-danger" onclick="rechazarOrden(<?php echo $orden['id']; ?>)">
                                                            ❌
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-sm btn-info" onclick="verOrden(<?php echo $orden['id']; ?>)">
                                                        👁️
                                                    </button>
                                                </div>
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
</body>
</html>
