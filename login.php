<?php
// login.php - Versión Mejorada
require_once 'core.php';

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';

if ($_POST) {
    $database = new Database();
    $db = $database->getConnection();
    
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Buscar usuario en la base de datos
    $query = "SELECT u.*, r.nombre_rol 
              FROM usuarios u 
              JOIN roles r ON u.id_rol = r.id_rol 
              WHERE u.email = ? AND u.estado = 'activo'";
    $stmt = $db->prepare($query);
    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verificar credenciales
    if ($usuario) {
        // En un sistema real, usaríamos password_verify()
        // Para desarrollo, comparación directa
        if ($password === 'admin123') { // Cambiar por password_verify() en producción
            $_SESSION['usuario_id'] = $usuario['id_usuario'];
            $_SESSION['nombre'] = $usuario['nombre'];
            $_SESSION['rol_id'] = $usuario['id_rol'];
            $_SESSION['rol_nombre'] = $usuario['nombre_rol'];
            
            // Actualizar último login
            $updateQuery = "UPDATE usuarios SET ultimo_login = NOW() WHERE id_usuario = ?";
            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->execute([$usuario['id_usuario']]);
            
            header("Location: index.php");
            exit();
        } else {
            $error = "Contraseña incorrecta";
        }
    } else {
        $error = "Usuario no encontrado o inactivo";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #34495e;
            --accent: #e74c3c;
        }
        
        .login-container {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><polygon fill="%23ffffff10" points="0,1000 1000,0 1000,1000"/></svg>');
            background-size: cover;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
            max-width: 420px;
            width: 100%;
            animation: slideUp 0.6s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-header {
            background: linear-gradient(135deg, var(--accent), #c0392b);
            color: white;
            padding: 2.5rem 2rem;
            text-align: center;
            position: relative;
        }
        
        .login-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #f1c40f, #e74c3c, #9b59b6);
        }
        
        .logo {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
        }
        
        .login-body {
            padding: 2.5rem 2rem;
        }
        
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 0.2rem rgba(231, 76, 60, 0.1);
        }
        
        .input-group {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            z-index: 5;
        }
        
        .input-with-icon {
            padding-left: 45px;
        }
        
        .btn-login {
            background: linear-gradient(135deg, var(--accent), #c0392b);
            border: none;
            border-radius: 12px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.25rem;
        }
        
        .demo-credentials {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1.25rem;
            margin-top: 1.5rem;
            border-left: 4px solid var(--accent);
        }
        
        .demo-title {
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .demo-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .demo-item:last-child {
            border-bottom: none;
        }
        
        .demo-label {
            font-weight: 500;
            color: #6c757d;
        }
        
        .demo-value {
            font-weight: 600;
            color: var(--primary);
            font-family: 'Courier New', monospace;
        }
        
        .copy-btn {
            background: none;
            border: none;
            color: var(--accent);
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 4px;
            transition: all 0.2s ease;
        }
        
        .copy-btn:hover {
            background: rgba(231, 76, 60, 0.1);
        }
        
        .system-info {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e9ecef;
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        .floating-shapes {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
        }
        
        .shape {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }
        
        .shape:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 10%;
            left: 10%;
            animation-delay: 0s;
        }
        
        .shape:nth-child(2) {
            width: 120px;
            height: 120px;
            top: 60%;
            right: 10%;
            animation-delay: 2s;
        }
        
        .shape:nth-child(3) {
            width: 60px;
            height: 60px;
            bottom: 20%;
            left: 20%;
            animation-delay: 4s;
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0) rotate(0deg);
            }
            50% {
                transform: translateY(-20px) rotate(180deg);
            }
        }
        
        @media (max-width: 576px) {
            .login-card {
                margin: 1rem;
            }
            
            .login-body {
                padding: 2rem 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Floating Shapes Background -->
        <div class="floating-shapes">
            <div class="shape"></div>
            <div class="shape"></div>
            <div class="shape"></div>
        </div>
        
        <div class="login-card">
            <!-- Header -->
            <div class="login-header">
                <div class="logo">
                    <i class="fas fa-cocktail"></i>
                </div>
                <h1 class="h3 mb-2"><?php echo SITE_NAME; ?></h1>
                <p class="mb-0 opacity-75">Sistema de Gestión Integral</p>
            </div>
            
            <!-- Login Form -->
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if (!empty($_SESSION['flash_logout'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        Sesión cerrada correctamente.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['flash_logout']); ?>
                <?php endif; ?>
                
                <form method="POST" id="loginForm">
                    <!-- Email Field -->
                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold">Correo Electrónico</label>
                        <div class="input-group">
                            <i class="fas fa-envelope input-icon"></i>
                            <input type="email" 
                                   class="form-control input-with-icon" 
                                   id="email" 
                                   name="email" 
                                   placeholder="usuario@ejemplo.com" 
                                   value="admin@bar.com"
                                   required
                                   autocomplete="email">
                        </div>
                    </div>
                    
                    <!-- Password Field -->
                    <div class="mb-4">
                        <label for="password" class="form-label fw-semibold">Contraseña</label>
                        <div class="input-group">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" 
                                   class="form-control input-with-icon" 
                                   id="password" 
                                   name="password" 
                                   placeholder="Ingresa tu contraseña" 
                                   value="admin123"
                                   required
                                   autocomplete="current-password">
                        </div>
                    </div>
                    
                    <!-- Submit Button -->
                    <div class="d-grid">
                        <button type="submit" class="btn btn-login">
                            <i class="fas fa-sign-in-alt me-2"></i>
                            Iniciar Sesión
                        </button>
                    </div>
                </form>
                
                <!-- Demo Credentials -->
                <div class="demo-credentials">
                    <div class="demo-title">
                        <i class="fas fa-info-circle me-1"></i>
                        Credenciales de Demo
                    </div>
                    
                    <div class="demo-item">
                        <span class="demo-label">Usuario:</span>
                        <div>
                            <span class="demo-value">admin@bar.com</span>
                            <button class="copy-btn ms-2" onclick="copyToClipboard('admin@bar.com')">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="demo-item">
                        <span class="demo-label">Contraseña:</span>
                        <div>
                            <span class="demo-value">admin123</span>
                            <button class="copy-btn ms-2" onclick="copyToClipboard('admin123')">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- System Info -->
                <div class="system-info">
                    <i class="fas fa-shield-alt me-1"></i>
                    Sistema seguro • <?php echo date('Y'); ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para copiar al portapapeles
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // Mostrar feedback visual
                const buttons = document.querySelectorAll('.copy-btn');
                buttons.forEach(btn => {
                    const icon = btn.querySelector('i');
                    if (icon.classList.contains('fa-copy')) {
                        icon.className = 'fas fa-check text-success';
                        setTimeout(() => {
                            icon.className = 'fas fa-copy';
                        }, 2000);
                    }
                });
            }).catch(function(err) {
                console.error('Error al copiar: ', err);
            });
        }
        
        // Animación de entrada para los campos
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.form-control');
            inputs.forEach((input, index) => {
                input.style.animationDelay = (index * 0.1) + 's';
                input.classList.add('animate__animated', 'animate__fadeInUp');
            });
            
            // Auto-seleccionar contenido de los campos demo
            const emailField = document.getElementById('email');
            const passwordField = document.getElementById('password');
            
            emailField.addEventListener('focus', function() {
                this.select();
            });
            
            passwordField.addEventListener('focus', function() {
                this.select();
            });
            
            // Validación del formulario
            const form = document.getElementById('loginForm');
            form.addEventListener('submit', function(e) {
                const email = emailField.value.trim();
                const password = passwordField.value;
                
                if (!email || !password) {
                    e.preventDefault();
                    alert('Por favor, complete todos los campos');
                    return false;
                }
            });
        });
        
        // Efecto de partículas al hacer hover en el botón
        const loginBtn = document.querySelector('.btn-login');
        if (loginBtn) {
            loginBtn.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px) scale(1.02)';
            });
            
            loginBtn.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        }
    </script>
</body>
</html>