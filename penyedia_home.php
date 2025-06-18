<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['IdUser']) || $_SESSION['RoleAktif'] !== 'penyedia') {
    header("Location: login_register.php");
    exit();
}

$IdUser = $_SESSION['IdUser'];
$now = date('Y-m-d H:i:s');
mysqli_query($conn, "UPDATE user SET LastActive = '$now' WHERE IdUser = $IdUser");

$sqlPenyedia = "SELECT IdPenyedia FROM penyediafasilitas WHERE IdUser = ?";
$stmtPenyedia = $conn->prepare($sqlPenyedia);
$stmtPenyedia->bind_param("i", $IdUser);
$stmtPenyedia->execute();
$resultPenyedia = $stmtPenyedia->get_result();

if ($resultPenyedia->num_rows > 0) {
    $rowPenyedia = $resultPenyedia->fetch_assoc();
    $IdPenyedia = $rowPenyedia['IdPenyedia'];

    $sqlFasilitas = "SELECT * FROM fasilitas WHERE IdPenyedia = ?";
    $stmtFasilitas = $conn->prepare($sqlFasilitas);
    $stmtFasilitas->bind_param("i", $IdPenyedia);
    $stmtFasilitas->execute();
    $resultFasilitas = $stmtFasilitas->get_result();
} else {
    $resultFasilitas = false;
}

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
    <meta charset="UTF-8">
    <title>Beranda Penyedia - Vooking</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="penyedia_home.css">
    <link rel="stylesheet" href="notif_chat.css">
</head>
<body>
<?php include "sidebar_penyedia.php"; ?>

<div class="main">
    <?php include "header_penyedia.php";?>      

    <?php $isEmpty = !$resultFasilitas || $resultFasilitas->num_rows === 0; ?>
    <div class="content <?= $isEmpty ? 'empty' : '' ?>">
        <?php if ($isEmpty): ?>
            <div class="empty-message">Fasilitas Kosong</div>
        <?php else: ?>
            <?php while ($row = $resultFasilitas->fetch_assoc()): ?>
                <a href="detail_fasilitas.php?id_fasilitas=<?= $row['IdFasilitas'] ?>" class="facility-card-link">
                    <div class="facility-card">
                        <img src="<?= htmlspecialchars($row['FotoFasilitas'] ?: 'assets/default.jpg') ?>" alt="<?= htmlspecialchars($row['NamaFasilitas']) ?>">
                        <p class="facility-name"><?= htmlspecialchars($row['NamaFasilitas']) ?></p>
                    </div>
                </a>
            <?php endwhile; ?>
        <?php endif; ?>
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

function toggleNotifikasi() {
    const dropdown = document.getElementById("notifDropdown");
    dropdown.style.display = dropdown.style.display === "block" ? "none" : "block";
}

// Klik di luar untuk tutup dropdown
document.addEventListener('click', function(event) {
    const notifWrapper = document.querySelector('.notif-icon-wrapper');
    if (!notifWrapper.contains(event.target)) {
        document.getElementById("notifDropdown").style.display = "none";
    }
});
</script>
</body>
</html>
