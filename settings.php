<?php
require_once __DIR__ . '/config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Only Admin (1) or Superadmin (5) access
if ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 5) {
    header('Location: dashboard.php');
    exit();
}

// Ensure settings table exists (Self-healing)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(50) NOT NULL UNIQUE,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // Ensure default IVA setting
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = 'iva_percentage'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('iva_percentage', '0')");
    }
} catch (Exception $e) {
    // Silent fail or log if needed
}

$success_msg = '';
$error_msg = '';

// Handle Backup
if (isset($_POST['backup'])) {
    // Security check: Only Superadmin (Role 5) can backup
    if ($_SESSION['role_id'] != 5) {
        $error_msg = 'Acceso denegado. Solo el Superadmin puede realizar respaldos.';
    } else {
        $tables = [];
    $stmt = $pdo->query('SHOW TABLES');
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    $sqlScript = "-- Backup de Base de Datos: bar_system\n";
    $sqlScript .= "-- Fecha: " . date('Y-m-d H:i:s') . "\n";
    $sqlScript .= "-- Generado por Bar System\n\n";
    $sqlScript .= "SET FOREIGN_KEY_CHECKS = 0;\n";
    $sqlScript .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    $sqlScript .= "SET time_zone = \"+00:00\";\n\n";
    
    foreach ($tables as $table) {
        // Add DROP TABLE IF EXISTS
        $sqlScript .= "-- --------------------------------------------------------\n";
        $sqlScript .= "-- Estructura de tabla para la tabla `$table`\n";
        $sqlScript .= "-- --------------------------------------------------------\n\n";
        $sqlScript .= "DROP TABLE IF EXISTS `$table`;\n";
        
        // Get CREATE TABLE statement
        $stmt = $pdo->query('SHOW CREATE TABLE `' . $table . '`');
        $row = $stmt->fetch(PDO::FETCH_NUM);
        $createTable = $row[1];
        
        // Replace CREATE TABLE with CREATE TABLE IF NOT EXISTS
        $createTable = str_replace('CREATE TABLE', 'CREATE TABLE IF NOT EXISTS', $createTable);
        $sqlScript .= $createTable . ";\n\n";

        // Get table data
        $stmt = $pdo->query('SELECT * FROM `' . $table . '`');
        $columnCount = $stmt->columnCount();
        $rowCount = 0;

        if ($columnCount > 0) {
            $sqlScript .= "-- Volcado de datos para la tabla `$table`\n\n";
            
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $sqlScript .= "INSERT INTO `$table` VALUES (";
                for ($j = 0; $j < $columnCount; $j++) {
                    if (isset($row[$j])) {
                        $sqlScript .= '"' . addslashes($row[$j]) . '"';
                    } else {
                        $sqlScript .= 'NULL';
                    }
                    if ($j < ($columnCount - 1)) {
                        $sqlScript .= ', ';
                    }
                }
                $sqlScript .= ");\n";
                $rowCount++;
            }
            $sqlScript .= "\n";
        }
    }
    
    $sqlScript .= "SET FOREIGN_KEY_CHECKS = 1;\n";

    if (!empty($sqlScript)) {
        // Save the SQL script to a backup file
        $backup_file_name = 'backup_bar_system_' . date('Y-m-d_H-i-s') . '.sql';
        
        header('Content-Type: application/octet-stream');
        header("Content-Transfer-Encoding: Binary");
        header("Content-disposition: attachment; filename=\"" . $backup_file_name . "\"");
        echo $sqlScript;
        exit;
    }
}
}

