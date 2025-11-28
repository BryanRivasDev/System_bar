<?php
require_once __DIR__ . '/config/db.php';
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 5)) {
    header('Location: index.php');
    exit();
}

$success_count = 0;
$error_msg = '';

// Handle Bulk Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['products'])) {
    try {
        $pdo->beginTransaction();
        
        $products = $_POST['products'];
        
        foreach ($products as $index => $prod) {
            // Skip empty rows
            if (empty($prod['name']) || empty($prod['price'])) {
                continue;
            }
            
            // 1. Handle Category
            $category_id = null;
            if (!empty($prod['category'])) {
                $cat_name = trim($prod['category']);
                
                // Check if category exists
                $stmt = $pdo->prepare('SELECT id FROM categories WHERE name = ?');
                $stmt->execute([$cat_name]);
                $cat = $stmt->fetch();
                
                if ($cat) {
                    $category_id = $cat['id'];
                } else {
                    // Create new category
                    $stmt = $pdo->prepare('INSERT INTO categories (name) VALUES (?)');
                    $stmt->execute([$cat_name]);
                    $category_id = $pdo->lastInsertId();
                }
            }
            
            // 2. Handle Code (Auto-generate if empty)
            $code = trim($prod['code']);
            if (empty($code)) {
                // Generate code: PROD-{ID} (We need next ID approx)
                // Simple random fallback to avoid collision in this batch
                $code = 'PROD-' . strtoupper(substr(md5(uniqid()), 0, 6));
            }
            
            // 3. Insert Product
            $stmt = $pdo->prepare('INSERT INTO products (code, name, price, stock, category_id, status) VALUES (?, ?, ?, ?, ?, "active")');
            $stmt->execute([
                $code,
                trim($prod['name']),
                $prod['price'],
                $prod['stock'] ?? 0,
                $category_id
            ]);
            
            $success_count++;
        }
        
        $pdo->commit();
        header("Location: products.php?success=bulk_added&count=$success_count");
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_msg = 'Error al guardar productos: ' . $e->getMessage();
    }
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
            <li><a href="reports.php">📈 Reportes</a></li>
            <li><a href="users.php">👥 Usuarios</a></li>
            <li><a href="settings.php" class="active">⚙️ Configuración</a></li>
            <li><a href="logout.php">🚪 Cerrar Sesión</a></li>
        </ul>
    </aside>
    
    <main class="main-content">
        <div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1>Inicialización de Menú</h1>
                <p>Ingresa múltiples productos a la vez</p>
            </div>
            <a href="settings.php" class="btn btn-secondary"><span>🔙</span> Volver</a>
        </div>
        
        <?php if ($error_msg): ?>
            <div class="alert alert-danger">❌ <?= $error_msg ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h3>Ingreso Masivo</h3>
                <button type="button" onclick="addRow()" class="btn btn-sm btn-primary">➕ Agregar Fila</button>
            </div>
            
            <form method="POST" id="bulkForm">
                <div class="table-responsive">
                    <table class="table" id="productsTable">
                        <thead>
                            <tr>
                                <th>Código (Opcional)</th>
                                <th>Nombre del Producto *</th>
                                <th>Categoría (Crear/Seleccionar)</th>
                                <th>Precio *</th>
                                <th>Stock Inicial</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Rows will be added here -->
                        </tbody>
                    </table>
                </div>
                
                <div style="padding: 20px; text-align: right; border-top: 1px solid var(--border-color);">
                    <button type="submit" class="btn btn-success btn-lg">💾 Guardar Todos los Productos</button>
                </div>
            </form>
        </div>
    </main>
</div>

<style>
.table input {
    width: 100%;
    padding: 8px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    background: var(--bg-primary);
    color: var(--text-primary);
}
.btn-remove {
    background: var(--danger);
    color: white;
    border: none;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>

<script>
function addRow() {
    const tbody = document.querySelector('#productsTable tbody');
    const index = tbody.children.length;
    
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>
            <input type="text" name="products[${index}][code]" placeholder="Auto">
        </td>
        <td>
            <input type="text" name="products[${index}][name]" required placeholder="Ej: Cerveza Toña">
        </td>
        <td>
            <input type="text" name="products[${index}][category]" list="categories" placeholder="Ej: Cervezas">
        </td>
        <td>
            <input type="number" step="0.01" name="products[${index}][price]" required placeholder="0.00">
        </td>
        <td>
            <input type="number" name="products[${index}][stock]" value="100">
        </td>
        <td>
            <button type="button" class="btn-remove" onclick="this.closest('tr').remove()">×</button>
        </td>
    `;
    tbody.appendChild(tr);
}

// Add 5 rows by default
for(let i=0; i<5; i++) addRow();
</script>

<!-- Datalist for existing categories -->
<datalist id="categories">
    <?php
    $cats = $pdo->query('SELECT name FROM categories ORDER BY name')->fetchAll();
    foreach ($cats as $cat) {
        echo '<option value="' . htmlspecialchars($cat['name']) . '">';
    }
    ?>
</datalist>

<?php include __DIR__ . '/includes/footer.php'; ?>
