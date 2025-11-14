<?php
// modules/ventas/factura.php
require_once '../../core.php';
checkAuth();
checkPermission([1, 2, 3]);

$error = '';
$factura = null;
$detalles_factura = [];

// Obtener ID de la mesa desde la URL
$mesa_id = isset($_GET['mesa_id']) ? intval($_GET['mesa_id']) : 0;

if ($mesa_id > 0) {
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        // 1. Obtener información de la mesa
        $query = "SELECT m.*, p.id_pedido, p.total, p.fecha_pedido, p.estado_pedido
                  FROM mesas m 
                  LEFT JOIN pedidos p ON m.id_mesa = p.id_mesa 
                  WHERE m.id_mesa = ? 
                  AND p.estado_pedido IN ('pendiente', 'en_preparacion', 'listo')
                  ORDER BY p.fecha_pedido DESC 
                  LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute([$mesa_id]);
        $mesa = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$mesa) {
            $error = "No se encontró un pedido activo para la mesa seleccionada";
        } else {
            // 2. Obtener detalles del pedido
            $query = "SELECT dp.*, p.nombre_producto, p.descripcion
                      FROM detalle_pedidos dp
                      JOIN productos p ON dp.id_producto = p.id_producto
                      WHERE dp.id_pedido = ?
                      ORDER BY dp.id_detalle";
            $stmt = $db->prepare($query);
            $stmt->execute([$mesa['id_pedido']]);
            $detalles_factura = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 3. Buscar factura existente o crear una nueva
            $query = "SELECT f.* 
                      FROM facturas f 
                      WHERE f.id_pedido = ? 
                      LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->execute([$mesa['id_pedido']]);
            $factura = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$factura) {
                // No crear factura automáticamente desde aquí. Mostrar opción para generar manualmente.
                $mostrar_crear_factura = true;
            }
        }
        
    } catch (Exception $e) {
        $error = "Error al generar la factura: " . $e->getMessage();
    }
}

