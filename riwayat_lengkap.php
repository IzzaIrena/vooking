<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['IdUser'])) {
    header("Location: login_register.php");
    exit();
}

$IdUser = $_SESSION['IdUser'];

// Dapatkan IdPelanggan berdasarkan IdUser
$sqlGetPelanggan = "SELECT IdPelanggan FROM pelanggan WHERE IdUser = ?";
$stmtGet = $conn->prepare($sqlGetPelanggan);
$stmtGet->bind_param("i", $IdUser);
$stmtGet->execute();
$resGet = $stmtGet->get_result();
$dataPelanggan = $resGet->fetch_assoc();
$IdPelanggan = $dataPelanggan['IdPelanggan'] ?? 0;

$riwayatQuery = mysqli_query($conn, "
    SELECT b.Nama, b.*, u.NamaTipeUnit, f.NamaFasilitas 
    FROM booking b 
    JOIN unit u ON b.IdUnit = u.IdUnit 
    JOIN fasilitas f ON u.IdFasilitas = f.IdFasilitas
    WHERE b.IdPelanggan = $IdPelanggan
    ORDER BY b.IdBooking DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <title>Riwayat Booking Lengkap</title>
    <link rel="stylesheet" href="home.css?v=<?= time(); ?>" />
    <style>
        .container {
            max-width: 900px;
            margin: 40px auto;
            background: #fff;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 0 12px rgba(0,0,0,0.1);
        }

        .riwayat-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        .riwayat-table th, .riwayat-table td {
            border: 1px solid #ccc;
            padding: 10px;
            text-align: left;
        }

        .riwayat-table th {
            background-color: #f0f4ff;
            color: #003087;
        }

        .back-btn {
            display: inline-block;
            margin-top: 20px;
            padding: 8px 16px;
            background-color: #003087;
            color: white;
            border-radius: 8px;
            text-decoration: none;
        }

        .back-btn:hover {
            background-color: #002060;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Riwayat Booking Lengkap</h2>
    <?php if (mysqli_num_rows($riwayatQuery) > 0): ?>
        <table class="riwayat-table">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Fasilitas</th>
                    <th>Unit - Ruang</th>
                    <th>Jam</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($riwayatQuery)): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['Tanggal']) ?></td>
                        <td><?= htmlspecialchars($row['NamaFasilitas']) ?></td>
                        <td><?= htmlspecialchars($row['NamaTipeUnit']) ?> - <?= htmlspecialchars($row['NomorRuang']) ?></td>
                        <td><?= $row['Jam'] ? htmlspecialchars($row['Jam']) : 'Full Day' ?></td>
                        <td><?= ucfirst($row['StatusBooking']) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Tidak ada riwayat booking ditemukan.</p>
    <?php endif; ?>

    <a href="profil_pelanggan.php" class="back-btn">Kembali ke Profil</a>
</div>
</body>
</html>
