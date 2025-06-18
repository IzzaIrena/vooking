<?php
session_start();
include 'koneksi.php';

$IdPenyedia = (int)$_POST['IdPenyedia'];
$IdPelanggan = (int)$_POST['IdPelanggan'];
$IdFasilitas = (int)$_POST['IdFasilitas'];

$stmt = $conn->prepare("SELECT * FROM chat WHERE IdPenyedia = ? AND IdPelanggan = ? AND IdFasilitas = ? ORDER BY Waktu ASC");
$stmt->bind_param("iii", $IdPenyedia, $IdPelanggan, $IdFasilitas);
$stmt->execute();
$result = $stmt->get_result();

$lastDate = '';
while ($row = $result->fetch_assoc()) {
    $messageDate = date('d M Y', strtotime($row['Waktu']));
    $messageTime = date('H:i', strtotime($row['Waktu']));

    if ($messageDate !== $lastDate) {
        echo '<div class="tanggal-chat">' . $messageDate . '</div>';
        $lastDate = $messageDate;
    }

    $class = $row['Pengirim'] == 'pelanggan' ? 'pelanggan' : 'penyedia';
    echo '<div class="pesan ' . $class . '">' . 
            htmlspecialchars($row['Pesan']) . 
            '<small>' . $messageTime . '</small>' . 
         '</div>';
}