// Procesar pago de la factura
if ($_POST && isset($_POST['factura_id']) && $factura) {
    $metodo_pago = $_POST['metodo_pago'];
    $observaciones = trim($_POST['observaciones']);
    
    try {
        $db->beginTransaction();
        
        // Actualizar factura como pagada
        $query = "UPDATE facturas 
                  SET estado = 'pagada', 
                      metodo_pago = ?,
                      fecha_factura = NOW(),
                      observaciones = ?
                  WHERE id_factura = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$metodo_pago, $observaciones, $_POST['factura_id']]);
        
        // Liberar la mesa
        $query = "UPDATE mesas SET estado = 'libre' WHERE id_mesa = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$mesa_id]);
        
        // Marcar pedido como entregado
        $query = "UPDATE pedidos SET estado_pedido = 'entregado' WHERE id_pedido = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$mesa['id_pedido']]);
        
        $db->commit();
        
        $success = "Factura pagada exitosamente. Mesa liberada.";
        // Recargar los datos
        header("Location: factura.php?mesa_id=$mesa_id&success=1");
        exit;
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Error al procesar el pago: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .invoice-header {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .invoice-body {
            padding: 2rem;
        }
        
        .invoice-footer {
            background: #f8f9fa;
            padding: 1.5rem 2rem;
            border-top: 1px solid #dee2e6;
        }
        
        .table-invoice th {
            border-top: none;
            background: #f8f9fa;
        }
        
        .total-section {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 2rem;
        }
        
        .payment-methods .btn {
            margin: 0.25rem;
        }
        
        .invoice-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .status-badge {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <main class="container-fluid" style="margin-top: 80px;">
        <div class="container">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-file-invoice me-2"></i>
                    Factura
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="nueva_venta.php" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-arrow-left me-1"></i> Volver a Ventas
                    </a>
                    <?php if ($factura): ?>
                    <button class="btn btn-success" onclick="window.print()">
                        <i class="fas fa-print me-1"></i> Imprimir
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Alertas -->
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    Factura pagada exitosamente. Mesa liberada.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!$mesa_id): ?>
                <div class="alert alert-warning text-center">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Selecciona una mesa para generar la factura
                </div>
                
                <!-- Selector de Mesas Ocupadas -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-table me-2"></i>
                            Mesas con Pedidos Activos
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php
                            $database = new Database();
                            $db = $database->getConnection();
                            $query = "SELECT m.id_mesa, m.numero_mesa, p.id_pedido, p.total, p.estado_pedido
                                      FROM mesas m 
                                      JOIN pedidos p ON m.id_mesa = p.id_mesa 
                                      WHERE m.estado = 'ocupada' 
                                      AND p.estado_pedido IN ('pendiente', 'en_preparacion', 'listo')
                                      ORDER BY m.numero_mesa";
                            $stmt = $db->prepare($query);
                            $stmt->execute();
                            $mesas_ocupadas = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($mesas_ocupadas as $mesa): 
                            ?>
                                <div class="col-md-3 mb-3">
                                    <a href="factura.php?mesa_id=<?php echo $mesa['id_mesa']; ?>" 
                                       class="btn btn-outline-primary w-100 py-3">
                                        <i class="fas fa-table me-2"></i>
                                        Mesa <?php echo $mesa['numero_mesa']; ?>
                                        <br>
                                        <small class="text-muted">
                                            $<?php echo number_format($mesa['total'], 2); ?>
                                        </small>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if (empty($mesas_ocupadas)): ?>
                                <div class="col-12 text-center py-4">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No hay mesas con pedidos activos</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
            <?php elseif ($mesa): ?>
                <?php if ($factura): ?>
                
                <!-- Factura -->
                <div class="invoice-container">
                    <!-- Encabezado -->
                    <div class="invoice-header">
                        <div class="row align-items-center">
                            <div class="col-md-6 text-start">
                                <h2 class="mb-1"><?php echo SITE_NAME; ?></h2>
                                <p class="mb-0 opacity-75">Sistema de Gestión de Bar</p>
                            </div>
                            <div class="col-md-6 text-end">
                                <div class="invoice-number"><?php echo $factura['numero_factura']; ?></div>
                                <p class="mb-0">
                                    <?php echo date('d/m/Y H:i', strtotime($factura['fecha_factura'])); ?>
                                </p>
                                <span class="badge status-badge bg-<?php echo $factura['estado'] == 'pagada' ? 'success' : 'warning'; ?>">
                                    <?php echo strtoupper($factura['estado']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Cuerpo -->
                    <div class="invoice-body">
                        <!-- Información del Cliente -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6 class="text-muted">INFORMACIÓN DE LA MESA</h6>
                                <p class="mb-1">
                                    <strong>Mesa:</strong> <?php echo $mesa['numero_mesa']; ?>
                                </p>
                                <p class="mb-1">
                                    <strong>Ubicación:</strong> <?php echo $mesa['ubicacion']; ?>
                                </p>
                                <p class="mb-0">
                                    <strong>Capacidad:</strong> <?php echo $mesa['capacidad']; ?> personas
                                </p>
                            </div>
                            <div class="col-md-6 text-end">
                                <h6 class="text-muted">ATENDIDO POR</h6>
                                <p class="mb-0">
                                    <strong><?php echo $_SESSION['nombre']; ?></strong>
                                </p>
                                <p class="mb-0 text-muted">
                                    <?php echo $_SESSION['rol_nombre']; ?>
                                </p>
                            </div>
                        </div>
                        
                        <!-- Detalles de la Factura -->
                        <div class="table-responsive">
                            <table class="table table-invoice">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th class="text-center">Cantidad</th>
                                        <th class="text-end">Precio Unitario</th>
                                        <th class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($detalles_factura as $detalle): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo $detalle['nombre_producto']; ?></strong>
                                            <?php if ($detalle['descripcion']): ?>
                                            <br><small class="text-muted"><?php echo $detalle['descripcion']; ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center"><?php echo $detalle['cantidad']; ?></td>
                                        <td class="text-end">$<?php echo number_format($detalle['precio_unitario'], 2); ?></td>
                                        <td class="text-end">$<?php echo number_format($detalle['subtotal'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Totales -->
                        <div class="row justify-content-end">
                            <div class="col-md-6">
                                <div class="total-section">
                                    <div class="row">
                                        <div class="col-6">
                                            <strong>Subtotal:</strong>
                                        </div>
                                        <div class="col-6 text-end">
                                            $<?php echo number_format($factura['subtotal'], 2); ?>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-6">
                                            <strong>IVA (19%):</strong>
                                        </div>
                                        <div class="col-6 text-end">
                                            $<?php echo number_format($factura['iva'], 2); ?>
                                        </div>
                                    </div>
                                    <div class="row mt-2 pt-2 border-top">
                                        <div class="col-6">
                                            <h5 class="mb-0">TOTAL:</h5>
                                        </div>
                                        <div class="col-6 text-end">
                                            <h4 class="mb-0">$<?php echo number_format($factura['total'], 2); ?></h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pie de página -->
                    <div class="invoice-footer">
                        <?php if ($factura['estado'] == 'pendiente'): ?>
                        <form method="POST" id="formPago">
                            <input type="hidden" name="factura_id" value="<?php echo $factura['id_factura']; ?>">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="mb-3">Método de Pago</h6>
                                    <div class="payment-methods">
                                        <div class="btn-group w-100" role="group">
                                            <input type="radio" class="btn-check" name="metodo_pago" id="efectivo" value="efectivo" checked>
                                            <label class="btn btn-outline-success" for="efectivo">
                                                <i class="fas fa-money-bill-wave me-2"></i>Efectivo
                                            </label>
                                            
                                            <input type="radio" class="btn-check" name="metodo_pago" id="tarjeta" value="tarjeta">
                                            <label class="btn btn-outline-primary" for="tarjeta">
                                                <i class="fas fa-credit-card me-2"></i>Tarjeta
                                            </label>
                                            
                                            <input type="radio" class="btn-check" name="metodo_pago" id="transferencia" value="transferencia">
                                            <label class="btn btn-outline-info" for="transferencia">
                                                <i class="fas fa-university me-2"></i>Transferencia
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="observaciones" class="form-label">Observaciones</label>
                                        <textarea class="form-control" id="observaciones" name="observaciones" rows="2" placeholder="Observaciones del pago..."></textarea>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-success btn-lg w-100">
                                        <i class="fas fa-check-circle me-2"></i>
                                        Procesar Pago
                                    </button>
                                </div>
                            </div>
                        </form>
                        <?php else: ?>
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="mb-2">Información del Pago</h6>
                                <p class="mb-1">
                                    <strong>Método:</strong> 
                                    <span class="text-capitalize"><?php echo $factura['metodo_pago']; ?></span>
                                </p>
                                <p class="mb-0">
                                    <strong>Estado:</strong> 
                                    <span class="badge bg-success">PAGADA</span>
                                </p>
                            </div>
                            <div class="col-md-6 text-end">
                                <p class="text-muted mb-0">
                                    Factura procesada el:<br>
                                    <strong><?php echo date('d/m/Y H:i', strtotime($factura['fecha_factura'])); ?></strong>
                                </p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <!-- Vista previa de factura (NO guardada) y opción para generarla manualmente -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Vista previa de factura - Mesa <?php echo $mesa['numero_mesa']; ?></h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">La factura aún no está guardada en la base de datos. Presiona "Generar factura" para guardarla como <strong>pendiente</strong>.</p>
                        <div class="table-responsive mb-3">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th class="text-center">Cantidad</th>
                                        <th class="text-end">Precio</th>
                                        <th class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($detalles_factura as $detalle): ?>
                                    <tr>
                                        <td><?php echo $detalle['nombre_producto']; ?></td>
                                        <td class="text-center"><?php echo $detalle['cantidad']; ?></td>
                                        <td class="text-end">$<?php echo number_format($detalle['precio_unitario'],2); ?></td>
                                        <td class="text-end">$<?php echo number_format($detalle['subtotal'],2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="row justify-content-end mb-3">
                            <div class="col-md-4">
                                <div class="border p-3 rounded">
                                    <div class="d-flex justify-content-between"><strong>Subtotal</strong><span>$<?php echo number_format($mesa['total']/1.19,2); ?></span></div>
                                    <div class="d-flex justify-content-between"><strong>IVA (19%)</strong><span>$<?php echo number_format($mesa['total'] - ($mesa['total']/1.19),2); ?></span></div>
                                    <hr>
                                    <div class="d-flex justify-content-between"><strong>TOTAL</strong><span>$<?php echo number_format($mesa['total'],2); ?></span></div>
                                </div>
                            </div>
                        </div>

                        <form method="POST" action="../../modules/ventas/facturas.php">
                            <input type="hidden" name="crear_factura" value="1">
                            <input type="hidden" name="id_pedido" value="<?php echo $mesa['id_pedido']; ?>">
                            <input type="hidden" name="mesa_id" value="<?php echo $mesa_id; ?>">
                            <button type="submit" class="btn btn-primary">Generar factura (guardar como pendiente)</button>
                            <a href="factura.php" class="btn btn-outline-secondary ms-2">Cancelar</a>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validación del formulario de pago
        document.addEventListener('DOMContentLoaded', function() {
            const formPago = document.getElementById('formPago');
            if (formPago) {
                formPago.addEventListener('submit', function(e) {
                    const total = <?php echo $factura ? $factura['total'] : 0; ?>;
                    
                    if (!confirm(`¿Confirmar pago de $${total.toFixed(2)}?`)) {
                        e.preventDefault();
                        return false;
                    }
                });
            }
        });
        
        // Auto-seleccionar método de pago al hacer clic
        document.querySelectorAll('.payment-methods .btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.payment-methods .btn').forEach(b => {
                    b.classList.remove('active');
                });
                this.classList.add('active');
            });
        });
    </script>
</body>
</html>