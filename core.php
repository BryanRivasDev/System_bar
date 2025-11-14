<?php
// core.php - Manejo centralizado de sesiones y configuraciones

// Iniciar sesión solo una vez en toda la aplicación
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Definir constantes globales
define('SITE_NAME', 'Sistema Bar');
define('SITE_URL', 'http://localhost/System_bar');

// Incluir configuración de base de datos
require_once 'config/database.php';

// Funciones de autenticación
function checkAuth() {
    if (!isset($_SESSION['usuario_id'])) {
        header("Location: " . SITE_URL . "/login.php");
        exit();
    }
}

function checkPermission($rolesPermitidos) {
    if (!isset($_SESSION['rol_id']) || !in_array($_SESSION['rol_id'], $rolesPermitidos)) {
        header("Location: " . SITE_URL . "/index.php?error=permiso");
        exit();
    }
}

// Función para debug
function debug($data) {
    echo "<pre>";
    print_r($data);
    echo "</pre>";
}
?>