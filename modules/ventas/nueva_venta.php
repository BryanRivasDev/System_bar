<?php
// modules/ventas/nueva_venta.php
require_once __DIR__ . '/../../core.php';
checkAuth();
checkPermission([1, 2, 3]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Venta - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .product-card {
            transition: transform 0.2s;
            cursor: pointer;
            margin-bottom: 1rem;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .product-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .mesa-btn {
            height: 60px;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            border: none;
            padding: 1rem 1.5rem;
            font-weight: 600;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
        }
        
        #pedido-actual {
            min-height: 200px;
        }
        
        .table-sm th, .table-sm td {
            padding: 0.5rem;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <main class="container-fluid" style="margin-top: 80px;">
        <div class="container">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-cash-register me-2"></i>
                    Nueva Venta
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="<?php echo SITE_URL; ?>/modules/ventas/mesas.php" class="btn btn-outline-info me-2">
                        <i class="fas fa-table me-1"></i>
                        Gestión de Mesas
                    </a>
                </div>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <!-- Selección de Mesa -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-table me-2"></i>
                                Seleccionar Mesa
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (mesasDisponibles()): ?>
                                <div class="row" id="mesas-container">
                                    <?php mostrarMesas(); ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning text-center">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    No hay mesas disponibles en este momento
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Productos -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-utensils me-2"></i>
                                Productos Disponibles
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (productosDisponibles()): ?>
                                <div class="row" id="productos-container">
                                    <?php mostrarProductos(); ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning text-center">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    No hay productos disponibles
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card sticky-top" style="top: 100px;">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-list me-2"></i>
                                Pedido Actual
                            </h5>
                        </div>
                        <div class="card-body">
                            <div id="pedido-actual">
                                <p class="text-muted text-center">
                                    <i class="fas fa-shopping-cart fa-2x mb-3 d-block"></i>
                                    Selecciona una mesa y productos para comenzar
                                </p>
                            </div>
                            <div class="mt-3">
                                <button class="btn btn-success w-100 btn-lg" onclick="finalizarPedido(event, this)">
                                    <i class="fas fa-check me-2"></i>
                                    Finalizar Pedido
                                </button>
                                <div class="mt-2">
                                    <!-- Enlaces absolutos usando SITE_URL para evitar 404 desde subdirectorios -->
                                    <a href="<?php echo SITE_URL; ?>/modules/ventas/facturas.php" class="btn btn-outline-info w-100">
                                        <i class="fas fa-file-invoice me-1"></i>
                                        Ver Facturas
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let pedidoActual = [];
        let mesaSeleccionada = null;

        function seleccionarMesa(mesaId, numeroMesa) {
            console.log('Seleccionando mesa:', mesaId, numeroMesa);
            mesaSeleccionada = {id: mesaId, numero: numeroMesa};
            $('.mesa-btn').removeClass('btn-primary').addClass('btn-outline-primary');
            $(`#mesa-${mesaId}`).removeClass('btn-outline-primary').addClass('btn-primary');
            actualizarResumenPedido();
        }

        function agregarProducto(productoId, nombre, precio) {
            console.log('Agregando producto:', productoId, nombre, precio);
            
            if (!mesaSeleccionada) {
                alert('⚠️ Primero selecciona una mesa');
                return;
            }

            const productoExistente = pedidoActual.find(p => p.id === productoId);
            if (productoExistente) {
                productoExistente.cantidad++;
            } else {
                pedidoActual.push({
                    id: productoId,
                    nombre: nombre,
                    precio: precio,
                    cantidad: 1
                });
            }
            actualizarResumenPedido();
        }

        function actualizarResumenPedido() {
            const container = $('#pedido-actual');
            let html = '';

            if (mesaSeleccionada) {
                html += `<div class="alert alert-info">
                            <i class="fas fa-table me-2"></i>
                            <strong>Mesa ${mesaSeleccionada.numero}</strong>
                         </div>`;
            }

            if (pedidoActual.length > 0) {
                let total = 0;
                html += '<div class="table-responsive"><table class="table table-sm table-hover">';
                html += '<thead><tr><th>Producto</th><th>Cantidad</th><th>Subtotal</th><th></th></tr></thead><tbody>';
                
                pedidoActual.forEach(producto => {
                    const subtotal = producto.precio * producto.cantidad;
                    total += subtotal;
                    html += `
                        <tr>
                            <td>${producto.nombre}</td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-secondary" onclick="modificarCantidad(${producto.id}, -1)">-</button>
                                    <button class="btn btn-outline-dark disabled">${producto.cantidad}</button>
                                    <button class="btn btn-outline-secondary" onclick="modificarCantidad(${producto.id}, 1)">+</button>
                                </div>
                            </td>
                            <td>$${subtotal.toFixed(2)}</td>
                            <td><button class="btn btn-sm btn-outline-danger" onclick="eliminarProducto(${producto.id})"><i class="fas fa-times"></i></button></td>
                        </tr>
                    `;
                });
                
                html += `</tbody></table></div>`;
                html += `<div class="border-top pt-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Total:</h5>
                                <h4 class="mb-0 text-success">$${total.toFixed(2)}</h4>
                            </div>
                         </div>`;
            } else {
                html = `<p class="text-muted text-center">
                            <i class="fas fa-shopping-cart fa-2x mb-3 d-block"></i>
                            ${mesaSeleccionada ? 
                                'Agrega productos al pedido' : 
                                'Selecciona una mesa y productos para comenzar'
                            }
                        </p>`;
            }

            container.html(html);
        }

        function modificarCantidad(productoId, cambio) {
            const producto = pedidoActual.find(p => p.id === productoId);
            if (producto) {
                producto.cantidad += cambio;
                if (producto.cantidad <= 0) {
                    eliminarProducto(productoId);
                } else {
                    actualizarResumenPedido();
                }
            }
        }

        function eliminarProducto(productoId) {
            pedidoActual = pedidoActual.filter(p => p.id !== productoId);
            actualizarResumenPedido();
        }

        function finalizarPedido(e, btn) {
            if (!mesaSeleccionada) {
                alert('❌ Primero selecciona una mesa');
                return;
            }

            if (pedidoActual.length === 0) {
                alert('❌ Agrega al menos un producto al pedido');
                return;
            }

            if (!confirm('¿Estás seguro de que deseas finalizar este pedido?')) {
                return;
            }

            // Mostrar loading
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Procesando...';
            btn.disabled = true;

            // Preparar datos para enviar
            const datos = {
                mesa_id: mesaSeleccionada.id,
                productos: pedidoActual
            };

            console.log('Enviando pedido:', datos);

            fetch('<?php echo SITE_URL; ?>/modules/ventas/procesar_pedido.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(datos)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error en la respuesta del servidor: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Respuesta del servidor:', data);

                if (data.success) {
                    alert('✅ Pedido #' + data.pedido_id + ' creado exitosamente\nTotal: $' + data.total.toFixed(2));
                    location.reload();
                } else {
                    let errorMsg = '❌ Error al crear el pedido';
                    if (data.error) errorMsg += ': ' + data.error;
                    if (data.debug) errorMsg += '\n' + data.debug;
                    alert(errorMsg);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('❌ Error de conexión: ' + error.message);
            })
            .finally(() => {
                // Restaurar botón
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }

        // Inicializar
        $(document).ready(function() {
            console.log('Página cargada correctamente');
            actualizarResumenPedido();
        });
    </script>
</body>
</html>

<?php
function mesasDisponibles() {
    $database = new Database();
    $db = $database->getConnection();
    $query = "SELECT COUNT(*) as total FROM mesas WHERE estado = 'libre'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total'] > 0;
}

function productosDisponibles() {
    $database = new Database();
    $db = $database->getConnection();
    $query = "SELECT COUNT(*) as total FROM productos WHERE estado = 'activo' AND stock_actual > 0";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total'] > 0;
}

function mostrarMesas() {
    $database = new Database();
    $db = $database->getConnection();
    $query = "SELECT * FROM mesas WHERE estado = 'libre' ORDER BY numero_mesa";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<div class='col-6 col-md-4 col-lg-3 mb-3'>
                <button class='btn btn-outline-primary w-100 mesa-btn py-3' 
                        id='mesa-{$row['id_mesa']}' 
                        onclick='seleccionarMesa({$row['id_mesa']}, \"{$row['numero_mesa']}\")'>
                    <i class='fas fa-table me-2'></i>
                    Mesa {$row['numero_mesa']}
                </button>
              </div>";
    }
}

function mostrarProductos() {
    $database = new Database();
    $db = $database->getConnection();
    $query = "SELECT p.*, c.nombre_categoria 
              FROM productos p 
              JOIN categorias c ON p.id_categoria = c.id_categoria 
              WHERE p.estado = 'activo' AND p.stock_actual > 0
              ORDER BY c.nombre_categoria, p.nombre_producto";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $categoria_actual = '';
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($categoria_actual != $row['nombre_categoria']) {
            if ($categoria_actual != '') echo '</div>';
            echo "<div class='col-12 mt-4'>
                    <h5 class='border-bottom pb-2'>
                        <i class='fas fa-tag me-2 text-primary'></i>
                        {$row['nombre_categoria']}
                    </h5>
                  </div>
                  <div class='row'>";
            $categoria_actual = $row['nombre_categoria'];
        }
        
        echo "<div class='col-md-6 col-lg-4 mb-3'>
                <div class='card product-card h-100'>
                    <div class='card-body d-flex flex-column'>
                        <h6 class='card-title'>{$row['nombre_producto']}</h6>
                        <p class='card-text flex-grow-1'><small class='text-muted'>{$row['descripcion']}</small></p>
                        <div class='mt-auto'>
                            <p class='card-text fw-bold text-success mb-1'>$" . number_format($row['precio_venta'], 2) . "</p>
                            <p class='card-text'><small class='text-muted'>Stock: {$row['stock_actual']}</small></p>
                            <button class='btn btn-primary w-100' 
                                    onclick='agregarProducto({$row['id_producto']}, \"{$row['nombre_producto']}\", {$row['precio_venta']})'>
                                <i class='fas fa-plus me-1'></i>
                                Agregar
                            </button>
                        </div>
                    </div>
                </div>
              </div>";
    }
    if ($categoria_actual != '') echo '</div>';
}
?>