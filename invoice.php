<?php
require_once __DIR__ . '/config/db.php';
session_start();

// Access control
if (!isset($_SESSION['user_id']) || (!in_array($_SESSION['role_id'], [1, 2]))) {
    header('Location: index.php');
    exit();
}

$invoice_id = $_GET['invoice_id'] ?? null;
if (!$invoice_id) {
    header('Location: tables.php');
    exit();
}

// Fetch invoice details
$stmt = $pdo->prepare('SELECT * FROM invoices WHERE id = ?');
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    die("Factura no encontrada");
}

// Fetch order items
$stmt = $pdo->prepare('SELECT od.*, p.name as product_name FROM order_details od JOIN products p ON od.product_id = p.id WHERE od.order_id = ?');
$stmt->execute([$invoice['order_id']]);
$order_items = $stmt->fetchAll();

// Handle Email Sending
$email_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
    $to = $_POST['email'];
    $subject = "Factura #" . $invoice['id'] . " - Bar System";
    
    // Construct Email Body
    $message = "
    <html>
    <head>
    <title>Factura #{$invoice['id']}</title>
    </head>
    <body>
    <h2>Gracias por su visita</h2>
    <p>Adjuntamos los detalles de su consumo:</p>
    <table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>
        <tr style='background-color: #f2f2f2;'>
            <th>Producto</th>
            <th>Precio</th>
            <th>Cant.</th>
            <th>Subtotal</th>
        </tr>";
    
    foreach ($order_items as $item) {
        $subtotal = number_format($item['price'] * $item['quantity'], 2);
        $price = number_format($item['price'], 2);
        $message .= "
        <tr>
            <td>{$item['product_name']}</td>
            <td>C$ {$price}</td>
            <td>{$item['quantity']}</td>
            <td>C$ {$subtotal}</td>
        </tr>";
    }
    
    $total = number_format($invoice['total'], 2);
    $message .= "
        <tr style='background-color: #333; color: white;'>
            <td colspan='3' align='right'><strong>TOTAL</strong></td>
            <td><strong>C$ {$total}</strong></td>
        </tr>
    </table>
    <p>Fecha: {$invoice['date_created']}</p>
    </body>
    </html>
    ";
    
    // Headers
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: <noreply@barsystem.com>' . "\r\n";
    
    // Send
    if(mail($to, $subject, $message, $headers)) {
        $email_message = '<div class="alert alert-success">✅ Factura enviada correctamente a ' . htmlspecialchars($to) . '</div>';
    } else {
        $email_message = '<div class="alert alert-warning">⚠️ No se pudo enviar el correo. Verifique la configuración del servidor (sendmail/SMTP).</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura #<?php echo $invoice['id']; ?></title>
    <?php include __DIR__ . '/includes/header.php'; ?>
    <style>
        .invoice-wrapper {
            max-width: 800px;
            margin: 40px auto;
            padding: 40px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .invoice-header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f3f4f6;
        }
        .invoice-header h1 {
            color: var(--primary);
            font-size: 32px;
            margin: 0 0 10px 0;
        }
        .invoice-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
            background: #f8fafc;
            padding: 20px;
            border-radius: 12px;
        }
        .info-group label {
            display: block;
            color: #64748b;
            font-size: 14px;
            margin-bottom: 5px;
        }
        .info-group div {
            font-weight: 600;
            color: #1e293b;
            font-size: 16px;
        }
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .invoice-table th {
            background: #f1f5f9;
            color: #475569;
            font-weight: 600;
            padding: 15px;
            text-align: left;
            border-radius: 8px 8px 0 0;
        }
        .invoice-table td {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
        }
        .total-row td {
            background: var(--primary);
            color: white;
            font-weight: 700;
            font-size: 18px;
        }
        .actions-bar {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #f3f4f6;
        }
        .email-form {
            margin-top: 30px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 12px;
            display: none;
        }
        .email-form.active {
            display: block;
        }
        @media print {
            .dashboard-wrapper { display: block; }
            .sidebar, .actions-bar, .email-form, .btn-secondary { display: none !important; }
            .invoice-wrapper { box-shadow: none; margin: 0; padding: 0; }
            body { background: white; }
        }
    </style>
</head>
<body>
<div class="dashboard-wrapper">
    <?php if ($_SESSION['role_id'] == 1): ?>
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
            <li><a href="users.php">👥 Usuarios</a></li>
            <li><a href="logout.php">🚪 Cerrar Sesión</a></li>
        </ul>
    </aside>
    <?php endif; ?>
    
    <main class="main-content" style="<?= $_SESSION['role_id'] == 2 ? 'margin-left: 0;' : '' ?>">
        <div class="invoice-wrapper">
            <?= $email_message ?>
            
            <div class="invoice-header">
                <h1>Factura #<?= str_pad($invoice['id'], 6, '0', STR_PAD_LEFT) ?></h1>
                <p>Comprobante de Consumo</p>
            </div>
            
            <div class="invoice-info">
                <div class="info-group">
                    <label>Fecha y Hora</label>
                    <div><?= date('d/m/Y h:i A', strtotime($invoice['date_created'])) ?></div>
                </div>
                <div class="info-group">
                    <label>Mesa / Cliente</label>
                    <div><?= htmlspecialchars($invoice['table_name']) ?></div>
                </div>
                <div class="info-group">
                    <label>Método de Pago</label>
                    <div>
                        <?php 
                        $methods = ['cash' => 'Efectivo', 'card' => 'Tarjeta', 'transfer' => 'Transferencia'];
                        echo $methods[$invoice['payment_method']] ?? $invoice['payment_method'];
                        ?>
                    </div>
                </div>
                <div class="info-group">
                    <label>Atendido por</label>
                    <div><?= $_SESSION['role_id'] == 1 ? 'Administrador' : 'Mesero' ?></div>
                </div>
            </div>
            
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Precio</th>
                        <th>Cant.</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order_items as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['product_name']) ?></td>
                        <td>C$ <?= number_format($item['price'], 2) ?></td>
                        <td><?= $item['quantity'] ?></td>
                        <td>C$ <?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <td colspan="3" style="text-align: right; border-radius: 0 0 0 8px;">TOTAL</td>
                        <td style="border-radius: 0 0 8px 0;">C$ <?= number_format($invoice['total'], 2) ?></td>
                    </tr>
                </tbody>
            </table>
            
            <div class="actions-bar">
                <button onclick="window.print()" class="btn btn-primary">
                    🖨️ Imprimir
                </button>
                <button onclick="toggleEmailForm()" class="btn btn-secondary">
                    📧 Enviar por Correo
                </button>
                <a href="tables.php" class="btn btn-secondary" style="margin-left: auto;">
                    Volver a Mesas
                </a>
            </div>
            
            <div id="emailForm" class="email-form">
                <h3>Enviar Factura por Correo</h3>
                <form method="POST" style="display: flex; gap: 10px; margin-top: 15px;">
                    <input type="hidden" name="send_email" value="1">
                    <input type="email" name="email" class="form-control" placeholder="correo@cliente.com" required style="flex: 1;">
                    <button type="submit" class="btn btn-primary">Enviar</button>
                </form>
            </div>
        </div>
    </main>
</div>

<script>
function toggleEmailForm() {
    const form = document.getElementById('emailForm');
    form.classList.toggle('active');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
