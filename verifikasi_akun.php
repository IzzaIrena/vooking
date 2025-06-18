<?php
include 'koneksi.php';

// Proses verifikasi saat form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idVerifikasi = $_POST['idVerifikasi'];
    $aksi = $_POST['aksi']; // 'setuju' atau 'tolak'
    $alasanTolak = $_POST['alasanTolak'] ?? null;

    // Ambil data verifikasi dulu
    $sql = "SELECT v.IdPenyedia, u.IdUser 
            FROM verifikasipenyedia v
            JOIN penyediafasilitas p ON v.IdPenyedia = p.IdPenyedia
            JOIN user u ON p.IdUser = u.IdUser
            WHERE v.IdVerifikasi = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idVerifikasi);
    $stmt->execute();
    $result = $stmt->get_result();
    $verifikasiData = $result->fetch_assoc();

    if ($verifikasiData) {
        $idPenyedia = $verifikasiData['IdPenyedia'];
        $idUser = $verifikasiData['IdUser'];

        if ($aksi === 'setuju') {
            // Update status verifikasi menjadi disetujui
            $conn->query("UPDATE verifikasipenyedia SET Status = 'disetujui', AlasanTolak = NULL WHERE IdVerifikasi = $idVerifikasi");
            $conn->query("UPDATE penyediafasilitas SET StatusVerifikasi = 'disetujui' WHERE IdPenyedia = $idPenyedia");
            $conn->query("UPDATE user SET RoleAktif = 'penyedia' WHERE IdUser = $idUser");
            $conn->query("UPDATE penyediafasilitas SET StatusLangganan = 'aktif' WHERE IdPenyedia = $idPenyedia");

            // Tambahkan TanggalAktivasi jika belum ada
            $today = date('Y-m-d');
            $conn->query("UPDATE penyediafasilitas SET TanggalAktivasi = '$today' WHERE IdPenyedia = $idPenyedia AND TanggalAktivasi IS NULL");

            // Hitung PeriodeLangganan (1 tahun)
            $periodeMulai = date('Y-m-d');
            $periodeAkhir = date('Y-m-d', strtotime('+1 year'));

            // Ambil bukti pembayaran dari tabel verifikasipenyedia
            $getBukti = $conn->prepare("SELECT BuktiPembayaran FROM verifikasipenyedia WHERE IdVerifikasi = ?");
            $getBukti->bind_param("i", $idVerifikasi);
            $getBukti->execute();
            $resultBukti = $getBukti->get_result();
            $rowBukti = $resultBukti->fetch_assoc();
            $buktiPembayaran = $rowBukti ? $rowBukti['BuktiPembayaran'] : null;

            // Masukkan ke langganan_penyedia
            $insertLangganan = $conn->prepare("INSERT INTO langganan_penyedia (IdPenyedia, PeriodeMulai, PeriodeAkhir, StatusPembayaran, BuktiPembayaran, CatatanAdmin) 
            VALUES (?, ?, ?, 'diterima', ?, 'Langganan awal saat disetujui')");
            $insertLangganan->bind_param("isss", $idPenyedia, $periodeMulai, $periodeAkhir, $buktiPembayaran);
            $insertLangganan->execute();

            // Tambah notifikasi ke user
            $isiPesan = "Permintaan Anda untuk menjadi penyedia fasilitas telah disetujui. Selamat bergabung!";
            $conn->query("INSERT INTO notifikasi (IdUser, IsiPesan, Tipe, Tanggal, StatusBaca) 
                        VALUES ($idUser, '$isiPesan', 'verifikasi', NOW(), 0)");

            $pesan = "Akun penyedia disetujui.";
        } elseif ($aksi === 'tolak') {
            if (!$alasanTolak) {
                $error = "Alasan penolakan harus diisi!";
            } else {
                $alasanTolakEsc = $conn->real_escape_string($alasanTolak);
                $conn->query("UPDATE verifikasipenyedia SET Status = 'ditolak', AlasanTolak = '$alasanTolakEsc' WHERE IdVerifikasi = $idVerifikasi");
                $conn->query("UPDATE penyediafasilitas SET StatusVerifikasi = 'ditolak' WHERE IdPenyedia = $idPenyedia");

                // Tambah notifikasi ke user
                $isiPesan = "Permintaan Anda untuk menjadi penyedia fasilitas ditolak. Alasan: " . $alasanTolakEsc;
                $conn->query("INSERT INTO notifikasi (IdUser, IsiPesan, Tipe, Tanggal, StatusBaca) 
                            VALUES ($idUser, '$isiPesan', 'verifikasi', NOW(), 0)");

                $pesan = "Akun penyedia ditolak.";
            }
        }
    } else {
        $error = "Data verifikasi tidak ditemukan.";
    }
}

// Ambil data verifikasi yang statusnya menunggu
$sql = "SELECT v.IdVerifikasi, v.TanggalPembayaran, v.BuktiPembayaran, v.Status, v.AlasanTolak, 
        p.IdPenyedia, u.Username, u.Email 
        FROM verifikasipenyedia v
        JOIN penyediafasilitas p ON v.IdPenyedia = p.IdPenyedia
        JOIN user u ON p.IdUser = u.IdUser
        WHERE v.Status = 'menunggu'
        ORDER BY v.TanggalPembayaran ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <title>Verifikasi Akun Penyedia</title>
    <link rel="stylesheet" href="admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <script>
        function toggleRejectForm(id) {
            const form = document.getElementById('reject-form-' + id);
            if (form.style.display === 'none' || form.style.display === '') {
                form.style.display = 'block';
            } else {
                form.style.display = 'none';
            }
        }
    </script>
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
        <h1>Verifikasi Akun Penyedia</h1>

        <?php if (!empty($pesan)) : ?>
            <div class="message success"><?= htmlspecialchars($pesan) ?></div>
        <?php endif; ?>
        <?php if (!empty($error)) : ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($result->num_rows === 0): ?>
            <p>Tidak ada permintaan verifikasi baru.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Tanggal Pembayaran</th>
                        <th>Bukti Pembayaran</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php 
                $no = 1;
                while ($row = $result->fetch_assoc()) : 
                ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= htmlspecialchars($row['Username']) ?></td>
                        <td><?= htmlspecialchars($row['Email']) ?></td>
                        <td><?= $row['TanggalPembayaran'] ?></td>
                        <td>
                            <?php if ($row['BuktiPembayaran']) : ?>
                                <a href="uploads/bukti_pembayaran/<?= htmlspecialchars($row['BuktiPembayaran']) ?>" target="_blank">
                                    <img src="uploads/bukti_pembayaran/<?= htmlspecialchars($row['BuktiPembayaran']) ?>" alt="Bukti Pembayaran" class="bukti-img" />
                                </a>
                            <?php else: ?>
                                Tidak ada
                            <?php endif; ?>
                        </td>
                        <td><?= ucfirst($row['Status']) ?></td>
                        <td>
                            <form method="POST" style="display:inline-block;">
                                <input type="hidden" name="idVerifikasi" value="<?= $row['IdVerifikasi'] ?>" />
                                <input type="hidden" name="aksi" value="setuju" />
                                <button type="submit" class="btn btn-approve"><i class="fa fa-check"></i> Setujui</button>
                            </form>

                            <button onclick="toggleRejectForm(<?= $row['IdVerifikasi'] ?>)" class="btn btn-reject"><i class="fa fa-times"></i> Tolak</button>

                            <form method="POST" id="reject-form-<?= $row['IdVerifikasi'] ?>" class="reject-form" style="display:none;">
                                <input type="hidden" name="idVerifikasi" value="<?= $row['IdVerifikasi'] ?>" />
                                <input type="hidden" name="aksi" value="tolak" />
                                <textarea name="alasanTolak" placeholder="Masukkan alasan penolakan..." required></textarea>
                                <button type="submit" class="btn btn-reject" style="margin-top:5px;"><i class="fa fa-paper-plane"></i> Kirim Penolakan</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
