<?php
require_once __DIR__ . '/config/db.php';

// Test role update
echo "=== TEST DE ACTUALIZACIÓN DE ROLES ===\n\n";

// Get current roles
echo "Roles actuales:\n";
$roles = $pdo->query('SELECT * FROM roles')->fetchAll();
foreach ($roles as $role) {
    echo "ID: {$role['id']} - Nombre: {$role['name']}\n";
}

echo "\n--- Probando actualización ---\n";

// Try to update role ID 2 (Waiter)
$test_role_id = 2;
$new_name = "Mesero Actualizado " . date('H:i:s');

echo "Intentando actualizar rol ID $test_role_id a '$new_name'\n";

$stmt = $pdo->prepare('UPDATE roles SET name = ? WHERE id = ?');
$result = $stmt->execute([$new_name, $test_role_id]);

if ($result) {
    echo "✅ Actualización ejecutada correctamente\n";
    echo "Filas afectadas: " . $stmt->rowCount() . "\n";
} else {
    echo "❌ Error en la actualización\n";
    print_r($stmt->errorInfo());
}

echo "\nRoles después de la actualización:\n";
$roles = $pdo->query('SELECT * FROM roles')->fetchAll();
foreach ($roles as $role) {
    echo "ID: {$role['id']} - Nombre: {$role['name']}\n";
}
?>
