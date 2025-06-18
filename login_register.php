<?php
session_start();
include 'koneksi.php';

// Tangkap error jika ada
$login_error = $_SESSION['login_error'] ?? [];
$register_error = $_SESSION['register_error'] ?? [];
unset($_SESSION['login_error'], $_SESSION['register_error']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Vooking Login & Register</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
    <link rel="stylesheet" href="style.css" />
    <style>
        .register-panel { display: none; }
        .container.register-mode .login-panel { display: none; }
        .container.register-mode .register-panel { display: flex; }
        .error-message {
            color: red;
            font-size: 12px;
            margin-top: 4px;
            margin-left: 4px;
        }
    </style>
</head>
<body>
    <div class="container <?= isset($_SESSION['show_register']) ? ($_SESSION['show_register'] ? 'register-mode' : '') : '' ?>" id="container">


        <!-- Panel Login -->
        <div class="login-panel">
            <div class="left-panel">
                <img src="Vicon.png" alt="Logo Vooking" class="logo" />
                <h1>VOOKING</h1>
                <p>Yuk, mulai perjalananmu bareng kami. Booking fasilitas jadi makin mudah dan cepat.</p>
                <p class="daftar-teks">Belum punya akun? Daftarkan segera</p>
                <button class="register-btn" onclick="showRegister()">Registrasi Akun</button>
            </div>

            <div class="right-panel">
                <h2>Welcome back to Vooking!</h2>
                <p class="subtext">Book what you need, when you need it – all in one place.</p>

                <form action="login.php" method="post">
                    <div class="form-group">
                        <label>Email</label>
                        <div class="input-wrapper">
                            <i class="fas fa-envelope"></i>
                           <input type="email" name="email" placeholder="Masukkan email" required>
                        </div>
                        <?php if (isset($login_error['email'])): ?>
                            <div class="error-message"><?= $login_error['email'] ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label>Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="password" placeholder="Masukkan password" required>
                        </div>
                        <?php if (isset($login_error['password'])): ?>
                            <div class="error-message"><?= $login_error['password'] ?></div>
                        <?php endif; ?>
                    </div>

                    <button class="login-btn" type="submit">LOGIN</button>
                </form>
            </div>
        </div>

        <!-- Panel Registrasi -->
        <div class="register-panel">
            <div class="left-panel">
                <img src="Vicon.png" alt="Logo Vooking" class="logo" />
                <h1>VOOKING</h1>
                <p>Yuk, mulai perjalananmu bareng kami. Booking fasilitas jadi makin mudah dan cepat.</p>
                <button class="register-btn" onclick="showLogin()">LOGIN</button>
            </div>

            <div class="right-panel">
                <h2 class="register-title">Create your Vooking account</h2>
                <p class="subtext">Join now and start discovering or listing facilities in moments</p>

                <form action="register.php" method="post">
                    <div class="form-group">
                        <label for="nama">Nama</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-user"></i>
                            <input type="text" id="username" name="username" placeholder="Masukkan nama" required />
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="reg-email">Email</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-envelope"></i>
                            <input type="email" id="reg-email" name="email" placeholder="Masukkan email" required />
                        </div>
                        <?php if (isset($register_error['email'])): ?>
                            <div class="error-message"><?= $register_error['email'] ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="reg-password">Password</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-key"></i>
                            <input
                                type="password"
                                id="reg-password" name="password"
                                placeholder="Masukkan password" required
                            />
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm-password">Konfirmasi Password</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-key"></i>
                            <input
                                type="password"
                                id="confirm-password" name="confirm_password"
                                placeholder="Konfirmasi password" required
                            />
                        </div>
                        <?php if (isset($register_error['confirm_password'])): ?>
                            <div class="error-message"><?= $register_error['confirm_password'] ?></div>
                        <?php endif; ?>
                    </div>

                    <button class="login-btn" type="submit">DAFTAR</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showRegister() {
            document.getElementById("container").classList.add("register-mode");
        }
        function showLogin() {
            document.getElementById("container").classList.remove("register-mode");
        }
    </script>
</body>
</html>
