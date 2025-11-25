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


// Check if there's ANY active cash register (not just user's own)
$stmt = $pdo->query('SELECT * FROM cash_register WHERE type = "open" AND status = "active" ORDER BY date_created DESC LIMIT 1');
$active_register = $stmt->fetch();

// Get all tables with their current orders
$stmt = $pdo->query('
    SELECT t.*, 
           o.id as order_id, 
           o.total as order_total,
           o.status as order_status
    FROM tables t
    LEFT JOIN orders o ON t.id = o.table_id AND o.status = "pending"
    ORDER BY t.name
');
$tables = $stmt->fetchAll();
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
            <li><a href="tables.php" class="active">🪑 Mesas</a></li>
            <li><a href="pos.php">💳 POS</a></li>
            <li><a href="kitchen.php">👨‍🍳 Cocina</a></li>
            <li><a href="cash_register.php">💰 Caja</a></li>
            <li><a href="reports.php">📈 Reportes</a></li>
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
                <a href="tables.php" class="nav-btn active">🪑 Mesas</a>
                <a href="logout.php" class="nav-btn logout-btn">🚪 Cerrar Sesión</a>
            </div>
        </div>
        <?php endif; ?>

        <div class="page-header">
            <h1>Gestión de Mesas</h1>
            <p>Vista y estado de las mesas del bar</p>
        </div>
        
        <?php if (isset($_GET['success']) && $_GET['success'] === 'payment_completed'): ?>
            <div class="alert alert-success">
                ✅ Pago procesado exitosamente
            </div>
        <?php endif; ?>
        
        <?php if (!$active_register): ?>
            <div class="alert alert-warning">
                ⚠️ Debe abrir la caja antes de tomar pedidos. <a href="cash_register.php" style="color: var(--primary); font-weight: 600;">Ir a Caja</a>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h3>Mesas del Bar</h3>
            </div>
            <div class="tables-grid">
                <?php foreach ($tables as $table): ?>
                    <div class="table-card table-<?= $table['order_id'] ? 'occupied' : 'available' ?>">
                        <div class="table-name"><?= htmlspecialchars($table['name']) ?></div>
                        <div class="table-status">
                            <?php if ($table['order_id']): ?>
                                🔴 Ocupada
                            <?php else: ?>
                                ✅ Disponible
                            <?php endif; ?>
                        </div>
                        <?php if ($table['order_id']): ?>
                            <div class="table-total">
                                Total: C$<?= number_format($table['order_total'], 2) ?>
                            </div>
                            <div class="table-actions">
                                <a href="view_order.php?table=<?= $table['id'] ?>" class="btn btn-sm btn-primary">Ver Cuenta</a>
                            </div>
                        <?php else: ?>
                            <div class="table-actions">
                                <?php if ($active_register): ?>
                                    <a href="pos.php?table=<?= $table['id'] ?>" class="btn btn-sm btn-primary">Tomar Pedido</a>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-secondary" disabled>Abrir Caja Primero</button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</div>

<style>
.tables-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
    padding: 20px;
}

.table-card {
    background: var(--bg-secondary);
    border-radius: 12px;
    padding: 25px;
    text-align: center;
    border: 2px solid var(--border-color);
    transition: all 0.3s ease;
}

.table-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

.table-available {
    border-color: #10b981;
}

.table-occupied {
    border-color: #f59e0b;
    background: rgba(245, 158, 11, 0.05);
}

.table-name {
    font-size: 24px;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 10px;
}

.table-status {
    font-size: 16px;
    margin-bottom: 15px;
    color: var(--text-secondary);
}

.table-total {
    font-size: 20px;
    font-weight: 700;
    color: var(--accent);
    margin-bottom: 15px;
}

.table-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.alert-warning {
    background: #fef3c7;
    color: #92400e;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid #f59e0b;
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
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
