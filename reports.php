<?php
require_once __DIR__ . '/config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Get date range
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Sales report
$stmt = $pdo->prepare('
    SELECT DATE(date_created) as date, COUNT(*) as orders, SUM(total) as total
    FROM orders
    WHERE DATE(date_created) BETWEEN ? AND ? AND status = "completed"
    GROUP BY DATE(date_created)
    ORDER BY date DESC
');
$stmt->execute([$start_date, $end_date]);
$sales_by_date = $stmt->fetchAll();

// Total sales
$stmt = $pdo->prepare('SELECT SUM(total) as total FROM orders WHERE DATE(date_created) BETWEEN ? AND ? AND status = "completed"');
$stmt->execute([$start_date, $end_date]);
$total_sales = $stmt->fetch()['total'] ?? 0;

// Top products
$stmt = $pdo->prepare('
    SELECT p.name, SUM(od.quantity) as quantity, SUM(od.quantity * od.price) as total
    FROM order_details od
    JOIN products p ON od.product_id = p.id
    JOIN orders o ON od.order_id = o.id
    WHERE DATE(o.date_created) BETWEEN ? AND ? AND o.status = "completed"
    GROUP BY p.id
    ORDER BY total DESC
    LIMIT 10
');
$stmt->execute([$start_date, $end_date]);
$top_products = $stmt->fetchAll();

// Payment methods
$stmt = $pdo->prepare('
    SELECT method, COUNT(*) as count, SUM(amount) as total
    FROM payments p
    JOIN orders o ON p.order_id = o.id
    WHERE DATE(p.date_created) BETWEEN ? AND ?
    GROUP BY method
');
$stmt->execute([$start_date, $end_date]);
$payment_methods = $stmt->fetchAll();

// Low stock products
$low_stock = $pdo->query('SELECT * FROM products WHERE stock < 10 AND status = "active" ORDER BY stock ASC')->fetchAll();
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
            <li><a href="reports.php" class="active">📈 Reportes</a></li>
            <li><a href="users.php">👥 Usuarios</a></li>
            <li><a href="settings.php">⚙️ Configuración</a></li>
            <li><a href="logout.php">🚪 Cerrar Sesión</a></li>
        </ul>
    </aside>
    
    <main class="main-content">
        <div class="page-header">
            <h1>Reportes</h1>
            <p>Análisis de ventas e inventario</p>
        </div>
        
        <!-- Date Filter -->
        <div class="card no-print">
            <div class="card-header">
                <h3>Filtrar por Fecha</h3>
            </div>
            <form method="GET" style="padding: 20px; display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                <div class="form-group">
                    <label>Fecha Inicio</label>
                    <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>">
                </div>
                <div class="form-group">
                    <label>Fecha Fin</label>
                    <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>">
                </div>
                <div class="form-group">
                    <label style="visibility: hidden;">Acciones</label>
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                        <a href="report_print.php?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" target="_blank" class="btn btn-secondary"><span>🖨️</span> PDF</a>
                        <a href="export_report.php?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="btn btn-success"><span>📊</span> Excel</a>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Summary Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--success);">💰</div>
                <div class="stat-info">
                    <div class="stat-label">Ventas Totales</div>
                    <div class="stat-value">C$<?= number_format($total_sales, 2) ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--primary);">📦</div>
                <div class="stat-info">
                    <div class="stat-label">Productos Vendidos</div>
                    <div class="stat-value"><?= count($top_products) ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--danger);">⚠️</div>
                <div class="stat-info">
                    <div class="stat-label">Stock Bajo</div>
                    <div class="stat-value"><?= count($low_stock) ?></div>
                </div>
            </div>
        </div>
        
        <!-- Sales by Date -->
        <div class="card">
            <div class="card-header">
                <h3>Ventas por Día</h3>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Pedidos</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales_by_date as $sale): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($sale['date'])) ?></td>
                                <td><?= $sale['orders'] ?></td>
                                <td>C$<?= number_format($sale['total'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="reports-grid">
            <!-- Top Products -->
            <div class="card">
                <div class="card-header">
                    <h3>Productos Más Vendidos</h3>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_products as $product): ?>
                                <tr>
                                    <td><?= htmlspecialchars($product['name']) ?></td>
                                    <td><?= $product['quantity'] ?></td>
                                    <td>C$<?= number_format($product['total'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Payment Methods -->
            <div class="card">
                <div class="card-header">
                    <h3>Métodos de Pago</h3>
                </div>
                <div style="padding: 20px;">
                    <?php foreach ($payment_methods as $method): ?>
                        <div class="payment-stat">
                            <div>
                                <strong>
                                    <?php
                                        $icons = ['cash' => '💵 Efectivo', 'card' => '💳 Tarjeta', 'transfer' => '🏦 Transferencia'];
                                        echo $icons[$method['method']];
                                    ?>
                                </strong>
                                <div style="color: var(--text-secondary); font-size: 14px;"><?= $method['count'] ?> transacciones</div>
                            </div>
                            <strong style="color: var(--success);">C$<?= number_format($method['total'], 2) ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Low Stock Alert -->
        <?php if (!empty($low_stock)): ?>
            <div class="card">
                <div class="card-header" style="background: var(--danger); color: white;">
                    <h3>⚠️ Productos con Stock Bajo</h3>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Stock Actual</th>
                                <th>Precio</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($low_stock as $product): ?>
                                <tr>
                                    <td><?= htmlspecialchars($product['name']) ?></td>
                                    <td><span class="badge badge-danger"><?= $product['stock'] ?> unidades</span></td>
                                    <td>C$<?= number_format($product['price'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
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
    font-size: 28px;
    font-weight: 700;
}

.reports-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-top: 30px;
}

.payment-stat {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    background: var(--bg-secondary);
    border-radius: 8px;
    margin-bottom: 10px;
}
    margin-bottom: 10px;
}

@media print {
    .sidebar, .no-print, .btn, form {
        display: none !important;
    }
    .main-content {
        margin-left: 0 !important;
        padding: 0 !important;
    }
    .dashboard-wrapper {
        display: block !important;
    }
    .card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
    body {
        background: white !important;
        color: black !important;
    }
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
