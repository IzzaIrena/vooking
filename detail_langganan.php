<?php
include 'koneksi.php';

// Ambil langganan terakhir untuk setiap penyedia
$query = "
    SELECT lp.*, u.Username, u.NoHp, u.FotoProfil
    FROM langganan_penyedia lp
    JOIN (
        SELECT IdPenyedia, MAX(PeriodeMulai) AS MaxPeriodeMulai
        FROM langganan_penyedia
        GROUP BY IdPenyedia
    ) latest ON lp.IdPenyedia = latest.IdPenyedia AND lp.PeriodeMulai = latest.MaxPeriodeMulai AND lp.StatusPembayaran = 'diterima'
    JOIN penyediafasilitas pf ON lp.IdPenyedia = pf.IdPenyedia
    JOIN user u ON pf.IdUser = u.IdUser
    WHERE u.RoleAktif = 'penyedia'
    ORDER BY lp.PeriodeMulai DESC
";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Detail Langganan Penyedia</title>
    <link rel="stylesheet" href="detail_langganan_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<div class="header-flex">
    <a href="pembayaran_langganan.php" class="button-back"><i class="fa fa-arrow-left"></i> Kembali</a>
    <h2 class="center-title">Daftar Langganan Penyedia</h2>
</div>

<table>
    <thead>
        <tr>
            <th>No</th>
            <th>Foto</th>
            <th>Nama</th>
            <th>No HP</th>
            <th>Periode Mulai</th>
            <th>Periode Akhir</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($result->num_rows > 0): $no = 1; ?>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $no++ ?></td>
                <td>
                    <?php if (!empty($row['FotoProfil'])): ?>
                        <img src="<?= htmlspecialchars($row['FotoProfil']) ?>" class="profile-pic" alt="Foto Profil">
                    <?php else: ?>
                        <i class="fa-solid fa-user-circle default-user-icon"></i>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($row['Username']) ?></td>
                <td><?= htmlspecialchars($row['NoHp']) ?></td>
                <td><?= date('d-m-Y', strtotime($row['PeriodeMulai'])) ?></td>
                <td><?= date('d-m-Y', strtotime($row['PeriodeAkhir'])) ?></td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="8" style="text-align:center; color:gray;">Belum ada data langganan.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

</body>
</html>
