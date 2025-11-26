<?php
require_once __DIR__ . '/config/db.php';
$stmt = $pdo->query('SELECT * FROM payments WHERE order_id = 9');
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Payments for order 9:\n";
print_r($payments);
