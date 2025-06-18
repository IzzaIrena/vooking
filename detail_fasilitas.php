<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['IdUser']) || $_SESSION['RoleAktif'] !== 'penyedia') {
    header("Location: login_register.php");
    exit();
}

if (!isset($_GET['id_fasilitas']) || !is_numeric($_GET['id_fasilitas'])) {
    echo "ID fasilitas tidak valid.";
    exit();
}

$idFasilitas = (int) $_GET['id_fasilitas'];

$stmtFasilitas = $conn->prepare("SELECT * FROM fasilitas WHERE IdFasilitas = ?");
$stmtFasilitas->bind_param("i", $idFasilitas);
$stmtFasilitas->execute();
$resultFasilitas = $stmtFasilitas->get_result();

if ($resultFasilitas->num_rows === 0) {
    echo "Fasilitas tidak ditemukan.";
    exit();
}
$fasilitas = $resultFasilitas->fetch_assoc();
$tipeBooking = $fasilitas['TipeBooking']; // 'jam' atau 'harian'

$query = "SELECT b.*, u.NamaTipeUnit, 
                 IFNULL(us.Username, b.Nama) AS NamaPemesan, 
                 IFNULL(us.NoHp, b.NomorHp) AS NoHp
          FROM booking b 
          JOIN unit u ON b.IdUnit = u.IdUnit 
          LEFT JOIN pelanggan p ON b.IdPelanggan = p.IdPelanggan
          LEFT JOIN user us ON p.IdUser = us.IdUser
          WHERE u.IdFasilitas = ? 
          ORDER BY b.Tanggal DESC, b.Jam ASC";

$stmtBooking = $conn->prepare($query);
$stmtBooking->bind_param("i", $idFasilitas);
$stmtBooking->execute();
$resultBooking = $stmtBooking->get_result();

$hasNewChat = false;

if (isset($IdPenyedia)) {
    $sqlUnreadChat = "SELECT COUNT(*) AS jumlah FROM chat 
                    WHERE IdPenyedia = ? AND Pengirim = 'pelanggan' AND Status = 'belum'";
    $stmtUnread = $conn->prepare($sqlUnreadChat);
    $stmtUnread->bind_param("i", $IdPenyedia);
    $stmtUnread->execute();
    $resultUnread = $stmtUnread->get_result();
    $rowUnread = $resultUnread->fetch_assoc();

    $hasNewChat = $rowUnread['jumlah'] > 0;
}

$hasNewNotif = false;

$sqlNotif = "SELECT COUNT(*) AS jumlah FROM notifikasi WHERE IdUser = ? AND StatusBaca = 0";
$stmtNotif = $conn->prepare($sqlNotif);
$stmtNotif->bind_param("i", $IdUser);
$stmtNotif->execute();
$resultNotif = $stmtNotif->get_result();
$rowNotif = $resultNotif->fetch_assoc();

$hasNewNotif = $rowNotif['jumlah'] > 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <title>Detail Fasilitas - <?= htmlspecialchars($fasilitas['NamaFasilitas']) ?></title>
    <link rel="stylesheet" href="penyedia_home.css" />
    <link rel="stylesheet" href="detail_fasilitas.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body>
<div class="header">
<div class="logo-container">
    <i class="fa-solid fa-arrow-left back-home-icon" onclick="window.location.href='penyedia_home.php'"></i>
    <img src="Vicon.png" alt="Logo Vooking" class="logo-img" />
    <span class="logo-text">VOOKING</span>
</div>
<div class="icons">
    <div class="chat-icon-wrapper">
        <a href="chat_list.php" title="Chat Masuk">
            <i class="fa-solid fa-comments"></i>
        </a>
        <?php if ($hasNewChat): ?>
            <span class="chat-badge"></span>
        <?php endif; ?>
    </div>
    <div class="notif-icon-wrapper">
        <a href="semua_notifikasi.php" style="position: relative; color: inherit; text-decoration: none;">
            <i class="fa-solid fa-bell"></i>
            <?php if ($hasNewNotif): ?>
                <span class="notif-badge"></span>
            <?php endif; ?>
        </a>
    </div>
    <a href="profil_penyedia.php"><i class="fa-solid fa-user" id="profile-icon"></i></a>
</div>  
</div>
<div class="main">
    <div class="profile-container" style="display: flex; gap: 20px; padding: 20px;">
        <div class="profile-sidebar" style="min-width: 250px; border-right: 1px solid #ddd; padding-right: 20px;">
            <!-- Contoh sidebar: info singkat fasilitas -->
            <img src="<?= htmlspecialchars($fasilitas['FotoFasilitas'] ?? 'default-facility.png') ?>" 
                 alt="Foto Fasilitas" style="width:100%; border-radius: 8px; margin-bottom: 15px;">
            <h3><?= htmlspecialchars($fasilitas['NamaFasilitas']) ?></h3>
            <p><?= nl2br(htmlspecialchars($fasilitas['Deskripsi'] ?? '-')) ?></p>
        </div>

        <div class="profile-content" style="flex-grow: 1;">
            <h2>Data Booking</h2>

            <?php if ($resultBooking->num_rows === 0): ?>
                <p>Tidak ada data booking untuk fasilitas ini.</p>
            <?php else: ?>
                <table border="1" cellpadding="8" cellspacing="0" style="width:100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th>Nama Pemesan & No HP</th>
                            <th>Tipe Unit</th>
                            <th>Nomor Ruang</th>
                            <th>Tanggal</th>
                            <?php if ($tipeBooking === 'jam'): ?>
                                <th>Jam</th>
                            <?php endif; ?>
                            <th>Status Booking</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($booking = $resultBooking->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($booking['NamaPemesan']) ?><br>
                                    <small><?= htmlspecialchars($booking['NoHp']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($booking['NamaTipeUnit']) ?></td>
                                <td><?= htmlspecialchars($booking['NomorRuang']) ?></td>
                                <td><?= htmlspecialchars($booking['Tanggal']) ?></td>
                                <?php if ($tipeBooking === 'jam'): ?>
                                    <td><?= htmlspecialchars($booking['Jam']) ?></td>
                                <?php endif; ?>
                                <td><?= htmlspecialchars($booking['StatusBooking']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
function cekChatBaru() {
    fetch('cek_chat_baru.php')
        .then(response => response.json())
        .then(data => {
            const badge = document.querySelector('.chat-badge');
            if (data.success && data.jumlah > 0) {
                if (!badge) {
                    const newBadge = document.createElement('span');
                    newBadge.classList.add('chat-badge');
                    document.querySelector('.chat-icon-wrapper').appendChild(newBadge);
                }
            } else {
                if (badge) {
                    badge.remove();
                }
            }
        })
        .catch(error => {
            console.error('Gagal cek chat baru:', error);
        });
}

// Jalankan pertama kali dan setiap 10 detik
cekChatBaru();
setInterval(cekChatBaru, 10000); // setiap 10 detik

document.addEventListener('click', function(event) {
    const notifWrapper = document.querySelector('.notif-icon-wrapper');
    if (!notifWrapper.contains(event.target)) {
        document.getElementById("notifDropdown").style.display = "none";
    }
});
</script>
</body>
</html>
