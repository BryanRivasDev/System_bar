<?php
// modules/ventas/mesas.php
require_once __DIR__ . '/../../core.php';
checkAuth();
checkPermission([1, 2, 3]);

$database = new Database();
$db = $database->getConnection();

// Procesar cambio de estado de mesa
if ($_POST && isset($_POST['accion'])) {
    $mesa_id = intval($_POST['mesa_id']);
    
    try {
        if ($_POST['accion'] == 'liberar') {
            $query = "UPDATE mesas SET estado = 'libre' WHERE id_mesa = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$mesa_id]);
            
            $_SESSION['success'] = "Mesa liberada correctamente";
        } elseif ($_POST['accion'] == 'ocupar') {
            $query = "UPDATE mesas SET estado = 'ocupada' WHERE id_mesa = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$mesa_id]);
            
            $_SESSION['success'] = "Mesa marcada como ocupada";
        } elseif ($_POST['accion'] == 'reservar') {
            $query = "UPDATE mesas SET estado = 'reservada' WHERE id_mesa = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$mesa_id]);
            
            $_SESSION['success'] = "Mesa reservada correctamente";
        }
        
        header("Location: mesas.php");
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error al actualizar la mesa: " . $e->getMessage();
    }
}

// Obtener todas las mesas con información de pedidos activos
$query = "SELECT m.*, 
          p.id_pedido, 
          p.total, 
          p.estado_pedido,
          f.estado as estado_factura,
          f.metodo_pago
          FROM mesas m 
          LEFT JOIN pedidos p ON m.id_mesa = p.id_mesa 
            AND p.estado_pedido IN ('pendiente', 'en_preparacion', 'listo')
          LEFT JOIN facturas f ON p.id_pedido = f.id_pedido
          ORDER BY m.numero_mesa";
