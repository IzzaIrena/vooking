<?php
session_start();

if (isset($_SESSION['AdminLogin']) && $_SESSION['AdminLogin'] === true) {
    $redirect = 'login_admin.php';
} else {
    $redirect = 'login_register.php';
}

session_unset();
session_destroy();

header("Location: $redirect");
exit;
?>
