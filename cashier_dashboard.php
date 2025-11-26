<?php
require_once __DIR__ . '/config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Only Cashier can access (role_id = 3)
if ($_SESSION['role_id'] != 3) {
    header('Location: dashboard.php');
    exit();
}

$success_msg = '';
$error_msg = '';

// Handle cash register open
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['open_register'])) {
    $initial_amount = $_POST['initial_amount'];
    
    $stmt = $pdo->prepare('INSERT INTO cash_register (user_id, amount, type, status) VALUES (?, ?, "open", "active")');
    $stmt->execute([$_SESSION['user_id'], $initial_amount]);
    $success_msg = 'Caja abierta exitosamente';
}

// Handle cash register close
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['close_register'])) {
    $register_id = $_POST['register_id'];
    $final_amount = $_POST['final_amount'];
    
    try {
        $pdo->beginTransaction();
        
        // Close the register
        $stmt = $pdo->prepare('UPDATE cash_register SET status = "closed" WHERE id = ?');
        $stmt->execute([$register_id]);
        
        // Insert closing record
        $stmt = $pdo->prepare('INSERT INTO cash_register (user_id, amount, type, status) VALUES (?, ?, "close", "closed")');
        $stmt->execute([$_SESSION['user_id'], $final_amount]);
        
        $pdo->commit();
        $success_msg = 'Caja cerrada exitosamente';
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_msg = 'Error al cerrar caja: ' . $e->getMessage();
    }
}

// Handle payment processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    $order_id = $_POST['order_id'];
    $payment_method = $_POST['payment_method'];
    $amount = $_POST['amount'];
    
    // Get active cash register (system-wide)
    $stmt = $pdo->query('SELECT id FROM cash_register WHERE status = "active" ORDER BY id DESC LIMIT 1');
    $active_register = $stmt->fetch();
    
    if (!$active_register) {
        $error_msg = 'Debe abrir la caja antes de procesar pagos';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Insert payment
            $stmt = $pdo->prepare('INSERT INTO payments (order_id, method, amount, cash_register_id, date_created) VALUES (?, ?, ?, ?, NOW())');
            $stmt->execute([$order_id, $payment_method, $amount, $active_register['id']]);
            
            // Update order status to completed
            $stmt = $pdo->prepare('UPDATE orders SET status = "completed" WHERE id = ?');
            $stmt->execute([$order_id]);
            
            // Update table status to available
            $stmt = $pdo->prepare('UPDATE tables t JOIN orders o ON t.id = o.table_id SET t.status = "available" WHERE o.id = ?');
            $stmt->execute([$order_id]);
            
            $pdo->commit();
            $success_msg = 'Pago procesado exitosamente';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = 'Error al procesar el pago: ' . $e->getMessage();
        }
    }
}

// Check if there's an active cash register (system-wide, not per user)
$stmt = $pdo->query('SELECT cr.*, u.name as cashier_name FROM cash_register cr JOIN users u ON cr.user_id = u.id WHERE cr.status = "active" ORDER BY cr.id DESC LIMIT 1');
$active_register = $stmt->fetch();