$stmt = $db->prepare($query);
$stmt->execute();
$mesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Mesas - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .mesa-card {
            transition: transform 0.2s;
            margin-bottom: 1.5rem;
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .mesa-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.2);
        }
        
        .mesa-libre {
            border-left: 5px solid #28a745;
        }
        
        .mesa-ocupada {
            border-left: 5px solid #dc3545;
        }
        
        .mesa-reservada {
            border-left: 5px solid #ffc107;
        }
        
        .estado-badge {
            font-size: 0.8rem;
            padding: 0.5rem 1rem;
        }
        
        .card {
            border-radius: 15px;
        }
        
        .btn-action {
            margin: 0.2rem;
        }
        
        .factura-info {
            background: #e7f3ff;
            border-radius: 5px;
            padding: 8px;
            margin-top: 8px;
            font-size: 0.85rem;
        }
        
        .factura-pendiente {
            background: #fff3cd;
            border-left: 3px solid #ffc107;
        }
        
        .factura-pagada {
            background: #d1edff;
            border-left: 3px solid #0dcaf0;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <main class="container-fluid" style="margin-top: 80px;">
        <div class="container">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-table me-2"></i>
                    Gestión de Mesas
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="nueva_venta.php" class="btn btn-primary me-2">
                        <i class="fas fa-plus me-1"></i>
                        Nueva Venta
                    </a>
                    <a href="facturas.php" class="btn btn-outline-info">
                        <i class="fas fa-file-invoice me-1"></i>
                        Ver Facturas
                    </a>
                </div>
            </div>

            <!-- Alertas -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <?php foreach ($mesas as $mesa): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card mesa-card mesa-<?php echo $mesa['estado']; ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-table me-2"></i>
                                        Mesa <?php echo $mesa['numero_mesa']; ?>
                                    </h5>
                                    <span class="badge estado-badge bg-<?php 
                                        echo $mesa['estado'] == 'libre' ? 'success' : 
                                             ($mesa['estado'] == 'ocupada' ? 'danger' : 'warning'); 
                                    ?>">
                                        <?php echo strtoupper($mesa['estado']); ?>
                                    </span>
                                </div>
                                
                                <div class="mb-3">
                                    <p class="mb-1">
                                        <strong>Capacidad:</strong> <?php echo $mesa['capacidad']; ?> personas
                                    </p>
                                    <p class="mb-1">
                                        <strong>Ubicación:</strong> <?php echo $mesa['ubicacion'] ?: 'Sin ubicación'; ?>
                                    </p>
                                    
                                    <?php if ($mesa['id_pedido']): ?>
                                        <div class="factura-info <?php echo $mesa['estado_factura'] == 'pagada' ? 'factura-pagada' : 'factura-pendiente'; ?>">
                                            <p class="mb-1">
                                                <strong>Pedido #<?php echo $mesa['id_pedido']; ?></strong>
                                            </p>
                                            <p class="mb-1">
                                                <strong>Total:</strong> $<?php echo number_format($mesa['total'], 2); ?>
                                            </p>
                                            <p class="mb-0">
                                                <strong>Factura:</strong> 
                                                <span class="badge bg-<?php echo $mesa['estado_factura'] == 'pagada' ? 'success' : 'warning'; ?>">
                                                    <?php echo strtoupper($mesa['estado_factura'] ?? 'PENDIENTE'); ?>
                                                </span>
                                            </p>
                                            <?php if ($mesa['estado_factura'] == 'pagada' && $mesa['metodo_pago']): ?>
                                                <p class="mb-0">
                                                    <strong>Método:</strong> 
                                                    <span class="text-capitalize"><?php echo $mesa['metodo_pago']; ?></span>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="btn-group w-100" role="group">
                                    <?php if ($mesa['estado'] == 'libre'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="mesa_id" value="<?php echo $mesa['id_mesa']; ?>">
                                            <input type="hidden" name="accion" value="ocupar">
                                            <button type="submit" class="btn btn-outline-danger btn-action">
                                                <i class="fas fa-user-times me-1"></i>Ocupar
                                            </button>
                                        </form>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="mesa_id" value="<?php echo $mesa['id_mesa']; ?>">
                                            <input type="hidden" name="accion" value="reservar">
                                            <button type="submit" class="btn btn-outline-warning btn-action">
                                                <i class="fas fa-clock me-1"></i>Reservar
                                            </button>
                                        </form>
                                        <a href="nueva_venta.php" class="btn btn-success btn-action">
                                            <i class="fas fa-utensils me-1"></i>Pedir
                                        </a>
                                        
                                    <?php elseif ($mesa['estado'] == 'ocupada'): ?>
                                        <!-- SOLUCIÓN: Mostrar botón de facturar solo si la factura está pendiente o no existe -->
                                        <?php if (!$mesa['estado_factura'] || $mesa['estado_factura'] == 'pendiente'): ?>
                                            <a href="facturas.php?mesa_id=<?php echo $mesa['id_mesa']; ?>" class="btn btn-info btn-action">
                                                <i class="fas fa-file-invoice me-1"></i>Facturar
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-outline-info btn-action" disabled>
                                                <i class="fas fa-check me-1"></i>Pagada
                                            </button>
                                        <?php endif; ?>
                                        
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="mesa_id" value="<?php echo $mesa['id_mesa']; ?>">
                                            <input type="hidden" name="accion" value="liberar">
                                            <button type="submit" class="btn btn-success btn-action">
                                                <i class="fas fa-check me-1"></i>Liberar
                                            </button>
                                        </form>
                                        
                                        <a href="nueva_venta.php" class="btn btn-primary btn-action">
                                            <i class="fas fa-plus me-1"></i>Agregar
                                        </a>
                                        
                                    <?php elseif ($mesa['estado'] == 'reservada'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="mesa_id" value="<?php echo $mesa['id_mesa']; ?>">
                                            <input type="hidden" name="accion" value="liberar">
                                            <button type="submit" class="btn btn-success btn-action">
                                                <i class="fas fa-check me-1"></i>Liberar
                                            </button>
                                        </form>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="mesa_id" value="<?php echo $mesa['id_mesa']; ?>">
                                            <input type="hidden" name="accion" value="ocupar">
                                            <button type="submit" class="btn btn-outline-danger btn-action">
                                                <i class="fas fa-user-times me-1"></i>Ocupar
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($mesas)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-table fa-4x text-muted mb-3"></i>
                    <h4 class="text-muted">No hay mesas configuradas</h4>
                    <p class="text-muted">Contacta al administrador para agregar mesas al sistema.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para liberar mesa con confirmación
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const accion = this.querySelector('input[name="accion"]').value;
                    if (accion === 'liberar') {
                        if (!confirm('¿Estás seguro de que deseas liberar esta mesa?')) {
                            e.preventDefault();
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>