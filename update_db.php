<?php
require_once __DIR__ . '/config/db.php';

try {
    echo "Actualizando base de datos...\n";
    
    // Add cash_register_id to payments table
    $pdo->exec("ALTER TABLE payments ADD COLUMN cash_register_id INT NULL");
    
    // Add foreign key
    $pdo->exec("ALTER TABLE payments ADD CONSTRAINT fk_payments_cash_register FOREIGN KEY (cash_register_id) REFERENCES cash_register(id)");
    
    echo "✅ Columna cash_register_id agregada a la tabla payments.\n";
    
} catch (PDOException $e) {
    echo "⚠️ Nota: " . $e->getMessage() . "\n";
}
?>
