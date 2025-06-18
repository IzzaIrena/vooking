<?php
session_start();
include 'koneksi.php';

header('Content-Type: application/json');

if (!isset($_SESSION['IdUser'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$IdUser = $_SESSION['IdUser'];

// Cek apakah ada pesan baru dari penyedia untuk pelanggan ini
$query = "SELECT COUNT(*) as jumlah 
          FROM chat 
          WHERE Penerima = $IdUser AND IsRead = 0";

$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);

echo json_encode(['success' => true, 'jumlah' => (int)$row['jumlah']]);
?>
