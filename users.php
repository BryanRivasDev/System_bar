<?php
require_once __DIR__ . '/config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Only admin can access
if ($_SESSION['role_id'] != 1) {
    header('Location: dashboard.php');
    exit();
}

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $name = $_POST['name'];
                $email = $_POST['email'];
                $username = $_POST['username'];
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $role_id = $_POST['role_id'];
                
                $stmt = $pdo->prepare('INSERT INTO users (name, email, username, password, role_id, status) VALUES (?, ?, ?, ?, ?, "active")');
                $stmt->execute([$name, $email, $username, $password, $role_id]);
                $success = 'Usuario creado exitosamente';
                break;
                
            case 'update':
                $user_id = $_POST['user_id'];
                $name = $_POST['name'];
                $email = $_POST['email'];
                $role_id = $_POST['role_id'];
                $status = $_POST['status'];
                
                $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ?, role_id = ?, status = ? WHERE id = ?');
                $stmt->execute([$name, $email, $role_id, $status, $user_id]);
                $success = 'Usuario actualizado exitosamente';
                break;
                
            case 'reset_password':
                $user_id = $_POST['user_id'];
                $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
                $stmt->execute([$new_password, $user_id]);
                $success = 'Contraseña actualizada exitosamente';
                break;
                
            case 'delete':
                $user_id = $_POST['user_id'];
                $stmt = $pdo->prepare('UPDATE users SET status = "inactive" WHERE id = ?');
                $stmt->execute([$user_id]);
                $success = 'Usuario desactivado exitosamente';
                break;
                
            case 'create_role':
                $role_name = $_POST['role_name'];
                $stmt = $pdo->prepare('INSERT INTO roles (name) VALUES (?)');
                $stmt->execute([$role_name]);
                header('Location: users.php?tab=roles&success=role_created');
                exit();
                
            case 'update_role':
                $role_id = $_POST['role_id'];
                $role_name = $_POST['role_name'];
                $stmt = $pdo->prepare('UPDATE roles SET name = ? WHERE id = ?');
                $stmt->execute([$role_name, $role_id]);
                header('Location: users.php?tab=roles&success=role_updated');
                exit();
                
            case 'delete_role':
                $role_id = $_POST['role_id'];
                // Check if role is in use
                $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM users WHERE role_id = ?');
                $stmt->execute([$role_id]);
                $count = $stmt->fetch()['count'];
                
                if ($count > 0) {
                    header('Location: users.php?tab=roles&error=role_in_use&count=' . $count);
                    exit();
                } else {
                    $stmt = $pdo->prepare('DELETE FROM roles WHERE id = ?');
                    $stmt->execute([$role_id]);
                    header('Location: users.php?tab=roles&success=role_deleted');
                    exit();
                }
                break;
        }
    }
}

