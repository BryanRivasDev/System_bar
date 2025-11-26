<?php
require_once __DIR__ . '/config/db.php';
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($username && $password) {
        $stmt = $pdo->prepare('SELECT id, password, name, role_id, is_super_admin FROM users WHERE username = :username AND status = "active"');
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $username;
            $_SESSION['name']      = $user['name'];
            $_SESSION['role_id']   = $user['role_id'];
            $_SESSION['is_super_admin'] = $user['is_super_admin'];
            
            // Redirect based on role
            if ($user['role_id'] == 4) {
                // Kitchen user goes directly to kitchen
                header('Location: kitchen.php');
            } elseif ($user['role_id'] == 3) {
                // Cashier goes to cashier dashboard
                header('Location: cashier_dashboard.php');
            } elseif ($user['role_id'] == 2) {
                // Waiter goes to tables
                header('Location: tables.php');
            } else {
                // Other users go to dashboard
                header('Location: dashboard.php');
            }
            exit();
        } else {
            $error = 'Usuario o contraseña incorrectos.';
        }
    } else {
        $error = 'Por favor ingrese usuario y contraseña.';
    }
}
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="login-wrapper">
    <div class="login-container">
        <div class="login-header">
            <h1>🍹 Bar System</h1>
            <p>Ingrese sus credenciales para continuar</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Usuario</label>
                <input type="text" id="username" name="username" class="form-input" placeholder="Ingrese su usuario" required>
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" class="form-input" placeholder="Ingrese su contraseña" required>
            </div>
            
            <button type="submit" class="btn-login">Iniciar Sesión</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
