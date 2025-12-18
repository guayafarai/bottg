<div class="top-header">
    <div class="search-box">
        <input type="text" placeholder="🔍 Buscar..." id="searchInput">
    </div>
    
    <div class="user-info">
        <div class="notification-badge" onclick="location.href='ordenes.php?estado=revision'">
            <i class="fas fa-bell" style="font-size: 20px; cursor: pointer;"></i>
            <?php
            $sql = "SELECT COUNT(*) as total FROM ordenes_pago WHERE estado = 'revision'";
            $stmt = $db->conn->query($sql);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result['total'] > 0):
            ?>
                <span class="badge"><?php echo $result['total']; ?></span>
            <?php endif; ?>
        </div>
        
        <div style="display: flex; align-items: center; gap: 10px;">
            <span><?php echo PanelAuth::getUsuarioActual(); ?></span>
            <i class="fas fa-user-circle" style="font-size: 24px;"></i>
        </div>
    </div>
</div>

<script>
// Auto-refresh cada 30 segundos si hay notificaciones pendientes
<?php if ($result['total'] > 0): ?>
    setTimeout(() => location.reload(), 30000);
<?php endif; ?>
</script>
