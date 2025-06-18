<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['IdUser']) || $_SESSION['RoleAktif'] !== 'penyedia') {
    header("Location: login_register.php");
    exit();
}

$IdUser = $_SESSION['IdUser'];

// Ambil IdPenyedia berdasarkan IdUser
$queryPenyedia = "SELECT IdPenyedia FROM penyediafasilitas WHERE IdUser = ?";
$stmtPenyedia = mysqli_prepare($conn, $queryPenyedia);
mysqli_stmt_bind_param($stmtPenyedia, "i", $IdUser);
mysqli_stmt_execute($stmtPenyedia);
$resultPenyedia = mysqli_stmt_get_result($stmtPenyedia);
$rowPenyedia = mysqli_fetch_assoc($resultPenyedia);
$IdPenyedia = $rowPenyedia['IdPenyedia'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['id_booking'], $_POST['aksi'])) {
        $idBooking = (int) $_POST['id_booking'];
        $aksi = $_POST['aksi'];

        // Ambil info pelanggan dan nama fasilitas dari booking
        $queryInfo = "SELECT p.IdUser, f.NamaFasilitas, u.NamaTipeUnit, b.NomorRuang, b.Tanggal, b.Jam
                    FROM booking b
                    JOIN pelanggan p ON b.IdPelanggan = p.IdPelanggan
                    JOIN unit u ON b.IdUnit = u.IdUnit
                    JOIN fasilitas f ON u.IdFasilitas = f.IdFasilitas
                    WHERE b.IdBooking = ?";
        $stmtInfo = mysqli_prepare($conn, $queryInfo);
        mysqli_stmt_bind_param($stmtInfo, "i", $idBooking);
        mysqli_stmt_execute($stmtInfo);
        $resultInfo = mysqli_stmt_get_result($stmtInfo);
        $row = mysqli_fetch_assoc($resultInfo);

        $IdUserPelanggan = $row['IdUser'];
        $namaFasilitas = $row['NamaFasilitas'];
        $namaTipeUnit = $row['NamaTipeUnit'];
        $nomorRuang = $row['NomorRuang'];
        $tanggal = date('d M Y', strtotime($row['Tanggal']));
        $jam = $row['Jam'];

        $jamMulai = "";
        $jamSelesai = "";
        if (strpos($jam, '-') !== false) {
            list($jamMulai, $jamSelesai) = explode('-', $jam);
        } else {
            $jamMulai = $jam;
        }

        if ($jamMulai) {
            $jamMulai = date('H:i', strtotime($jamMulai));
        }
        if ($jamSelesai) {
            $jamSelesai = date('H:i', strtotime($jamSelesai));
        }

        $waktuTampil = $jamMulai;
        if ($jamSelesai) {
            $waktuTampil .= " - " . $jamSelesai;
        }

        if ($aksi === 'setujui') {
            $status = 'diterima';
            $update = "UPDATE booking SET StatusBooking = ? WHERE IdBooking = ?";
            $stmt = mysqli_prepare($conn, $update);
            mysqli_stmt_bind_param($stmt, "si", $status, $idBooking);
            mysqli_stmt_execute($stmt);

            $pesan = "Pesanan Anda untuk fasilitas $namaFasilitas (Unit: $namaTipeUnit, Ruang: $nomorRuang) pada tanggal $tanggal pukul $jamMulai - $jamSelesai telah diterima.";
        } elseif ($aksi === 'tolak' && !empty($_POST['alasan_tolak'])) {
            $status = 'ditolak';
            $alasan = $_POST['alasan_tolak'];
            $update = "UPDATE booking SET StatusBooking = ?, AlasanTolak = ? WHERE IdBooking = ?";
            $stmt = mysqli_prepare($conn, $update);
            mysqli_stmt_bind_param($stmt, "ssi", $status, $alasan, $idBooking);
            mysqli_stmt_execute($stmt);

            $pesan = "Maaf, pesanan Anda untuk fasilitas $namaFasilitas (Unit: $namaTipeUnit, Ruang: $nomorRuang) pada tanggal $tanggal pukul $jamMulai - $jamSelesai ditolak. Alasan: $alasan";
        }

        // Insert notifikasi
        if (!empty($pesan)) {
            $tipe = 'booking';
            $tanggal = date('Y-m-d H:i:s');
            $statusBaca = 0;

            $insertNotif = "INSERT INTO notifikasi (IdUser, IsiPesan, Tipe, Tanggal, StatusBaca) 
                            VALUES (?, ?, ?, ?, ?)";
            $stmtNotif = mysqli_prepare($conn, $insertNotif);
            mysqli_stmt_bind_param($stmtNotif, "isssi", $IdUserPelanggan, $pesan, $tipe, $tanggal, $statusBaca);
            mysqli_stmt_execute($stmtNotif);
        }

        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Ambil data booking menunggu terbaru
$query = "SELECT 
    b.IdBooking,
    b.IdPelanggan,
    b.IdUnit,
    b.NomorRuang,
    b.Tanggal,
    b.Jam,
    b.BuktiDP,
    b.TotalDP AS DP,
    u.NamaTipeUnit,
    f.NamaFasilitas,
    u2.Username AS Nama,
    u2.NoHp
FROM booking b
JOIN unit u ON b.IdUnit = u.IdUnit
JOIN fasilitas f ON u.IdFasilitas = f.IdFasilitas
JOIN pelanggan p ON b.IdPelanggan = p.IdPelanggan
JOIN user u2 ON p.IdUser = u2.IdUser
WHERE f.IdPenyedia = ? AND b.StatusBooking = 'menunggu'
ORDER BY b.Tanggal";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $IdPenyedia);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$totalDP = 0;
$sqlTotalDP = "SELECT SUM(b.TotalDP) as TotalDP
               FROM booking b
               JOIN unit u ON b.IdUnit = u.IdUnit
               JOIN fasilitas f ON u.IdFasilitas = f.IdFasilitas
               WHERE f.IdPenyedia = ? AND b.StatusBooking = 'menunggu'";

$stmtTotalDP = $conn->prepare($sqlTotalDP);
$stmtTotalDP->bind_param("i", $IdPenyedia);
$stmtTotalDP->execute();
$resultTotalDP = $stmtTotalDP->get_result();

if ($rowTotal = $resultTotalDP->fetch_assoc()) {
    $totalDP = $rowTotal['TotalDP'] ?? 0;
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
    <meta charset="UTF-8" />
    <title>Verifikasi Pemesanan - Vooking</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link rel="stylesheet" href="penyedia_home.css?v=<?= time(); ?>" />
    <link rel="stylesheet" href="notif_chat.css">
    <link rel="stylesheet" href="button.css">
    <link rel="stylesheet" href="verfikasi_pemesanan.css">
    <script>
        function tampilkanFormTolak(id) {
            const form = document.getElementById('form-tolak-' + id);
            form.style.display = 'block';
        }

        function batalkanTolak(id) {
            const form = document.getElementById('form-tolak-' + id);
            form.style.display = 'none';
        }
    </script>
</head>
<body>
<?php include "sidebar_penyedia.php"; ?>

<div class="main">
    <div class="header-fixed">
        <?php include "header_penyedia.php";?> 
    </div>

    <div class="content-scrollable">
        <h2 style="text-align:center; margin-top:20px;">Daftar Pemesanan Menunggu</h2>
        <p style="text-align:center; font-size:18px; margin-top:10px;">
            Total DP Pemesanan Menunggu: <strong>Rp <?= number_format($totalDP, 0, ',', '.') ?></strong>
        </p>

        <?php if (mysqli_num_rows($result) > 0): ?>
            <table class="booking-table">
                <tr>
                    <th>Nama Fasilitas</th>
                    <th>Tipe Unit</th>
                    <th>Nomor Ruang</th>
                    <th>Nama Pemesan</th>
                    <th>Nomor HP</th>
                    <th>Tanggal</th>
                    <th>Jam</th>
                    <th>Total DP</th>
                    <th>Bukti DP</th>
                    <th>Aksi</th>
                </tr>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['NamaFasilitas']) ?></td>
                        <td><?= htmlspecialchars($row['NamaTipeUnit']) ?></td>
                        <td><?= htmlspecialchars($row['NomorRuang']) ?></td>
                        <td><?= htmlspecialchars($row['Nama']) ?></td>
                        <td><?= htmlspecialchars($row['NoHp']) ?></td>
                        <td>
                            <?php
                            $tanggalArray = explode(',', $row['Tanggal']);
                            foreach ($tanggalArray as $tgl) {
                                echo date('d M Y', strtotime(trim($tgl))) . "<br>";
                            }
                            ?>
                        </td>
                        <td><?= htmlspecialchars($row['Jam']) ?></td>
                        <td>Rp <?= number_format($row['DP'], 0, ',', '.') ?></td>
                        <td>
                            <?php if ($row['BuktiDP']): ?>
                                <a href="<?= htmlspecialchars($row['BuktiDP']) ?>" target="_blank">
                                    <img src="<?= htmlspecialchars($row['BuktiDP']) ?>" class="bukti-img" alt="Bukti DP" />
                                </a>
                            <?php else: ?>
                                Tidak ada
                            <?php endif; ?>
                        </td>
                        <td>
                            <!-- Tombol Setujui -->
                            <form method="POST" class="action-form">
                                <input type="hidden" name="id_booking" value="<?= $row['IdBooking'] ?>" />
                                <input type="hidden" name="aksi" value="setujui" />
                                <button type="submit" class="btn-accept"><i class="fa-solid fa-circle-check"></i> Terima</button>
                            </form>

                            <!-- Tombol untuk menampilkan form alasan -->
                            <button type="button" class="btn-decline" onclick="tampilkanFormTolak(<?= $row['IdBooking'] ?>)"><i class="fa-solid fa-circle-xmark"></i> Tolak</button>

                            <!-- Form alasan tolak (disembunyikan awalnya) -->
                            <form method="POST" class="action-form" id="form-tolak-<?= $row['IdBooking'] ?>" style="display: none; margin-top: 5px;">
                                <input type="hidden" name="id_booking" value="<?= $row['IdBooking'] ?>" />
                                <input type="hidden" name="aksi" value="tolak" />
                                <textarea name="alasan_tolak" rows="2" cols="20" placeholder="Alasan penolakan" required></textarea><br>
                                <button type="submit" class="btn-decline">Kirim</button>
                                <button type="button" onclick="batalkanTolak(<?= $row['IdBooking'] ?>)">Batal</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </table>
        <?php else: ?>
            <div class="empty-message">Tidak ada pemesanan yang menunggu.</div>
        <?php endif; ?>
    </div>
<script>
    function tampilkanFormTolak(id) {
        const form = document.getElementById('form-tolak-' + id);
        if (form) {
            form.style.display = 'block';
        }
    }

    function batalkanTolak(id) {
        const form = document.getElementById('form-tolak-' + id);
        if (form) {
            form.style.display = 'none';
        }
    }

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

    // Jalankan pertama kali dan setiap 10 detik
    cekChatBaru();
    setInterval(cekChatBaru, 10000); // setiap 10 detik

    document.addEventListener('click', function(event) {
        const notifWrapper = document.querySelector('.notif-icon-wrapper');
        if (!notifWrapper.contains(event.target)) {
            document.getElementById("notifDropdown").style.display = "none";
        }
    });
</script>
</body>
</html>