// Get all users
$stmt = $pdo->query('
    SELECT u.*, r.name as role_name 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    ORDER BY u.id DESC
');
$users = $stmt->fetchAll();

// Get all roles
$roles = $pdo->query('SELECT * FROM roles ORDER BY name')->fetchAll();
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
            <li><a href="users.php" class="active">👥 Usuarios</a></li>
            <li><a href="logout.php">🚪 Cerrar Sesión</a></li>
        </ul>
    </aside>
    
    <main class="main-content">
        <div class="page-header">
            <h1>Gestión de Usuarios</h1>
            <p>Administrar usuarios y roles del sistema</p>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                ✅ <?= $success ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                ✅ <?php 
                    if ($_GET['success'] == 'role_created') echo 'Rol creado exitosamente';
                    if ($_GET['success'] == 'role_updated') echo 'Rol actualizado exitosamente';
                    if ($_GET['success'] == 'role_deleted') echo 'Rol eliminado exitosamente';
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                ❌ <?= $error ?>
            </div>
        <?php endif; ?>
        
        <!-- Tabs Navigation -->
        <div class="tabs-nav">
            <button class="tab-btn <?= !isset($_GET['tab']) || $_GET['tab'] == 'users' ? 'active' : '' ?>" onclick="switchTab('users')">👥 Usuarios</button>
            <button class="tab-btn <?= isset($_GET['tab']) && $_GET['tab'] == 'roles' ? 'active' : '' ?>" onclick="switchTab('roles')">🎭 Roles</button>
        </div>
        
        <!-- Users Tab -->
        <div id="users-tab" class="tab-content <?= !isset($_GET['tab']) || $_GET['tab'] == 'users' ? 'active' : '' ?>">
        <div class="card mb-30">
            <div class="card-header">
                <h3>Crear Nuevo Usuario</h3>
            </div>
            <form method="POST" style="padding: 25px;">
                <input type="hidden" name="action" value="create">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Nombre Completo</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Usuario</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Contraseña</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Rol</label>
                        <select name="role_id" class="form-control" required>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">Crear Usuario</button>
            </form>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3>Usuarios del Sistema</h3>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Usuario</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= $user['id'] ?></td>
                                <td><?= htmlspecialchars($user['name']) ?></td>
                                <td><?= htmlspecialchars($user['username']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td>
                                    <span class="badge badge-<?= $user['role_id'] == 1 ? 'primary' : 'secondary' ?>">
                                        <?= htmlspecialchars($user['role_name']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $user['status'] == 'active' ? 'success' : 'danger' ?>">
                                        <?= $user['status'] == 'active' ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="editUser(<?= htmlspecialchars(json_encode($user)) ?>)">
                                        Editar
                                    </button>
                                    <button class="btn btn-sm btn-secondary" onclick="resetPassword(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')">
                                        Reset Pass
                                    </button>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <button class="btn btn-sm btn-danger" onclick="deactivateUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')">
                                            Desactivar
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        </div>
        <!-- End Users Tab -->
        
        <!-- Roles Tab -->
        <div id="roles-tab" class="tab-content <?= isset($_GET['tab']) && $_GET['tab'] == 'roles' ? 'active' : '' ?>">
            <div class="card mb-30">
                <div class="card-header">
                    <h3>Crear Nuevo Rol</h3>
                </div>
                <form method="POST" style="padding: 25px;">
                    <input type="hidden" name="action" value="create_role">
                    
                    <div class="form-group">
                        <label>Nombre del Rol</label>
                        <input type="text" name="role_name" class="form-control" placeholder="Ej: Cajero, Supervisor" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Crear Rol</button>
                </form>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3>Roles del Sistema</h3>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre del Rol</th>
                                <th>Usuarios Asignados</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roles as $role): ?>
                                <?php
                                // Count users with this role
                                $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM users WHERE role_id = ?');
                                $stmt->execute([$role['id']]);
                                $user_count = $stmt->fetch()['count'];
                                ?>
                                <tr>
                                    <td><?= $role['id'] ?></td>
                                    <td><strong><?= htmlspecialchars($role['name']) ?></strong></td>
                                    <td><?= $user_count ?> usuario(s)</td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick="editRole(<?= htmlspecialchars(json_encode($role)) ?>)">
                                            Editar
                                        </button>
                                        <?php if ($user_count == 0): ?>
                                            <button class="btn btn-sm btn-danger" onclick="deleteRole(<?= $role['id'] ?>, '<?= htmlspecialchars($role['name']) ?>')">
                                                Eliminar
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-secondary" disabled title="No se puede eliminar un rol con usuarios asignados">
                                                Eliminar
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!-- End Roles Tab -->
    </main>
</div>

<!-- Edit User Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Editar Usuario</h3>
            <span class="close" onclick="closeModal('editModal')">&times;</span>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="user_id" id="edit_user_id">
            
            <div class="form-group">
                <label>Nombre Completo</label>
                <input type="text" name="name" id="edit_name" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" id="edit_email" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label>Rol</label>
                <select name="role_id" id="edit_role_id" class="form-control" required>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Estado</label>
                <select name="status" id="edit_status" class="form-control" required>
                    <option value="active">Activo</option>
                    <option value="inactive">Inactivo</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary">Actualizar Usuario</button>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="resetModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Restablecer Contraseña</h3>
            <span class="close" onclick="closeModal('resetModal')">&times;</span>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" id="reset_user_id">
            
            <p>Usuario: <strong id="reset_username"></strong></p>
            
            <div class="form-group">
                <label>Nueva Contraseña</label>
                <input type="password" name="new_password" class="form-control" required>
            </div>
            
            <button type="submit" class="btn btn-primary">Restablecer Contraseña</button>
        </form>
    </div>
</div>

<!-- Edit Role Modal -->
<div id="editRoleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Editar Rol</h3>
            <span class="close" onclick="closeModal('editRoleModal')">&times;</span>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update_role">
            <input type="hidden" name="role_id" id="modal_edit_role_id">
            
            <div class="form-group">
                <label>Nombre del Rol</label>
                <input type="text" name="role_name" id="modal_edit_role_name" class="form-control" required>
            </div>
            
            <button type="submit" class="btn btn-primary">Actualizar Rol</button>
        </form>
    </div>
</div>

<style>
.tabs-nav {
    display: flex;
    gap: 10px;
    margin-bottom: 30px;
    border-bottom: 2px solid var(--border-color);
    padding-bottom: 0;
}

.tab-btn {
    padding: 12px 24px;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    color: var(--text-secondary);
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.tab-btn.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
}

.tab-btn:hover {
    color: var(--primary);
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--text-secondary);
}

.mb-30 {
    margin-bottom: 30px;
}

.badge-primary {
    background: var(--primary);
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
}

.modal-content {
    background-color: var(--bg-card);
    margin: 5% auto;
    padding: 0;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
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

.close {
    color: var(--text-secondary);
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover {
    color: var(--danger);
}

.modal form {
    padding: 20px;
}
</style>

<script>
function editUser(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_name').value = user.name;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_role_id').value = user.role_id;
    document.getElementById('edit_status').value = user.status;
    document.getElementById('editModal').style.display = 'block';
}

function resetPassword(userId, username) {
    document.getElementById('reset_user_id').value = userId;
    document.getElementById('reset_username').textContent = username;
    document.getElementById('resetModal').style.display = 'block';
}

function deactivateUser(userId, username) {
    if (confirm(`¿Está seguro de desactivar al usuario "${username}"?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="user_id" value="${userId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function editRole(role) {
    document.getElementById('modal_edit_role_id').value = role.id;
    document.getElementById('modal_edit_role_name').value = role.name;
    document.getElementById('editRoleModal').style.display = 'block';
}

function deleteRole(roleId, roleName) {
    if (confirm(`¿Está seguro de eliminar el rol "${roleName}"?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_role">
            <input type="hidden" name="role_id" value="${roleId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function switchTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active from all buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(tabName + '-tab').classList.add('active');
    event.target.classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
