<?php
require_once __DIR__ . '/../../core.php';
checkAuth();
checkPermission([1,2,3]);

$database = new Database();
$db = $database->getConnection();

$error = '';
try {
	// Lista de productos con stock actual
	$query = "SELECT id_producto, nombre_producto, stock_actual FROM productos ORDER BY nombre_producto";
	$stmt = $db->prepare($query);
	$stmt->execute();
	$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

	// Ultimos movimientos de inventario
	$query = "SELECT i.*, p.nombre_producto FROM inventario i LEFT JOIN productos p ON i.id_producto = p.id_producto ORDER BY i.id_inventario DESC LIMIT 200";
	$stmt = $db->prepare($query);
	$stmt->execute();
	$movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
	$error = $e->getMessage();
}
// Export CSV/PDF para inventario
if (isset($_GET['export'])) {
	$export = $_GET['export'];
	if ($export === 'csv') {
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename=report_inventario_' . date('Ymd') . '.csv');
		$out = fopen('php://output', 'w');
		fputcsv($out, ['ID Producto','Producto','Stock Actual']);
		foreach ($productos as $p) {
			fputcsv($out, [$p['id_producto'], $p['nombre_producto'], $p['stock_actual']]);
		}
		exit;
	}

	if ($export === 'pdf') {
		$autoload = __DIR__ . '/../../vendor/autoload.php';
		if (!file_exists($autoload)) {
			$error = 'Para exportar PDF instala Dompdf: ejecuta "composer require dompdf/dompdf" en el proyecto.';
		} else {
			require_once $autoload;
			$html = '<h1>Reporte de Inventario</h1>';
			$html .= '<h3>Productos</h3>';
			$html .= '<table border="1" cellpadding="6" cellspacing="0" width="100%">';
			$html .= '<thead><tr><th>ID</th><th>Producto</th><th>Stock</th></tr></thead><tbody>';
			foreach ($productos as $p) {
				$html .= '<tr>';
				$html .= '<td>' . $p['id_producto'] . '</td>';
				$html .= '<td>' . htmlspecialchars($p['nombre_producto']) . '</td>';
				$html .= '<td>' . $p['stock_actual'] . '</td>';
				$html .= '</tr>';
			}
			$html .= '</tbody></table>';

			try {
				$dompdf = new Dompdf\Dompdf();
				$dompdf->loadHtml($html);
				$dompdf->setPaper('A4', 'portrait');
				$dompdf->render();
				$dompdf->stream('report_inventario.pdf', ['Attachment' => true]);
				exit;
			} catch (Exception $e) {
				$error = 'Error al generar PDF: ' . $e->getMessage();
			}
		}
	}
}

include __DIR__ . '/../../includes/header.php';
?>

<!doctype html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Reporte de Inventario - <?php echo SITE_NAME; ?></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
	<link href="<?php echo SITE_URL; ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include __DIR__ . '/../../includes/header.php'; ?>
    		<main class="container">
			<div class="py-4">
				<h1>Reporte de Inventario</h1>
				<?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

				<div class="card mb-3">
					<div class="card-header">Productos</div>
					<div class="card-body table-responsive">
						<table class="table table-sm">
							<thead>
								<tr><th>#</th><th>Producto</th><th class="text-end">Stock Actual</th></tr>
							</thead>
							<tbody>
								<?php foreach ($productos as $p): ?>
								<tr>
									<td><?php echo $p['id_producto']; ?></td>
									<td><?php echo $p['nombre_producto']; ?></td>
									<td class="text-end"><?php echo $p['stock_actual']; ?></td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>

				<div class="card">
					<div class="card-header">Últimos movimientos</div>
					<div class="card-body table-responsive">
						<table class="table table-sm">
							<thead>
								<tr><th>#</th><th>Producto</th><th>Tipo</th><th class="text-end">Cantidad</th><th>Stock Anterior</th><th>Stock Actual</th><th>Motivo</th></tr>
							</thead>
							<tbody>
								<?php foreach ($movimientos as $m): ?>
								<tr>
									<td><?php echo $m['id_inventario']; ?></td>
									<td><?php echo $m['nombre_producto']; ?></td>
									<td><?php echo $m['tipo_movimiento']; ?></td>
									<td class="text-end"><?php echo $m['cantidad']; ?></td>
									<td><?php echo $m['stock_anterior']; ?></td>
									<td><?php echo $m['stock_actual']; ?></td>
									<td><?php echo $m['motivo']; ?></td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
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

