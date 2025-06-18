<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['IdUser']) || $_SESSION['RoleAktif'] !== 'penyedia') {
    header("Location: login_register.php");
    exit();
}

if (isset($_GET['id'])) {
    $idFasilitas = (int) $_GET['id'];
    $idUser = $_SESSION['IdUser'];

    // Pastikan user memiliki fasilitas ini
    $cek = $conn->prepare("SELECT f.IdFasilitas 
                           FROM fasilitas f
                           JOIN penyediafasilitas p ON f.IdPenyedia = p.IdPenyedia
                           WHERE f.IdFasilitas = ? AND p.IdUser = ?");
    $cek->bind_param("ii", $idFasilitas, $idUser);
    $cek->execute();
    $result = $cek->get_result();

    if ($result->num_rows > 0) {
        // Hapus unit terlebih dahulu (jika ada foreign key)
        $conn->query("DELETE FROM unit WHERE IdFasilitas = $idFasilitas");

        // Hapus fasilitas
        $conn->query("DELETE FROM fasilitas WHERE IdFasilitas = $idFasilitas");
    }
}

header("Location: edit_fasilitas.php");
exit();
?>
