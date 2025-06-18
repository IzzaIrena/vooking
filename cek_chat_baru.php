<?php
session_start();
include 'koneksi.php';

header('Content-Type: application/json');

if (!isset($_SESSION['IdUser']) || $_SESSION['RoleAktif'] !== 'penyedia') {
    echo json_encode(['success' => false]);
    exit;
}

$IdUser = $_SESSION['IdUser'];

$sqlPenyedia = "SELECT IdPenyedia FROM penyediafasilitas WHERE IdUser = ?";
$stmt = $conn->prepare($sqlPenyedia);
$stmt->bind_param("i", $IdUser);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $IdPenyedia = $row['IdPenyedia'];

    $sql = "SELECT COUNT(*) AS jumlah FROM chat WHERE IdPenyedia = ? AND Pengirim = 'pelanggan' AND Status = 'belum'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $IdPenyedia);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    echo json_encode(['success' => true, 'jumlah' => (int)$row['jumlah']]);
} else {
    echo json_encode(['success' => false]);
}
