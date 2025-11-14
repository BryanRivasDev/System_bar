<?php
// includes/header.php
// Este archivo asume que la sesión ya fue iniciada en 'core.php'
?>
<!-- Inline critical navbar styles (applied after site CSS to ensure immediate effect) -->
<style>
    .navbar-custom { background: #ffffff !important; }
    .navbar-custom .nav-link, .navbar-custom .navbar-brand, .navbar-custom i { color: #1e88ff !important; opacity: 1 !important; }
    .navbar-custom .nav-link.active, .navbar-custom .nav-link:hover { color: #1565c0 !important; background: rgba(30,136,255,0.06) !important; }
    .navbar-custom .text-muted, .navbar-custom small { color: #6b7280 !important; }
</style>
<nav class="navbar navbar-expand-lg navbar-custom shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="<?php echo SITE_URL; ?>/index.php">
            <i class="fas fa-cocktail me-2"></i>
            <span><?php echo defined('SITE_NAME') ? SITE_NAME : 'Sistema Bar'; ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/index.php"><i class="fas fa-tachometer-alt me-1"></i>Dashboard</a>
                </li>

                <?php if (isset($_SESSION['rol_id']) && in_array($_SESSION['rol_id'], [1,2,3])): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], 'ventas') !== false ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/modules/ventas/nueva_venta.php"><i class="fas fa-cash-register me-1"></i>Nueva Venta</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo SITE_URL; ?>/modules/ventas/pedidos.php"><i class="fas fa-list me-1"></i>Pedidos</a>
                </li>
                <?php endif; ?>

                <?php if (isset($_SESSION['rol_id']) && in_array($_SESSION['rol_id'], [1,2])): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo SITE_URL; ?>/modules/inventario/productos.php"><i class="fas fa-boxes me-1"></i>Inventario</a>
                </li>
                <?php endif; ?>

                <?php if (isset($_SESSION['rol_id']) && in_array($_SESSION['rol_id'], [1,2,3])): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo SITE_URL; ?>/modules/caja/index.php"><i class="fas fa-cash-register me-1"></i>Caja</a>
                </li>
                <?php endif; ?>

                <?php if (isset($_SESSION['rol_id']) && $_SESSION['rol_id'] == 1): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo SITE_URL; ?>/modules/usuarios/index.php"><i class="fas fa-users me-1"></i>Usuarios</a>
                </li>
                <?php endif; ?>

                <?php if (isset($_SESSION['rol_id']) && in_array($_SESSION['rol_id'], [1,2])): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo SITE_URL; ?>/modules/reportes/index.php"><i class="fas fa-chart-bar me-1"></i>Reportes</a>
                </li>
                <?php endif; ?>

                <?php if (isset($_SESSION['rol_id']) && in_array($_SESSION['rol_id'], [4,5])): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo SITE_URL; ?>/modules/cocina/pedidos_pendientes.php"><i class="fas fa-utensils me-1"></i>Cocina/Bar</a>
                </li>
                <?php endif; ?>
            </ul>

            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <?php if (isset($_SESSION['usuario_id'])): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="avatar rounded-circle bg-secondary text-white d-inline-flex justify-content-center align-items-center me-2" style="width:34px;height:34px;font-size:0.9rem;"><?php echo strtoupper(substr($_SESSION['nombre'],0,1)); ?></span>
                        <span class="d-none d-lg-inline"><?php echo htmlspecialchars($_SESSION['nombre']); ?></span>
                        <small class="text-muted ms-2 d-none d-lg-inline">(<?php echo htmlspecialchars($_SESSION['rol_nombre']); ?>)</small>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/modules/usuarios/editar.php?id=<?php echo isset($_SESSION['usuario_id'])?intval($_SESSION['usuario_id']):0; ?>"><i class="fas fa-user-circle me-2"></i>Perfil</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#logoutModal"><i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión</button>
                        </li>
                    </ul>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo SITE_URL; ?>/login.php"><i class="fas fa-sign-in-alt me-1"></i>Iniciar Sesión</a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<!-- Logout Confirmation Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="logoutModalLabel">Confirmar cierre de sesión</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                ¿Seguro que deseas cerrar sesión?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form action="<?php echo SITE_URL; ?>/logout.php" method="post" style="margin:0;">
                    <button type="submit" class="btn btn-danger">Cerrar Sesión</button>
                </form>
            </div>
        </div>
    </div>
</div>