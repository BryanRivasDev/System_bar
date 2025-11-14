<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // 1. Insertar categorías si no existen
    $query = "INSERT IGNORE INTO categorias (nombre_categoria, descripcion, tipo) VALUES 
              ('Cervezas', 'Cervezas nacionales e importadas', 'bebida'),
              ('Licores', 'Whiskys, vodkas, rones', 'bebida'),
              ('Cocteles', 'Bebidas mezcladas', 'bebida'),
              ('Vinos', 'Vinos tintos y blancos', 'bebida'),
              ('Entradas', 'Aperitivos y entradas', 'comida'),
              ('Platos Fuertes', 'Platos principales', 'comida'),
              ('Postres', 'Postres y dulces', 'comida')";
    $db->exec($query);
    echo "✅ Categorías insertadas<br>";

    // 2. Insertar mesas
    $query = "INSERT IGNORE INTO mesas (numero_mesa, capacidad, estado, ubicacion) VALUES 
              ('1', 4, 'libre', 'Interior'),
              ('2', 4, 'libre', 'Interior'),
              ('3', 6, 'libre', 'Terraza'),
              ('4', 2, 'libre', 'Barra'),
              ('5', 8, 'libre', 'Sala Privada'),
              ('6', 4, 'libre', 'Terraza')";
    $db->exec($query);
    echo "✅ Mesas insertadas<br>";

    // 3. Insertar productos
    $query = "INSERT IGNORE INTO productos (id_categoria, codigo_producto, nombre_producto, descripcion, precio_venta, costo, stock_minimo, stock_actual, unidad_medida, estado) VALUES 
              (1, 'CER001', 'Cerveza Corona', 'Cerveza lager mexicana 355ml', 5.00, 2.50, 10, 50, 'unidad', 'activo'),
              (1, 'CER002', 'Cerveza Heineken', 'Cerveza lager holandesa 330ml', 6.00, 3.00, 10, 40, 'unidad', 'activo'),
              (1, 'CER003', 'Cerveza Club Colombia', 'Cerveza lager colombiana 330ml', 4.50, 2.00, 10, 60, 'unidad', 'activo'),
              (2, 'LIC001', 'Whisky Jack Daniels', 'Whisky Tennessee 45ml', 12.00, 6.00, 5, 20, 'unidad', 'activo'),
              (2, 'LIC002', 'Ron Viejo de Caldas', 'Ron colombiano 45ml', 8.00, 3.50, 5, 30, 'unidad', 'activo'),
              (3, 'COC001', 'Mojito', 'Ron, hierbabuena, limón, soda', 10.00, 3.00, 5, 0, 'unidad', 'activo'),
              (3, 'COC002', 'Margarita', 'Tequila, triple sec, limón', 12.00, 4.00, 5, 0, 'unidad', 'activo'),
              (4, 'VIN001', 'Vino Tinto Casa Grande', 'Vino tinto reserva copa', 8.00, 3.00, 5, 25, 'unidad', 'activo'),
              (5, 'ENT001', 'Nachos Supreme', 'Nachos con queso, guacamole y crema', 8.00, 3.00, 5, 25, 'porción', 'activo'),
              (5, 'ENT002', 'Alitas BBQ', 'Alitas de pollo en salsa barbacoa', 12.00, 5.00, 5, 30, 'porción', 'activo'),
              (6, 'PLA001', 'Hamburguesa Clásica', 'Carne, queso, lechuga, tomate', 15.00, 6.00, 5, 30, 'unidad', 'activo'),
              (6, 'PLA002', 'Pizza Margherita', 'Pizza con tomate, mozzarella y albahaca', 18.00, 7.00, 5, 20, 'unidad', 'activo'),
              (7, 'POS001', 'Tiramisú', 'Postre italiano con café y cacao', 7.00, 2.50, 5, 15, 'porción', 'activo'),
              (7, 'POS002', 'Brownie con Helado', 'Brownie de chocolate con helado de vainilla', 9.00, 3.00, 5, 20, 'porción', 'activo')";
    $db->exec($query);
    echo "✅ Productos insertados<br>";

    echo "<h3>🎉 ¡Datos de prueba insertados correctamente!</h3>";
    echo "<p><a href='modules/ventas/nueva_venta.php' class='btn btn-success'>Ir a Nueva Venta</a></p>";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
    echo "<br>Verifica que las tablas existan en la base de datos.";
}
?>