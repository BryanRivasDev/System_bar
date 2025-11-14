<?php
require_once __DIR__ . '/../../core.php';
checkAuth();
checkPermission([1,2,3]);

$database = new Database();
$db = $database->getConnection();

$error = '';
$start = $_GET['start'] ?? date('Y-m-d');
$end = $_GET['end'] ?? date('Y-m-d');

try {
	// Ventas por producto (usar facturas pagadas)
	$query = "SELECT dp.id_producto, p.nombre_producto, SUM(dp.cantidad) as cantidad, SUM(dp.subtotal) as total
			  FROM detalle_pedidos dp
			  JOIN productos p ON dp.id_producto = p.id_producto
			  JOIN pedidos pe ON dp.id_pedido = pe.id_pedido
			  JOIN facturas f ON pe.id_pedido = f.id_pedido
			  WHERE f.estado = 'pagada' AND DATE(f.fecha_factura) BETWEEN ? AND ?
			  GROUP BY dp.id_producto, p.nombre_producto
			  ORDER BY total DESC";
	$stmt = $db->prepare($query);
	$stmt->execute([$start, $end]);
	$ventas_productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

	// Total ventas en rango
	$query = "SELECT COALESCE(SUM(total),0) as total FROM facturas WHERE estado = 'pagada' AND DATE(fecha_factura) BETWEEN ? AND ?";
	$stmt = $db->prepare($query);
	$stmt->execute([$start, $end]);
	$total_general = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

	// Manejo de exportaciones
	if (isset($_GET['export'])) {
		$export = $_GET['export'];
		if ($export === 'csv') {
			header('Content-Type: text/csv; charset=utf-8');
			header('Content-Disposition: attachment; filename=report_ventas_' . $start . '_' . $end . '.csv');
			$out = fopen('php://output', 'w');
			fputcsv($out, ['ID Producto','Producto','Cantidad','Total']);
			foreach ($ventas_productos as $v) {
				fputcsv($out, [$v['id_producto'],$v['nombre_producto'],$v['cantidad'],$v['total']]);
			}
			exit;
		}

		if ($export === 'pdf') {
			$autoload = __DIR__ . '/../../vendor/autoload.php';
			if (!file_exists($autoload)) {
				$error = 'Para exportar PDF instala Dompdf: ejecuta "composer require dompdf/dompdf" en el proyecto.';
			} else {
				require_once $autoload;
				$html = '<h1>Reporte de Ventas ' . htmlspecialchars($start) . ' - ' . htmlspecialchars($end) . '</h1>';
				$html .= '<h3>Total Ventas: $' . number_format($total_general,2) . '</h3>';
				$html .= '<table border="1" cellpadding="6" cellspacing="0" width="100%">';
				$html .= '<thead><tr><th>ID</th><th>Producto</th><th>Cantidad</th><th>Total</th></tr></thead><tbody>';
				foreach ($ventas_productos as $v) {
					$html .= '<tr>';
					$html .= '<td>' . $v['id_producto'] . '</td>';
					$html .= '<td>' . htmlspecialchars($v['nombre_producto']) . '</td>';
					$html .= '<td>' . $v['cantidad'] . '</td>';
					$html .= '<td>$' . number_format($v['total'],2) . '</td>';
					$html .= '</tr>';
				}
				$html .= '</tbody></table>';

				try {
					$dompdf = new Dompdf\Dompdf();
					$dompdf->loadHtml($html);
					$dompdf->setPaper('A4', 'portrait');
					$dompdf->render();
					$dompdf->stream('report_ventas_' . $start . '_' . $end . '.pdf', ['Attachment' => true]);
					exit;
				} catch (Exception $e) {
					$error = 'Error al generar PDF: ' . $e->getMessage();
				}
			}
		}

}

} catch (Exception $e) {
	$error = $e->getMessage();
}

include __DIR__ . '/../../includes/header.php';
?>
<!doctype html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Reporte de Ventas - <?php echo SITE_NAME; ?></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
	<link href="<?php echo SITE_URL; ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<main class="container">
	<div class="py-4">
		<h1>Reporte de Ventas</h1>
		<?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

		<form class="row g-2 mb-3">
			<div class="col-auto">
				<label class="form-label">Desde</label>
				<input type="date" name="start" class="form-control" value="<?php echo htmlspecialchars($start); ?>">
			</div>
			<div class="col-auto">
				<label class="form-label">Hasta</label>
				<input type="date" name="end" class="form-control" value="<?php echo htmlspecialchars($end); ?>">
			</div>
			<div class="col-auto align-self-end">
				<button class="btn btn-primary">Filtrar</button>
				<a class="btn btn-outline-secondary" href="?start=<?php echo $start; ?>&end=<?php echo $end; ?>&export=csv">Exportar CSV</a>
			</div>
		</form>

		<div class="card mb-3">
			<div class="card-body">
				<h5>Total Ventas: $<?php echo number_format($total_general,2); ?></h5>
			</div>
		</div>

		<div class="card">
			<div class="card-header">Ventas por Producto</div>
			<div class="card-body table-responsive">
				<table class="table table-sm">
					<thead>
						<tr><th>#</th><th>Producto</th><th class="text-center">Cantidad</th><th class="text-end">Total</th></tr>
					</thead>
					<tbody>
						<?php foreach ($ventas_productos as $v): ?>
						<tr>
							<td><?php echo $v['id_producto']; ?></td>
							<td><?php echo $v['nombre_producto']; ?></td>
							<td class="text-center"><?php echo $v['cantidad']; ?></td>
							<td class="text-end">$<?php echo number_format($v['total'],2); ?></td>
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

