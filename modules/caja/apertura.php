<?php
// modules/caja/apertura.php
require_once '../../core.php';
checkAuth();
checkPermission([1, 2, 3]); // Admin, Gerente, Cajero

$error = '';
$success = '';

// Verificar si ya hay una caja abierta
$database = new Database();
$db = $database->getConnection();
$query = "SELECT COUNT(*) as count FROM caja WHERE estado = 'abierta'";
$stmt = $db->prepare($query);
$stmt->execute();
$caja_abierta = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;

// Procesar apertura de caja
if ($_POST && !$caja_abierta) {
    $monto_inicial = floatval($_POST['monto_inicial']);
    $observaciones = trim($_POST['observaciones']);
    
    if ($monto_inicial >= 0) {
        try {
            $query = "INSERT INTO caja (fecha_apertura, monto_inicial, estado, id_usuario_apertura, observaciones) 
                      VALUES (NOW(), ?, 'abierta', ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$monto_inicial, $_SESSION['usuario_id'], $observaciones]);
            
            $success = "Caja abierta exitosamente con monto inicial: $" . number_format($monto_inicial, 2);
        } catch (Exception $e) {
            $error = "Error al abrir la caja: " . $e->getMessage();
        }
    } else {
        $error = "El monto inicial no puede ser negativo";
    }
}

