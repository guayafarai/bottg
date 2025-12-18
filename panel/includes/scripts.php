<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Funciones globales para el panel

// Aprobar orden con confirmación
function aprobarOrden(id) {
    Swal.fire({
        title: '¿Aprobar esta orden?',
        text: 'Los créditos se acreditarán automáticamente',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, aprobar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('panel_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=aprobar_orden&id=' + id
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('¡Aprobada!', 'La orden ha sido aprobada', 'success')
                    .then(() => location.reload());
                } else {
                    Swal.fire('Error', data.error, 'error');
                }
            });
        }
    });
}

// Rechazar orden con motivo
function rechazarOrden(id) {
    Swal.fire({
        title: 'Rechazar orden',
        input: 'text',
        inputLabel: 'Motivo del rechazo',
        inputPlaceholder: 'Ej: Comprobante ilegible',
        showCancelButton: true,
        confirmButtonText: 'Rechazar',
        cancelButtonText: 'Cancelar',
        inputValidator: (value) => {
            if (!value) {
                return 'Debes ingresar un motivo'
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('panel_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=rechazar_orden&id=' + id + '&motivo=' + encodeURIComponent(result.value)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Rechazada', 'La orden ha sido rechazada', 'success')
                    .then(() => location.reload());
                } else {
                    Swal.fire('Error', data.error, 'error');
                }
            });
        }
    });
}

// Ver orden en modal
function verOrden(id) {
    fetch('panel_api.php?action=obtener_orden&id=' + id)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const orden = data.orden;
                Swal.fire({
                    title: 'Orden #' + orden.id,
                    html: `
                        <div style="text-align: left;">
                            <p><strong>Código:</strong> ${orden.codigo_orden}</p>
                            <p><strong>Usuario:</strong> ${orden.telegram_id}</p>
                            <p><strong>Créditos:</strong> ${orden.creditos}</p>
                            <p><strong>Monto:</strong> ${orden.moneda} ${orden.monto}</p>
                            <p><strong>Método:</strong> ${orden.metodo_pago}</p>
                            <p><strong>Estado:</strong> ${orden.estado}</p>
                        </div>
                    `,
                    width: 600
                });
            }
        });
}

// Notificación de sonido
function playNotificationSound() {
    const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBTGH0fPTgjMGHm7A7+OZSA0PXK/o7qlYEwpDmuDyu2wiBTOGz/PTgjQGHmy/7+OYRw0OXK7o7qhXEwlDmd/zu2siBTOGz/PTgjMGHmy/7+OYRw0OXK7o7qhXEwlDmd/zu2siBTOGz/PTgjMGHmy/7+OYRw0OXK7o7qhXEwlDmd/zu2siBTOGz/PTgjMGHmy/7+OYRw0OXK7o7qhXEwlDmd/zu2siBTOGz/PTgjMGHmy/7+OYRw0OXK7o7qhXEwlDmd/zu2siBTOGz/PTgjMGHmy/7+OYRw0OXK7o7qhXEwlDmd/zu2siBTOGz/PTgjMGHmy/7+OYRw0OXK7o7qhXEwlDmd/zu2siBTOGz/PTgjMGHmy/7+OYRw0OXK7o7qhXEwlDmd/zu2siBTOGz/PTgjMGHmy/7+OYRw0OXK7o7qhXEwlDmd/zu2siBTOGz/PTgjMGHmy/7+OYRw0OXK7o7qhXEwlDmd/zu2siBTOGz/PTgjMGHmy/7+OYRw0OXK7o7qhXEwlDmd/zu2siBTOGz/PTgjMGHmy/7+OYRw0OXK7o7qhXEwlDmd/zu2siBTOGz/PTgjMGHmy/7+OYRw0OXK7o7qhXEwlDmd/zu2siBTOGz/PTgjMGHmy/7+OYRw0OXK7o7qhXEw');
    audio.play();
}
</script>
