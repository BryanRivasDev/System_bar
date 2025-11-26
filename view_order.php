<?php
require_once __DIR__ . '/config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Allow access for Admin (1) and Waiter (2)
if ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 2) {
    header('Location: dashboard.php');
    exit();
}

$table_id = $_GET['table'] ?? null;

if (!$table_id) {
    header('Location: tables.php');
    exit();
}

// Get table info
$stmt = $pdo->prepare('SELECT * FROM tables WHERE id = ?');
$stmt->execute([$table_id]);
$table = $stmt->fetch();

if (!$table) {
    header('Location: tables.php');
    exit();
}

// Get active order for this table
$stmt = $pdo->prepare('SELECT * FROM orders WHERE table_id = ? AND status = "pending" LIMIT 1');
$stmt->execute([$table_id]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: pos.php?table=' . $table_id);
    exit();
}

// Get order details
$stmt = $pdo->prepare('
    SELECT od.*, p.name as product_name
    FROM order_details od
    JOIN products p ON od.product_id = p.id
    WHERE od.order_id = ?
');
$stmt->execute([$order['id']]);
$order_items = $stmt->fetchAll();

// Check if there's ANY active cash register (not just user's own)
$stmt = $pdo->query('SELECT * FROM cash_register WHERE type = "open" AND status = "active" ORDER BY date_created DESC LIMIT 1');
$active_register = $stmt->fetch();

// Process payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    if (!$active_register) {
        $error = 'Debe abrir la caja antes de procesar pagos';
    } else {
        $payment_method = $_POST['payment_method'];
        
        // Create invoices table if not exists (DDL causes implicit commit, so do it before transaction)
        $pdo->exec("CREATE TABLE IF NOT EXISTS invoices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            table_name VARCHAR(50),
            total DECIMAL(10,2),
            payment_method VARCHAR(20),
            date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(id)
        )");

        $pdo->beginTransaction();
        
        try {

            // Update stock
            foreach ($order_items as $item) {
                $stmt = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
                $stmt->execute([$item['quantity'], $item['product_id']]);
            }
            
            // Create payment
            $stmt = $pdo->prepare('INSERT INTO payments (order_id, amount, method, cash_register_id) VALUES (?, ?, ?, ?)');
            $stmt->execute([$order['id'], $order['total'], $payment_method, $active_register['id']]);
            
            // Close order
            $stmt = $pdo->prepare('UPDATE orders SET status = "completed" WHERE id = ?');
            $stmt->execute([$order['id']]);
            
            // Free table
            $stmt = $pdo->prepare('UPDATE tables SET status = "available" WHERE id = ?');
            $stmt->execute([$table_id]);
            
            // Create Invoice Record
            $stmt = $pdo->prepare('INSERT INTO invoices (order_id, table_name, total, payment_method) VALUES (?, ?, ?, ?)');
            $stmt->execute([$order['id'], $table['name'], $order['total'], $payment_method]);
            $invoice_id = $pdo->lastInsertId();
            
            $pdo->commit();
            
            header('Location: invoice.php?invoice_id=' . $invoice_id);
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error al procesar el pago: ' . $e->getMessage();
        }
    }
}
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="dashboard-wrapper">
    <?php if ($_SESSION['role_id'] == 1): ?>
    <!-- Admin sidebar -->
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
            <li><a href="settings.php">⚙️ Configuración</a></li>
            <li><a href="logout.php">🚪 Cerrar Sesión</a></li>
        </ul>
    </aside>
    <?php endif; ?>
    
    <main class="main-content" style="<?= $_SESSION['role_id'] == 2 ? 'margin-left: 0;' : '' ?>">
        <?php if ($_SESSION['role_id'] == 2): ?>
        <!-- Waiter navigation -->
        <div class="waiter-nav">
            <div class="waiter-nav-header">
                <h2>🍹 Bar System - Mesero</h2>
            </div>
            <div class="waiter-nav-buttons">
                <a href="tables.php" class="nav-btn">🪑 Mesas</a>
                <a href="logout.php" class="nav-btn logout-btn">🚪 Cerrar Sesión</a>
            </div>
        </div>
        <?php endif; ?>
        <div class="page-header">
            <h1>Cuenta de <?= htmlspecialchars($table['name']) ?></h1>
            <p>Pedido #<?= $order['id'] ?></p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                ❌ <?= $error ?>
            </div>
        <?php endif; ?>
        
        <?php if (!$active_register): ?>
            <div class="alert alert-warning">
                ⚠️ Debe abrir la caja antes de procesar pagos. <a href="cash_register.php" style="color: var(--primary); font-weight: 600;">Ir a Caja</a>
            </div>
        <?php endif; ?>
        
        <div class="invoice-layout">
            <div class="card">
                <div class="card-header" style="background: var(--primary); color: white;">
                    <h3>Detalle del Pedido</h3>
                </div>
                <div class="invoice-items">
                    <table class="invoice-table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Precio</th>
                                <th>Cant.</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order_items as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['product_name']) ?></td>
                                    <td>C$<?= number_format($item['price'], 2) ?></td>
                                    <td><?= $item['quantity'] ?></td>
                                    <td>C$<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td colspan="3"><strong>TOTAL</strong></td>
                                <td><strong>C$<?= number_format($order['total'], 2) ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header" style="background: var(--success); color: white;">
                    <h3>Procesar Pago</h3>
                </div>
                <form method="POST" action="view_order.php?table=<?= $table_id ?>" style="padding: 30px;">
                    <input type="hidden" name="process_payment" value="1">
                    
                    <div class="payment-methods-horizontal">
                        <label class="payment-option-horizontal">
                            <input type="radio" name="payment_method" value="cash" required <?= !$active_register ? 'disabled' : '' ?>>
                            <div class="payment-card-horizontal">
                                <div class="payment-icon-horizontal">💵</div>
                                <div class="payment-label-horizontal">Efectivo</div>
                            </div>
                        </label>
                        
                        <label class="payment-option-horizontal">
                            <input type="radio" name="payment_method" value="card" required <?= !$active_register ? 'disabled' : '' ?>>
                            <div class="payment-card-horizontal">
                                <div class="payment-icon-horizontal">💳</div>
                                <div class="payment-label-horizontal">Tarjeta</div>
                            </div>
                        </label>
                        
                        <label class="payment-option-horizontal">
                            <input type="radio" name="payment_method" value="transfer" required <?= !$active_register ? 'disabled' : '' ?>>
                            <div class="payment-card-horizontal">
                                <div class="payment-icon-horizontal">🏦</div>
                                <div class="payment-label-horizontal">Transferencia</div>
                            </div>
                        </label>
                    </div>
                    
                    <div class="payment-total">
                        <span>Total a Pagar:</span>
                        <strong>C$<?= number_format($order['total'], 2) ?></strong>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block btn-lg" <?= !$active_register ? 'disabled' : '' ?>>
                        Cobrar y Cerrar Cuenta
                    </button>
                    
                    <a href="pos.php?table=<?= $table_id ?>" class="btn btn-secondary btn-block" style="margin-top: 10px;">
                        Agregar más productos
                    </a>
                </form>
            </div>
        </div>
    </main>
