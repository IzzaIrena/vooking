<?php
session_start();
include 'koneksi.php';

if (!isset($_GET['id'])) {
    echo "ID fasilitas tidak ditemukan.";
    exit();
}

$idFasilitas = (int)$_GET['id'];

$role = $_SESSION['RoleAktif'] ?? 'pelanggan';
$profile_link = ($role === 'penyedia') ? 'profil_penyedia.php' : 'profil_pelanggan.php';

// Gunakan referer jika valid, fallback ke default
if (isset($_SERVER['HTTP_REFERER'])) {
    $referer = basename(parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH));

    // Hanya izinkan kembali ke halaman yang memang aman
    $allowed_back_pages = ['home.php', 'penyedia_booking.php'];
    if (in_array($referer, $allowed_back_pages)) {
        $back_link = $referer;
    } else {
        $back_link = ($role === 'penyedia') ? 'penyedia_booking.php' : 'home.php';
    }
} else {
    $back_link = ($role === 'penyedia') ? 'penyedia_booking.php' : 'home.php';
}

// Ambil data fasilitas
$queryFasilitas = "SELECT * FROM fasilitas WHERE IdFasilitas = $idFasilitas";
$resultFasilitas = mysqli_query($conn, $queryFasilitas);
$fasilitas = mysqli_fetch_assoc($resultFasilitas);

if (!$fasilitas) {
    echo "Fasilitas tidak ditemukan.";
    exit();
}

$userId = $_SESSION['IdUser'] ?? null;
$message = '';

// Cari IdPelanggan berdasarkan IdUser yang login
$idUser = $_SESSION['IdUser'] ?? null;
$idPelanggan = null;

if ($idUser) {
    $sqlCariPelanggan = "SELECT IdPelanggan FROM pelanggan WHERE IdUser = ?";
    $stmtCariPelanggan = $conn->prepare($sqlCariPelanggan);
    $stmtCariPelanggan->bind_param("i", $idUser);
    $stmtCariPelanggan->execute();
    $resultCari = $stmtCariPelanggan->get_result();
    if ($rowPelanggan = $resultCari->fetch_assoc()) {
        $idPelanggan = $rowPelanggan['IdPelanggan'];
    }
}

// Proses submit ulasan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ulasan'])) {
    if (!$userId) {
        $message = "Anda harus login untuk memberi ulasan.";
    } else {
        $rating = floatval($_POST['rating']);
        $komentar = trim($_POST['komentar']);

        // Validasi rating
        if ($rating < 0 || $rating > 5) {
            $message = "Rating harus antara 0 sampai 5.";
        } else {
            // Cek apakah user sudah memberi ulasan untuk fasilitas ini
            $checkSql = "SELECT * FROM ulasan WHERE IdFasilitas = ? AND IdPelanggan = ?";
            $stmtCheck = $conn->prepare($checkSql);
            $stmtCheck->bind_param("ii", $idFasilitas, $idPelanggan);
            $stmtCheck->execute();
            $resultCheck = $stmtCheck->get_result();

            if ($resultCheck->num_rows > 0) {
                // Update ulasan lama
                $updateSql = "UPDATE ulasan SET Rating = ?, Komentar = ?, Tanggal = NOW() WHERE IdFasilitas = ? AND IdPelanggan = ?";
                $stmtUpdate = $conn->prepare($updateSql);
                $stmtUpdate->bind_param("dsii", $rating, $komentar, $idFasilitas, $idPelanggan);
                $stmtUpdate->execute();
                $message = "Ulasan berhasil diperbarui.";
            } else {
                // Insert ulasan baru
                $insertSql = "INSERT INTO ulasan (IdFasilitas, IdPelanggan, Rating, Komentar) VALUES (?, ?, ?, ?)";
                $stmtInsert = $conn->prepare($insertSql);
                $stmtInsert->bind_param("iids", $idFasilitas, $idPelanggan, $rating, $komentar);
                $stmtInsert->execute();
                $message = "Terima kasih atas ulasan Anda.";
            }
        }
    }
}

// Ambil data unit
$queryUnit = "SELECT * FROM unit WHERE IdFasilitas = $idFasilitas";
$resultUnit = mysqli_query($conn, $queryUnit);
$units = [];
while ($row = mysqli_fetch_assoc($resultUnit)) {
    $units[] = $row;
}

$queryPenyedia = "
    SELECT u.Username, u.FotoProfil, u.LastActive
    FROM penyediafasilitas pf
    JOIN user u ON pf.IdPenyedia = u.IdUser
    WHERE pf.IdPenyedia = {$fasilitas['IdPenyedia']}
    LIMIT 1
";

