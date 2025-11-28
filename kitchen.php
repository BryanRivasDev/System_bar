<?php
require_once __DIR__ . '/config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Check if user has kitchen role
if ($_SESSION['role_id'] != 4 && $_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 5) {
    header('Location: dashboard.php');
    exit();
}

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_order'])) {
    $order_id = $_POST['order_id'];
    $stmt = $pdo->prepare('UPDATE orders SET status = "completed" WHERE id = ?');
    $stmt->execute([$order_id]);
    header('Location: kitchen.php?success=completed');
    exit();
}

// Get pending orders for kitchen
$stmt = $pdo->query('
    SELECT o.*, t.name as table_name, u.name as waiter_name,
           GROUP_CONCAT(CONCAT(od.quantity, "x ", p.name) SEPARATOR ", ") as items
    FROM orders o
    JOIN tables t ON o.table_id = t.id
    JOIN users u ON o.user_id = u.id
    JOIN order_details od ON o.id = od.order_id
    JOIN products p ON od.product_id = p.id
    WHERE o.status = "pending"
    GROUP BY o.id
    ORDER BY o.date_created ASC
');
$pending_orders = $stmt->fetchAll();

// Get user's role name
$stmt = $pdo->prepare('SELECT name FROM roles WHERE id = ?');
$stmt->execute([$_SESSION['role_id']]);
$user_role_name = $stmt->fetchColumn() ?: 'Usuario';
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="dashboard-wrapper">
    <?php if ($_SESSION['role_id'] == 1 || $_SESSION['role_id'] == 5): ?>
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>🍹 Bar System</h2>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php">📊 Dashboard</a></li>
            <li><a href="products.php">📦 Productos</a></li>
            <li><a href="tables.php">🪑 Mesas</a></li>
            <li><a href="pos.php">💳 POS</a></li>
            <li><a href="kitchen.php" class="active">👨‍🍳 Cocina</a></li>
            <li><a href="cash_register.php">💰 Caja</a></li>
            <li><a href="reports.php">📈 Reportes</a></li>
            <li><a href="users.php">👥 Usuarios</a></li>
            <li><a href="settings.php">⚙️ Configuración</a></li>
            <li><a href="logout.php" class="logout-link">🚪 Cerrar Sesión</a></li>
        </ul>
    </aside>
    <?php endif; ?>
    
    <main class="main-content" style="<?= $_SESSION['role_id'] == 4 ? 'margin-left: 0;' : '' ?>">
        <div class="page-header">
            <div>
                <h1>Vista de Cocina</h1>
                <p>Pedidos pendientes de preparación</p>
            </div>
            <div class="user-profile-header" style="margin-right: 20px;">
                <div class="user-avatar">
                    <?= strtoupper(substr($_SESSION['name'], 0, 1)) ?>
                </div>
                <div class="user-details">
                    <span class="user-name"><?= htmlspecialchars($_SESSION['name']) ?></span>
                    <span class="user-role"><?= htmlspecialchars($user_role_name) ?></span>
                </div>
            </div>
        </div>
        
        <?php if (isset($_GET['success']) && $_GET['success'] === 'completed'): ?>
            <div class="alert alert-success">
                ✅ Pedido marcado como completado
            </div>
        <?php endif; ?>
        
        <?php if (empty($pending_orders)): ?>
            <div class="card">
                <div style="padding: 60px; text-align: center;">
                    <h2 style="color: var(--text-secondary);">✅ No hay pedidos pendientes</h2>
                    <p style="color: var(--text-secondary);">Los nuevos pedidos aparecerán aquí automáticamente</p>
                </div>
            </div>
        <?php else: ?>
            <div class="kitchen-grid">
                <?php foreach ($pending_orders as $order): ?>
                    <div class="kitchen-order">
                        <div class="order-header">
                            <h3>Pedido #<?= $order['id'] ?></h3>
                            <span class="order-time"><?= date('H:i', strtotime($order['date_created'])) ?></span>
                        </div>
                        <div class="order-info">
                            <div><strong>Mesa:</strong> <?= htmlspecialchars($order['table_name']) ?></div>
                            <div><strong>Mesero:</strong> <?= htmlspecialchars($order['waiter_name']) ?></div>
                        </div>
                        <div class="order-items">
                            <h4>Productos:</h4>
                            <p><?= htmlspecialchars($order['items']) ?></p>
                        </div>
                        <div class="order-total">
                            <strong>Total: C$<?= number_format($order['total'], 2) ?></strong>
                        </div>
                        <form method="POST" style="padding: 15px;">
                            <input type="hidden" name="complete_order" value="1">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <button type="submit" class="btn btn-success btn-block">
                                ✅ Pedido Listo
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<style>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.kitchen-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}

.kitchen-order {
    background: var(--bg-card);
    border: 3px solid var(--accent);
    border-radius: 12px;
    overflow: hidden;
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.order-header {
    background: var(--accent);
    color: white;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.order-header h3 {
    margin: 0;
    font-size: 24px;
}

.order-time {
    font-size: 20px;
    font-weight: 700;
}

.order-info {
    padding: 20px;
    border-bottom: 1px solid var(--border-color);
}

.order-info div {
    margin-bottom: 8px;
    color: var(--text-secondary);
}

.order-items {
    padding: 20px;
    background: var(--bg-secondary);
}

.order-items h4 {
    margin-bottom: 10px;
    color: var(--text-primary);
}

.order-items p {
    color: var(--text-secondary);
    line-height: 1.6;
}

.order-total {
    padding: 20px;
    background: var(--primary);
    color: white;
    text-align: center;
    font-size: 20px;
}
</style>

<script>
// Auto-refresh every 30 seconds
setTimeout(() => location.reload(), 30000);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
