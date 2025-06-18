<?php
session_start();
include 'koneksi.php';

$IdUser = $_SESSION['IdUser'] ?? null;
if ($IdUser) {
    $stmt = $conn->prepare("DELETE FROM notifikasi WHERE IdUser = ?");
    $stmt->bind_param("i", $IdUser);
    $stmt->execute();
}

header("Location: semua_notifikasi.php"); 
exit();
?>
