<?php
require_once __DIR__ . '/../../core.php';
checkAuth();
checkPermission([1,2,3]);

$database = new Database();
$db = $database->getConnection();

$error = '';

$start = isset($_GET['start']) && !empty($_GET['start']) ? $_GET['start'] : date('Y-m-01');
$end   = isset($_GET['end']) && !empty($_GET['end']) ? $_GET['end'] : date('Y-m-d');
$id_caja = isset($_GET['id_caja']) && $_GET['id_caja'] !== '' ? $_GET['id_caja'] : null;

try {
	// Totales por método de pago (solo facturas pagadas)
	$params = [$start, $end];
	$sqlWhere = "DATE(fecha_factura) BETWEEN ? AND ? AND estado = 'pagada'";
	if ($id_caja) { $sqlWhere .= " AND id_caja = ?"; $params[] = $id_caja; }

	$query = "SELECT metodo_pago, COALESCE(SUM(total),0) as total, COUNT(*) as count
			  FROM facturas
			  WHERE $sqlWhere
			  GROUP BY metodo_pago";
	$stmt = $db->prepare($query);
	$stmt->execute($params);
	$ventas_metodo = $stmt->fetchAll(PDO::FETCH_ASSOC);

	// Listado de facturas en rango
	$params2 = [$start, $end];
	$sqlWhere2 = "DATE(fecha_factura) BETWEEN ? AND ?";
	if ($id_caja) { $sqlWhere2 .= " AND id_caja = ?"; $params2[] = $id_caja; }
	$query = "SELECT f.*, p.id_pedido, p.total as pedido_total
			  FROM facturas f
			  LEFT JOIN pedidos p ON f.id_pedido = p.id_pedido
			  WHERE $sqlWhere2
			  ORDER BY f.fecha_factura DESC";
	$stmt = $db->prepare($query);
	$stmt->execute($params2);
	$facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

	// Total general (solo pagadas)
	$params3 = [$start, $end];
	$sqlWhere3 = "DATE(fecha_factura) BETWEEN ? AND ? AND estado = 'pagada'";
	if ($id_caja) { $sqlWhere3 .= " AND id_caja = ?"; $params3[] = $id_caja; }
	$query = "SELECT COALESCE(SUM(total),0) as total FROM facturas WHERE $sqlWhere3";
	$stmt = $db->prepare($query);
	$stmt->execute($params3);
	$total_general = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

	// Export CSV / PDF
	if (isset($_GET['export'])) {
		$export = $_GET['export'];
		if ($export === 'csv') {
			header('Content-Type: text/csv; charset=utf-8');
			header('Content-Disposition: attachment; filename=report_caja_' . $start . '_' . $end . '.csv');
			$out = fopen('php://output', 'w');
			fputcsv($out, ['ID Factura','Numero','Fecha','Caja','Total','Estado','Metodo']);
			foreach ($facturas as $f) {
				fputcsv($out, [$f['id_factura'],$f['numero_factura'],$f['fecha_factura'],$f['id_caja'],$f['total'],$f['estado'],$f['metodo_pago']]);
			}
			exit;
		} elseif ($export === 'pdf') {
			$autoload = __DIR__ . '/../../vendor/autoload.php';
			if (!file_exists($autoload)) {
				$error = 'Para exportar PDF instala Dompdf: ejecuta "composer require dompdf/dompdf" en el proyecto.';
			} else {
				require_once $autoload;
				$html = '<h1>Reporte de Caja ' . htmlspecialchars($start) . ' - ' . htmlspecialchars($end) . '</h1>';
				$html .= '<h3>Total: $' . number_format($total_general,2) . '</h3>';
				$html .= '<table border="1" cellpadding="6" cellspacing="0" width="100%">';
				$html .= '<thead><tr><th>ID</th><th>Numero</th><th>Fecha</th><th>Caja</th><th>Total</th><th>Estado</th><th>Metodo</th></tr></thead><tbody>';
				foreach ($facturas as $f) {
					$html .= '<tr>';
					$html .= '<td>' . $f['id_factura'] . '</td>';
					$html .= '<td>' . htmlspecialchars($f['numero_factura']) . '</td>';
					$html .= '<td>' . htmlspecialchars($f['fecha_factura']) . '</td>';
					$html .= '<td>' . $f['id_caja'] . '</td>';
					$html .= '<td>$' . number_format($f['total'],2) . '</td>';
					$html .= '<td>' . htmlspecialchars($f['estado']) . '</td>';
					$html .= '<td>' . htmlspecialchars($f['metodo_pago']) . '</td>';
					$html .= '</tr>';
				}
				$html .= '</tbody></table>';

				try {
					$dompdf = new \Dompdf\Dompdf();
					$dompdf->loadHtml($html);
					$dompdf->setPaper('A4', 'landscape');
					$dompdf->render();
					$dompdf->stream('report_caja_' . $start . '_' . $end . '.pdf', ['Attachment' => true]);
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
?>
<!doctype html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Reporte de Caja - <?php echo SITE_NAME; ?></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
	<link href="<?php echo SITE_URL; ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<main class="container">
	<div class="py-4">
		<h1>Reporte de Caja</h1>
		<?php if ($error): ?>
			<div class="alert alert-danger"><?php echo $error; ?></div>
		<?php endif; ?>

		<form class="row g-2 mb-3" method="get">
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
				<a class="btn btn-outline-secondary" href="?start=<?php echo $start; ?>&end=<?php echo $end; ?>&export=pdf">Exportar PDF</a>
			</div>
		</form>

		<div class="card mb-3">
			<div class="card-body">
				<h5>Totales por método</h5>
				<div class="row">
					<?php foreach ($ventas_metodo as $vm): ?>
						<div class="col-md-3">
							<div class="p-2 border rounded">
								<strong><?php echo $vm['metodo_pago'] ?: 'Sin especificar'; ?></strong>
								<div>$<?php echo number_format($vm['total'],2); ?></div>
								<small class="text-muted"><?php echo $vm['count']; ?> facturas</small>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
				<hr>
				<h5>Total General: $<?php echo number_format($total_general,2); ?></h5>
			</div>
		</div>

		<div class="card">
			<div class="card-header">Facturas</div>
			<div class="card-body table-responsive">
				<table class="table table-sm">
					<thead>
						<tr><th>#</th><th>Numero</th><th>Fecha</th><th>Caja</th><th>Total</th><th>Estado</th><th>Metodo</th></tr>
					</thead>
					<tbody>
						<?php foreach ($facturas as $f): ?>
						<tr>
							<td><?php echo $f['id_factura']; ?></td>
							<td><?php echo $f['numero_factura']; ?></td>
							<td><?php echo $f['fecha_factura']; ?></td>
							<td><?php echo $f['id_caja']; ?></td>
							<td>$<?php echo number_format($f['total'],2); ?></td>
							<td><?php echo $f['estado']; ?></td>
							<td><?php echo $f['metodo_pago']; ?></td>
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



					if ($id_caja) { $sqlWhere3 .= " AND id_caja = ?"; $params3[] = $id_caja; }
					$query = "SELECT COALESCE(SUM(total),0) as total FROM facturas WHERE $sqlWhere3";
					$stmt = $db->prepare($query);
					$stmt->execute($params3);
					$total_general = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

					// Export CSV / PDF
					if (isset($_GET['export'])) {
						$export = $_GET['export'];
						if ($export === 'csv') {
							header('Content-Type: text/csv; charset=utf-8');
							header('Content-Disposition: attachment; filename=report_caja_' . $start . '_' . $end . '.csv');
							$out = fopen('php://output', 'w');
							fputcsv($out, ['ID Factura','Numero','Fecha','Caja','Total','Estado','Metodo']);
							foreach ($facturas as $f) {
								fputcsv($out, [$f['id_factura'],$f['numero_factura'],$f['fecha_factura'],$f['id_caja'],$f['total'],$f['estado'],$f['metodo_pago']]);
							}
							exit;
						} elseif ($export === 'pdf') {
							$autoload = __DIR__ . '/../../vendor/autoload.php';
							if (!file_exists($autoload)) {
								$error = 'Para exportar PDF instala Dompdf: ejecuta "composer require dompdf/dompdf" en el proyecto.';
							} else {
								require_once $autoload;
								// Generar HTML simple
								$html = '<h1>Reporte de Caja ' . htmlspecialchars($start) . ' - ' . htmlspecialchars($end) . '</h1>';
								$html .= '<h3>Total: $' . number_format($total_general,2) . '</h3>';
								$html .= '<table border="1" cellpadding="6" cellspacing="0" width="100%">';
								$html .= '<thead><tr><th>ID</th><th>Numero</th><th>Fecha</th><th>Caja</th><th>Total</th><th>Estado</th><th>Metodo</th></tr></thead><tbody>';
								foreach ($facturas as $f) {
									$html .= '<tr>';
									$html .= '<td>' . $f['id_factura'] . '</td>';
									$html .= '<td>' . htmlspecialchars($f['numero_factura']) . '</td>';
									$html .= '<td>' . htmlspecialchars($f['fecha_factura']) . '</td>';
									$html .= '<td>' . $f['id_caja'] . '</td>';
									$html .= '<td>$' . number_format($f['total'],2) . '</td>';
									$html .= '<td>' . htmlspecialchars($f['estado']) . '</td>';
									$html .= '<td>' . htmlspecialchars($f['metodo_pago']) . '</td>';
									$html .= '</tr>';
								}
								$html .= '</tbody></table>';

								// Render PDF
								try {
									$dompdf = new \Dompdf\Dompdf();
									$dompdf->loadHtml($html);
									$dompdf->setPaper('A4', 'landscape');
									$dompdf->render();
									$dompdf->stream('report_caja_' . $start . '_' . $end . '.pdf', ['Attachment' => true]);
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

