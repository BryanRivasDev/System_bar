<?php
// logout.php - establece un flash en sesión, limpia credenciales y redirige al login
if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

// permitir tanto POST como GET para compatibilidad
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET') {
	// Establece flash en sesión para mostrar mensaje en login
	$_SESSION['flash_logout'] = 1;

	// Eliminar identificadores de autenticación pero conservar sesión para el flash
	unset($_SESSION['usuario_id'], $_SESSION['nombre'], $_SESSION['rol_id'], $_SESSION['rol_nombre']);

	// Regenera el id de sesión para mitigar session fixation
	if (function_exists('session_regenerate_id')) {
		session_regenerate_id(true);
	}
}

// Redirige al login (sin parámetros en URL)
$redirect = 'login.php';
if (!headers_sent()) {
	header('Location: ' . $redirect);
	exit();
} else {
	// Fallback si los headers ya fueron enviados
	echo '<!doctype html><html><head><meta charset="utf-8"><title>Redirigiendo</title></head><body>';
	echo '<p>Redirigiendo al login...</p>';
	echo '<script>window.location.href="' . $redirect . '";</script>';
	echo '</body></html>';
	exit();
}
?>