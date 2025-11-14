<?php
// index.php - Versión corregida (sin espacios en blanco)
ob_start(); // Iniciar buffer de salida para prevenir espacios
require_once 'core.php';
checkAuth();

// Inicializar base de datos
$database = new Database();
$db = $database->getConnection();

// DEFINIR LAS FUNCIONES PRIMERO
function getVentasHoy($db) {
    $query = "SELECT COALESCE(SUM(total), 0) as total FROM facturas WHERE DATE(fecha_factura) = CURDATE() AND estado = 'pagada'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row['total'];
}

function getPedidosActivos($db) {
    $query = "SELECT COUNT(*) as total FROM pedidos WHERE estado_pedido IN ('pendiente', 'en_preparacion')";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row['total'];
}

function getProductosBajos($db) {
    $query = "SELECT COUNT(*) as total FROM productos WHERE stock_actual <= stock_minimo AND estado = 'activo'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row['total'];
}

function getCajaActual($db) {
    $query = "SELECT COALESCE(SUM(total_ventas), 0) as total FROM caja WHERE estado = 'abierta'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row['total'];
}

function getPedidosRecientes($db) {
    $query = "SELECT p.id_pedido, m.numero_mesa, p.estado_pedido, p.fecha_pedido, 
                     TIME_FORMAT(p.fecha_pedido, '%H:%i') as hora_pedido
              FROM pedidos p 
              JOIN mesas m ON p.id_mesa = m.id_mesa 
              WHERE p.estado_pedido IN ('pendiente', 'en_preparacion')
              ORDER BY p.fecha_pedido DESC LIMIT 8";
    $stmt = $db->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getProductosPopulares($db) {
    $query = "SELECT p.nombre_producto, SUM(dp.cantidad) as total_vendido
              FROM detalle_pedidos dp
              JOIN productos p ON dp.id_producto = p.id_producto
              JOIN pedidos ped ON dp.id_pedido = ped.id_pedido
              WHERE DATE(ped.fecha_pedido) = CURDATE()
              GROUP BY p.id_producto
              ORDER BY total_vendido DESC LIMIT 6";
    $stmt = $db->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getEstadoCaja($db) {
    $query = "SELECT estado FROM caja WHERE estado = 'abierta' LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['estado'] : 'cerrada';
}

function getColorEstado($estado) {
    switch($estado) {
        case 'pendiente': return 'warning';
        case 'en_preparacion': return 'info';
        case 'listo': return 'success';
        case 'entregado': return 'secondary';
        default: return 'light';
    }
}

// AHORA LLAMAR LAS FUNCIONES
$ventas_hoy = getVentasHoy($db);
$pedidos_activos = getPedidosActivos($db);
$productos_bajos = getProductosBajos($db);
$caja_actual = getCajaActual($db);
$pedidos_recientes = getPedidosRecientes($db);
$productos_populares = getProductosPopulares($db);
$estado_caja = getEstadoCaja($db);
ob_end_clean(); // Limpiar buffer antes de enviar HTML
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo SITE_URL; ?>/assets/css/style.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #34495e;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
            --info: #3498db;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .dashboard-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 20px 20px;
        }
        
        .stat-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 1.5rem;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .stat-card .card-body {
            padding: 1.5rem;
        }
        
        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.8;
            margin-bottom: 1rem;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin: 0.5rem 0;
        }
        
        .stat-title {
            font-size: 0.9rem;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .module-card {
            border: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }
        
        .module-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .module-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-right: 1rem;
        }
        
        .badge-estado {
            font-size: 0.7rem;
            padding: 0.4em 0.8em;
        }
        
        .quick-actions {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .pedido-item {
            border-left: 4px solid;
            padding-left: 1rem;
            margin-bottom: 1rem;
        }
        
        .pedido-pendiente { border-left-color: var(--warning); }
        .pedido-preparacion { border-left-color: var(--info); }
        .pedido-listo { border-left-color: var(--success); }
        
        .producto-popular {
            display: flex;
            justify-content: between;
            align-items: center;
            padding: 0.75rem;
            border-bottom: 1px solid #eee;
        }
        
        .producto-popular:last-child {
            border-bottom: none;
        }
        
        .progress {
            height: 8px;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <?php 
    // Incluir header sin espacios
    ob_start();
    include 'includes/header.php';
    $header_content = ob_get_clean();
    echo trim($header_content);
    ?>

    <main class="container-fluid">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="h2 mb-2">Bienvenido, <?php echo explode(' ', $_SESSION['nombre'])[0]; ?>!</h1>
                        <p class="mb-0 opacity-75">Resumen general del negocio - <?php echo date('d/m/Y'); ?></p>
                    </div>
                    <div class="col-md-4 text-end">
                        <span class="badge bg-<?php echo $estado_caja == 'abierta' ? 'success' : 'secondary'; ?> fs-6 p-2">
                            <i class="fas fa-cash-register me-1"></i>
                            Caja <?php echo $estado_caja == 'abierta' ? 'Abierta' : 'Cerrada'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <!-- Estadísticas Principales -->
            <div class="row">
                <div class="col-xl-3 col-md-6">
                    <div class="card stat-card bg-primary text-white">
                        <div class="card-body text-center">
                            <i class="fas fa-dollar-sign stat-icon"></i>
                            <div class="stat-number">$<?php echo number_format($ventas_hoy, 2); ?></div>
                            <div class="stat-title">Ventas Hoy</div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card stat-card bg-success text-white">
                        <div class="card-body text-center">
                            <i class="fas fa-list-alt stat-icon"></i>
                            <div class="stat-number"><?php echo $pedidos_activos; ?></div>
                            <div class="stat-title">Pedidos Activos</div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card stat-card bg-warning text-white">
                        <div class="card-body text-center">
                            <i class="fas fa-exclamation-triangle stat-icon"></i>
                            <div class="stat-number"><?php echo $productos_bajos; ?></div>
                            <div class="stat-title">Productos Bajos</div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card stat-card bg-info text-white">
                        <div class="card-body text-center">
                            <i class="fas fa-cash-register stat-icon"></i>
                            <div class="stat-number">$<?php echo number_format($caja_actual, 2); ?></div>
                            <div class="stat-title">Caja Actual</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Columna Izquierda -->
                <div class="col-lg-8">
                    <!-- Acciones Rápidas -->
                    <div class="quick-actions">
                        <h5 class="mb-3"><i class="fas fa-bolt me-2 text-warning"></i>Acciones Rápidas</h5>
                        <div class="row g-3">
                            <div class="col-md-3 col-6">
                                <a href="modules/ventas/nueva_venta.php" class="btn btn-outline-primary w-100 d-flex flex-column align-items-center py-3">
                                    <i class="fas fa-plus-circle fa-2x mb-2"></i>
                                    <span>Nueva Venta</span>
                                </a>
                            </div>
                            <div class="col-md-3 col-6">
                                <a href="modules/caja/<?php echo $estado_caja == 'abierta' ? 'cierre.php' : 'apertura.php'; ?>" class="btn btn-outline-<?php echo $estado_caja == 'abierta' ? 'warning' : 'success'; ?> w-100 d-flex flex-column align-items-center py-3">
                                    <i class="fas fa-cash-register fa-2x mb-2"></i>
                                    <span><?php echo $estado_caja == 'abierta' ? 'Cerrar Caja' : 'Abrir Caja'; ?></span>
                                </a>
                            </div>
                            <div class="col-md-3 col-6">
                                <a href="modules/inventario/productos.php" class="btn btn-outline-info w-100 d-flex flex-column align-items-center py-3">
                                    <i class="fas fa-boxes fa-2x mb-2"></i>
                                    <span>Inventario</span>
                                </a>
                            </div>
                            <div class="col-md-3 col-6">
                                <a href="modules/ventas/pedidos.php" class="btn btn-outline-success w-100 d-flex flex-column align-items-center py-3">
                                    <i class="fas fa-utensils fa-2x mb-2"></i>
                                    <span>Ver Pedidos</span>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Pedidos Recientes -->
                    <div class="card module-card">
                        <div class="card-header bg-white border-0">
                            <h5 class="mb-0"><i class="fas fa-clock me-2 text-primary"></i>Pedidos Recientes</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($pedidos_recientes) > 0): ?>
                                <?php foreach ($pedidos_recientes as $pedido): ?>
                                    <div class="pedido-item pedido-<?php echo str_replace('_', '-', $pedido['estado_pedido']); ?>">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>Mesa <?php echo $pedido['numero_mesa']; ?></strong>
                                                <small class="text-muted d-block">Pedido #<?php echo $pedido['id_pedido']; ?> - <?php echo $pedido['hora_pedido']; ?></small>
                                            </div>
                                            <span class="badge bg-<?php echo getColorEstado($pedido['estado_pedido']); ?> badge-estado">
                                                <?php echo ucfirst(str_replace('_', ' ', $pedido['estado_pedido'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted text-center py-3">No hay pedidos activos</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Columna Derecha -->
                <div class="col-lg-4">
                    <!-- Productos Populares -->
                    <div class="card module-card">
                        <div class="card-header bg-white border-0">
                            <h5 class="mb-0"><i class="fas fa-chart-line me-2 text-success"></i>Top Productos Hoy</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($productos_populares) > 0): ?>
                                <?php 
                                $max_vendido = max(array_column($productos_populares, 'total_vendido'));
                                foreach ($productos_populares as $producto): 
                                    $porcentaje = $max_vendido > 0 ? ($producto['total_vendido'] / $max_vendido) * 100 : 0;
                                ?>
                                    <div class="producto-popular">
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between">
                                                <span class="fw-medium"><?php echo $producto['nombre_producto']; ?></span>
                                                <span class="text-primary fw-bold"><?php echo $producto['total_vendido']; ?></span>
                                            </div>
                                            <div class="progress">
                                                <div class="progress-bar bg-success" style="width: <?php echo $porcentaje; ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted text-center py-3">No hay ventas hoy</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Módulos del Sistema -->
                    <div class="card module-card">
                        <div class="card-header bg-white border-0">
                            <h5 class="mb-0"><i class="fas fa-th-large me-2 text-info"></i>Módulos del Sistema</h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <a href="modules/ventas/nueva_venta.php" class="list-group-item list-group-item-action d-flex align-items-center">
                                    <div class="module-icon bg-primary text-white">
                                        <i class="fas fa-shopping-cart"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">Ventas</h6>
                                        <small class="text-muted">Gestión de ventas y pedidos</small>
                                    </div>
                                </a>
                                <a href="modules/inventario/productos.php" class="list-group-item list-group-item-action d-flex align-items-center">
                                    <div class="module-icon bg-success text-white">
                                        <i class="fas fa-boxes"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">Inventario</h6>
                                        <small class="text-muted">Control de stock y productos</small>
                                    </div>
                                </a>
                                <a href="modules/caja/index.php" class="list-group-item list-group-item-action d-flex align-items-center">
                                    <div class="module-icon bg-warning text-white">
                                        <i class="fas fa-cash-register"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">Caja</h6>
                                        <small class="text-muted">Apertura y cierre de caja</small>
                                    </div>
                                </a>
                                <?php if (in_array($_SESSION['rol_id'], [4, 5])): ?>
                                <a href="modules/cocina/pedidos_pendientes.php" class="list-group-item list-group-item-action d-flex align-items-center">
                                    <div class="module-icon bg-danger text-white">
                                        <i class="fas fa-utensils"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">Cocina/Bar</h6>
                                        <small class="text-muted">Preparación de pedidos</small>
                                    </div>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh cada 30 segundos
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>