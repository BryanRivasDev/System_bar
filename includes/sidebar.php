<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="index.php">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
            </li>
            
            <?php if (in_array($_SESSION['rol_id'], [1, 2, 3])): ?>
            <li class="nav-item">
                <a class="nav-link" href="modules/ventas/nueva_venta.php">
                    <i class="fas fa-cash-register"></i>
                    Nueva Venta
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (in_array($_SESSION['rol_id'], [1, 2, 3])): ?>
            <li class="nav-item">
                <a class="nav-link" href="modules/ventas/pedidos.php">
                    <i class="fas fa-list"></i>
                    Pedidos
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (in_array($_SESSION['rol_id'], [1, 2])): ?>
            <li class="nav-item">
                <a class="nav-link" href="modules/inventario/productos.php">
                    <i class="fas fa-boxes"></i>
                    Inventario
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (in_array($_SESSION['rol_id'], [1, 2, 3])): ?>
            <li class="nav-item">
                <a class="nav-link" href="modules/caja/index.php">
                    <i class="fas fa-cash-register"></i>
                    Caja
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (in_array($_SESSION['rol_id'], [4, 5])): ?>
            <li class="nav-item">
                <a class="nav-link" href="modules/cocina/pedidos_pendientes.php">
                    <i class="fas fa-utensils"></i>
                    Pedidos Pendientes
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($_SESSION['rol_id'] == 1): ?>
            <li class="nav-item">
                <a class="nav-link" href="modules/usuarios/index.php">
                    <i class="fas fa-users"></i>
                    Usuarios
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (in_array($_SESSION['rol_id'], [1, 2])): ?>
            <li class="nav-item">
                <a class="nav-link" href="modules/reportes/ventas.php">
                    <i class="fas fa-chart-bar"></i>
                    Reportes
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </div>
</nav>