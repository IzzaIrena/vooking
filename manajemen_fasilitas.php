<?php
include 'koneksi.php';

$tinjauId = isset($_GET['tinjau']) ? intval($_GET['tinjau']) : null;
$idLaporan = isset($_GET['idlaporan']) ? intval($_GET['idlaporan']) : null;
$idUserPelapor = isset($_GET['iduser']) ? intval($_GET['iduser']) : null;
$namaFasilitas = isset($_GET['fasilitas']) ? mysqli_real_escape_string($conn, $_GET['fasilitas']) : null;

// Kirim notifikasi dan update laporan jika lengkap
if ($tinjauId && $idLaporan && $idUserPelapor && $namaFasilitas) {
    // Update status laporan
    $conn->query("UPDATE laporan SET StatusLaporan = 'ditinjau' WHERE IdLaporan = $idLaporan");

    // Kirim notifikasi sistem
    $namaFasilitasEscaped = mysqli_real_escape_string($conn, $namaFasilitas);
    $isiPesan = "Laporan Anda terhadap fasilitas \"$namaFasilitasEscaped\" sedang ditinjau oleh admin.";
    $tanggal = date("Y-m-d H:i:s");
    $conn->query("INSERT INTO notifikasi (IdUser, IsiPesan, Tipe, Tanggal, StatusBaca) 
                  VALUES ($idUserPelapor, '$isiPesan', 'sistem', '$tanggal', 0)");

    // Redirect agar parameter idlaporan & iduser tidak dikirim ulang saat refresh
    header("Location: manajemen_fasilitas.php?tinjau=$tinjauId");
    exit;
}

// Proses aksi admin
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'], $_GET['aksi'])) {
    $id = intval($_GET['id']);
    $aksi = $_GET['aksi'];
    
    if ($aksi === 'tangguhkan') {
        $conn->query("UPDATE fasilitas SET Status = 'non_aktif_admin' WHERE IdFasilitas = $id");
    } elseif ($aksi === 'aktifkan') {
        $conn->query("UPDATE fasilitas SET Status = 'aktif' WHERE IdFasilitas = $id");
    }

    header("Location: manajemen_fasilitas.php");
    exit;
}

$sql = "SELECT f.*, u.Username AS NamaPenyedia 
        FROM fasilitas f
        JOIN penyediafasilitas p ON f.IdPenyedia = p.IdPenyedia
        JOIN user u ON p.IdUser = u.IdUser";

if ($tinjauId) {
    // Tampilkan fasilitas ditinjau di paling atas
    $sql .= " ORDER BY (IdFasilitas = $tinjauId) DESC, IdFasilitas DESC";
}
$result = $conn->query($sql);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <title>Manajemen Fasilitas</title>
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
            <h1>Manajemen Fasilitas</h1>
            <table>
                <thead>
                    <tr>
                        <th>Foto</th>
                        <th>Nama Fasilitas</th>
                        <th>Nama Penyedia</th>
                        <th>Kategori</th>
                        <th>Status</th>
                        <th>Ketersediaan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <?php $highlight = ($tinjauId === intval($row['IdFasilitas'])) ? 'style="background-color: #fff8dc;"' : ''; ?>
                    <tr <?= $highlight ?>>
                        <td>
                            <?php if (!empty($row['FotoFasilitas'])): ?>
                                <img src="<?= htmlspecialchars($row['FotoFasilitas']) ?>" alt="">
                            <?php else: ?>
                                <i class="fa fa-image" style="font-size: 24px; color:#ccc;"></i>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($row['NamaFasilitas']) ?></td>
                        <td><?= htmlspecialchars($row['NamaPenyedia']) ?></td>
                        <td><?= htmlspecialchars($row['Kategori']) ?></td>
                        <td><?= htmlspecialchars($row['Status']) ?></td>
                        <td><?= htmlspecialchars($row['Ketersediaan'] ?? 'Buka') ?></td>
                        <td>
                            <?php if ($row['Status'] === 'aktif'): ?>
                                <button class="btn-aksi btn-tangguhkan"
                                    onclick="if(confirm('Tangguhkan fasilitas ini?')) window.location='?id=<?= $row['IdFasilitas'] ?>&aksi=tangguhkan'">
                                    Tangguhkan
                                </button>
                            <?php elseif ($row['Status'] === 'non_aktif_admin'): ?>
                                <button class="btn-aksi btn-aktifkan"
                                    onclick="if(confirm('Aktifkan kembali fasilitas ini?')) window.location='?id=<?= $row['IdFasilitas'] ?>&aksi=aktifkan'">
                                    Aktifkan
                                </button>
                            <?php else: ?>
                                <button class="btn-aksi btn-disabled" disabled>--</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>