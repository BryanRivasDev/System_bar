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

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    if ($_GET['ajax'] === 'add_to_order') {
        $product_id = $_POST['product_id'];
        $quantity = $_POST['quantity'];
        
        // Get product details
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        if ($product) {
            // Check if table has an active order
            $stmt = $pdo->prepare('SELECT id FROM orders WHERE table_id = ? AND status = "pending" LIMIT 1');
            $stmt->execute([$table_id]);
            $order = $stmt->fetch();
            
            if (!$order) {
                // Create new order for this table
                $stmt = $pdo->prepare('INSERT INTO orders (table_id, user_id, total, status) VALUES (?, ?, 0, "pending")');
                $stmt->execute([$table_id, $_SESSION['user_id']]);
                $order_id = $pdo->lastInsertId();
            } else {
                $order_id = $order['id'];
            }
            
            // Check if product already in order
            $stmt = $pdo->prepare('SELECT id, quantity FROM order_details WHERE order_id = ? AND product_id = ?');
            $stmt->execute([$order_id, $product_id]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Update quantity
                $stmt = $pdo->prepare('UPDATE order_details SET quantity = quantity + ? WHERE id = ?');
                $stmt->execute([$quantity, $existing['id']]);
            } else {
                // Add new item
                $stmt = $pdo->prepare('INSERT INTO order_details (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)');
                $stmt->execute([$order_id, $product_id, $quantity, $product['price']]);
            }
            
            // Update order total
            $stmt = $pdo->prepare('
                UPDATE orders SET total = (
                    SELECT SUM(quantity * price) FROM order_details WHERE order_id = ?
                ) WHERE id = ?
            ');
            $stmt->execute([$order_id, $order_id]);
            
            // Update table status
            $stmt = $pdo->prepare('UPDATE tables SET status = "occupied" WHERE id = ?');
            $stmt->execute([$table_id]);
        }
        
        echo json_encode(['success' => true]);
        exit();
    }
    
    if ($_GET['ajax'] === 'get_order') {
        $stmt = $pdo->prepare('
            SELECT od.*, p.name as product_name
            FROM order_details od
            JOIN products p ON od.product_id = p.id
            JOIN orders o ON od.order_id = o.id
            WHERE o.table_id = ? AND o.status = "pending"
        ');
        $stmt->execute([$table_id]);
        $items = $stmt->fetchAll();
        
        $stmt = $pdo->prepare('SELECT total FROM orders WHERE table_id = ? AND status = "pending" LIMIT 1');
        $stmt->execute([$table_id]);
        $order = $stmt->fetch();
        
        echo json_encode([
            'items' => $items,
            'total' => $order['total'] ?? 0
        ]);
        exit();
    }
}

// Get products
$products = $pdo->query('SELECT * FROM products WHERE status = "active" AND stock > 0 ORDER BY category_id, name')->fetchAll();

// Get categories
$categories = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();

// Get current order
$stmt = $pdo->prepare('
    SELECT od.*, p.name as product_name
    FROM order_details od
    JOIN products p ON od.product_id = p.id
    JOIN orders o ON od.order_id = o.id
    WHERE o.table_id = ? AND o.status = "pending"
');
$stmt->execute([$table_id]);
$order_items = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT total FROM orders WHERE table_id = ? AND status = "pending" LIMIT 1');
$stmt->execute([$table_id]);
$order = $stmt->fetch();
$order_total = $order['total'] ?? 0;
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
            <li><a href="pos.php" class="active">💳 POS</a></li>
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
        
        <div class="pos-header">
            <div>
                <h1><?= htmlspecialchars($table['name']) ?></h1>
                <p>Agregar productos al pedido</p>
            </div>
            <div class="pos-header-actions">
                <a href="tables.php" class="btn btn-secondary">← Volver a Mesas</a>
                <?php if ($order_total > 0): ?>
                    <a href="view_order.php?table=<?= $table_id ?>" class="btn btn-success">
                        Ver Cuenta (C$<?= number_format($order_total, 2) ?>)
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="pos-modern-layout">
            <!-- Products Section -->
            <div class="products-section-modern">
                <!-- Search Bar -->
                <div class="search-container">
                    <input type="text" 
                           id="searchProduct" 
                           class="search-input" 
                           placeholder="🔍 Buscar producto..."
                           onkeyup="searchProducts()">
                </div>
                
                <div class="categories-bar">
                    <button class="category-btn active" onclick="filterCategory('all')" data-category="all">
                        Todos
                    </button>
                    <?php foreach ($categories as $cat): ?>
                        <button class="category-btn" onclick="filterCategory(<?= $cat['id'] ?>)" data-category="<?= $cat['id'] ?>">
                            <?= htmlspecialchars($cat['name']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                
                <div class="products-grid-modern">
                    <?php foreach ($products as $product): ?>
                        <div class="product-item" data-category="<?= $product['category_id'] ?>" onclick="quickAdd(<?= $product['id'] ?>, '<?= htmlspecialchars($product['name']) ?>')">
                            <div class="product-info">
                                <div class="product-name"><?= htmlspecialchars($product['name']) ?></div>
                                <div class="product-price">C$<?= number_format($product['price'], 2) ?></div>
                            </div>
                            <div class="product-add">+</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Order Summary -->
            <div class="order-summary">
                <div class="summary-header">
                    <h3>Pedido Actual</h3>
                    <span class="item-count" id="item-count"><?= count($order_items) ?> items</span>
                </div>
                
                <div class="order-items" id="order-items">
                    <?php if (empty($order_items)): ?>
                        <div class="empty-order">
                            <p>No hay productos en el pedido</p>
                            <small>Selecciona productos para agregar</small>
                        </div>
                    <?php else: ?>
                        <?php foreach ($order_items as $item): ?>
                            <div class="order-item">
                                <div class="item-details">
                                    <div class="item-name"><?= htmlspecialchars($item['product_name']) ?></div>
                                    <div class="item-price">C$<?= number_format($item['price'], 2) ?> × <?= $item['quantity'] ?></div>
                                </div>
                                <div class="item-total">C$<?= number_format($item['price'] * $item['quantity'], 2) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="order-total">
                    <span>TOTAL</span>
                    <strong id="order-total">C$<?= number_format($order_total, 2) ?></strong>
                </div>
                
                <?php if ($order_total > 0): ?>
                    <a href="view_order.php?table=<?= $table_id ?>" class="btn btn-primary btn-block btn-lg">
                        Procesar Pago
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<style>
.pos-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid var(--border-color);
}

.pos-header h1 {
    margin: 0;
    color: var(--text-primary);
    font-size: 32px;
}

.pos-header p {
    margin: 5px 0 0 0;
    color: var(--text-secondary);
}

.pos-header-actions {
    display: flex;
    gap: 15px;
}

.pos-modern-layout {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 30px;
    height: calc(100vh - 250px);
}

.products-section-modern {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.search-container {
    padding: 0 0 10px 0;
}

.search-input {
    width: 100%;
    padding: 14px 18px;
    border: 2px solid var(--border-color);
    border-radius: 10px;
    background: var(--bg-secondary);
    color: var(--text-primary);
    font-size: 15px;
    transition: all 0.3s ease;
}

.search-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.search-input::placeholder {
    color: var(--text-secondary);
}

.categories-bar {
    display: flex;
    gap: 10px;
    overflow-x: auto;
    padding-bottom: 10px;
}

.category-btn {
    padding: 12px 24px;
    background: var(--bg-secondary);
    border: 2px solid var(--border-color);
    border-radius: 25px;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.3s ease;
    white-space: nowrap;
    font-weight: 600;
}

.category-btn.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.category-btn:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
}

.products-grid-modern {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
    overflow-y: auto;
    padding-right: 10px;
}

.product-item {
    background: var(--bg-card);
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 20px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.product-item:hover {
    transform: translateY(-5px);
    border-color: var(--primary);
    box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3);
}

.product-item:active {
    transform: scale(0.98);
}

.product-info {
    flex: 1;
}

.product-name {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 8px;
    font-size: 16px;
}

.product-price {
    font-size: 20px;
    font-weight: 700;
    color: var(--primary);
}

.product-add {
    width: 40px;
    height: 40px;
    background: var(--primary);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: 700;
}

.order-summary {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 25px;
    display: flex;
    flex-direction: column;
    height: fit-content;
    max-height: calc(100vh - 250px);
}

.summary-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--border-color);
}

