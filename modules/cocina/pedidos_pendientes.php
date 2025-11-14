<?php
require_once '../../config/config.php';
checkAuth();
checkPermission([4, 5]); // Cocina y Barman
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos Pendientes - <?php echo SITE_NAME; ?></title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    <?php include '../../includes/sidebar.php'; ?>

    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Pedidos Pendientes</h1>
        </div>

        <div class="row" id="pedidos-container">
            <?php mostrarPedidosPendientes(); ?>
        </div>
    </main>

    <script src="../../assets/js/jquery.min.js"></script>
    <script src="../../assets/js/bootstrap.min.js"></script>
    <script>
        function actualizarEstado(productoId, nuevoEstado) {
            $.post('actualizar_estado.php', {
                detalle_id: productoId,
                estado: nuevoEstado
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error al actualizar estado');
                }
            });
        }

        // Actualizar cada 30 segundos
        setInterval(function() {
            $('#pedidos-container').load(' #pedidos-container > *');
        }, 30000);
    </script>
</body>
</html>

<?php
function mostrarPedidosPendientes() {
    $database = new Database();
    $db = $database->getConnection();
    
    // Determinar tipo de productos según el rol
    $tipo_producto = ($_SESSION['rol_id'] == 4) ? 'comida' : 'bebida';
    
    $query = "SELECT dp.id_detalle, p.id_pedido, m.numero_mesa, pr.nombre_producto, 
                     dp.cantidad, dp.estado, dp.observaciones, dp.fecha_creacion
              FROM detalle_pedidos dp
              JOIN pedidos p ON dp.id_pedido = p.id_pedido
              JOIN mesas m ON p.id_mesa = m.id_mesa
              JOIN productos pr ON dp.id_producto = pr.id_producto
              JOIN categorias c ON pr.id_categoria = c.id_categoria
              WHERE dp.estado IN ('pendiente', 'en_preparacion') 
              AND c.tipo = ?
              ORDER BY p.fecha_pedido ASC, dp.id_detalle ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$tipo_producto]);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $badge_color = getColorEstado($row['estado']);
        
        echo "<div class='col-md-6 mb-3'>
                <div class='card'>
                    <div class='card-header d-flex justify-content-between'>
                        <h6>Mesa {$row['numero_mesa']} - Pedido #{$row['id_pedido']}</h6>
                        <span class='badge bg-{$badge_color}'>{$row['estado']}</span>
                    </div>
                    <div class='card-body'>
                        <h6>{$row['nombre_producto']} x {$row['cantidad']}</h6>
                        <p class='text-muted'>{$row['observaciones']}</p>
                        <div class='btn-group w-100'>
                            <button class='btn btn-sm btn-warning' onclick='actualizarEstado({$row['id_detalle']}, \"en_preparacion\")'>
                                En Preparación
                            </button>
                            <button class='btn btn-sm btn-success' onclick='actualizarEstado({$row['id_detalle']}, \"listo\")'>
                                Listo
                            </button>
                        </div>
                    </div>
                </div>
              </div>";
    }
}

function getColorEstado($estado) {
    switch($estado) {
        case 'pendiente': return 'warning';
        case 'en_preparacion': return 'info';
        case 'listo': return 'success';
        default: return 'secondary';
    }
}
?>