$queryUlasan = "
    SELECT u.Username, u.FotoProfil, ulasan.Rating, ulasan.Komentar, ulasan.Tanggal
    FROM ulasan
    JOIN pelanggan p ON ulasan.IdPelanggan = p.IdPelanggan
    JOIN user u ON p.IdUser = u.IdUser
    WHERE ulasan.IdFasilitas = ?
    ORDER BY ulasan.Tanggal DESC
";

$stmtUlasan = $conn->prepare($queryUlasan);
$stmtUlasan->bind_param("i", $idFasilitas);
$stmtUlasan->execute();
$resultUlasan = $stmtUlasan->get_result();
$ulasan_terbatas = [];
$ulasan_semua = [];

$counter = 0;
while ($row = $resultUlasan->fetch_assoc()) {
    if ($counter < 3) {
        $ulasan_terbatas[] = $row;
    }
    $ulasan_semua[] = $row;
    $counter++;
}

$totalUlasan = count($ulasan_semua);

// Hitung rata-rata rating
$queryAvgRating = "SELECT AVG(Rating) AS AvgRating FROM ulasan WHERE IdFasilitas = ?";
$stmtAvg = $conn->prepare($queryAvgRating);
$stmtAvg->bind_param("i", $idFasilitas);
$stmtAvg->execute();
$resultAvg = $stmtAvg->get_result();
$avgRating = 0;
if ($rowAvg = $resultAvg->fetch_assoc()) {
    $avgRating = round($rowAvg['AvgRating'], 1); // Pembulatan 1 angka di belakang koma
}

// Ambil data penyedia & user
$queryPenyedia = "
    SELECT u.Username, u.FotoProfil, u.LastActive
    FROM penyediafasilitas pf
    JOIN user u ON pf.IdUser = u.IdUser
    WHERE pf.IdPenyedia = {$fasilitas['IdPenyedia']}
    LIMIT 1
";
$resultPenyedia = mysqli_query($conn, $queryPenyedia);
$penyedia = mysqli_fetch_assoc($resultPenyedia);

// Format LastActive
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Detail Fasilitas - VOOKING</title>
    <link rel="stylesheet" href="home.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="penyedia_home.css">
