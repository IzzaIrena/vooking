<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['IdUser'])) {
    header("Location: login.php");
    exit;
}

$IdUser = $_SESSION['IdUser'];

// Cari IdPelanggan
$stmt = $conn->prepare("SELECT IdPelanggan FROM pelanggan WHERE IdUser = ?");
$stmt->bind_param("i", $IdUser);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows == 0) {
    die("Data pelanggan tidak ditemukan. Silakan hubungi admin.");
}
$row = $res->fetch_assoc();
$IdPelanggan = $row['IdPelanggan'];

$IdPenyedia = isset($_GET['IdPenyedia']) ? (int)$_GET['IdPenyedia'] : 0;
$IdFasilitas = isset($_GET['IdFasilitas']) ? (int)$_GET['IdFasilitas'] : 0;

// Fungsi format LastActive
function formatLastActive($datetime) {
    if (!$datetime) return "Tidak diketahui";
    $diff = time() - strtotime($datetime);
    if ($diff < 300) return "Online";
    elseif ($diff < 3600) return "Aktif " . floor($diff / 60) . " menit lalu";
    elseif ($diff < 86400) return "Aktif " . floor($diff / 3600) . " jam lalu";
    else return "Aktif terakhir " . date("d M Y H:i", strtotime($datetime));
}

// Ambil info penyedia & fasilitas
$fasilitas = $conn->query("SELECT * FROM fasilitas WHERE IdFasilitas = $IdFasilitas")->fetch_assoc();
$penyedia = $conn->query("SELECT u.Username, u.FotoProfil, u.LastActive 
                          FROM penyediafasilitas p 
                          JOIN user u ON u.IdUser = p.IdUser 
                          WHERE p.IdPenyedia = $IdPenyedia")->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Chat dengan Penyedia</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="chat_pelanggan.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

<!-- <div class="vooking-header">
    <div class="logo-title">
        <a href="javascript:history.back()"><i class="fa-solid fa-arrow-left back-home-icon"></i></a>
        <img src="Vicon.png" class="logo-img" />
        <h1>VOOKING</h1>
    </div>
</div> -->

<div class="chat-container">
    <div class="penyedia-info">
        <a href="javascript:history.back()" class="back-icon">
            <i class="fa-solid fa-arrow-left back-home-icon"></i>
        </a>
        <img src="<?= htmlspecialchars($penyedia['FotoProfil'] ?? 'default-profile.png') ?>" alt="Penyedia">
        <div>
            <strong><?= htmlspecialchars($penyedia['Username']) ?></strong><br>
            <small><?= formatLastActive($penyedia['LastActive']) ?></small>
        </div>
    </div>

    <div class="fasilitas-info">
        <img src="<?= htmlspecialchars($fasilitas['FotoFasilitas'] ?? 'default.jpg') ?>" alt="Fasilitas">
        <div>
            <strong><?= htmlspecialchars($fasilitas['NamaFasilitas']) ?></strong><br>
            <small><?= htmlspecialchars($fasilitas['Lokasi']) ?></small>
        </div>
    </div>

    <div class="chat-box" id="chat-box">
        <!-- Pesan akan dimuat dengan AJAX -->
    </div>

    <form id="form-chat" class="form-chat">
        <textarea name="Pesan" id="Pesan" required placeholder="Tulis pesan..."></textarea>
        <button type="submit">Kirim</button>
    </form>
</div>

<script>
$(document).ready(function () {
    function loadChat() {
        $.ajax({
            url: 'ambil_pesan.php',
            type: 'POST',
            data: {
                IdPenyedia: <?= $IdPenyedia ?>,
                IdPelanggan: <?= $IdPelanggan ?>,
                IdFasilitas: <?= $IdFasilitas ?>
            },
            success: function (data) {
                $('#chat-box').html(data);
                $('#chat-box').scrollTop($('#chat-box')[0].scrollHeight);
            }
        });
    }

    loadChat();
    setInterval(loadChat, 2000); // refresh tiap 2 detik

    $('#form-chat').on('submit', function (e) {
        e.preventDefault();
        let pesan = $('#Pesan').val();

        if (pesan.trim() === '') return;

        $.post('kirim_pesan.php', {
            Pesan: pesan,
            IdPenyedia: <?= $IdPenyedia ?>,
            IdPelanggan: <?= $IdPelanggan ?>,
            IdFasilitas: <?= $IdFasilitas ?>
        }, function () {
            $('#Pesan').val('');
            loadChat();
        });
    });
});
</script>

</body>
</html>
