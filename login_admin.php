<?php
session_start();
include 'koneksi.php';

// Cek error login admin
$login_error = $_SESSION['admin_login_error'] ?? [];
unset($_SESSION['admin_login_error']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Vooking Admin Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="login_style_admin.css">
</head>
<body>
    <div class="container">
        <!-- Panel kiri -->
        <div class="left-panel">
            <img src="Vicon.png" alt="Logo Vooking" class="logo" />
            <h1>VOOKING</h1>
            <p class="subtext">Admin Panel<br>Powering seamless facility management and smart booking control.</p>
        </div>

        <!-- Panel kanan -->
        <div class="right-panel">
            <h2>Welcome Back, Admin!</h2>
            <p class="subtext">Manage bookings, oversee users, and keep everything running smoothly.<br>
            Please sign in to access the Vooking Admin Dashboard.</p>

            <form action="proses_login_admin.php" method="post">
                <div class="form-group">
                    <label>Email</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" placeholder="Masukkan Email" required>
                    </div>
                    <?php if (isset($login_error['email'])): ?>
                        <div class="error-message"><?= $login_error['email'] ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" placeholder="Masukkan Password" required>
                    </div>
                    <?php if (isset($login_error['password'])): ?>
                        <div class="error-message"><?= $login_error['password'] ?></div>
                    <?php endif; ?>
                </div>

                <div class="button-wrapper">
                    <button class="login-btn" type="submit">LOGIN ADMIN</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
