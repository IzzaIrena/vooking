<?php
include 'koneksi.php';

// Ambil data penyedia dan status langganan terakhir
$queryTelat = "
    SELECT 
        pf.IdPenyedia, 
        pf.StatusLangganan, 
        pf.TanggalAktivasi,
        u.IdUser, 
        u.Username, 
        u.NoHp, 
        u.BergabungSejak, 
        u.RoleAktif, 
        u.FotoProfil,
        lp.PeriodeMulai, 
        lp.PeriodeAkhir, 
        lp.StatusPembayaran
    FROM penyediafasilitas pf
    JOIN user u ON pf.IdUser = u.IdUser
    LEFT JOIN (
        SELECT IdPenyedia, MAX(PeriodeMulai) as PeriodeMulai, MAX(PeriodeAkhir) as PeriodeAkhir, StatusPembayaran
        FROM langganan_penyedia
        WHERE StatusPembayaran = 'diterima'
        GROUP BY IdPenyedia
    ) lp ON pf.IdPenyedia = lp.IdPenyedia
    WHERE u.RoleAktif = 'penyedia'
";

$resultTelat = $conn->query($queryTelat);
$sekarang = new DateTime();
if ($resultTelat->num_rows > 0) {
    while ($row = $resultTelat->fetch_assoc()) {
        $idUser = $row['IdUser'];
        $idPenyedia = $row['IdPenyedia'];
        $statusLangganan = $row['StatusLangganan'];

        $tanggalAkhir = isset($row['PeriodeAkhir']) ? new DateTime($row['PeriodeAkhir']) : null;

        if ($tanggalAkhir && $sekarang > $tanggalAkhir) {
            $interval = $tanggalAkhir->diff($sekarang)->days;

            // Jika lewat lebih dari 3 hari dan status masih aktif
            if ($interval > 3 && $statusLangganan !== 'non_aktif') {
                // Update status
                $conn->query("UPDATE penyediafasilitas SET StatusLangganan = 'non_aktif' WHERE IdPenyedia = '$idPenyedia'");
                $conn->query("UPDATE user SET RoleAktif = 'pelanggan' WHERE IdUser = '$idUser'");

                // Kirim notifikasi sistem
                $pesanAuto = "Status penyedia Anda telah dihentikan karena tidak memperpanjang langganan dalam 3 hari.";
                $conn->query("INSERT INTO notifikasi (IdUser, IsiPesan, Tipe, Tanggal, StatusBaca) VALUES ('$idUser', '$pesanAuto', 'sistem', NOW(), 0)");
            }
        }
    }
}

// Reset result untuk ditampilkan kembali di tabel
$resultTelat = $conn->query($queryTelat);


$queryPembayaran = "
    SELECT lp.*, u.Username, u.NoHp, u.FotoProfil
    FROM langganan_penyedia lp
    JOIN penyediafasilitas pf ON lp.IdPenyedia = pf.IdPenyedia
    JOIN user u ON pf.IdUser = u.IdUser
    WHERE lp.StatusPembayaran = 'menunggu'
";

$resultPembayaran = $conn->query($queryPembayaran);

