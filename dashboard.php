<?php
require_once __DIR__ . '/config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Redirect based on role
if ($_SESSION['role_id'] == 4) {
    // Kitchen user goes to kitchen
    header('Location: kitchen.php');
    exit();
} elseif ($_SESSION['role_id'] == 2) {
    // Waiter goes to tables
    header('Location: tables.php');
    exit();
}

// Get active register (system-wide)
$stmt = $pdo->query('SELECT * FROM cash_register WHERE status = "active" ORDER BY id DESC LIMIT 1');
$active_register = $stmt->fetch();

// Initialize variables
$total_sales = 0;
$total_orders = 0;
$top_products = [];
$category_sales = [];

if ($active_register) {
    // Total sales (since register opened)
    $stmt = $pdo->prepare('
        SELECT SUM(p.amount) as total_sales, COUNT(DISTINCT o.id) as total_orders 
        FROM payments p
        JOIN orders o ON p.order_id = o.id
        WHERE p.date_created >= ?
    ');
    $stmt->execute([$active_register['date_created']]);
    $sales_data = $stmt->fetch();
    $total_sales = $sales_data['total_sales'] ?? 0;
    $total_orders = $sales_data['total_orders'] ?? 0;

    // Top 5 products (since register opened)
    $stmt = $pdo->prepare('
        SELECT p.name, SUM(od.quantity) as quantity, SUM(od.quantity * od.price) as total
        FROM order_details od
        JOIN products p ON od.product_id = p.id
        JOIN orders o ON od.order_id = o.id
        JOIN payments pay ON o.id = pay.order_id
        WHERE pay.date_created >= ?
        GROUP BY p.id
        ORDER BY total DESC
        LIMIT 5
    ');
    $stmt->execute([$active_register['date_created']]);
    $top_products = $stmt->fetchAll();

    // Sales by category (since register opened)
    $stmt = $pdo->prepare('
        SELECT c.name, SUM(od.quantity * od.price) as total
        FROM order_details od
        JOIN products p ON od.product_id = p.id
        JOIN categories c ON p.category_id = c.id
        JOIN orders o ON od.order_id = o.id
        JOIN payments pay ON o.id = pay.order_id
        WHERE pay.date_created >= ?
        GROUP BY c.id
        ORDER BY total DESC
    ');
    $stmt->execute([$active_register['date_created']]);
    $category_sales = $stmt->fetchAll();
}

// Pending orders (always show pending regardless of register status)
$stmt = $pdo->query('SELECT COUNT(*) as pending FROM orders WHERE status = "pending"');
$pending_orders = $stmt->fetch()['pending'] ?? 0;

// Active tables (always show active tables)
$stmt = $pdo->query('SELECT COUNT(*) as active FROM tables WHERE status = "occupied"');
$active_tables = $stmt->fetch()['active'] ?? 0;
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="dashboard-wrapper">
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>🍹 Bar System</h2>
        </div>
        
        <ul class="sidebar-menu">
            <li><a href="dashboard.php" class="active">📊 Dashboard</a></li>
            <li><a href="products.php">📦 Productos</a></li>
            <li><a href="tables.php">🪑 Mesas</a></li>
            <li><a href="pos.php">💳 POS</a></li>
            <li><a href="kitchen.php">👨‍🍳 Cocina</a></li>
            <li><a href="cash_register.php">💰 Caja</a></li>
            <li><a href="reports.php">📈 Reportes</a></li>
            <li><a href="users.php">👥 Usuarios</a></li>
            <li><a href="settings.php">⚙️ Configuración</a></li>
            <li><a href="logout.php" class="logout-link">🚪 Cerrar Sesión</a></li>
        </ul>
    </aside>
    
    <main class="main-content">
        <div class="page-header">
            <div>
                <h1>Dashboard</h1>
                <p>Resumen de actividad del día - <?= date('d/m/Y') ?></p>
            </div>
            <div class="user-profile-header">
                <div class="user-avatar">
                    <?= strtoupper(substr($_SESSION['name'], 0, 1)) ?>
                </div>
                <div class="user-details">
                    <span class="user-name"><?= htmlspecialchars($_SESSION['name']) ?></span>
                    <span class="user-role"><?= $_SESSION['role_id'] == 1 ? 'Administrador' : 'Usuario' ?></span>
                </div>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--success);">💰</div>
                <div class="stat-info">
                    <div class="stat-label">Ventas del Día</div>
                    <div class="stat-value">C$<?= number_format($total_sales, 2) ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--primary);">📋</div>
                <div class="stat-info">
                    <div class="stat-label">Pedidos Completados</div>
                    <div class="stat-value"><?= $total_orders ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--accent);">⏳</div>
                <div class="stat-info">
                    <div class="stat-label">Pedidos Pendientes</div>
                    <div class="stat-value"><?= $pending_orders ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--secondary);">🪑</div>
                <div class="stat-info">
                    <div class="stat-label">Mesas Ocupadas</div>
                    <div class="stat-value"><?= $active_tables ?>/6</div>
                </div>
            </div>
        </div>
        
        <!-- Charts Row -->
        <div class="charts-grid">
            <!-- Category Sales Pie Chart -->
            <div class="card">
                <div class="card-header">
                    <h3>Ventas por Categoría</h3>
                </div>
                <?php if (!empty($category_sales)): ?>
                    <div class="chart-container">
                        <canvas id="categoryChart"></canvas>
                    </div>
                    <div class="category-legend">
                        <?php 
                        $colors = ['#10b981', '#6366f1', '#f59e0b', '#ec4899'];
                        foreach ($category_sales as $index => $cat): 
                        ?>
                            <div class="legend-item">
                                <span class="legend-color" style="background: <?= $colors[$index % 4] ?>;"></span>
                                <span><?= htmlspecialchars($cat['name']) ?>: C$<?= number_format($cat['total'], 2) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="padding: 60px; text-align: center;">
                        <p style="color: var(--text-secondary);">No hay ventas registradas hoy</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Top Products Bar Chart -->
            <div class="card">
                <div class="card-header">
                    <h3>Productos Más Vendidos</h3>
                </div>
                <?php if (!empty($top_products)): ?>
                    <div class="chart-container">
                        <canvas id="productsChart"></canvas>
                    </div>
                <?php else: ?>
                    <div style="padding: 60px; text-align: center;">
                        <p style="color: var(--text-secondary);">No hay ventas registradas hoy</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 25px;
    display: flex;
    align-items: center;
    gap: 20px;
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
}

