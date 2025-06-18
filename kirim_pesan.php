<?php
session_start();
include 'koneksi.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['Pesan'])) {
    $pesan = $_POST['Pesan'];
    $IdPenyedia = (int)$_POST['IdPenyedia'];
    $IdPelanggan = (int)$_POST['IdPelanggan'];
    $IdFasilitas = (int)$_POST['IdFasilitas'];
    $pengirim = 'pelanggan';

    $stmt = $conn->prepare("INSERT INTO chat (IdPenyedia, IdPelanggan, IdFasilitas, Pesan, Pengirim) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiss", $IdPenyedia, $IdPelanggan, $IdFasilitas, $pesan, $pengirim);
    $stmt->execute();
}
