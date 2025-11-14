<?php
// modules/ventas/procesar_pedido.php - VERSIÓN CORREGIDA
require_once '../../core.php';
checkAuth();
checkPermission([1, 2, 3]);

header('Content-Type: application/json');

// Leer datos JSON del request
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['mesa_id']) || !isset($input['productos'])) {
    echo json_encode([
        'success' => false, 
        'error' => 'Datos incompletos',
        'debug' => 'Faltan mesa_id o productos en los datos'
    ]);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $db->beginTransaction();
    
    // Nota: ya no se crea factura automáticamente aquí. La factura se genera manualmente desde la interfaz de facturas.
    
    // 2. Crear pedido
    $query = "INSERT INTO pedidos (id_mesa, id_usuario, estado_pedido, total) 
              VALUES (?, ?, 'pendiente', 0)";
    $stmt = $db->prepare($query);
    $stmt->execute([$input['mesa_id'], $_SESSION['usuario_id']]);
    $id_pedido = $db->lastInsertId();
    
    // 3. Actualizar estado de la mesa
    $query = "UPDATE mesas SET estado = 'ocupada' WHERE id_mesa = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$input['mesa_id']]);
    
    $total_pedido = 0;
    
    // 4. Agregar productos al pedido y actualizar inventario
    foreach ($input['productos'] as $producto) {
        $subtotal = $producto['precio'] * $producto['cantidad'];
        $total_pedido += $subtotal;
        
        // Insertar detalle del pedido
        $query = "INSERT INTO detalle_pedidos (id_pedido, id_producto, cantidad, precio_unitario, subtotal) 
                  VALUES (?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            $id_pedido, 
            $producto['id'], 
            $producto['cantidad'], 
            $producto['precio'], 
            $subtotal
        ]);
        
        // Obtener stock actual antes de la actualización
        $query = "SELECT stock_actual FROM productos WHERE id_producto = ?";
        $stmt_stock = $db->prepare($query);
        $stmt_stock->execute([$producto['id']]);
        $stock_anterior = $stmt_stock->fetch(PDO::FETCH_ASSOC)['stock_actual'];
        $stock_actual = $stock_anterior - $producto['cantidad'];
        
        // Actualizar inventario del producto
        $query = "UPDATE productos SET stock_actual = ? WHERE id_producto = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$stock_actual, $producto['id']]);
        
        // Registrar movimiento de inventario
        $query = "INSERT INTO inventario (id_producto, tipo_movimiento, cantidad, stock_anterior, stock_actual, motivo, id_usuario) 
                  VALUES (?, 'salida', ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            $producto['id'],
            $producto['cantidad'],
            $stock_anterior,
            $stock_actual,
            "Venta - Pedido #" . $id_pedido,
            $_SESSION['usuario_id']
        ]);
    }
    
    // 5. Actualizar total del pedido
    $query = "UPDATE pedidos SET total = ? WHERE id_pedido = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$total_pedido, $id_pedido]);
    
    // Commit después de crear pedido y detalles (sin crear factura)
    $db->commit();

    echo json_encode([
        'success' => true,
        'pedido_id' => $id_pedido,
        'total' => $total_pedido,
        'message' => 'Pedido creado exitosamente (factura no creada automáticamente)'
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    error_log("Error en procesar_pedido: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage(),
        'debug' => 'Error en la transacción de base de datos'
    ]);
}
?>