</head>
<body>
<div class="main">
    <!-- Header -->
    <div class="header-fixed">
        <div class="vooking-header">
            <div class="logo-title">
                <a href="<?= $back_link ?>" class="back-home-link">
                    <i class="fa-solid fa-arrow-left back-home-icon"></i>
                </a>
                <img src="Vicon.png" class="logo-img" />
                <h1>VOOKING</h1>
            </div>
        </div>
    </div>

    <div class="content-scrollable">
        <!-- Detail Fasilitas -->
        <div class="detail-container">

            <!-- Informasi Penyedia -->
            <?php if ($penyedia): ?>
                <div class="penyedia-info">
                    <a href="lihat_profil_penyedia.php?id=<?= $fasilitas['IdPenyedia'] ?>">
                        <img src="<?= !empty($penyedia['FotoProfil']) ? htmlspecialchars($penyedia['FotoProfil']) : 'img/default_profile.png' ?>" alt="Foto Profil" style="border-radius:50%; width:50px; height:50px; object-fit:cover;">
                    </a>
                    <div class="text">
                        <a href="lihat_profil_penyedia.php?id=<?= $fasilitas['IdPenyedia'] ?>" style="text-decoration:none; color:black;">
                            <strong><?= htmlspecialchars($penyedia['Username']) ?></strong>
                        </a>
                        <span><?= formatLastActive($penyedia['LastActive']) ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <h2><?= htmlspecialchars($fasilitas['NamaFasilitas']) ?></h2>

            <div class="fasilitas-wrapper">
                <img src="<?= !empty($fasilitas['FotoFasilitas']) ? htmlspecialchars($fasilitas['FotoFasilitas']) : 'img/default.jpg' ?>" class="fasilitas-img" alt="Foto Fasilitas">

                <div class="fasilitas-info">
                    <p><strong>Kategori:</strong> <?= htmlspecialchars($fasilitas['Kategori']) ?></p>
                    <p><strong>Lokasi:</strong> <?= htmlspecialchars($fasilitas['Lokasi']) ?></p>
                    <p><strong>Provinsi:</strong> <?= htmlspecialchars($fasilitas['Provinsi'] ?? '-') ?></p>
                    <p><strong>Tipe Booking:</strong> <?= htmlspecialchars($fasilitas['TipeBooking']) ?></p>
                    <p><strong>Jam Operasional:</strong> <?= htmlspecialchars($fasilitas['JamBuka']) ?> - <?= htmlspecialchars($fasilitas['JamTutup']) ?></p>
                    <p><strong>No. Rekening:</strong> <?= htmlspecialchars($fasilitas['NoRekening'] ?? '-') ?></p>
                    <p><strong>Deskripsi:</strong> <?= nl2br(htmlspecialchars($fasilitas['Deskripsi'] ?? '-')) ?></p>
                </div>
            </div>

            <h3>Daftar Unit</h3>
            <?php if (count($units) > 0): ?>
                <table class="unit-table">
                    <thead>
                        <tr>
                            <th>Nama Tipe Unit</th>
                            <th>Jumlah Ruang</th>
                            <th>Harga</th>
                            <th>DP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($units as $unit): ?>
                            <tr>
                                <td><?= htmlspecialchars($unit['NamaTipeUnit']) ?></td>
                                <td><?= htmlspecialchars($unit['JumlahRuang']) ?></td>
                                <td>Rp<?= number_format($unit['Harga'], 0, ',', '.') ?></td>
                                <td>Rp<?= number_format($unit['DP'], 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Tidak ada unit tersedia.</p>
            <?php endif; ?>

            <div class="rating-overall">
                <h3>Rating Keseluruhan</h3>
                <?php if ($totalUlasan > 0): ?>
                    <p><strong><?= $avgRating ?> / 5</strong> dari <?= $totalUlasan ?> ulasan</p>
                <?php else: ?>
                    <p>Belum ada ulasan.</p>
                <?php endif; ?>
            </div>

            <a href="booking_fasilitas.php?id=<?= $idFasilitas ?>" class="booking">
            <i class="fa fa-calendar-check"></i> Booking Sekarang
            </a>
            <a href="chat.php?IdPenyedia=<?= $fasilitas['IdPenyedia'] ?>&IdFasilitas=<?= $fasilitas['IdFasilitas'] ?>" class="booking" style="margin-left:10px;">
            <i class="fa-solid fa-comment"></i> Hubungi Penyedia
            </a> 
            
            <?php if (isset($idPelanggan) && $fasilitas['IdPenyedia'] != $_SESSION['IdUser']): ?>
                <a href="laporkan_fasilitas.php?id=<?= $idFasilitas ?>" class="btn-laporkan">
                    <i class="fa-solid fa-flag"></i> Laporkan
                </a>
            <?php endif; ?>
            
            <div class="ulasan-section">
                <h3>Berikan Rating dan Ulasan</h3>
                <?php if ($message): ?>
                    <p><em><?= htmlspecialchars($message) ?></em></p>
                <?php endif; ?>

                <?php if (isset($_SESSION['IdUser'])): ?>
                <form method="post" action="">
                    <label for="rating">Rating (0 - 5):</label><br>
                    <input type="number" step="0.1" min="0" max="5" name="rating" id="rating" required><br>
                    <label for="komentar">Komentar:</label><br>
                    <textarea name="komentar" id="komentar" rows="4" maxlength="1000" placeholder="Tulis ulasan Anda..."></textarea><br>
                    <button type="submit" name="submit_ulasan"
                    style="display: inline-flex; align-items: center; justify-content: center;
                            background-color: #0056b3; color: white; font-size: 14px;
                            font-weight: bold; border: none; border-radius: 6px;
                            padding: 10px 16px; cursor: pointer; height: 40px;
                            width: auto; max-width: 200px; transition: background-color 0.3s ease;">
                    <i class="fas fa-comment-dots" style="margin-right: 8px;"></i> Kirim Ulasan
                    </button>

                </form>
                <?php else: ?>
                    <p><em>Silakan login untuk memberikan ulasan.</em></p>
                <?php endif; ?>

                <h3>Ulasan Pengguna</h3>
                <?php if (count($ulasan_terbatas) > 0): ?>
                    <?php foreach ($ulasan_terbatas as $ulasan): ?>
                        <div class="ulasan-box">
                            <?php if (!empty($ulasan['FotoProfil'])): ?>
                                <img src="<?= htmlspecialchars($ulasan['FotoProfil']) ?>" alt="Foto Profil" class="ulasan-foto">
                            <?php else: ?>
                                <div class="ulasan-foto default-icon">
                                    <i class="fa-solid fa-user"></i>
                                </div>
                            <?php endif; ?>
                            <div class="ulasan-detail">
                                <strong><?= htmlspecialchars($ulasan['Username']) ?></strong>
                                <span><?= htmlspecialchars($ulasan['Tanggal']) ?> | Rating: <?= htmlspecialchars($ulasan['Rating']) ?>/5</span>
                                <p><?= nl2br(htmlspecialchars($ulasan['Komentar'])) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($totalUlasan > 3): ?>
                        <a href="ulasan_fasilitas.php?id=<?= $idFasilitas ?>" class="lihat-semua-btn">Lihat Semua Ulasan (<?= $totalUlasan ?>)</a>
                    <?php endif; ?>
                <?php else: ?>
                    <p>Belum ada ulasan untuk fasilitas ini.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script>
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
</script>
</body>
</html>
