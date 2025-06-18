<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['IdUser']) || $_SESSION['RoleAktif'] !== 'penyedia') {
    header("Location: login_register.php");
    exit();
}

$IdUser = $_SESSION['IdUser'];

// Ambil IdPelanggan berdasarkan IdUser
$sql_pelanggan = "SELECT IdPelanggan FROM pelanggan WHERE IdUser = ?";
$stmt_pelanggan = $conn->prepare($sql_pelanggan);
$stmt_pelanggan->bind_param("i", $IdUser);
$stmt_pelanggan->execute();
$result_pelanggan = $stmt_pelanggan->get_result();

$IdPelanggan = null;
if ($row_pelanggan = $result_pelanggan->fetch_assoc()) {
    $IdPelanggan = $row_pelanggan['IdPelanggan'];
}

// Ambil data booking jika IdPelanggan tersedia
if ($IdPelanggan) {
    $query = "
        SELECT b.IdBooking, b.Tanggal, b.Jam, b.StatusBooking,
               f.NamaFasilitas, u.NamaTipeUnit, b.NomorRuang, f.TipeBooking
        FROM booking b
        JOIN unit u ON b.IdUnit = u.IdUnit
        JOIN fasilitas f ON u.IdFasilitas = f.IdFasilitas
        WHERE b.IdPelanggan = ?
        ORDER BY b.Tanggal DESC
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $IdPelanggan);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = false;
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
    <title>Riwayat Booking Anda - Vooking</title>
    <link rel="stylesheet" href="penyedia_home.css">
    <link rel="stylesheet" href="riwayat_booking_penyedia.css">
    <link rel="stylesheet" href="notif_chat.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<?php include "sidebar_penyedia.php"; ?>

<div class="main">
    <div class="header-fixed">
        <?php include "header_penyedia.php"; ?> 
    </div>

    <div class="content">
        <h2><i class="fa-solid fa-clock-rotate-left"></i> Riwayat Booking Anda</h2>

        <?php if ($result && $result->num_rows > 0): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Fasilitas</th>
                            <th>Unit</th>
                            <th>Nomor Ruang</th>
                            <th>Tanggal</th>
                            <th>Jam</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td data-label="Fasilitas"><?= htmlspecialchars($row['NamaFasilitas']) ?></td>
                        <td data-label="Tipe Unit"><?= htmlspecialchars($row['NamaTipeUnit']) ?></td>
                        <td data-label="Tipe Booking"><?= htmlspecialchars($row['NomorRuang']) ?></td>
                        <td data-label="Tanggal"><?= htmlspecialchars($row['Tanggal']) ?></td>
                        <td data-label="Jam">
                            <?= ($row['TipeBooking'] === 'harian') ? 'Full Day' : htmlspecialchars($row['Jam']) ?>
                        </td>
                        <td data-label="Status"><span class="status <?= $row['StatusBooking'] ?>"><?= $row['StatusBooking'] ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>Anda belum pernah melakukan booking sebagai penyedia.</p>
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

cekChatBaru();
setInterval(cekChatBaru, 10000);

document.addEventListener('click', function(event) {
    const notifWrapper = document.querySelector('.notif-icon-wrapper');
    if (!notifWrapper.contains(event.target)) {
        document.getElementById("notifDropdown").style.display = "none";
    }
});
</script>
</body>
</html>