// Obtener historial de cajas recientes
$query = "SELECT c.*, u.nombre as usuario_apertura, u2.nombre as usuario_cierre
          FROM caja c 
          LEFT JOIN usuarios u ON c.id_usuario_apertura = u.id_usuario 
          LEFT JOIN usuarios u2 ON c.id_usuario_cierre = u2.id_usuario 
          ORDER BY c.fecha_apertura DESC 
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute();
$historial_cajas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apertura de Caja - <?php echo SITE_NAME; ?></title>
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
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            border: none;
            padding: 1.5rem;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            padding: 0.75rem 2rem;
            font-weight: 600;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.4);
        }
        
        .monto-input {
            font-size: 1.5rem;
            font-weight: 600;
            text-align: center;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 1rem;
        }
        
        .monto-input:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        
        .estado-badge {
            font-size: 0.8rem;
            padding: 0.5em 1em;
        }
        
        .historial-item {
            border-left: 4px solid #28a745;
            padding-left: 1rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .historial-item:hover {
            background-color: #f8f9fa;
            transform: translateX(5px);
        }
        
        .alert {
            border: none;
            border-radius: 10px;
            padding: 1rem 1.5rem;
        }
        
        .disabled-card {
            opacity: 0.7;
            pointer-events: none;
        }
        
        .info-box {
            background: linear-gradient(135deg, #17a2b8, #6f42c1);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .info-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.9;
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
                    <i class="fas fa-cash-register me-2"></i>
                    Apertura de Caja
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="../../index.php" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-arrow-left me-1"></i> Volver al Dashboard
                    </a>
                    <a href="cierre.php" class="btn btn-warning">
                        <i class="fas fa-lock me-1"></i> Cierre de Caja
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
            <?php if ($caja_abierta): ?>
                <div class="info-box">
                    <i class="fas fa-info-circle info-icon"></i>
                    <h3>¡Caja Actualmente Abierta!</h3>
                    <p class="mb-0">Ya existe una caja abierta en el sistema. Debes cerrar la caja actual antes de abrir una nueva.</p>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Formulario de Apertura -->
                <div class="col-lg-6">
                    <div class="card <?php echo $caja_abierta ? 'disabled-card' : ''; ?>">
                        <div class="card-header">
                            <h4 class="mb-0">
                                <i class="fas fa-lock-open me-2"></i>
                                Nueva Apertura de Caja
                            </h4>
                        </div>
                        <div class="card-body p-4">
                            <?php if ($caja_abierta): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                                    <h5 class="text-warning">Caja ya está abierta</h5>
                                    <p class="text-muted">No puedes abrir una nueva caja mientras haya una activa.</p>
                                    <a href="cierre.php" class="btn btn-warning">
                                        <i class="fas fa-lock me-1"></i> Ir a Cierre de Caja
                                    </a>
                                </div>
                            <?php else: ?>
                                <form method="POST" id="formApertura">
                                    <div class="mb-4">
                                        <label for="monto_inicial" class="form-label fw-bold">
                                            <i class="fas fa-money-bill-wave me-1"></i>
                                            Monto Inicial de Caja
                                        </label>
                                        <input type="number" 
                                               class="form-control monto-input" 
                                               id="monto_inicial" 
                                               name="monto_inicial" 
                                               step="0.01" 
                                               min="0" 
                                               value="0.00" 
                                               required
                                               placeholder="0.00">
                                        <div class="form-text">
                                            Ingrese el monto con el que inicia la caja hoy.
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
                                                  placeholder="Observaciones sobre la apertura de caja..."></textarea>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <div class="card bg-light">
                                                <div class="card-body text-center">
                                                    <small class="text-muted">Usuario</small>
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
                                        <button type="submit" class="btn btn-success btn-lg">
                                            <i class="fas fa-lock-open me-2"></i>
                                            Abrir Caja
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Información y Historial -->
                <div class="col-lg-6">
                    <!-- Información General -->
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                Información de Apertura
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-6 mb-3">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <i class="fas fa-calendar-day fa-2x text-primary mb-2"></i>
                                            <h6>Fecha Actual</h6>
                                            <div class="fw-bold"><?php echo date('d/m/Y'); ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <i class="fas fa-user fa-2x text-success mb-2"></i>
                                            <h6>Responsable</h6>
                                            <div class="fw-bold"><?php echo $_SESSION['nombre']; ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Importante:</strong> Verifique el monto inicial antes de abrir la caja. 
                                Esta acción no se puede deshacer.
                            </div>
                        </div>
                    </div>

                    <!-- Historial Reciente -->
                    <div class="card">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-history me-2"></i>
                                Historial Reciente de Cajas
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($historial_cajas) > 0): ?>
                                <?php foreach ($historial_cajas as $caja): ?>
                                    <div class="historial-item">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <strong>
                                                    <?php echo date('d/m/Y', strtotime($caja['fecha_apertura'])); ?>
                                                </strong>
                                                <span class="badge bg-<?php echo $caja['estado'] == 'abierta' ? 'success' : 'secondary'; ?> estado-badge ms-2">
                                                    <?php echo ucfirst($caja['estado']); ?>
                                                </span>
                                            </div>
                                            <div class="text-end">
                                                <div class="fw-bold text-success">
                                                    $<?php echo number_format($caja['monto_inicial'], 2); ?>
                                                </div>
                                                <small class="text-muted">
                                                    Inicial
                                                </small>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between text-muted small">
                                            <span>
                                                <i class="fas fa-user me-1"></i>
                                                <?php echo $caja['usuario_apertura']; ?>
                                            </span>
                                            <?php if ($caja['fecha_cierre']): ?>
                                                <span>
                                                    <i class="fas fa-lock me-1"></i>
                                                    <?php echo date('H:i', strtotime($caja['fecha_cierre'])); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($caja['observaciones']): ?>
                                            <div class="mt-2 small">
                                                <i class="fas fa-sticky-note me-1 text-muted"></i>
                                                <?php echo $caja['observaciones']; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted text-center py-3">
                                    <i class="fas fa-inbox fa-2x mb-3 d-block"></i>
                                    No hay historial de cajas
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
            // Formatear monto inicial
            const montoInput = document.getElementById('monto_inicial');
            if (montoInput) {
                montoInput.addEventListener('blur', function() {
                    if (this.value) {
                        this.value = parseFloat(this.value).toFixed(2);
                    }
                });
                
                montoInput.addEventListener('focus', function() {
                    if (this.value === '0.00') {
                        this.value = '';
                    }
                });
            }
            
            // Validación del formulario
            const form = document.getElementById('formApertura');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const monto = parseFloat(montoInput.value);
                    if (monto < 0) {
                        e.preventDefault();
                        alert('El monto inicial no puede ser negativo');
                        montoInput.focus();
                        return false;
                    }
                    
                    if (!confirm('¿Está seguro de que desea abrir la caja con el monto inicial ingresado?')) {
                        e.preventDefault();
                        return false;
                    }
                });
            }
            
            // Auto-focus en el monto inicial
            if (montoInput && !<?php echo $caja_abierta ? 'true' : 'false'; ?>) {
                montoInput.focus();
                montoInput.select();
            }
        });
    </script>
</body>
</html>