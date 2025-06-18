<?php
session_start();
include 'koneksi.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $register_error = [];

    if ($password !== $confirm) {
        $register_error['confirm_password'] = "Password dan konfirmasi tidak cocok.";
    }

    $cek = $conn->prepare("SELECT * FROM user WHERE Email = ?");
    $cek->bind_param("s", $email);
    $cek->execute();
    $hasil = $cek->get_result();
    if ($hasil->num_rows > 0) {
        $register_error['email'] = "Email sudah terdaftar.";
    }

    if (!empty($register_error)) {
        $_SESSION['register_error'] = $register_error;
        $_SESSION['show_register'] = true;
        header("Location: login_register.php");
        exit();
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO user (Username, Email, Password, RoleAktif) VALUES (?, ?, ?, 'pelanggan')");
    $stmt->bind_param("sss", $username, $email, $hashed_password);

    if ($stmt->execute()) {
        // Ambil ID user yang baru dibuat
        $user_id = $stmt->insert_id;
        
        // Tambahkan ke tabel pelanggan
        $stmt2 = $conn->prepare("INSERT INTO pelanggan (IdUser) VALUES (?)");
        $stmt2->bind_param("i", $user_id);
        $stmt2->execute();

        // Simpan ke sesi untuk login otomatis
        $_SESSION['IdUser'] = $user_id;
        $_SESSION['Username'] = $username;
        $_SESSION['RoleAktif'] = 'pelanggan';

        // Arahkan ke home.php
        header("Location: home.php");
        exit();
    } else {
        $_SESSION['register_error']['email'] = "Terjadi kesalahan saat mendaftar.";
        $_SESSION['show_register'] = true;
        header("Location: login_register.php");
        exit();
    }
} else {
    header("Location: login_register.php");
    exit();
}
