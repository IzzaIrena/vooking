<?php
include 'koneksi.php';

// Proses aksi laporan
if (isset($_GET['tinjau'], $_GET['iduser'], $_GET['fasilitas'])) {
    $idLaporan = intval($_GET['tinjau']);
    $idUser = intval($_GET['iduser']);
    $namaFasilitas = mysqli_real_escape_string($conn, $_GET['fasilitas']);
    $pesan = "Laporan Anda terhadap fasilitas '$namaFasilitas' sedang ditinjau oleh admin.";
    $tanggal = date("Y-m-d H:i:s");

    // Update status laporan ke 'ditinjau'
    $conn->query("UPDATE laporan SET StatusLaporan = 'ditinjau' WHERE IdLaporan = $idLaporan");

    // Kirim notifikasi
    $stmt = $conn->prepare("INSERT INTO notifikasi (IdUser, IsiPesan, Tipe, Tanggal, StatusBaca)
                            VALUES (?, ?, 'sistem', ?, 0)");
    $stmt->bind_param("iss", $idUser, $pesan, $tanggal);
    $stmt->execute();
    $stmt->close();
}

if (isset($_GET['terima'], $_GET['iduser'], $_GET['fasilitas'])) {
    $idLaporan = intval($_GET['terima']);
    $idUser = intval($_GET['iduser']);
    $namaFasilitas = mysqli_real_escape_string($conn, $_GET['fasilitas']);
    $pesan = "Laporan Anda terhadap fasilitas '$namaFasilitas' telah diterima. Terima kasih atas laporannya.";
    $tanggal = date("Y-m-d H:i:s");

    // Update status ke selesai
    $conn->query("UPDATE laporan SET StatusLaporan = 'selesai' WHERE IdLaporan = $idLaporan");

    // Kirim notifikasi
    $stmt = $conn->prepare("INSERT INTO notifikasi (IdUser, IsiPesan, Tipe, Tanggal, StatusBaca)
                            VALUES (?, ?, 'sistem', ?, 0)");
    $stmt->bind_param("iss", $idUser, $pesan, $tanggal);
    $stmt->execute();
    $stmt->close();
}

if (isset($_GET['tolak'], $_GET['iduser'], $_GET['fasilitas'])) {
    $idLaporan = intval($_GET['tolak']);
    $idUser = intval($_GET['iduser']);
    $namaFasilitas = mysqli_real_escape_string($conn, $_GET['fasilitas']);
    $pesan = "Laporan Anda terhadap fasilitas '$namaFasilitas' telah ditolak oleh admin. Silakan cek kembali.";
    $tanggal = date("Y-m-d H:i:s");

    // Update status ke selesai
    $conn->query("UPDATE laporan SET StatusLaporan = 'selesai' WHERE IdLaporan = $idLaporan");

    // Kirim notifikasi
    $stmt = $conn->prepare("INSERT INTO notifikasi (IdUser, IsiPesan, Tipe, Tanggal, StatusBaca)
                            VALUES (?, ?, 'sistem', ?, 0)");
    $stmt->bind_param("iss", $idUser, $pesan, $tanggal);
    $stmt->execute();
    $stmt->close();
}

$query = "
    SELECT l.IdLaporan, u.IdUser, u.Username, f.IdFasilitas, f.NamaFasilitas, l.Deskripsi, l.BuktiFoto, l.StatusLaporan
    FROM laporan l
    JOIN pelanggan p ON l.IdPelanggan = p.IdPelanggan
    JOIN user u ON p.IdUser = u.IdUser
    JOIN fasilitas f ON l.IdFasilitas = f.IdFasilitas
    WHERE l.StatusLaporan != 'selesai'
    ORDER BY l.IdLaporan ASC
";

$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Masuk</title>
    <link rel="stylesheet" href="admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
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
            <h1>Laporan Fasilitas Masuk</h1>
            <div class="container">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Pelapor</th>
                            <th>Fasilitas</th>
                            <th>Deskripsi</th>
                            <th>Bukti Foto</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= htmlspecialchars($row['Username']) ?></td>
                            <td><?= htmlspecialchars($row['NamaFasilitas']) ?></td>
                            <td><?= nl2br(htmlspecialchars($row['Deskripsi'])) ?></td>
                            <td>
                                <?php if ($row['BuktiFoto']): ?>
                                    <a href="uploads/bukti_laporan/<?= htmlspecialchars($row['BuktiFoto']) ?>" target="_blank">
                                        <img src="uploads/bukti_laporan/<?= htmlspecialchars($row['BuktiFoto']) ?>" class="bukti-foto" alt="Bukti Foto">
                                    </a>
                                <?php else: ?>
                                    Tidak Ada
                                <?php endif; ?>
                            <td>
                                <div class="aksi-container">
                                    <a href="manajemen_fasilitas.php?tinjau=<?= $row['IdFasilitas'] ?>&idlaporan=<?= $row['IdLaporan'] ?>&iduser=<?= $row['IdUser'] ?>&fasilitas=<?= urlencode($row['NamaFasilitas']) ?>"
                                    class="aksi-link ditinjau" 
                                    onclick="return confirm('Tinjau laporan ini?')">
                                    Tinjau
                                    </a>

                                    <a href="?terima=<?= $row['IdLaporan'] ?>&iduser=<?= $row['IdUser'] ?>&fasilitas=<?= urlencode($row['NamaFasilitas']) ?>" 
                                    class="aksi-link selesai" 
                                    onclick="return confirm('Terima laporan ini?')">
                                    Terima
                                    </a>

                                    <a href="?tolak=<?= $row['IdLaporan'] ?>&iduser=<?= $row['IdUser'] ?>&fasilitas=<?= urlencode($row['NamaFasilitas']) ?>" 
                                    class="aksi-link dikirim" 
                                    onclick="return confirm('Tolak laporan ini?')">
                                    Tolak
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>