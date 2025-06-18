<?php
session_start();
include 'koneksi.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "Penyedia tidak ditemukan.";
    exit();
}
$idPenyedia = (int) $_GET['id'];

// Query untuk mendapatkan data penyedia, termasuk LastActive
$queryPenyedia = mysqli_query($conn, "
    SELECT user.*, penyediafasilitas.TanggalAktivasi 
    FROM user 
    JOIN penyediafasilitas ON user.IdUser = penyediafasilitas.IdUser 
    WHERE penyediafasilitas.IdPenyedia = $idPenyedia
");

if (!$queryPenyedia) {
    die("Terjadi kesalahan pada query penyedia: " . mysqli_error($conn));
}

$dataPenyedia = mysqli_fetch_assoc($queryPenyedia);

if (!$dataPenyedia) {
    echo "Penyedia tidak ditemukan.";
    exit();
}

// Query untuk mendapatkan daftar fasilitas
$queryFasilitas = mysqli_query($conn, "
    SELECT * FROM fasilitas WHERE IdPenyedia = $idPenyedia
");

if (!$queryFasilitas) {
    die("Terjadi kesalahan pada query fasilitas: " . mysqli_error($conn));
}

function formatLastActive($datetime) {
    if (!$datetime) return "Tidak diketahui";

    $diff = time() - strtotime($datetime);

    if ($diff < 300) {
        return "Online";
    } elseif ($diff < 3600) {
        return "Aktif " . floor($diff / 60) . " menit lalu";
    } elseif ($diff < 86400) {
        return "Aktif " . floor($diff / 3600) . " jam lalu";
    } else {
        return "Aktif terakhir " . date("d M Y H:i", strtotime($datetime));
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lihat Profil Penyedia - Vooking</title>
    <link rel="stylesheet" href="home.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="lihat_profil_penyedia.css">
</head>
<body>
<div class="main">
    <div class="vooking-header">
        <div class="logo-title">
            <a href="javascript:history.back()" class="back-home-link">
                <i class="fa-solid fa-arrow-left back-home-icon"></i>
            </a>
            <img src="Vicon.png" class="logo-img" />
            <h1>VOOKING</h1>
        </div>
    </div>

    <div class="profile-container">
        <!-- KIRI: FOTO + DESKRIPSI -->
        <div class="profile-box-left">
            <div class="foto-wrapper">
            <?php if (!empty($dataPenyedia['FotoProfil'])): ?>
                <img src="<?= htmlspecialchars($dataPenyedia['FotoProfil']) ?>" class="profile-pic" alt="Foto Profil">
            <?php else: ?>
                <div class="profile-icon-default"><i class="fa-solid fa-user"></i></div>
            <?php endif; ?>
            </div>
            <h4>Deskripsi</h4>
            <div class="deskripsi">
                <?= nl2br(htmlspecialchars($dataPenyedia['Deskripsi'] ?? 'Belum ada deskripsi')) ?>
            </div>
        </div>

        <!-- KANAN: PROFIL + FASILITAS -->
        <div class="profile-box-right">
            <div class="profile-info">
                <h2>Profil Penyedia</h2>
                <h3><?= htmlspecialchars($dataPenyedia['Username']) ?></h3>
                <p><?= formatLastActive($dataPenyedia['LastActive'] ?? null) ?></p>
                <p>Bergabung sejak <?= date('d M Y', strtotime($dataPenyedia['BergabungSejak'])) ?></p>
                <p>Nomor HP: <?= htmlspecialchars($dataPenyedia['NoHp']) ?></p>
            </div>

            <h3 class="fasilitas-title">Daftar Fasilitas yang Disediakan:</h3>
            <div class="fasilitas-list">
            <?php while ($fasilitas = mysqli_fetch_assoc($queryFasilitas)): ?>
                <a href="lihat_fasilitas.php?id=<?= $fasilitas['IdFasilitas'] ?>" class="fasilitas-item" style="text-decoration: none; color: inherit;">
                    <img src="<?= htmlspecialchars($fasilitas['FotoFasilitas']) ?>" alt="Fasilitas">
                    <p><strong><?= htmlspecialchars($fasilitas['NamaFasilitas']) ?></strong></p>
                </a>
            <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>
