<?php
require_once __DIR__ . '/config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Get report type and date range
$report_type = $_GET['type'] ?? 'sales';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Logic based on report type
if ($report_type === 'sales') {
    // Sales report logic (Existing)
    $stmt = $pdo->prepare('
        SELECT DATE(date_created) as date, COUNT(*) as orders, SUM(total) as total
        FROM orders
        WHERE DATE(date_created) BETWEEN ? AND ? AND status = "completed"
        GROUP BY DATE(date_created)
        ORDER BY date DESC
    ');
    $stmt->execute([$start_date, $end_date]);
    $sales_by_date = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT SUM(total) as total FROM orders WHERE DATE(date_created) BETWEEN ? AND ? AND status = "completed"');
    $stmt->execute([$start_date, $end_date]);
    $total_sales = $stmt->fetch()['total'] ?? 0;

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

    $stmt = $pdo->prepare('
        SELECT method, COUNT(*) as count, SUM(amount) as total
        FROM payments p
        JOIN orders o ON p.order_id = o.id
        WHERE DATE(p.date_created) BETWEEN ? AND ?
        GROUP BY method
    ');
    $stmt->execute([$start_date, $end_date]);
    $payment_methods = $stmt->fetchAll();

} elseif ($report_type === 'inventory') {
    // Inventory report logic (New)
    $stmt = $pdo->query('
        SELECT p.*, c.name as category_name, (p.stock * p.price) as total_value
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.status = "active"
        ORDER BY p.stock ASC
    ');
    $inventory = $stmt->fetchAll();
    
    $total_inventory_value = 0;
    $total_items = 0;
    foreach ($inventory as $item) {
        $total_inventory_value += $item['total_value'];
        $total_items += $item['stock'];
    }

} elseif ($report_type === 'waiters') {
    // Waiters report logic (New)
    // Fetch all waiters for the dropdown
    $waiters_list = $pdo->query('SELECT id, name FROM users WHERE role_id = 2 ORDER BY name')->fetchAll();
    
    $waiter_id = $_GET['waiter_id'] ?? 'all';
    
    $sql = '
        SELECT u.name, 
               COUNT(o.id) as total_orders, 
               COALESCE(SUM(o.total), 0) as total_sales
        FROM users u
        LEFT JOIN orders o ON u.id = o.user_id 
            AND o.status = "completed" 
            AND DATE(o.date_created) BETWEEN ? AND ?
        WHERE u.role_id = 2
    ';
    
    $params = [$start_date, $end_date];
    
    if ($waiter_id !== 'all' && is_numeric($waiter_id)) {
        $sql .= ' AND u.id = ?';
        $params[] = $waiter_id;
    }
    
    $sql .= ' GROUP BY u.id ORDER BY total_sales DESC';
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $waiters_stats = $stmt->fetchAll();
}

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
        <div class="page-header no-print">
            <h1>Reportes</h1>
            <p>Análisis y estadísticas del sistema</p>
        </div>

        <!-- Print Header (Visible only on print) -->
        <div class="print-header">
            <div style="text-align: center; margin-bottom: 20px;">
                <h1 style="font-size: 24px; margin: 0;">🍹 Bar System</h1>
                <p style="margin: 5px 0; font-size: 14px;">Reporte de <?= ucfirst($report_type) ?></p>
                <p style="margin: 5px 0; font-size: 12px; color: #666;">Generado el: <?= date('d/m/Y H:i') ?></p>
                <?php if ($report_type !== 'inventory'): ?>
                    <p style="margin: 5px 0; font-size: 12px;">Período: <?= date('d/m/Y', strtotime($start_date)) ?> - <?= date('d/m/Y', strtotime($end_date)) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Report Navigation Tabs -->
        <div class="report-tabs">
            <a href="?type=sales&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="tab-btn <?= $report_type === 'sales' ? 'active' : '' ?>">📊 Ventas</a>
            <a href="?type=inventory" class="tab-btn <?= $report_type === 'inventory' ? 'active' : '' ?>">📦 Inventario</a>
            <a href="?type=waiters&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="tab-btn <?= $report_type === 'waiters' ? 'active' : '' ?>">👥 Meseros</a>
        </div>
        
        <!-- Date Filter (Only for Sales and Waiters) -->
        <?php if ($report_type !== 'inventory'): ?>
        <div class="card no-print">
            <div class="card-header">
                <h3>Filtrar por Fecha</h3>
            </div>
            <form method="GET" style="padding: 20px; display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                <input type="hidden" name="type" value="<?= $report_type ?>">
                <div class="form-group">
                    <label>Fecha Inicio</label>
                    <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>">
                </div>
                <div class="form-group">
                    <label>Fecha Fin</label>
                    <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>">
                </div>

                <?php if ($report_type === 'waiters' && isset($waiters_list)): ?>
                <div class="form-group">
                    <label>Mesero</label>
                    <select name="waiter_id" class="form-control">
                        <option value="all">Todos</option>
                        <?php foreach ($waiters_list as $waiter): ?>
                            <option value="<?= $waiter['id'] ?>" <?= (isset($_GET['waiter_id']) && $_GET['waiter_id'] == $waiter['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($waiter['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="form-group">
                    <label style="visibility: hidden;">Acciones</label>
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                        <button type="button" onclick="window.print()" class="btn btn-secondary"><span>🖨️</span> Imprimir</button>
                    </div>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- SALES REPORT -->
        <?php if ($report_type === 'sales'): ?>
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
                        <div class="stat-label">Productos Top</div>
                        <div class="stat-value"><?= count($top_products) ?></div>
                    </div>
                </div>
            </div>

            <div class="reports-grid">
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
                                            echo $icons[$method['method']] ?? $method['method'];
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
        <?php endif; ?>

        <!-- INVENTORY REPORT -->
        <?php if ($report_type === 'inventory'): ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: var(--accent);">📊</div>
                    <div class="stat-info">
                        <div class="stat-label">Valor Inventario</div>
                        <div class="stat-value">C$<?= number_format($total_inventory_value, 2) ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: var(--secondary);">📦</div>
                    <div class="stat-info">
                        <div class="stat-label">Total Items</div>
                        <div class="stat-value"><?= $total_items ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: var(--primary);">🏷️</div>
                    <div class="stat-info">
                        <div class="stat-label">Productos Únicos</div>
                        <div class="stat-value"><?= count($inventory) ?></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Estado del Inventario</h3>
                    <button onclick="window.print()" class="btn btn-secondary btn-sm">🖨️ Imprimir</button>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Categoría</th>
                                <th>Precio Unit.</th>
                                <th>Stock</th>
                                <th>Valor Total</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inventory as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['name']) ?></td>
                                    <td><?= htmlspecialchars($item['category_name'] ?? 'Sin Categoría') ?></td>
                                    <td>C$<?= number_format($item['price'], 2) ?></td>
                                    <td>
                                        <?php if ($item['stock'] < 10): ?>
                                            <span class="badge badge-danger"><?= $item['stock'] ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-success"><?= $item['stock'] ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>C$<?= number_format($item['total_value'], 2) ?></td>
                                    <td>
                                        <?php if ($item['stock'] <= 0): ?>
                                            <span style="color: var(--danger);">Agotado</span>
                                        <?php elseif ($item['stock'] < 10): ?>
                                            <span style="color: var(--warning);">Bajo</span>
                                        <?php else: ?>
                                            <span style="color: var(--success);">Normal</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- WAITERS REPORT -->
        <?php if ($report_type === 'waiters'): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Desempeño de Meseros</h3>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Mesero</th>
                                <th>Pedidos Atendidos</th>
                                <th>Ventas Generadas</th>
                                <th>Promedio por Pedido</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($waiters_stats)): ?>
                                <tr><td colspan="4" style="text-align:center;">No hay datos para el periodo seleccionado</td></tr>
                            <?php else: ?>
                                <?php foreach ($waiters_stats as $waiter): ?>
                                    <tr>
                                        <td>
                                            <div style="display:flex; align-items:center; gap:10px;">
                                                <div class="user-avatar" style="width:30px; height:30px; font-size:14px;">
                                                    <?= strtoupper(substr($waiter['name'], 0, 1)) ?>
                                                </div>
                                                <?= htmlspecialchars($waiter['name']) ?>
                                            </div>
                                        </td>
                                        <td><?= $waiter['total_orders'] ?></td>
                                        <td>C$<?= number_format($waiter['total_sales'], 2) ?></td>
                                        <td>
                                            <?php 
                                            $avg = $waiter['total_orders'] > 0 ? $waiter['total_sales'] / $waiter['total_orders'] : 0;
                                            echo 'C$' . number_format($avg, 2);
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    </main>
</div>

<style>
.report-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    border-bottom: 2px solid var(--border-color);
    padding-bottom: 10px;
}

.tab-btn {
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    color: var(--text-secondary);
    font-weight: 600;
    transition: all 0.3s ease;
}

.tab-btn:hover {
    background: var(--bg-secondary);
    color: var(--text-primary);
}

.tab-btn.active {
    background: var(--primary);
    color: white;
}

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

@media print {
    @page {
        margin: 20mm;
        size: auto;
    }

    body {
        background: white !important;
        color: black !important;
        font-family: serif; /* Better for reading on paper */
    }

    .sidebar, 
    .no-print, 
    .report-tabs, 
    .page-header, 
    .btn,
    .form-control,
    .sidebar-menu,
    .user-profile-header {
        display: none !important;
    }

    .main-content {
        margin-left: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        background: white !important;
    }

    .dashboard-wrapper {
        display: block !important;
    }

    .card {
        box-shadow: none !important;
        border: none !important; /* Remove card borders for cleaner look */
        background: white !important;
        margin-bottom: 20px !important;
        padding: 0 !important;
    }

    .card-header {
        border-bottom: 2px solid #000 !important;
        padding: 10px 0 !important;
        margin: 0 0 15px 0 !important;
    }

    .card-header h3 {
        color: black !important;
        font-size: 18px !important;
    }

    .stats-grid {
        grid-template-columns: repeat(3, 1fr) !important; /* Force 3 columns for stats */
        gap: 15px !important;
        margin-bottom: 20px !important;
        page-break-inside: avoid;
    }

    .stat-card {
        border: 1px solid #ddd !important;
        background: white !important;
        padding: 15px !important;
    }

    .stat-value {
        color: black !important;
        font-size: 20px !important;
    }

    .stat-label {
        color: #333 !important;
    }

    .table {
        width: 100% !important;
        border-collapse: collapse !important;
        font-size: 12px !important;
    }

    .table th {
        background: #f0f0f0 !important;
        color: black !important;
        border: 1px solid #000 !important;
        font-weight: bold !important;
    }

    .table td {
        color: black !important;
        border: 1px solid #ddd !important;
    }

    .badge {
        border: 1px solid #000 !important;
        color: black !important;
        background: white !important; /* Remove background colors for badges */
    }

    .print-header {
        display: block !important;
    }

    /* Ensure charts or other complex elements don't break pages awkwardly */
    .reports-grid, .card, .table-responsive {
        page-break-inside: avoid;
    }
}

/* Hide print header on screen */
.print-header {
    display: none;
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
