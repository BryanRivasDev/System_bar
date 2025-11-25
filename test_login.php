<?php
// test_login.php - Script de prueba para diagnosticar el problema de login
require_once __DIR__ . '/config/db.php';

echo "<h2>🔍 Diagnóstico del Sistema de Login</h2>";
echo "<hr>";

// Test 1: Verificar conexión a la base de datos
echo "<h3>1. Conexión a la base de datos</h3>";
try {
    $stmt = $pdo->query("SELECT DATABASE()");
    $db = $stmt->fetchColumn();
    echo "<p style='color:green;'>✅ Conectado a la base de datos: <strong>$db</strong></p>";
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Error de conexión: " . $e->getMessage() . "</p>";
    exit;
}

// Test 2: Verificar si existe la tabla users
echo "<h3>2. Verificar tabla 'users'</h3>";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color:green;'>✅ La tabla 'users' existe</p>";
    } else {
        echo "<p style='color:red;'>❌ La tabla 'users' NO existe. Debes importar database.sql</p>";
        exit;
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Error: " . $e->getMessage() . "</p>";
    exit;
}

// Test 3: Verificar usuarios en la base de datos
echo "<h3>3. Usuarios en la base de datos</h3>";
try {
    $stmt = $pdo->query("SELECT id, name, username, email, status FROM users");
    $users = $stmt->fetchAll();
    
    if (count($users) > 0) {
        echo "<table border='1' cellpadding='10' style='border-collapse:collapse;'>";
        echo "<tr><th>ID</th><th>Nombre</th><th>Usuario</th><th>Email</th><th>Estado</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>{$user['id']}</td>";
            echo "<td>{$user['name']}</td>";
            echo "<td><strong>{$user['username']}</strong></td>";
            echo "<td>{$user['email']}</td>";
            echo "<td>{$user['status']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:red;'>❌ No hay usuarios en la base de datos</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Error: " . $e->getMessage() . "</p>";
}

// Test 4: Probar login con credenciales
echo "<h3>4. Prueba de autenticación</h3>";
$test_username = 'admin';
$test_password = 'admin123';

try {
    $stmt = $pdo->prepare('SELECT id, password, name, role_id FROM users WHERE username = :username AND status = "active"');
    $stmt->execute(['username' => $test_username]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "<p style='color:green;'>✅ Usuario '$test_username' encontrado en la base de datos</p>";
        echo "<p><strong>Hash almacenado:</strong><br><code style='background:#f0f0f0;padding:5px;'>{$user['password']}</code></p>";
        
        // Verificar contraseña
        if (password_verify($test_password, $user['password'])) {
            echo "<p style='color:green;'>✅ ¡La contraseña '$test_password' es CORRECTA!</p>";
            echo "<p style='color:green;'><strong>El login debería funcionar con:</strong></p>";
            echo "<ul>";
            echo "<li>Usuario: <strong>$test_username</strong></li>";
            echo "<li>Contraseña: <strong>$test_password</strong></li>";
            echo "</ul>";
        } else {
            echo "<p style='color:red;'>❌ La contraseña '$test_password' NO coincide con el hash</p>";
            
            // Generar nuevo hash
            $new_hash = password_hash($test_password, PASSWORD_DEFAULT);
            echo "<p><strong>Nuevo hash generado para '$test_password':</strong></p>";
            echo "<code style='background:#f0f0f0;padding:10px;display:block;'>$new_hash</code>";
            echo "<p>Ejecuta este SQL en phpMyAdmin:</p>";
            echo "<code style='background:#f0f0f0;padding:10px;display:block;'>";
            echo "UPDATE users SET password = '$new_hash' WHERE username = '$test_username';";
            echo "</code>";
        }
    } else {
        echo "<p style='color:red;'>❌ Usuario '$test_username' NO encontrado o está inactivo</p>";
        
        // Generar SQL para insertar usuario
        $new_hash = password_hash($test_password, PASSWORD_DEFAULT);
        echo "<p>Ejecuta este SQL en phpMyAdmin para crear el usuario:</p>";
        echo "<code style='background:#f0f0f0;padding:10px;display:block;'>";
        echo "INSERT INTO users (name, email, username, password, role_id, status) VALUES ";
        echo "('Admin User', 'admin@example.com', '$test_username', '$new_hash', 1, 'active');";
        echo "</code>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='index.php'>← Volver al login</a></p>";
?>
