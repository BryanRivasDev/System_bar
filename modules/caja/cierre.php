<?php
// modules/caja/cierre.php - VERSIÓN INDEPENDIENTE
require_once '../../core.php';
checkAuth();
checkPermission([1, 2, 3]);

$error = '';
$success = '';
$caja_actual = null;

// Obtener caja actual abierta
$database = new Database();
$db = $database->getConnection();

$query = "SELECT c.*, u.nombre as usuario_apertura 
          FROM caja c 
          JOIN usuarios u ON c.id_usuario_apertura = u.id_usuario 
          WHERE c.estado = 'abierta' 
          ORDER BY c.fecha_apertura DESC 
          LIMIT 1";
$stmt = $db->prepare($query);
$stmt->execute();
$caja_actual = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener ventas del día solo para referencia (NO para cálculo)
// Obtener ventas del día solo para referencia (NO para cálculo)
// Obtener ventas del día solo para referencia (NO para cálculo)
$ventas_referencia = [
    'total_ventas' => 0,
    'ventas_efectivo' => 0,
    'ventas_tarjeta' => 0,
    'ventas_transferencia' => 0,
    'debug_info' => '' // Para debugging
];

if ($caja_actual) {
    // Obtener la fecha de apertura de la caja actual
    $fecha_apertura = date('Y-m-d', strtotime($caja_actual['fecha_apertura']));
    $ventas_referencia['debug_info'] .= "📅 Fecha caja: {$fecha_apertura}<br>";
    $ventas_referencia['debug_info'] .= "🆔 ID Caja: {$caja_actual['id_caja']}<br>";
    
    // 1. Total de ventas del día desde facturas
    $query = "SELECT COALESCE(SUM(total), 0) as total 
              FROM facturas 
              WHERE id_caja = ? 
              AND estado = 'pagada'";
    $stmt = $db->prepare($query);
    $stmt->execute([$caja_actual['id_caja']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $ventas_referencia['total_ventas'] = $result['total'];
    $ventas_referencia['debug_info'] .= "💰 Total ventas: $" . number_format($result['total'], 2) . "<br>";
    
    // 2. Ventas por método de pago desde facturas
    $query = "SELECT metodo_pago, COALESCE(SUM(total), 0) as total 
              FROM facturas 
              WHERE id_caja = ? 
              AND estado = 'pagada'
              GROUP BY metodo_pago";
    $stmt = $db->prepare($query);
    $stmt->execute([$caja_actual['id_caja']]);
    $ventas_por_metodo = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $ventas_referencia['debug_info'] .= "💳 Métodos de pago encontrados: " . count($ventas_por_metodo) . "<br>";
    
    foreach ($ventas_por_metodo as $venta) {
        $ventas_referencia['debug_info'] .= "&nbsp;&nbsp;- {$venta['metodo_pago']}: $" . number_format($venta['total'], 2) . "<br>";
        switch($venta['metodo_pago']) {
            case 'efectivo':
                $ventas_referencia['ventas_efectivo'] = $venta['total'];
                break;
            case 'tarjeta':
                $ventas_referencia['ventas_tarjeta'] = $venta['total'];
                break;
            case 'transferencia':
                $ventas_referencia['ventas_transferencia'] = $venta['total'];
                break;
            case 'mixto':
                // Para pagos mixtos, distribuimos proporcionalmente
                $ventas_referencia['ventas_efectivo'] += $venta['total'] * 0.5;
                $ventas_referencia['ventas_tarjeta'] += $venta['total'] * 0.5;
                break;
        }
    }
    
    // 3. Si no hay ventas registradas, mostrar mensaje de debug
    if ($ventas_referencia['total_ventas'] == 0) {
        $ventas_referencia['debug_info'] .= "⚠️ No se encontraron ventas para esta caja<br>";
        
        // Verificar si hay facturas en la caja pero con otro estado
        $query = "SELECT COUNT(*) as total, estado 
                  FROM facturas 
                  WHERE id_caja = ? 
                  GROUP BY estado";
        $stmt = $db->prepare($query);
        $stmt->execute([$caja_actual['id_caja']]);
        $facturas_estado = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($facturas_estado as $factura) {
            $ventas_referencia['debug_info'] .= "&nbsp;&nbsp;Facturas con estado '{$factura['estado']}': {$factura['total']}<br>";
        }
        
        // Verificar si hay pedidos para esta fecha que podrían facturarse
        $query = "SELECT COUNT(*) as total 
                  FROM pedidos 
                  WHERE DATE(fecha_pedido) = ? 
                  AND estado_pedido IN ('entregado', 'listo')";
        $stmt = $db->prepare($query);
        $stmt->execute([$fecha_apertura]);
        $pedidos_sin_facturar = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        if ($pedidos_sin_facturar > 0) {
            $ventas_referencia['debug_info'] .= "📦 Hay {$pedidos_sin_facturar} pedidos sin facturar para esta fecha<br>";
        }
    }
}
// Obtener historial de cajas cerradas recientes
$query = "SELECT c.*, u.nombre as usuario_apertura, u2.nombre as usuario_cierre
          FROM caja c 
          LEFT JOIN usuarios u ON c.id_usuario_apertura = u.id_usuario 
          LEFT JOIN usuarios u2 ON c.id_usuario_cierre = u2.id_usuario 
          WHERE c.estado = 'cerrada'
          ORDER BY c.fecha_cierre DESC 
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute();
$historial_cierre = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cierre de Caja - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .card-header {
            background: linear-gradient(135deg, #dc3545, #e74c3c);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            border: none;
            padding: 1.5rem;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc3545, #e74c3c);
            border: none;
            padding: 0.75rem 2rem;
            font-weight: 600;
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.4);
        }
        
        .monto-input {
            font-size: 1.2rem;
            font-weight: 600;
            text-align: center;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 0.75rem;
        }
        
        .monto-input:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }
        
        .resumen-box {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .referencia-box {
            background: linear-gradient(135deg, #17a2b8, #6f42c1);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .info-box {
            background: linear-gradient(135deg, #17a2b8, #6f42c1);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .historial-item {
            border-left: 4px solid #dc3545;
            padding-left: 1rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .historial-item:hover {
            background-color: #f8f9fa;
            transform: translateX(5px);
        }
        
        .metodo-pago-card {
            border-left: 4px solid;
            margin-bottom: 1rem;
        }
        
        .efectivo { border-left-color: #28a745; }
        .tarjeta { border-left-color: #17a2b8; }
        .transferencia { border-left-color: #6f42c1; }
        
        .alert {
            border: none;
            border-radius: 10px;
            padding: 1rem 1.5rem;
        }
        
        .referencia-text {
            font-size: 0.85rem;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <main class="container-fluid" style="margin-top: 80px;">
        <div class="container">
            <!-- Header -->
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-lock me-2"></i>
                    Cierre de Caja - Conteo Físico
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="../../index.php" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-arrow-left me-1"></i> Volver al Dashboard
                    </a>
                    <a href="apertura.php" class="btn btn-success">
                        <i class="fas fa-lock-open me-1"></i> Apertura de Caja
                    </a>
                </div>
            </div>

            <!-- Alertas -->
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Información de Estado -->
            <?php if (!$caja_actual): ?>
                <div class="info-box">
                    <i class="fas fa-info-circle fa-3x mb-3"></i>
                    <h3>No hay Caja Abierta</h3>
                    <p class="mb-0">No existe una caja abierta en el sistema. Debes abrir una caja antes de poder cerrarla.</p>
                    <div class="mt-3">
                        <a href="apertura.php" class="btn btn-light">
                            <i class="fas fa-lock-open me-1"></i> Abrir Caja
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Formulario de Cierre -->
                <div class="col-lg-6">
                    <?php if ($caja_actual): ?>
                    <div class="card">
                        <div class="card-header">
                            <h4 class="mb-0">
                                <i class="fas fa-lock me-2"></i>
                                Conteo Físico de Caja
                            </h4>
                        </div>
                        <div class="card-body p-4">
                            <!-- Información de Referencia -->
                            <div class="referencia-box">
                                <h5 class="mb-3">
                                    <i class="fas fa-chart-bar me-2"></i>
                                    Ventas del Día (Solo Referencia)
                                </h5>
                                <div class="row text-center">
                                    <div class="col-4">
                                        <small>Total Ventas</small>
                                        <div class="h5 mb-0">$<?php echo number_format($ventas_referencia['total_ventas'], 2); ?></div>
                                    </div>
                                    <div class="col-4">
                                        <small>Monto Inicial</small>
                                        <div class="h5 mb-0">$<?php echo number_format($caja_actual['monto_inicial'], 2); ?></div>
                                    </div>
                                    <div class="col-4">
                                        <small>Diferencia Esperada</small>
                                        <div class="h5 mb-0">$<?php echo number_format($ventas_referencia['total_ventas'], 2); ?></div>
                                    </div>
                                </div>
                                <div class="referencia-text text-center mt-2">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Esta información es solo de referencia para tu control
                                </div>
                            </div>

                            <!-- Métodos de Pago - CONTEO REAL -->
                            <div class="mb-4">
                                <h6 class="fw-bold mb-3">
                                    <i class="fas fa-money-bill-wave me-1"></i>
                                    Montos Reales (Conteo Físico)
                                </h6>
                                
                                <form method="POST" id="formCierre">
                                    <div class="metodo-pago-card efectivo p-3 bg-light rounded">
                                        <div class="row align-items-center">
                                            <div class="col-md-8">
                                                <strong class="text-success">Efectivo en Caja</strong>
                                                <div class="referencia-text">
                                                    Ventas en efectivo: $<?php echo number_format($ventas_referencia['ventas_efectivo'], 2); ?>
                                                </div>
                                                <small class="text-muted">
                                                    Cuente el dinero físico en caja
                                                </small>
                                            </div>
                                            <div class="col-md-4">
                                                <input type="number" 
                                                       class="form-control monto-input" 
                                                       name="monto_efectivo" 
                                                       step="0.01" 
                                                       min="0" 
                                                       value="0.00" 
                                                       required
                                                       placeholder="0.00">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="metodo-pago-card tarjeta p-3 bg-light rounded">
                                        <div class="row align-items-center">
                                            <div class="col-md-8">
                                                <strong class="text-info">Tarjetas</strong>
                                                <div class="referencia-text">
                                                    Ventas con tarjeta: $<?php echo number_format($ventas_referencia['ventas_tarjeta'], 2); ?>
                                                </div>
                                                <small class="text-muted">
                                                    Total de vouchers/transacciones
                                                </small>
                                            </div>
                                            <div class="col-md-4">
                                                <input type="number" 
                                                       class="form-control monto-input" 
                                                       name="monto_tarjeta" 
                                                       step="0.01" 
                                                       min="0" 
                                                       value="<?php echo number_format($ventas_referencia['ventas_tarjeta'], 2); ?>" 
                                                       required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="metodo-pago-card transferencia p-3 bg-light rounded">
                                        <div class="row align-items-center">
                                            <div class="col-md-8">
                                                <strong class="text-primary">Transferencias</strong>
                                                <div class="referencia-text">
                                                    Ventas por transferencia: $<?php echo number_format($ventas_referencia['ventas_transferencia'], 2); ?>
                                                </div>
                                                <small class="text-muted">
                                                    Total de transferencias del día
                                                </small>
                                            </div>
                                            <div class="col-md-4">
                                                <input type="number" 
                                                       class="form-control monto-input" 
                                                       name="monto_transferencia" 
                                                       step="0.01" 
                                                       min="0" 
                                                       value="<?php echo number_format($ventas_referencia['ventas_transferencia'], 2); ?>" 
                                                       required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Total del Cierre -->
                                    <div class="resumen-box mt-4">
                                        <h6 class="mb-3">Total del Cierre</h6>
                                        <div class="row text-center">
                                            <div class="col-12">
                                                <small>Total Reportado (Efectivo + Tarjeta + Transferencia)</small>
                                                <div class="h3 mb-0" id="total-reportado">$0.00</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="observaciones" class="form-label fw-bold">
                                            <i class="fas fa-sticky-note me-1"></i>
                                            Observaciones (Opcional)
                                        </label>
                                        <textarea class="form-control" 
                                                  id="observaciones" 
                                                  name="observaciones" 
                                                  rows="3" 
                                                  placeholder="Observaciones sobre el conteo, billetes grandes, etc..."></textarea>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <div class="card bg-light">
                                                <div class="card-body text-center">
                                                    <small class="text-muted">Usuario Cierre</small>
                                                    <div class="fw-bold"><?php echo $_SESSION['nombre']; ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card bg-light">
                                                <div class="card-body text-center">
                                                    <small class="text-muted">Fecha y Hora</small>
                                                    <div class="fw-bold"><?php echo date('d/m/Y H:i:s'); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-danger btn-lg">
                                            <i class="fas fa-lock me-2"></i>
                                            Cerrar Caja
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Información y Historial -->
                <div class="col-lg-6">
                    <!-- Información de Caja Actual -->
                    <?php if ($caja_actual): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                Información de Caja Actual
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <i class="fas fa-user fa-2x text-success mb-2"></i>
                                            <h6>Usuario Apertura</h6>
                                            <div class="fw-bold"><?php echo $caja_actual['usuario_apertura']; ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <i class="fas fa-clock fa-2x text-primary mb-2"></i>
                                            <h6>Hora Apertura</h6>
                                            <div class="fw-bold"><?php echo date('H:i', strtotime($caja_actual['fecha_apertura'])); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Importante:</strong> Este cierre es por conteo físico. 
                                Ingrese los montos reales contados, independientemente del monto inicial.
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Historial de Cierres -->
                    <div class="card">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-history me-2"></i>
                                Historial de Cierres Recientes
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($historial_cierre) > 0): ?>
                                <?php foreach ($historial_cierre as $caja): ?>
                                    <div class="historial-item">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <strong>
                                                    <?php echo date('d/m/Y', strtotime($caja['fecha_apertura'])); ?>
                                                </strong>
                                                <span class="badge bg-secondary ms-2">
                                                    Cerrada
                                                </span>
                                            </div>
                                            <div class="text-end">
                                                <div class="fw-bold text-success">
                                                    $<?php echo number_format($caja['monto_final'], 2); ?>
                                                </div>
                                                <small class="text-muted">
                                                    Cierre
                                                </small>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between text-muted small">
                                            <span>
                                                <i class="fas fa-user me-1"></i>
                                                <?php echo $caja['usuario_cierre']; ?>
                                            </span>
                                            <span>
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo date('H:i', strtotime($caja['fecha_cierre'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted text-center py-3">
                                    <i class="fas fa-inbox fa-2x mb-3 d-block"></i>
                                    No hay historial de cierres
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Formatear inputs de monto
            const montoInputs = document.querySelectorAll('.monto-input');
            montoInputs.forEach(input => {
                input.addEventListener('blur', function() {
                    if (this.value) {
                        this.value = parseFloat(this.value).toFixed(2);
                    }
                    calcularTotal();
                });
                
                input.addEventListener('input', calcularTotal);
            });
            
            // Calcular total en tiempo real
            function calcularTotal() {
                let totalReportado = 0;
                montoInputs.forEach(input => {
                    totalReportado += parseFloat(input.value) || 0;
                });
                
                document.getElementById('total-reportado').textContent = '$' + totalReportado.toFixed(2);
            }
            
            // Validación del formulario
            const form = document.getElementById('formCierre');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const totalReportado = parseFloat(document.getElementById('total-reportado').textContent.replace('$', ''));
                    
                    let mensaje = '¿Está seguro de que desea cerrar la caja?\n\n';
                    mensaje += 'Total Reportado: $' + totalReportado.toFixed(2) + '\n\n';
                    mensaje += '✅ Este cierre registra solo el conteo físico actual.';
                    
                    if (!confirm(mensaje)) {
                        e.preventDefault();
                        return false;
                    }
                });
            }
            
            // Calcular total inicial
            calcularTotal();
            
            // Auto-focus en efectivo
            const efectivoInput = document.querySelector('input[name="monto_efectivo"]');
            if (efectivoInput) {
                efectivoInput.focus();
            }
        });
    </script>
</body>
</html>