.stat-label {
    color: var(--text-secondary);
    font-size: 14px;
    margin-bottom: 5px;
}

.stat-value {
    color: var(--text-primary);
    font-size: 32px;
    font-weight: 700;
}

.charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
    gap: 20px;
}

.chart-container {
    padding: 30px;
    min-height: 350px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.category-legend {
    padding: 0 20px 20px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 10px;
}

.legend-color {
    width: 20px;
    height: 20px;
    border-radius: 4px;
}

@media (max-width: 1024px) {
    .charts-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Category Sales Pie Chart
const categoryCtx = document.getElementById('categoryChart');
if (categoryCtx) {
    new Chart(categoryCtx, {
        type: 'pie',
        data: {
            labels: [<?php foreach ($category_sales as $cat) echo '"' . htmlspecialchars($cat['name']) . '",'; ?>],
            datasets: [{
                data: [<?php foreach ($category_sales as $cat) echo $cat['total'] . ','; ?>],
                backgroundColor: ['#10b981', '#6366f1', '#f59e0b', '#ec4899'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
}

// Top Products Bar Chart
const productsCtx = document.getElementById('productsChart');
if (productsCtx) {
    new Chart(productsCtx, {
        type: 'bar',
        data: {
            labels: [<?php foreach ($top_products as $prod) echo '"' . htmlspecialchars($prod['name']) . '",'; ?>],
            datasets: [{
                label: 'Ventas (C$)',
                data: [<?php foreach ($top_products as $prod) echo $prod['total'] . ','; ?>],
                backgroundColor: '#6366f1',
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'C$' + value;
                        }
                    }
                }
            }
        }
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
