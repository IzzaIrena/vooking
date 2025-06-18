<?php
include 'koneksi.php';

// Pengguna Terdaftar
$result = $conn->query("SELECT COUNT(*) as total_user FROM user");
$dataUser = $result->fetch_assoc();
$totalUser = $dataUser['total_user'] ?? 0;

// Permintaan Verifikasi
$result = $conn->query("SELECT COUNT(*) as total_verifikasi FROM verifikasipenyedia WHERE Status = 'menunggu'");
$dataVerifikasi = $result->fetch_assoc();
$totalVerifikasi = $dataVerifikasi['total_verifikasi'] ?? 0;

// Langganan Telat berdasarkan langganan terakhir per penyedia
$today = date('Y-m-d');
$result = $conn->query("
    SELECT COUNT(*) AS total_langganan_telat
    FROM (
        SELECT lp.IdPenyedia, MAX(lp.PeriodeAkhir) AS AkhirTerbaru
        FROM langganan_penyedia lp
        JOIN penyediafasilitas pf ON lp.IdPenyedia = pf.IdPenyedia
        JOIN user u ON pf.IdUser = u.IdUser
        WHERE lp.StatusPembayaran = 'diterima'
          AND u.RoleAktif = 'penyedia'
        GROUP BY lp.IdPenyedia
    ) AS latest_langganan
    WHERE AkhirTerbaru < '$today'
");
$dataLanggananTelat = $result->fetch_assoc();
$totalLanggananTelat = $dataLanggananTelat['total_langganan_telat'] ?? 0;

// Laporan Masuk (hanya yang belum selesai)
$result = $conn->query("SELECT COUNT(*) as total_laporan FROM laporan WHERE StatusLaporan != 'selesai'");
$dataLaporan = $result->fetch_assoc();
$totalLaporan = $dataLaporan['total_laporan'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <title>Dashboard Admin Vooking</title>
    <link rel="stylesheet" href="admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
  <?php include "sidebar_admin.php"; ?>

    <div class="main">
        <div class="header">
            <div class="logo-container">
                <img src="Vicon.png" alt="Logo Vooking" class="logo-img">
                <div class="logo-text">VOOKING</div>
            </div>
        </div>

        <div class="content">
            <h1>Dashboard & Pemantauan Data</h1>
            <div class="stats">
                <div class="stat-box">
                    <div class="circle" style="--value: <?= min($totalUser, 100) ?>;">
                        <div class="number"><?= $totalUser ?></div>
                    </div>
                    <p>Pengguna Terdaftar</p>
                    <i class="fa fa-users icon"></i>
                </div>

                <div class="stat-box">
                    <div class="circle" style="--value: <?= min($totalVerifikasi, 100) ?>;">
                        <div class="number"><?= $totalVerifikasi ?></div>
                    </div>
                    <p>Permintaan Verifikasi</p>
                    <i class="fa fa-user-check icon"></i>
                </div>

                <div class="stat-box">
                    <div class="circle" style="--value: <?= min($totalLanggananTelat, 100) ?>;">
                        <div class="number"><?= $totalLanggananTelat ?></div>
                    </div>
                    <p>Langganan Telat</p>
                    <i class="fa fa-clock icon"></i>
                </div>

                <div class="stat-box">
                    <div class="circle" style="--value: <?= min($totalLaporan, 100) ?>;">
                        <div class="number"><?= $totalLaporan ?></div>
                    </div>
                    <p>Laporan Masuk</p>
                    <i class="fa fa-file-alt icon"></i>
                </div>
    </div>
</body>
</html>
