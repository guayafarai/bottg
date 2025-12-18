<?php
// Verificar autenticación
PanelAuth::requerirAuth();
$paginaActual = basename($_SERVER['PHP_SELF'], '.php');
?>
<div class="sidebar">
    <div class="sidebar-header">
        <h2><?php echo PANEL_LOGO; ?> <?php echo PANEL_EMPRESA; ?></h2>
        <p>Panel de Administración</p>
    </div>
    
    <ul class="sidebar-menu">
        <li>
            <a href="dashboard.php" class="<?php echo $paginaActual == 'dashboard' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i> Dashboard
            </a>
        </li>
        <li>
            <a href="ordenes.php" class="<?php echo $paginaActual == 'ordenes' ? 'active' : ''; ?>">
                <i class="fas fa-shopping-cart"></i> Órdenes
                <?php
                // Mostrar badge de pendientes
                $sql = "SELECT COUNT(*) as total FROM ordenes_pago WHERE estado = 'revision'";
                $stmt = $db->conn->query($sql);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result['total'] > 0):
                ?>
                    <span class="badge badge-danger" style="margin-left: auto;"><?php echo $result['total']; ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li>
            <a href="usuarios.php" class="<?php echo $paginaActual == 'usuarios' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> Usuarios
            </a>
        </li>
        <li>
            <a href="configuracion.php" class="<?php echo $paginaActual == 'configuracion' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i> Configuración
            </a>
        </li>
        <li>
            <a href="promociones.php" class="<?php echo $paginaActual == 'promociones' ? 'active' : ''; ?>">
                <i class="fas fa-gift"></i> Promociones
            </a>
        </li>
        <li>
            <a href="reportes.php" class="<?php echo $paginaActual == 'reportes' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i> Reportes
            </a>
        </li>
        <li>
            <a href="?logout=1" style="color: #e74c3c;">
                <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
            </a>
        </li>
    </ul>
    
    <div style="position: absolute; bottom: 20px; left: 20px; right: 20px; text-align: center; opacity: 0.5; font-size: 12px;">
        v<?php echo PANEL_VERSION; ?>
    </div>
</div>

<?php
// Procesar logout
if (isset($_GET['logout'])) {
    PanelAuth::logout();
}
?>
