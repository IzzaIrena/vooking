<?php
session_start();
include 'koneksi.php';

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

$login_error = [];

if (empty($email)) {
    $login_error['email'] = "Email wajib diisi!";
}

if (empty($password)) {
    $login_error['password'] = "Password wajib diisi!";
}

if (!$login_error) {
    $query = $conn->prepare("SELECT * FROM user WHERE Email = ?");
    $query->bind_param("s", $email);
    $query->execute();
    $result = $query->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['Password'])) {

            if ($row['StatusAkun'] !== 'aktif') {
                $login_error['email'] = "Akun Tidak Aktif!";
            } else {
                // Set session user info
                $_SESSION['IdUser'] = $row['IdUser'];
                $_SESSION['RoleAktif'] = $row['RoleAktif'];
                $_SESSION['Username'] = $row['Username'];

                // Update LastActive
                $update = $conn->prepare("UPDATE user SET LastActive = NOW() WHERE IdUser = ?");
                $update->bind_param("i", $row['IdUser']);
                $update->execute();

                // Redirect berdasarkan role
                if ($row['RoleAktif'] === 'pelanggan') {
                    header("Location: home.php");
                } elseif ($row['RoleAktif'] === 'penyedia') {
                    header("Location: penyedia_home.php"); // Buat nanti
                } else {
                    // Jika role tidak valid
                    $login_error['email'] = "Role pengguna tidak valid.";
                }
                exit();
            }

        } else {
            $login_error['password'] = "Password salah!";
        }
    } else {
        $login_error['email'] = "Email tidak ditemukan!";
    }
}

// Jika ada error, simpan dan kembali ke halaman login
$_SESSION['login_error'] = $login_error;
$_SESSION['show_register'] = false; // tampilkan form login
header("Location: login_register.php");
exit();
?>