// Handle Reset
if (isset($_POST['reset_db'])) {
    // Security check: Only Superadmin (Role 5) can reset DB
    if ($_SESSION['role_id'] != 5) {
        $error_msg = 'Acceso denegado. Solo el Superadmin puede restablecer el sistema.';
    } else {
        $super_admin_username = $_POST['super_admin_username'];
    $super_admin_password = $_POST['super_admin_password'];
    
    // Verify Super Admin credentials
    $stmt = $pdo->prepare('SELECT id, password FROM users WHERE username = ? AND role_id = 1 AND is_super_admin = 1');
    $stmt->execute([$super_admin_username]);
    $super_admin = $stmt->fetch();

    if ($super_admin && password_verify($super_admin_password, $super_admin['password'])) {
        try {
            // Disable foreign key checks
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

            // Truncate transactional tables (TRUNCATE auto-commits in MySQL)
            $pdo->exec('TRUNCATE TABLE order_details');
            $pdo->exec('TRUNCATE TABLE payments');
            $pdo->exec('TRUNCATE TABLE orders');
            
            // Check if invoices table exists before truncating
            $stmt = $pdo->query("SHOW TABLES LIKE 'invoices'");
            if ($stmt->rowCount() > 0) {
                $pdo->exec('TRUNCATE TABLE invoices');
            }
            
            $pdo->exec('TRUNCATE TABLE cash_register');

            // Delete non-super-admin users (using DELETE instead of TRUNCATE to preserve Super Admin)
            $stmt = $pdo->prepare('DELETE FROM users WHERE is_super_admin != 1 OR is_super_admin IS NULL');
            $stmt->execute();

            // Reset other tables
            $pdo->exec('TRUNCATE TABLE products');
            $pdo->exec('TRUNCATE TABLE categories');
            $pdo->exec('TRUNCATE TABLE tables');

            // Re-enable foreign key checks
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

            $success_msg = 'El sistema ha sido restablecido correctamente.';
        } catch (Exception $e) {
            // Re-enable foreign keys in case of error
            try {
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            } catch (Exception $ex) {
                // Ignore if this fails
            }
            $error_msg = 'Error al restablecer el sistema: ' . $e->getMessage();
        }
    } else {
        $error_msg = 'Credenciales de Super Admin incorrectas. No se realizaron cambios.';
    }
}
}

