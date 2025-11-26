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
            case 'add':
                $code = $_POST['code'];
                $name = $_POST['name'];
                $price = $_POST['price'];
                $stock = $_POST['stock'];
                $category_id = $_POST['category_id'] ?: null;
                
                $stmt = $pdo->prepare('INSERT INTO products (code, name, price, stock, category_id) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$code, $name, $price, $stock, $category_id]);
                header('Location: products.php?success=added');
                exit();
                break;
                
            case 'delete':
                $id = $_POST['id'];
                $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
                $stmt->execute([$id]);
                header('Location: products.php?success=deleted');
                exit();
                break;
        }
    }
}

// Get all products
$products = $pdo->query('SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC')->fetchAll();
$categories = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="dashboard-wrapper">
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>🍹 Bar System</h2>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php">📊 Dashboard</a></li>
            <li><a href="products.php" class="active">📦 Inventario</a></li>
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
    
    <main class="main-content">
        <div class="page-header">
            <h1>Gestión de Inventario</h1>
            <p>Administra el inventario de productos</p>
        </div>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <?php 
                    if ($_GET['success'] === 'added') echo '✅ Producto agregado exitosamente';
                    if ($_GET['success'] === 'deleted') echo '✅ Producto eliminado exitosamente';
                ?>
            </div>
        <?php endif; ?>
        

        
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h3>Lista de Inventario</h3>
                <div style="flex: 1; max-width: 400px; margin-left: 20px;">
                    <input type="text" id="searchInput" class="form-control" placeholder="🔍 Buscar por código, nombre o categoría..." style="margin: 0;">
                </div>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Categoría</th>
                            <th>Precio</th>
                            <th>Stock</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?= htmlspecialchars($product['code']) ?></td>
                                <td><?= htmlspecialchars($product['name']) ?></td>
                                <td><?= htmlspecialchars($product['category_name'] ?? 'Sin categoría') ?></td>
                                <td>C$<?= number_format($product['price'], 2) ?></td>
                                <td><?= $product['stock'] ?></td>
                                <td>
                                    <span class="badge badge-<?= $product['status'] === 'active' ? 'success' : 'danger' ?>">
                                        <?= $product['status'] === 'active' ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar este producto?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $product['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<style>
.form-grid {display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; padding: 20px;}
.form-group {display: flex; flex-direction: column;}
.form-group label {margin-bottom: 8px; font-weight: 600; color: var(--text-secondary);}
.form-control {padding: 10px; border: 2px solid var(--border-color); border-radius: 8px; background: var(--bg-primary); color: var(--text-primary);}
.card-header {padding: 20px; border-bottom: 1px solid var(--border-color);}
.card-header h3 {margin: 0; color: var(--text-primary);}
.table-responsive {overflow-x: auto;}
.table {width: 100%; border-collapse: collapse;}
.table th, .table td {padding: 12px; text-align: left; border-bottom: 1px solid var(--border-color);}
.table th {background: var(--bg-primary); color: var(--text-secondary); font-weight: 600;}
.badge {padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;}
.badge-success {background: #10b981; color: white;}
.badge-danger {background: #ef4444; color: white;}
.btn-sm {padding: 6px 12px; font-size: 12px;}
.btn-danger {background: var(--danger); color: white;}
.alert {padding: 15px; border-radius: 8px; margin-bottom: 20px;}
.alert-success {background: #d1fae5; color: #065f46; border-left: 4px solid #10b981;}
</style>

<script>
// Search functionality
document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const tableRows = document.querySelectorAll('.table tbody tr');
    
    tableRows.forEach(row => {
        const code = row.cells[0].textContent.toLowerCase();
        const name = row.cells[1].textContent.toLowerCase();
        const category = row.cells[2].textContent.toLowerCase();
        
        if (code.includes(searchTerm) || name.includes(searchTerm) || category.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