.summary-header h3 {
    margin: 0;
    color: var(--text-primary);
}

.item-count {
    background: var(--primary);
    color: white;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
}

.order-items {
    flex: 1;
    overflow-y: auto;
    margin-bottom: 20px;
    max-height: 400px;
}

.empty-order {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-secondary);
}

.empty-order p {
    font-size: 18px;
    margin-bottom: 10px;
}

.order-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    background: var(--bg-secondary);
    border-radius: 8px;
    margin-bottom: 10px;
}

.item-details {
    flex: 1;
}

.item-name {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.item-price {
    font-size: 14px;
    color: var(--text-secondary);
}

.item-total {
    font-size: 18px;
    font-weight: 700;
    color: var(--primary);
}

.order-total {
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

.btn-lg {
    padding: 15px;
    font-size: 18px;
}

.btn-success {
    background: var(--success);
    color: white;
}

.btn-success:hover {
    background: #059669;
}

@media (max-width: 1200px) {
    .pos-modern-layout {
        grid-template-columns: 1fr;
        height: auto;
    }
    
    .order-summary {
        max-height: 500px;
    }
}
</style>

<script>
function quickAdd(productId, productName) {
    const quantity = 1;
    
    fetch('?ajax=add_to_order&table=<?= $table_id ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `product_id=${productId}&quantity=${quantity}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateOrder();
        }
    });
}