// Handle IVA Update
if (isset($_POST['update_iva'])) {
    $iva_percentage = $_POST['iva_percentage'];
    
    // Update or insert setting
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('iva_percentage', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->execute([$iva_percentage, $iva_percentage]);
    
    $success_msg = 'Configuración de IVA actualizada correctamente.';
}

// Get user's role name
$stmt = $pdo->prepare('SELECT name FROM roles WHERE id = ?');
$stmt->execute([$_SESSION['role_id']]);
$user_role_name = $stmt->fetchColumn() ?: 'Usuario';
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="dashboard-wrapper">
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>🍹 Bar System</h2>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php">📊 Dashboard</a></li>
            <li><a href="products.php">📦 Productos</a></li>
            <li><a href="tables.php">🪑 Mesas</a></li>
            <li><a href="pos.php">💳 POS</a></li>
            <li><a href="kitchen.php">👨‍🍳 Cocina</a></li>
            <li><a href="cash_register.php">💰 Caja</a></li>
            <li><a href="reports.php">📈 Reportes</a></li>
            <li><a href="users.php">👥 Usuarios</a></li>
            <li><a href="settings.php" class="active">⚙️ Configuración</a></li>
            <li><a href="logout.php">🚪 Cerrar Sesión</a></li>
        </ul>
    </aside>
    
    <main class="main-content">
        <div class="page-header">
            <div>
                <h1>Configuración del Sistema</h1>
                <p>Administración de base de datos y mantenimiento</p>
            </div>
            <div class="user-profile-header">
                <div class="user-avatar">
                    <?= strtoupper(substr($_SESSION['name'], 0, 1)) ?>
                </div>
                <div class="user-details">
                    <span class="user-name"><?= htmlspecialchars($_SESSION['name']) ?></span>
                    <span class="user-role"><?= htmlspecialchars($user_role_name) ?></span>
                </div>
            </div>
        </div>
        
        <?php if ($success_msg): ?>
            <div class="alert alert-success">✅ <?= $success_msg ?></div>
        <?php endif; ?>
        
        <?php if ($error_msg): ?>
            <div class="alert alert-danger">❌ <?= $error_msg ?></div>
        <?php endif; ?>
        
        <div class="settings-grid">
            <!-- Backup Section -->
            <?php if ($_SESSION['role_id'] == 5): ?>
            <div class="card settings-card">
                <div class="card-header">
                    <h3>💾 Respaldo de Base de Datos</h3>
                </div>
                <div class="card-body">
                    <p>Descarga una copia completa de la base de datos en formato SQL. Útil para copias de seguridad manuales.</p>
                    <form method="POST">
                        <button type="submit" name="backup" class="btn btn-primary btn-block">
                            ⬇️ Descargar Respaldo SQL
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Menu Initialization Section -->
            <div class="card settings-card">
                <div class="card-header">
                    <h3>🚀 Inicialización de Menú</h3>
                </div>
                <div class="card-body">
                    <p>Herramienta para ingresar rápidamente todo el menú del bar. Ideal para la configuración inicial.</p>
                    <a href="menu_init.php" class="btn btn-success btn-block">
                        <span>📝</span> Ingresar Menú Masivo
                    </a>
                </div>
            </div>

            <!-- VAT Configuration Section -->
            <div class="card settings-card">
                <div class="card-header">
                    <h3>💰 Configuración de Facturación</h3>
                </div>
                <div class="card-body">
                    <p>Define el porcentaje de IVA que se aplicará a las facturas.</p>
                    <?php
                    // Get current IVA
                    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'iva_percentage'");
                    $stmt->execute();
                    $current_iva = $stmt->fetchColumn() ?: 0;
                    ?>
                    <form method="POST">
                        <div class="form-group">
                            <label>Porcentaje de IVA (%)</label>
                            <input type="number" name="iva_percentage" class="form-input" value="<?= $current_iva ?>" min="0" max="100" step="0.01" required>
                        </div>
                        <button type="submit" name="update_iva" class="btn btn-primary btn-block">
                            💾 Guardar Configuración
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Reset Section -->
            <?php if ($_SESSION['role_id'] == 5): ?>
            <div class="card settings-card danger-zone">
                <div class="card-header danger-header">
                    <h3>⚠️ Restablecer Sistema</h3>
                </div>
                <div class="card-body">
                    <p><strong>¡ACCIÓN DESTRUCTIVA!</strong></p>
                    <p>Esta acción eliminará permanentemente:</p>
                    <ul style="margin-bottom: 20px; padding-left: 20px;">
                        <li>Todos los productos y categorías</li>
                        <li>Todas las mesas y pedidos</li>
                        <li>Historial de ventas y reportes</li>
                        <li>Todos los usuarios (EXCEPTO tu cuenta de admin)</li>
                    </ul>
                    <button onclick="openModal()" class="btn btn-danger btn-block">
                        🗑️ Eliminar Todo y Restablecer
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Password Confirmation Modal -->
<div id="passwordModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>🔒 Confirmación de Super Admin</h2>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <form method="POST">
            <div class="modal-body">
                <p>Para confirmar el restablecimiento del sistema, por favor ingresa las credenciales del <strong>Super Admin</strong>.</p>
                <div class="form-group">
                    <label>Usuario Super Admin</label>
                    <input type="text" name="super_admin_username" class="form-input" required placeholder="Nombre de usuario del Super Admin" autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Contraseña Super Admin</label>
                    <input type="password" name="super_admin_password" class="form-input" required placeholder="Contraseña del Super Admin" autocomplete="off">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
                <button type="submit" name="reset_db" class="btn btn-danger">Confirmar Borrado</button>
            </div>
        </form>
    </div>
</div>

<style>
.settings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
}

.settings-card .card-body {
    padding: 25px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    height: 100%;
}

.danger-zone {
    border: 2px solid var(--danger);
}

.danger-header {
    background: var(--danger) !important;
    color: white;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    align-items: center;
    justify-content: center;
}

.modal-content {
    background-color: var(--bg-card);
    border-radius: 12px;
    width: 100%;
    max-width: 500px;
    box-shadow: 0 20px 50px rgba(0,0,0,0.3);
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from {transform: translateY(-50px); opacity: 0;}
    to {transform: translateY(0); opacity: 1;}
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    padding: 20px;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.close {
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    color: var(--text-secondary);
}

.close:hover {
    color: var(--text-primary);
}
</style>

<script>
function openModal() {
    document.getElementById('passwordModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('passwordModal').style.display = 'none';
}

// Close modal if clicking outside
window.onclick = function(event) {
    if (event.target == document.getElementById('passwordModal')) {
        closeModal();
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
