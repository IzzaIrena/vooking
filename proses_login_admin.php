<?php
session_start();
include 'koneksi.php';

$email = $_POST['email'];
$password = $_POST['password'];

$errors = [];

// Cek email di database
$query = "SELECT * FROM admin WHERE Email = '$email'";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) === 1) {
    $data = mysqli_fetch_assoc($result);

    // Bandingkan password dengan hash di database
    if (password_verify($password, $data['Password'])) {
        $_SESSION['admin'] = $data['IdAdmin'];
        $_SESSION['admin_nama'] = $data['NamaAdmin'];
        $_SESSION['AdminLogin'] = true; // ⬅️ tambahkan baris ini
        header("Location: dashboard_admin.php");
        exit;
    } else {
        $errors['password'] = "Password salah!";
    }
} else {
    $errors['email'] = "Email tidak ditemukan!";
}

// Jika ada error, kembalikan ke form login
$_SESSION['admin_login_error'] = $errors;
header("Location: login_admin.php");
exit;
?>
