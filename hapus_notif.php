<?php
include 'koneksi.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $IdUser = $_SESSION['IdUser'] ?? null;
    $id = intval($_POST['id']);

    $stmt = $conn->prepare("DELETE FROM notifikasi WHERE IdNotifikasi = ? AND IdUser = ?");
    $stmt->bind_param("ii", $id, $IdUser);
    $stmt->execute();
    echo 'success';
}
?>
