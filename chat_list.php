<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['IdUser'])) {
    header("Location: login.php");
    exit();
}

$IdUserPenyedia = $_SESSION['IdUser'];

// Ambil ID penyedia dari user login
$qPenyedia = $conn->query("SELECT IdPenyedia FROM penyediafasilitas WHERE IdUser = $IdUserPenyedia");
$dataPenyedia = $qPenyedia->fetch_assoc();
$IdPenyedia = $dataPenyedia['IdPenyedia'];

// Ambil daftar chat terakhir dari tiap pelanggan dan fasilitas, termasuk foto profil dan foto fasilitas
$qChatList = $conn->query("
    SELECT 
        c1.IdChat,
        c1.IdPelanggan,
        c1.IdFasilitas,
        u.Username AS NamaPelanggan,
        u.FotoProfil,
        f.NamaFasilitas,
        f.FotoFasilitas,
        c1.Pesan AS PesanTerakhir,
        c1.Waktu AS WaktuTerakhir
    FROM chat c1
    JOIN (
        SELECT MAX(IdChat) AS MaxIdChat
        FROM chat
        WHERE IdPenyedia = $IdPenyedia
        GROUP BY IdPelanggan, IdFasilitas
    ) c2 ON c1.IdChat = c2.MaxIdChat
    JOIN pelanggan p ON c1.IdPelanggan = p.IdPelanggan
    JOIN user u ON p.IdUser = u.IdUser
    JOIN fasilitas f ON c1.IdFasilitas = f.IdFasilitas
    ORDER BY c1.Waktu DESC
");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Inbox Chat</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="chat_penyedia.css">
</head>
<body>

<div class="chat-list">

    <div class="chat-header">
        <a href="javascript:history.back()" class="back-arrow"><i class="fa fa-arrow-left"></i></a>
        <h2><i class="fa-solid fa-comments"></i> Chat Masuk</h2>
    </div>

    <?php if ($qChatList && $qChatList->num_rows > 0): ?>
        <?php while ($row = $qChatList->fetch_assoc()): ?>
            <a href="chat_masuk.php?IdChat=<?= $row['IdChat'] ?>" class="chat-item">
                <img 
                    src="<?= htmlspecialchars($row['FotoProfil'] ?: 'default-profile.png') ?>" 
                    alt="Foto Profil <?= htmlspecialchars($row['NamaPelanggan']) ?>" 
                    class="profile"
                    onerror="this.onerror=null;this.src='default-profile.png';"
                >
                <div class="chat-content">
                    <strong><?= htmlspecialchars($row['NamaPelanggan']) ?></strong>
                    <small><?= htmlspecialchars($row['PesanTerakhir']) ?></small>
                </div>
                <div class="chat-time"><?= date('d M H:i', strtotime($row['WaktuTerakhir'])) ?></div>
                <img 
                    src="<?= htmlspecialchars($row['FotoFasilitas'] ?: 'default-fasilitas.jpg') ?>" 
                    alt="Foto Fasilitas <?= htmlspecialchars($row['NamaFasilitas']) ?>" 
                    class="chat-foto-fasilitas"
                    onerror="this.onerror=null;this.src='default-fasilitas.jpg';"
                >
            </a>
        <?php endwhile; ?>
    <?php else: ?>
        <p style="text-align:center; color:#666;">Belum ada pesan masuk.</p>
    <?php endif; ?>
</div>

</body>
</html>
