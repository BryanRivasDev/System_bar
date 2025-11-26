<?php
require_once __DIR__ . '/config/db.php';

echo "<h2>🔧 Actualización de Base de Datos - IVA</h2>";
echo "<style>body{font-family:sans-serif;padding:20px;} .success{color:green;} .error{color:red;}</style>";

try {
    // 1. Create settings table
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(50) NOT NULL UNIQUE,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    echo "<p class='success'>✅ Tabla 'settings' verificada/creada.</p>";

    // 2. Insert default IVA setting if not exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = 'iva_percentage'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('iva_percentage', '0')");
        echo "<p class='success'>✅ Configuración por defecto de IVA (0%) insertada.</p>";
    }

    // 3. Update invoices table
    // Check if columns exist
    $columns = $pdo->query("SHOW COLUMNS FROM invoices")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('subtotal', $columns)) {
        $pdo->exec("ALTER TABLE invoices ADD COLUMN subtotal DECIMAL(10,2) AFTER table_name");
        echo "<p class='success'>✅ Columna 'subtotal' agregada a 'invoices'.</p>";
    }
    
    if (!in_array('iva_amount', $columns)) {
        $pdo->exec("ALTER TABLE invoices ADD COLUMN iva_amount DECIMAL(10,2) AFTER subtotal");
        echo "<p class='success'>✅ Columna 'iva_amount' agregada a 'invoices'.</p>";
    }
    
    if (!in_array('iva_percentage', $columns)) {
        $pdo->exec("ALTER TABLE invoices ADD COLUMN iva_percentage DECIMAL(5,2) DEFAULT 0 AFTER iva_amount");
        echo "<p class='success'>✅ Columna 'iva_percentage' agregada a 'invoices'.</p>";
    }

    echo "<h3 class='success'>🎉 Actualización Completada Exitosamente</h3>";
    echo "<p><a href='settings.php'>Ir a Configuración</a></p>";

} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
