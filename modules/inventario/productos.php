<?php
require_once '../../config/config.php';
checkAuth();
checkPermission([1, 2]); // Admin y Gerente
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos - <?php echo SITE_NAME; ?></title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    <?php include '../../includes/sidebar.php'; ?>

    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Gestión de Productos</h1>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalProducto">Nuevo Producto</button>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>Categoría</th>
                        <th>Precio Venta</th>
                        <th>Stock</th>
                        <th>Stock Mínimo</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php mostrarProductos(); ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Modal Producto -->
    <div class="modal fade" id="modalProducto" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nuevo Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="procesar_producto.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Código</label>
                            <input type="text" class="form-control" name="codigo_producto" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nombre</label>
                            <input type="text" class="form-control" name="nombre_producto" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Categoría</label>
                            <select class="form-control" name="id_categoria" required>
                                <?php mostrarCategorias(); ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Precio Venta</label>
                                    <input type="number" step="0.01" class="form-control" name="precio_venta" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Costo</label>
                                    <input type="number" step="0.01" class="form-control" name="costo" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Stock Actual</label>
                                    <input type="number" class="form-control" name="stock_actual" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Stock Mínimo</label>
                                    <input type="number" class="form-control" name="stock_minimo" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descripción</label>
                            <textarea class="form-control" name="descripcion"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../../assets/js/jquery.min.js"></script>
    <script src="../../assets/js/bootstrap.min.js"></script>
</body>
</html>

<?php
function mostrarProductos() {
    $database = new Database();
    $db = $database->getConnection();
    $query = "SELECT p.*, c.nombre_categoria 
              FROM productos p 
              JOIN categorias c ON p.id_categoria = c.id_categoria 
              ORDER BY p.nombre_producto";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $estado_badge = $row['estado'] == 'activo' ? 'badge bg-success' : 'badge bg-danger';
        $stock_class = $row['stock_actual'] <= $row['stock_minimo'] ? 'text-danger fw-bold' : '';
        
        echo "<tr>
                <td>{$row['codigo_producto']}</td>
                <td>{$row['nombre_producto']}</td>
                <td>{$row['nombre_categoria']}</td>
                <td>$" . number_format($row['precio_venta'], 2) . "</td>
                <td class='{$stock_class}'>{$row['stock_actual']}</td>
                <td>{$row['stock_minimo']}</td>
                <td><span class='{$estado_badge}'>{$row['estado']}</span></td>
                <td>
                    <button class='btn btn-sm btn-warning'>Editar</button>
                    <button class='btn btn-sm btn-danger'>Eliminar</button>
                </td>
              </tr>";
    }
}

function mostrarCategorias() {
    $database = new Database();
    $db = $database->getConnection();
    $query = "SELECT * FROM categorias WHERE estado = 'activo' ORDER BY nombre_categoria";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<option value='{$row['id_categoria']}'>{$row['nombre_categoria']}</option>";
    }
}
?>