</div>

<style>
.invoice-layout {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
}

.invoice-items {
    padding: 20px;
}

.invoice-table {
    width: 100%;
    border-collapse: collapse;
}

.invoice-table th,
.invoice-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

.invoice-table th {
    background: var(--bg-primary);
    color: var(--text-secondary);
    font-weight: 600;
}

.invoice-table td {
    color: var(--text-primary);
}

.total-row {
    background: var(--primary);
    color: white !important;
}

.total-row td {
    color: white !important;
    font-size: 20px;
    padding: 20px 12px;
}

.payment-methods-horizontal {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 30px;
}

.payment-option-horizontal {
    cursor: pointer;
}

.payment-option-horizontal input[type="radio"] {
    opacity: 0;
    position: absolute;
    width: 0;
    height: 0;
}

.payment-card-horizontal {
    background: var(--bg-secondary);
    border: 3px solid var(--border-color);
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s ease;
    height: 100%;
}

.payment-option-horizontal input[type="radio"]:checked + .payment-card-horizontal {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.1);
    transform: scale(1.05);
}

.payment-option-horizontal input[type="radio"]:disabled + .payment-card-horizontal {
    opacity: 0.5;
    cursor: not-allowed;
}

.payment-card-horizontal:hover {
    border-color: var(--primary);
}

.payment-icon-horizontal {
    font-size: 48px;
    margin-bottom: 10px;
}

.payment-label-horizontal {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
}

.payment-total {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: var(--accent);
    color: white;
    border-radius: 12px;
    margin-bottom: 20px;
    font-size: 24px;
}

.btn-lg {
    padding: 15px;
    font-size: 18px;
}

.btn-secondary {
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 2px solid var(--border-color);
}

.btn-secondary:hover {
    background: var(--bg-primary);
}

.alert-warning {
    background: #fef3c7;
    color: #92400e;
    border-left: 4px solid #f59e0b;
}

.alert-danger {
    background: #fee2e2;
    color: #dc2626;
    border-left: 4px solid #dc2626;
}

.waiter-nav {
    background: var(--bg-secondary);
    border-bottom: 2px solid var(--border-color);
    padding: 20px 30px;
    margin-bottom: 30px;
    border-radius: 12px;
}

.waiter-nav-header h2 {
    color: var(--text-primary);
    margin: 0 0 15px 0;
    font-size: 24px;
}

.waiter-nav-buttons {
    display: flex;
    gap: 15px;
}

.nav-btn {
    padding: 12px 24px;
    background: var(--bg-primary);
    border: 2px solid var(--border-color);
    border-radius: 8px;
    color: var(--text-primary);
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.nav-btn.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.nav-btn:hover {
    transform: translateY(-2px);
    border-color: var(--primary);
}

.logout-btn {
    background: var(--danger);
    border-color: var(--danger);
    color: white;
    margin-left: auto;
}

.logout-btn:hover {
    background: #dc2626;
}

@media (max-width: 1024px) {
    .invoice-layout {
        grid-template-columns: 1fr;
    }
    
    .payment-methods-horizontal {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