// Handle aksi admin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['jadikan_pelanggan'])) {
        $idUser = $_POST['user_id'];
        $idPenyedia = $_POST['id_penyedia'];

        $conn->query("UPDATE penyediafasilitas SET StatusLangganan = 'non_aktif' WHERE IdPenyedia = '$idPenyedia'");
        $conn->query("UPDATE user SET RoleAktif = 'pelanggan' WHERE IdUser = '$idUser'");

        // TODO: Tambahkan insert ke tabel notifikasi jika perlu

        header("Location: pembayaran_langganan.php");
        exit;
    }
    if (isset($_POST['verifikasi_langganan'])) {
        $idLangganan = $_POST['id_langganan'];
        $aksi = $_POST['aksi'];
        $catatan = $aksi === 'tolak' ? $_POST['catatan'] : null;

        $statusBaru = $aksi === 'terima' ? 'diterima' : 'ditolak';
        $stmt = $conn->prepare("UPDATE langganan_penyedia SET StatusPembayaran=?, CatatanAdmin=? WHERE IdLangganan=?");
        $stmt->bind_param("ssi", $statusBaru, $catatan, $idLangganan);
        $stmt->execute();

        // TODO: Kirim notifikasi ke user (dummy alert, bisa diganti)
        echo "<script>alert('Langganan berhasil diverifikasi.'); window.location='pembayaran_langganan.php';</script>";
        exit;
    }

    if (isset($_POST['kirim_peringatan'])) {
        $idUser = $_POST['peringatan_id_user'];
        $idPenyedia = $_POST['peringatan_id_penyedia'];

        // Ambil PeriodeAkhir langganan terakhir
        $queryPeriode = "
            SELECT PeriodeAkhir 
            FROM langganan_penyedia 
            WHERE IdPenyedia = '$idPenyedia' AND StatusPembayaran = 'diterima' 
            ORDER BY PeriodeAkhir DESC 
            LIMIT 1
        ";
        $resPeriode = $conn->query($queryPeriode);
        if ($resPeriode->num_rows > 0) {
            $dataPeriode = $resPeriode->fetch_assoc();
            $periodeAkhir = new DateTime($dataPeriode['PeriodeAkhir']);
            $sekarang = new DateTime();
            $selisihHari = (int)$periodeAkhir->diff($sekarang)->format('%r%a');

            // Jika lebih dari 3 hari lewat dari periode akhir
            if ($selisihHari > 3) {
                $pesan = "Langganan Anda telah berakhir sejak $selisihHari hari yang lalu. Status Anda akan diubah menjadi pelanggan.";
            } else {
                $sisa = 3 - $selisihHari;
                $pesan = "Langganan Anda telah berakhir. Anda memiliki waktu $sisa hari lagi untuk memperpanjang langganan.";
            }

            // Kirim notifikasi
            $stmtNotif = $conn->prepare("INSERT INTO notifikasi (IdUser, IsiPesan, Tipe, Tanggal, StatusBaca) VALUES (?, ?, 'sistem', NOW(), 0)");
            $stmtNotif->bind_param("is", $idUser, $pesan);
            $stmtNotif->execute();

            echo "<script>alert('Notifikasi peringatan berhasil dikirim.'); window.location='pembayaran_langganan.php';</script>";
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pembayaran Langganan - Admin Vooking</title>
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
        <a href="detail_langganan.php" style="display:inline-block; margin-bottom:15px; background:#004080; color:white; padding:10px 15px; border-radius:8px; text-decoration:none;">
            <i class="fa fa-list"></i> Lihat Detail Langganan
        </a>
        <h2>Penyedia Telat Membayar</h2>
        <table>
            <thead>
                <tr>
                    <th>Foto</th>
                    <th>Nama</th>
                    <th>No HP</th>
                    <th>Tanggal Awal</th>
                    <th>Tanggal Selesai</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $hasTelat = false;
                while ($row = $resultTelat->fetch_assoc()):
                    $tanggalMulai = new DateTime($row['PeriodeMulai'] ?? $row['TanggalAktivasi']);
                    $tanggalSelesai = new DateTime($row['PeriodeAkhir'] ?? (clone $tanggalMulai)->modify('+1 year')->format('Y-m-d'));
                    $sekarang = new DateTime();
                    $telat = $tanggalSelesai !== null && $sekarang > $tanggalSelesai;
                    if (!$telat) continue;
                    $hasTelat = true;
                ?>
                <tr>
                    <td>
                        <?php if (!empty($row['FotoProfil'])): ?>
                            <img src="<?= htmlspecialchars($row['FotoProfil']) ?>" class="profile-pic" alt="Foto Profil">
                        <?php else: ?>
                            <i class="fa-solid fa-user-circle default-user-icon"></i>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($row['Username']) ?></td>
                    <td><?= htmlspecialchars($row['NoHp']) ?></td>
                    <td><?= $tanggalMulai ? $tanggalMulai->format('d-m-Y') : '-' ?></td>
                    <td><?= $tanggalSelesai ? $tanggalSelesai->format('d-m-Y') : '-' ?></td>
                    <td>
                        <?php if ($row['StatusLangganan'] !== 'non_aktif'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="user_id" value="<?= $row['IdUser'] ?>">
                                <input type="hidden" name="id_penyedia" value="<?= $row['IdPenyedia'] ?>">
                                <button type="submit" name="jadikan_pelanggan" class="btn-red" onclick="return confirm('Yakin ingin mengubah menjadi pelanggan?')">
                                    <i class="fa fa-user-minus"></i> Jadikan Pelanggan
                                </button>
                            </form>

                            <!-- Ikon peringatan -->
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="peringatan_id_user" value="<?= $row['IdUser'] ?>">
                                <input type="hidden" name="peringatan_id_penyedia" value="<?= $row['IdPenyedia'] ?>">
                                <button type="submit" name="kirim_peringatan" title="Kirim peringatan" style="background:none; border:none; cursor:pointer; margin-left:8px;">
                                    <i class="fa-solid fa-triangle-exclamation" style="color:orange; font-size:18px;"></i>
                                </button>
                            </form>
                        <?php else: ?>
                            <span style="color: gray;">Sudah jadi pelanggan</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>

                <?php if (!$hasTelat): ?>
                    <tr><td colspan="6" style="text-align:center; color:gray;">Tidak ada penyedia yang telat membayar.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <br><h2>Verifikasi Pembayaran Langganan</h2>
        <table>
            <thead>
                <tr>
                    <th>Foto</th>
                    <th>Nama</th>
                    <th>No HP</th>
                    <th>Periode Mulai</th>
                    <th>Periode Akhir</th>
                    <th>Bukti Pembayaran</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $hasPembayaran = false;
                while ($p = $resultPembayaran->fetch_assoc()):
                    $hasPembayaran = true;
                ?>

                <tr>
                    <td>
                        <?php if (!empty($p['FotoProfil'])): ?>
                            <img src="<?= htmlspecialchars($p['FotoProfil']) ?>" class="profile-pic" alt="Foto Profil">
                        <?php else: ?>
                            <i class="fa-solid fa-user-circle default-user-icon"></i>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($p['Username']) ?></td>
                    <td><?= htmlspecialchars($p['NoHp']) ?></td>
                    <td><?= date('d-m-Y', strtotime($p['PeriodeMulai'])) ?></td>
                    <td><?= date('d-m-Y', strtotime($p['PeriodeAkhir'])) ?></td>
                    <td><a href="<?= htmlspecialchars($p['BuktiPembayaran']) ?>" target="_blank">Lihat Bukti</a></td>
                    <td>
                        <form method="POST">
                            <input type="hidden" name="id_langganan" value="<?= $p['IdLangganan'] ?>">
                            <button type="submit" name="verifikasi_langganan" value="terima" class="btn-green" onclick="this.form.aksi.value='terima';">Terima</button>
                            <br><br>
                            <textarea name="catatan" placeholder="Alasan jika ditolak"></textarea>
                            <button type="submit" name="verifikasi_langganan" value="tolak" class="btn-red" onclick="this.form.aksi.value='tolak';">Tolak</button>
                            <input type="hidden" name="aksi" value="">
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if (!$hasPembayaran): ?>
                    <tr><td colspan="7" style="text-align:center; color:gray;">Tidak ada pembayaran yang perlu diverifikasi.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <!-- </div> -->
    </div>
</body>
</html>
