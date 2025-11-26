<?php
require_once __DIR__ . '/config/db.php';

echo "<h2>🔧 Reparación de Base de Datos</h2>";

try {
    // 1. Check and Add 'subtotal'
    try {
        $pdo->query("SELECT subtotal FROM invoices LIMIT 1");
        echo "<p style='color:green'>✅ Columna 'subtotal' ya existe.</p>";
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE invoices ADD COLUMN subtotal DECIMAL(10,2) AFTER table_name");
        echo "<p style='color:blue'>🛠️ Columna 'subtotal' agregada.</p>";
    }

    // 2. Check and Add 'iva_amount'
    try {
        $pdo->query("SELECT iva_amount FROM invoices LIMIT 1");
        echo "<p style='color:green'>✅ Columna 'iva_amount' ya existe.</p>";
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE invoices ADD COLUMN iva_amount DECIMAL(10,2) AFTER subtotal");
        echo "<p style='color:blue'>🛠️ Columna 'iva_amount' agregada.</p>";
    }

    // 3. Check and Add 'iva_percentage'
    try {
        $pdo->query("SELECT iva_percentage FROM invoices LIMIT 1");
        echo "<p style='color:green'>✅ Columna 'iva_percentage' ya existe.</p>";
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE invoices ADD COLUMN iva_percentage DECIMAL(5,2) DEFAULT 0 AFTER iva_amount");
        echo "<p style='color:blue'>🛠️ Columna 'iva_percentage' agregada.</p>";
    }

    echo "<h3 style='color:green'>✅ Reparación Completada. Intente procesar el pago nuevamente.</h3>";

} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error General: " . $e->getMessage() . "</p>";
}
?>