function updateOrder() {
    fetch('?ajax=get_order&table=<?= $table_id ?>')
        .then(response => response.json())
        .then(data => {
            const itemsContainer = document.getElementById('order-items');
            const itemCount = document.getElementById('item-count');
            const orderTotal = document.getElementById('order-total');
            
            if (data.items.length === 0) {
                itemsContainer.innerHTML = `
                    <div class="empty-order">
                        <p>No hay productos en el pedido</p>
                        <small>Selecciona productos para agregar</small>
                    </div>
                `;
            } else {
                itemsContainer.innerHTML = data.items.map(item => `
                    <div class="order-item">
                        <div class="item-details">
                            <div class="item-name">${item.product_name}</div>
                            <div class="item-price">C$${parseFloat(item.price).toFixed(2)} × ${item.quantity}</div>
                        </div>
                        <div class="item-total">C$${(item.price * item.quantity).toFixed(2)}</div>
                    </div>
                `).join('');
            }
            
            itemCount.textContent = data.items.length + ' items';
            orderTotal.textContent = '$' + parseFloat(data.total).toFixed(2);
            
            if (data.total > 0 && !document.querySelector('.btn-lg')) {
                location.reload();
            }
        });
}

function filterCategory(categoryId) {
    const products = document.querySelectorAll('.product-item');
    const tabs = document.querySelectorAll('.category-btn');
    
    tabs.forEach(tab => tab.classList.remove('active'));
    event.target.classList.add('active');
    
    document.getElementById('searchProduct').value = '';
    
    products.forEach(product => {
        if (categoryId === 'all' || product.dataset.category == categoryId) {
            product.style.display = 'flex';
        } else {
            product.style.display = 'none';
        }
    });
}

function searchProducts() {
    const searchTerm = document.getElementById('searchProduct').value.toLowerCase();
    const products = document.querySelectorAll('.product-item');
    
    products.forEach(product => {
        const productName = product.querySelector('.product-name').textContent.toLowerCase();
        
        if (productName.includes(searchTerm)) {
            product.style.display = 'flex';
        } else {
            product.style.display = 'none';
        }
    });
    
    if (searchTerm.length > 0) {
        document.querySelectorAll('.category-btn').forEach(tab => tab.classList.remove('active'));
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
