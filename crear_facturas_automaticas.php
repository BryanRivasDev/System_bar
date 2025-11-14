<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    echo "<h3>Pedidos del día sin factura</h3>";

    $fecha_hoy = date('Y-m-d');
    $query = "SELECT p.* 
              FROM pedidos p 
              LEFT JOIN facturas f ON p.id_pedido = f.id_pedido 
              WHERE DATE(p.fecha_pedido) = ? 
              AND f.id_factura IS NULL
              AND p.estado_pedido IN ('entregado', 'listo', 'pagado')";
    $stmt = $db->prepare($query);
    $stmt->execute([$fecha_hoy]);
    $pedidos_sin_facturar = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($pedidos_sin_facturar)) {
        echo "<p>No se encontraron pedidos sin factura para hoy.</p>";
    } else {
        echo "<ul>";
        foreach ($pedidos_sin_facturar as $pedido) {
            echo "<li>Pedido #{$pedido['id_pedido']} - Total: $" . number_format($pedido['total'],2) . " - <a href='modules/ventas/facturas.php?mesa_id={$pedido['id_mesa']}'>Generar factura</a></li>";
        }
        echo "</ul>";
    }

    echo "<p><a href='modules/caja/cierre.php' class='btn btn-secondary'>Ir a Cierre de Caja</a></p>";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>