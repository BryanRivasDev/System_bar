<?php
require_once __DIR__ . '/../../core.php';
checkAuth();
checkPermission([1,2,3]);
?>
<!doctype html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Reportes - <?php echo SITE_NAME; ?></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
	<link href="<?php echo SITE_URL; ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<main class="container">
	<div class="py-4">
		<div class="d-flex justify-content-between align-items-center mb-2">
			<div>
				<h1 class="h3 mb-0"><i class="fas fa-chart-line me-2"></i>Reportes</h1>
				<p class="text-muted mb-0">Accede a los informes y exportaciones disponibles.</p>
			</div>
			<div>
				<a href="<?php echo SITE_URL; ?>/modules/reportes/caja.php" class="btn btn-outline-secondary me-2"><i class="fas fa-cash-register me-1"></i> Abrir Caja</a>
				<a href="<?php echo SITE_URL; ?>/modules/reportes/ventas.php" class="btn btn-outline-secondary me-2"><i class="fas fa-chart-bar me-1"></i> Abrir Ventas</a>
			</div>
		</div>

		<div class="row g-3 mt-3">
			<div class="col-md-4">
				<div class="card h-100 shadow-sm">
					<div class="card-body d-flex">
						<div class="me-3 align-self-start">
							<div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width:56px;height:56px;font-size:20px;">
								<i class="fas fa-cash-register"></i>
							</div>
						</div>
						<div class="flex-grow-1">
							<h5 class="card-title mb-1">Caja</h5>
							<p class="text-muted small mb-2">Ventas por caja y métodos de pago. Ajusta fechas y filtros dentro del reporte.</p>
							<div>
								<a href="<?php echo SITE_URL; ?>/modules/reportes/caja.php" class="btn btn-sm btn-primary me-1">Abrir</a>
								<a href="<?php echo SITE_URL; ?>/modules/reportes/caja.php?export=csv" class="btn btn-sm btn-outline-secondary me-1">CSV</a>
								<a href="<?php echo SITE_URL; ?>/modules/reportes/caja.php?export=pdf" class="btn btn-sm btn-outline-secondary">PDF</a>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="col-md-4">
				<div class="card h-100 shadow-sm">
					<div class="card-body d-flex">
						<div class="me-3 align-self-start">
							<div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center" style="width:56px;height:56px;font-size:20px;">
								<i class="fas fa-chart-bar"></i>
							</div>
						</div>
						<div class="flex-grow-1">
							<h5 class="card-title mb-1">Ventas</h5>
							<p class="text-muted small mb-2">Informe por producto, por pedido y totales en un rango de fechas seleccionado.</p>
							<div>
								<a href="<?php echo SITE_URL; ?>/modules/reportes/ventas.php" class="btn btn-sm btn-primary me-1">Abrir</a>
								<a href="<?php echo SITE_URL; ?>/modules/reportes/ventas.php?export=csv" class="btn btn-sm btn-outline-secondary me-1">CSV</a>
								<a href="<?php echo SITE_URL; ?>/modules/reportes/ventas.php?export=pdf" class="btn btn-sm btn-outline-secondary">PDF</a>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="col-md-4">
				<div class="card h-100 shadow-sm">
					<div class="card-body d-flex">
						<div class="me-3 align-self-start">
							<div class="rounded-circle bg-warning text-white d-flex align-items-center justify-content-center" style="width:56px;height:56px;font-size:20px;">
								<i class="fas fa-boxes"></i>
							</div>
						</div>
						<div class="flex-grow-1">
							<h5 class="card-title mb-1">Inventario</h5>
							<p class="text-muted small mb-2">Consulta el estado de stock y los últimos movimientos de inventario.</p>
							<div>
								<a href="<?php echo SITE_URL; ?>/modules/reportes/inventario.php" class="btn btn-sm btn-primary me-1">Abrir</a>
								<a href="<?php echo SITE_URL; ?>/modules/reportes/inventario.php?export=csv" class="btn btn-sm btn-outline-secondary me-1">CSV</a>
								<a href="<?php echo SITE_URL; ?>/modules/reportes/inventario.php?export=pdf" class="btn btn-sm btn-outline-secondary">PDF</a>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo SITE_URL; ?>/assets/js/script.js"></script>
</body>
</html>

