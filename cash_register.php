<?php
require_once __DIR__ . '/config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'open':
                $amount = $_POST['amount'];
                $stmt = $pdo->prepare('INSERT INTO cash_register (user_id, amount, type, status) VALUES (?, ?, "open", "active")');
                $stmt->execute([$_SESSION['user_id'], $amount]);
                header('Location: cash_register.php?success=opened');
                exit();
                break;
                
            case 'close':
                $register_id = $_POST['register_id'];
                $amount = $_POST['amount'];
                
                // Close the register
                $stmt = $pdo->prepare('UPDATE cash_register SET status = "closed" WHERE id = ?');
                $stmt->execute([$register_id]);
                
                // Create close record
                $stmt = $pdo->prepare('INSERT INTO cash_register (user_id, amount, type, status) VALUES (?, ?, "close", "closed")');
                $stmt->execute([$_SESSION['user_id'], $amount]);
                
                header('Location: cash_register.php?success=closed');
                exit();
                break;
        }
    }
}

// Get active register
if (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] == 1) {
    // Super Admin sees ANY active register
    $stmt = $pdo->query('SELECT cr.*, u.name as user_name FROM cash_register cr JOIN users u ON cr.user_id = u.id WHERE cr.status = "active" ORDER BY cr.id DESC LIMIT 1');
    $active_register = $stmt->fetch();
} else {
    // Regular users see only THEIR active register
    $stmt = $pdo->prepare('SELECT cr.*, u.name as user_name FROM cash_register cr JOIN users u ON cr.user_id = u.id WHERE cr.user_id = ? AND cr.status = "active" ORDER BY cr.id DESC LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $active_register = $stmt->fetch();
}

// Get today's sales data
$today_sales = 0;
$sales_by_table = [];
$payment_breakdown = ['cash' => 0, 'card' => 0, 'transfer' => 0];

if ($active_register) {
    // Total sales (from the moment the register was opened)
    $stmt = $pdo->prepare('
        SELECT SUM(p.amount) as total 
        FROM payments p
        WHERE p.date_created >= ?
    ');
    $stmt->execute([$active_register['date_created']]);
    $result = $stmt->fetch();
    $today_sales = $result['total'] ?? 0;
    
    // Sales by table
    $stmt = $pdo->prepare('
        SELECT t.name as table_name, 
               COUNT(DISTINCT o.id) as order_count,
               SUM(p.amount) as table_total
        FROM payments p
        JOIN orders o ON p.order_id = o.id
        JOIN tables t ON o.table_id = t.id
        WHERE p.date_created >= ?
        GROUP BY t.id
        ORDER BY table_total DESC
    ');
    $stmt->execute([$active_register['date_created']]);
    $sales_by_table = $stmt->fetchAll();
    
    // Payment method breakdown
    $stmt = $pdo->prepare('
        SELECT method, SUM(amount) as total
        FROM payments
        WHERE date_created >= ?
        GROUP BY method
    ');
    $stmt->execute([$active_register['date_created']]);
    $payments = $stmt->fetchAll();
    
    foreach ($payments as $payment) {
        $payment_breakdown[$payment['method']] = $payment['total'];
    }
}

// Get register history
$stmt = $pdo->prepare('SELECT cr.*, u.name as user_name FROM cash_register cr JOIN users u ON cr.user_id = u.id ORDER BY cr.date_created DESC LIMIT 20');
$stmt->execute();
$register_history = $stmt->fetchAll();

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
            <li><a href="cash_register.php" class="active">💰 Caja</a></li>
            <li><a href="reports.php">📈 Reportes</a></li>
            <li><a href="users.php">👥 Usuarios</a></li>
            <li><a href="settings.php">⚙️ Configuración</a></li>
            <li><a href="logout.php">🚪 Cerrar Sesión</a></li>
        </ul>
    </aside>
    
    <main class="main-content">
        <div class="page-header">
            <div>
                <h1>Gestión de Caja</h1>
                <p>Apertura y cierre de caja</p>
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
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <?php 
                    if ($_GET['success'] === 'opened') echo '✅ Caja abierta exitosamente';
                    if ($_GET['success'] === 'closed') echo '✅ Caja cerrada exitosamente';
                ?>
            </div>
        <?php endif; ?>
        
        <div class="cash-grid">
            <?php if ($active_register): ?>
                <!-- Active Register -->
                <div class="card">
                    <div class="card-header" style="background: var(--success); color: white;">
                        <h3>✅ Caja Abierta</h3>
                    </div>
                    <div style="padding: 30px;">
                        <div class="cash-stat">
                            <span>Monto Inicial:</span>
                            <strong>C$<?= number_format($active_register['amount'], 2) ?></strong>
                        </div>
                        <div class="cash-stat">
                            <span>Ventas del Día:</span>
                            <strong>C$<?= number_format($today_sales, 2) ?></strong>
                        </div>
                        <div class="cash-stat" style="border-top: 2px solid var(--border-color); padding-top: 15px; margin-top: 15px;">
                            <span>Total en Caja:</span>
                            <strong style="color: var(--success); font-size: 24px;">C$<?= number_format($active_register['amount'] + $today_sales, 2) ?></strong>
                        </div>
                        
                        <!-- Payment Method Breakdown -->
                        <div style="margin-top: 30px;">
                            <h4 style="color: var(--text-primary); margin-bottom: 15px;">Desglose por Método de Pago</h4>
                            <div class="payment-breakdown">
                                <div class="payment-item">
                                    <span>💵 Efectivo:</span>
                                    <strong>C$<?= number_format($payment_breakdown['cash'], 2) ?></strong>
                                </div>
                                <div class="payment-item">
                                    <span>💳 Tarjeta:</span>
                                    <strong>C$<?= number_format($payment_breakdown['card'], 2) ?></strong>
                                </div>
                                <div class="payment-item">
                                    <span>🏦 Transferencia:</span>
                                    <strong>C$<?= number_format($payment_breakdown['transfer'], 2) ?></strong>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Sales by Table -->
                        <?php if (!empty($sales_by_table)): ?>
                            <div style="margin-top: 30px;">
                                <h4 style="color: var(--text-primary); margin-bottom: 15px;">Ventas por Mesa</h4>
                                <div class="table-sales">
                                    <?php foreach ($sales_by_table as $table_sale): ?>
                                        <div class="table-sale-item">
                                            <div>
                                                <strong><?= htmlspecialchars($table_sale['table_name']) ?></strong>
                                                <small><?= $table_sale['order_count'] ?> pedido(s)</small>
                                            </div>
                                            <strong style="color: var(--primary);">C$<?= number_format($table_sale['table_total'], 2) ?></strong>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" style="margin-top: 30px;" onsubmit="return confirm('¿Cerrar la caja?')">
                            <input type="hidden" name="action" value="close">
                            <input type="hidden" name="register_id" value="<?= $active_register['id'] ?>">
                            
                            <div class="form-group">
                                <label>Monto Final en Caja</label>
                                <input type="number" step="0.01" name="amount" class="form-control" value="<?= $active_register['amount'] + $today_sales ?>" required>
                            </div>
                            
                            <button type="submit" class="btn btn-danger btn-block">Cerrar Caja</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <!-- Open Register Form -->
                <div class="card">
                    <div class="card-header" style="background: var(--primary); color: white;">
                        <h3>Abrir Caja</h3>
                    </div>
                    <div style="padding: 30px;">
                        <form method="POST">
                            <input type="hidden" name="action" value="open">
                            
                            <div class="form-group">
                                <label>Monto Inicial</label>
                                <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" required>
                                <small style="color: var(--text-secondary);">Ingrese el monto con el que inicia la caja</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-block">Abrir Caja</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Register History -->
            <div class="card">
                <div class="card-header">
                    <h3>Historial de Caja</h3>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Usuario</th>
                                <th>Tipo</th>
                                <th>Monto</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($register_history as $record): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($record['date_created'])) ?></td>
                                    <td><?= htmlspecialchars($record['user_name']) ?></td>
                                    <td>
                                        <span class="badge badge-<?= $record['type'] === 'open' ? 'success' : 'danger' ?>">
                                            <?= $record['type'] === 'open' ? '📂 Apertura' : '🔒 Cierre' ?>
                                        </span>
                                    </td>
                                    <td>C$<?= number_format($record['amount'], 2) ?></td>
                                    <td>
                                        <span class="badge badge-<?= $record['status'] === 'active' ? 'success' : 'secondary' ?>">
                                            <?= $record['status'] === 'active' ? 'Activa' : 'Cerrada' ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<style>
.cash-grid {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 20px;
}

.cash-stat {
    display: flex;
    justify-content: space-between;
    padding: 15px 0;
    font-size: 16px;
}

.cash-stat span {
    color: var(--text-secondary);
}

.cash-stat strong {
    color: var(--text-primary);
    font-size: 20px;
}

.payment-breakdown {
    background: var(--bg-secondary);
    border-radius: 8px;
    padding: 15px;
}

.payment-item {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid var(--border-color);
}

.payment-item:last-child {
    border-bottom: none;
}

.payment-item span {
    color: var(--text-secondary);
    font-size: 16px;
}

.payment-item strong {
    color: var(--text-primary);
    font-size: 18px;
}

.table-sales {
    background: var(--bg-secondary);
    border-radius: 8px;
    padding: 15px;
    max-height: 300px;
    overflow-y: auto;
}

.table-sale-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    background: var(--bg-primary);
    border-radius: 6px;
    margin-bottom: 10px;
}

.table-sale-item:last-child {
    margin-bottom: 0;
}

.table-sale-item strong {
    color: var(--text-primary);
    display: block;
}

.table-sale-item small {
    color: var(--text-secondary);
    font-size: 12px;
}

.badge-secondary {
    background: #64748b;
    color: white;
}

@media (max-width: 1024px) {
    .cash-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