// Get pending orders (not yet paid)
$pending_orders = $pdo->query("
    SELECT o.*, t.name as table_name, u.name as waiter_name,
           (SELECT COUNT(*) FROM payments WHERE order_id = o.id) as has_payment
    FROM orders o
    JOIN tables t ON o.table_id = t.id
    JOIN users u ON o.user_id = u.id
    WHERE o.status = 'pending' OR (o.status = 'completed' AND NOT EXISTS (SELECT 1 FROM payments WHERE order_id = o.id))
    ORDER BY o.date_created DESC
")->fetchAll();

// Get recent payments (today or since register opened)
$date_condition = "DATE(p.date_created) = CURDATE()";
$params = [];

if ($active_register) {
    $date_condition = "p.date_created >= ?";
    $params[] = $active_register['date_created'];
}

$recent_payments = $pdo->prepare("
    SELECT p.*, o.id as order_id, t.name as table_name, o.total
    FROM payments p
    JOIN orders o ON p.order_id = o.id
    JOIN tables t ON o.table_id = t.id
    WHERE $date_condition
    ORDER BY p.date_created DESC
    LIMIT 10
");
$recent_payments->execute($params);
$recent_payments = $recent_payments->fetchAll();

// Calculate today's totals (or since register opened)
$today_stats = $pdo->prepare("
    SELECT 
        COUNT(*) as total_payments,
        SUM(amount) as total_amount,
        SUM(CASE WHEN method = 'cash' THEN amount ELSE 0 END) as cash_total,
        SUM(CASE WHEN method = 'card' THEN amount ELSE 0 END) as card_total,
        SUM(CASE WHEN method = 'transfer' THEN amount ELSE 0 END) as transfer_total
    FROM payments p
    WHERE $date_condition
");
$today_stats->execute($params);
$today_stats = $today_stats->fetch();
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="dashboard-wrapper">
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>🍹 Bar System - Cajero</h2>
        </div>
        
        <ul class="sidebar-menu">
            <li><a href="cashier_dashboard.php" class="active">💰 Caja</a></li>
            <li><a href="logout.php" class="logout-link">🚪 Cerrar Sesión</a></li>
        </ul>
    </aside>
    
    <main class="main-content">
        <div class="page-header">
            <div>
                <h1>Panel de Caja</h1>
                <p>Gestión de cobros y facturación</p>
            </div>
            <div class="user-profile-header">
                <div class="user-avatar">
                    <?= strtoupper(substr($_SESSION['name'], 0, 1)) ?>
                </div>
                <div class="user-details">
                    <span class="user-name"><?= htmlspecialchars($_SESSION['name']) ?></span>
                    <span class="user-role">Cajero</span>
                </div>
            </div>
        </div>
        
        <?php if ($success_msg): ?>
            <div class="alert alert-success">✅ <?= $success_msg ?></div>
        <?php endif; ?>
        
        <?php if ($error_msg): ?>
            <div class="alert alert-danger">❌ <?= $error_msg ?></div>
        <?php endif; ?>
        
        <!-- Cash Register Status -->
        <?php if ($active_register): ?>
            <div class="card" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; margin-bottom: 30px;">
                <div style="padding: 30px;">
                    <h3 style="margin: 0 0 10px 0; color: white;">✅ Caja Abierta</h3>
                    <p style="margin: 0 0 20px 0; opacity: 0.9; font-size: 14px;">Cajero: <?= htmlspecialchars($active_register['cashier_name']) ?></p>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                        <div>
                            <p style="margin: 0; opacity: 0.9;">Monto Inicial</p>
                            <h2 style="margin: 5px 0 0 0; color: white;">C$<?= number_format($active_register['amount'], 2) ?></h2>
                        </div>
                        <div>
                            <p style="margin: 0; opacity: 0.9;">Ventas del Día</p>
                            <h2 style="margin: 5px 0 0 0; color: white;">C$<?= number_format($today_stats['total_amount'] ?? 0, 2) ?></h2>
                        </div>
                        <?php if ($active_register['user_id'] == $_SESSION['user_id'] || (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] == 1) || $_SESSION['role_id'] == 3): ?>
                        <div>
                            <button class="btn btn-danger" onclick="openCloseRegisterModal()" style="margin-top: 10px;">
                                🔒 Cerrar Caja
                            </button>
                        </div>
                        <?php else: ?>
                        <div>
                            <span class="badge badge-secondary" style="padding: 10px 20px; font-size: 14px;">
                                Solo <?= htmlspecialchars($active_register['cashier_name']) ?> puede cerrar la caja
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="card" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; margin-bottom: 30px;">
                <div style="padding: 30px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: white;">⚠️ Caja Cerrada</h3>
                    <p style="margin: 0 0 20px 0; opacity: 0.9;">Debe abrir la caja para poder procesar pagos</p>
                    <button class="btn" onclick="openRegisterModal()" style="background: white; color: #d97706;">
                        📂 Abrir Caja
                    </button>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Today's Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">💵</div>
                <div class="stat-info">
                    <h3>Total del Día</h3>
                    <p class="stat-value">C$<?= number_format($today_stats['total_amount'] ?? 0, 2) ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💳</div>
                <div class="stat-info">
                    <h3>Efectivo</h3>
                    <p class="stat-value">C$<?= number_format($today_stats['cash_total'] ?? 0, 2) ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💳</div>
                <div class="stat-info">
                    <h3>Tarjeta</h3>
                    <p class="stat-value">C$<?= number_format($today_stats['card_total'] ?? 0, 2) ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🏦</div>
                <div class="stat-info">
                    <h3>Transferencia</h3>
                    <p class="stat-value">C$<?= number_format($today_stats['transfer_total'] ?? 0, 2) ?></p>
                </div>
            </div>
        </div>
        
        <!-- Pending Orders -->
        <div class="card">
            <div class="card-header">
                <h3>Pedidos Pendientes de Pago</h3>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Mesa</th>
                            <th>Mesero</th>
                            <th>Total</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pending_orders)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px;">
                                    <p style="color: var(--text-secondary);">No hay pedidos pendientes de pago</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pending_orders as $order): ?>
                                <tr>
                                    <td><?= $order['id'] ?></td>
                                    <td><?= htmlspecialchars($order['table_name']) ?></td>
                                    <td><?= htmlspecialchars($order['waiter_name']) ?></td>
                                    <td><strong>C$<?= number_format($order['total'], 2) ?></strong></td>
                                    <td><?= date('d/m/Y H:i', strtotime($order['date_created'])) ?></td>
                                    <td>
                                        <?php if ($active_register): ?>
                                            <button class="btn btn-sm btn-success" onclick="openPaymentModal(<?= htmlspecialchars(json_encode($order)) ?>)">
                                                💰 Cobrar
                                            </button>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Abra la caja primero</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Recent Payments -->
        <div class="card">
            <div class="card-header">
                <h3>Pagos Recientes (Hoy)</h3>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Hora</th>
                            <th>Mesa</th>
                            <th>Método</th>
                            <th>Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_payments)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 40px;">
                                    <p style="color: var(--text-secondary);">No hay pagos registrados hoy</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_payments as $payment): ?>
                                <tr>
                                    <td><?= date('H:i', strtotime($payment['date_created'])) ?></td>
                                    <td><?= htmlspecialchars($payment['table_name']) ?></td>
                                    <td>
                                        <span class="badge badge-<?= $payment['method'] == 'cash' ? 'success' : ($payment['method'] == 'card' ? 'primary' : 'secondary') ?>">
                                            <?= ucfirst($payment['method']) ?>
                                        </span>
                                    </td>
                                    <td>C$<?= number_format($payment['amount'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Payment Modal -->
<div id="paymentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>💰 Procesar Pago</h3>
            <span class="close" onclick="closePaymentModal()">&times;</span>
        </div>
        <form method="POST">
            <input type="hidden" name="process_payment" value="1">
            <input type="hidden" name="order_id" id="payment_order_id">
            <input type="hidden" name="amount" id="payment_amount">
            
            <div class="modal-body">
                <div class="payment-info">
                    <p><strong>Mesa:</strong> <span id="payment_table"></span></p>
                    <p><strong>Total a Cobrar:</strong> <span id="payment_total" style="font-size: 24px; color: var(--success);"></span></p>
                </div>
                
                <div class="form-group">
                    <label>Método de Pago</label>
                    <select name="payment_method" class="form-control" required>
                        <option value="cash">💵 Efectivo</option>
                        <option value="card">💳 Tarjeta</option>
                        <option value="transfer">🏦 Transferencia</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closePaymentModal()">Cancelar</button>
                <button type="submit" class="btn btn-success">Confirmar Pago</button>
            </div>
        </form>
    </div>
</div>

<!-- Open Register Modal -->
<div id="openRegisterModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>📂 Abrir Caja</h3>
            <span class="close" onclick="closeRegisterModal()">&times;</span>
        </div>
        <form method="POST">
            <input type="hidden" name="open_register" value="1">
            
            <div class="modal-body">
                <div class="form-group">
                    <label>Monto Inicial en Caja</label>
                    <input type="number" step="0.01" name="initial_amount" class="form-control" placeholder="0.00" required>
                    <small style="color: var(--text-secondary);">Ingrese el monto con el que inicia la caja</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeRegisterModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Abrir Caja</button>
            </div>
        </form>
    </div>
</div>

<!-- Close Register Modal -->
<div id="closeRegisterModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>🔒 Cerrar Caja</h3>
            <span class="close" onclick="closeRegisterModal()">&times;</span>
        </div>
        <form method="POST" onsubmit="return confirm('¿Está seguro de cerrar la caja?')">
            <input type="hidden" name="close_register" value="1">
            <input type="hidden" name="register_id" value="<?= $active_register['id'] ?? '' ?>">
            
            <div class="modal-body">
                <div class="form-group">
                    <label>Monto Final en Caja</label>
                    <input type="number" step="0.01" name="final_amount" class="form-control" value="<?= ($active_register['amount'] ?? 0) + ($today_stats['total_amount'] ?? 0) ?>" required>
                    <small style="color: var(--text-secondary);">Verifique el monto antes de cerrar</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeRegisterModal()">Cancelar</button>
                <button type="submit" class="btn btn-danger">Cerrar Caja</button>
            </div>
        </form>
    </div>
</div>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: var(--bg-card);
    border-radius: 12px;
    padding: 20px;
    border: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 15px;
}

.stat-icon {
    font-size: 36px;
}

.stat-info h3 {
    margin: 0;
    font-size: 14px;
    color: var(--text-secondary);
    font-weight: 500;
}

.stat-value {
    margin: 5px 0 0 0;
    font-size: 24px;
    font-weight: 700;
    color: var(--text-primary);
}

.payment-info {
    background: var(--bg-primary);
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.payment-info p {
    margin: 10px 0;
    font-size: 16px;
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.7);
    align-items: center;
    justify-content: center;
}

.modal-content {
    background-color: var(--bg-card);
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 20px 50px rgba(0,0,0,0.3);
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    color: var(--text-primary);
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
    color: var(--danger);
}

.badge-primary {
    background: var(--primary);
    color: white;
}
</style>

<script>
function openPaymentModal(order) {
    document.getElementById('payment_order_id').value = order.id;
    document.getElementById('payment_amount').value = order.total;
    document.getElementById('payment_table').textContent = order.table_name;
    document.getElementById('payment_total').textContent = 'C$' + parseFloat(order.total).toFixed(2);
    document.getElementById('paymentModal').style.display = 'flex';
}

function closePaymentModal() {
    document.getElementById('paymentModal').style.display = 'none';
}

function openRegisterModal() {
    document.getElementById('openRegisterModal').style.display = 'flex';
}

function openCloseRegisterModal() {
    document.getElementById('closeRegisterModal').style.display = 'flex';
}

function closeRegisterModal() {
    document.getElementById('openRegisterModal').style.display = 'none';
    document.getElementById('closeRegisterModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const paymentModal = document.getElementById('paymentModal');
    const openModal = document.getElementById('openRegisterModal');
    const closeModal = document.getElementById('closeRegisterModal');
    
    if (event.target == paymentModal) {
        closePaymentModal();
    }
    if (event.target == openModal || event.target == closeModal) {
        closeRegisterModal();
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
