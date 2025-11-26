<?php
require_once __DIR__ . '/config/db.php';
$stmt = $pdo->query('SELECT * FROM users WHERE id = 9');
$user = $stmt->fetch(PDO::FETCH_ASSOC);
echo "User 9:\n";
print_r($user);
