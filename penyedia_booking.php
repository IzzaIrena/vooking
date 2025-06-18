<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['IdUser']) || $_SESSION['RoleAktif'] !== 'penyedia') {
    header("Location: login_register.php");
    exit();
}

$IdUser = $_SESSION['IdUser'];

// Ambil IdPenyedia berdasarkan IdUser
$sqlGetPenyedia = "SELECT IdPenyedia FROM penyediafasilitas WHERE IdUser = ?";
$stmtGet = $conn->prepare($sqlGetPenyedia);
$stmtGet->bind_param("i", $IdUser);
$stmtGet->execute();
$resGet = $stmtGet->get_result();
$dataPenyedia = $resGet->fetch_assoc();
$IdPenyedia = $dataPenyedia['IdPenyedia'] ?? 0;

// Query fasilitas aktif milik penyedia lain
$query = "SELECT f.*
          FROM fasilitas f
          JOIN penyediafasilitas p ON f.IdPenyedia = p.IdPenyedia
          WHERE f.Status = 'aktif' AND p.IdUser != ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $IdUser);
$stmt->execute();
$fasilitas_result = $stmt->get_result();

$fasilitas_data = [];
while ($row = $fasilitas_result->fetch_assoc()) {
    $idf = (int)$row['IdFasilitas'];

    // Ambil harga minimum unit untuk fasilitas ini
    $resHarga = $conn->prepare("SELECT MIN(Harga) as minHarga FROM unit WHERE IdFasilitas = ?");
    $resHarga->bind_param("i", $idf);
    $resHarga->execute();
    $resultHarga = $resHarga->get_result();
    $rowHarga = $resultHarga->fetch_assoc();
    $row['HargaMin'] = ($rowHarga && $rowHarga['minHarga'] !== null) ? $rowHarga['minHarga'] : 0;
    $resHarga->close();

    // Ambil rating rata-rata dan jumlah ulasan
    $resUlasan = $conn->prepare("SELECT AVG(Rating) AS avgRating, COUNT(IdUlasan) AS reviewCount FROM ulasan WHERE IdFasilitas = ?");
    $resUlasan->bind_param("i", $idf);
    $resUlasan->execute();
    $resultUlasan = $resUlasan->get_result();
    $rowUlasan = $resultUlasan->fetch_assoc();
    $row['AverageRating'] = ($rowUlasan && $rowUlasan['avgRating'] !== null) ? round($rowUlasan['avgRating'], 1) : 0;
    $row['ReviewCount'] = ($rowUlasan) ? $rowUlasan['reviewCount'] : 0;
    $resUlasan->close();

    $fasilitas_data[] = $row;
}
$stmt->close();

// Ambil daftar provinsi unik dari fasilitas aktif milik penyedia lain
$provinsi_query = "
    SELECT DISTINCT f.Provinsi
    FROM fasilitas f
    JOIN penyediafasilitas p ON f.IdPenyedia = p.IdPenyedia
    WHERE f.Status = 'aktif' AND p.IdUser != ?
";
$provinsi_stmt = $conn->prepare($provinsi_query);
$provinsi_stmt->bind_param("i", $IdUser);
$provinsi_stmt->execute();
$provinsi_result = $provinsi_stmt->get_result();

$provinsi_list = [];
while ($rowProvinsi = $provinsi_result->fetch_assoc()) {
    $provinsi_list[] = $rowProvinsi['Provinsi'];
}
$provinsi_stmt->close();

// Cek chat masuk dari pelanggan
$hasNewChat = false;
if ($IdPenyedia) {
    $sqlUnread = "SELECT COUNT(*) AS jumlah FROM chat 
                  WHERE IdPenyedia = ? AND Pengirim = 'pelanggan' AND Status = 'belum'";
    $stmtChat = $conn->prepare($sqlUnread);
    $stmtChat->bind_param("i", $IdPenyedia);
    $stmtChat->execute();
    $resChat = $stmtChat->get_result();
    $dataChat = $resChat->fetch_assoc();

    $hasNewChat = $dataChat['jumlah'] > 0;
    $stmtChat->close();
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
    <meta charset="UTF-8">
    <title>Booking Fasilitas - Vooking</title>
    <link rel="stylesheet" href="penyedia_home.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="notif_chat.css">
</head>
<body>
<?php include "sidebar_penyedia.php";?>

<div class="main">
    <div class="header">
        <div class="logo-container">
            <img src="Vicon.png" alt="Logo Vooking" class="logo-img" />
            <span class="logo-text">VOOKING</span>
        </div>
        <div class="search-section">
            <input type="text" id="search-input" class="search-input" placeholder="Cari..." />
            <i class="fa fa-search search-icon"></i>
            <select id="filter-provinsi" class="filter-provinsi" aria-label="Filter Provinsi">
                <option value="all">Semua Lokasi</option>
                <?php foreach ($provinsi_list as $lok): ?>
                    <option value="<?= htmlspecialchars(strtolower($lok)) ?>"><?= htmlspecialchars($lok) ?></option>
                <?php endforeach; ?>
            </select>
            <i class="fa-solid fa-filter filter-icon" aria-hidden="true"></i>
        </div>
        <div class="icons">
            <div class="chat-icon-wrapper">
                <a href="chat_list.php" title="Chat Masuk">
                    <i class="fa-solid fa-comments"></i>
                </a>
                <?php if ($hasNewChat): ?>
                    <span class="chat-badge"></span>
                <?php endif; ?>
            </div>
            <div class="notif-icon-wrapper">
                <a href="semua_notifikasi.php" style="position: relative; color: inherit; text-decoration: none;">
                    <i class="fa-solid fa-bell"></i>
                    <?php if ($hasNewNotif): ?>
                        <span class="notif-badge"></span>
                    <?php endif; ?>
                </a>
            </div>
            <a href="profil_penyedia.php"><i class="fa-solid fa-user" id="profile-icon"></i></a>
        </div>        
    </div>

<div class="nav-tabs">
    <button class="tab active" data-kategori="all">Semua</button>
    <button class="tab" data-kategori="penginapan">Penginapan</button>
    <button class="tab" data-kategori="olahraga">Olahraga</button>
    <button class="tab" data-kategori="resto">Resto & Kuliner</button>
    <button class="tab" data-kategori="ruang">Ruang & Acara</button>
</div>

<div class="content card-grid">
    <!-- Kolom Kartu Kiri -->
    <div class="card-column">
        <?php foreach ($fasilitas_data as $i => $item): ?>
            <?php if ($i % 2 === 0): // Kartu di kolom kiri ?>
            <div class="card"
                data-kategori="<?= strtolower($item['Kategori']) ?>"
                data-nama="<?= strtolower($item['NamaFasilitas']) ?>"
                data-provinsi="<?= strtolower($item['Provinsi']) ?>">
                <div class="card-horizontal">
                    <img src="<?= !empty($item['FotoFasilitas']) ? htmlspecialchars($item['FotoFasilitas']) : 'img/default.jpg' ?>"
                         class="card-img-horizontal"
                         alt="<?= htmlspecialchars($item['NamaFasilitas']) ?>">
                    <div class="card-body">
                        <h3><?= htmlspecialchars($item['NamaFasilitas']) ?></h3>
                        <p class="location"><?= htmlspecialchars($item['Lokasi']) ?></p>
                        <div class="stars">
                            <?php
                            $rating = floatval($item['AverageRating']);
                            $fullStars = floor($rating);
                            $halfStar = ($rating - $fullStars >= 0.5) ? 1 : 0;
                            $emptyStars = 5 - $fullStars - $halfStar;

                            for ($i = 0; $i < $fullStars; $i++) {
                                echo '<i class="fa-solid fa-star"></i>';
                            }
                            if ($halfStar) {
                                echo '<i class="fa-solid fa-star-half-stroke"></i>';
                            }
                            for ($i = 0; $i < $emptyStars; $i++) {
                                echo '<i class="fa-regular fa-star"></i>';
                            }
                            //Tambahan: tampilkan angka rating jika ingin
                            echo " (" . number_format($rating, 1) . ")";
                            ?>
                        </div>
                        <p class="desc"><?= htmlspecialchars($item['Deskripsi']) ?></p>
                        <div class="price-section">
                            <div>
                                <div class="price-text">Harga Mulai Dari</div>
                                <div class="price">
                                    Rp<?= number_format($item['HargaMin'], 0, ',', '.') ?>/<?= htmlspecialchars($item['TipeBooking']) ?>
                                </div>
                            </div>
                            <a href="lihat_fasilitas.php?id=<?= urlencode($item['IdFasilitas']) ?>" class="lihat-btn">Lihat</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <!-- Kolom Kartu Kanan -->
    <div class="card-column">
        <?php foreach ($fasilitas_data as $i => $item): ?>
            <?php if ($i % 2 === 1): // Kartu di kolom kanan ?>
            <div class="card"
                data-kategori="<?= strtolower($item['Kategori']) ?>"
                data-nama="<?= strtolower($item['NamaFasilitas']) ?>"
                data-provinsi="<?= strtolower($item['Provinsi']) ?>">
                <div class="card-horizontal">
                    <img src="<?= !empty($item['FotoFasilitas']) ? htmlspecialchars($item['FotoFasilitas']) : 'img/default.jpg' ?>"
                         class="card-img-horizontal"
                         alt="<?= htmlspecialchars($item['NamaFasilitas']) ?>">
                    <div class="card-body">
                        <h3><?= htmlspecialchars($item['NamaFasilitas']) ?></h3>
                        <p class="location"><?= htmlspecialchars($item['Lokasi']) ?></p>
                        <div class="stars">
                            <?php
                            $rating = floatval($item['AverageRating']);
                            $fullStars = floor($rating);
                            $halfStar = ($rating - $fullStars >= 0.5) ? 1 : 0;
                            $emptyStars = 5 - $fullStars - $halfStar;

                            for ($i = 0; $i < $fullStars; $i++) {
                                echo '<i class="fa-solid fa-star"></i>';
                            }
                            if ($halfStar) {
                                echo '<i class="fa-solid fa-star-half-stroke"></i>';
                            }
                            for ($i = 0; $i < $emptyStars; $i++) {
                                echo '<i class="fa-regular fa-star"></i>';
                            }
                            //Tambahan: tampilkan angka rating jika ingin
                            echo " (" . number_format($rating, 1) . ")";
                            ?>
                        </div>
                        <p class="desc"><?= htmlspecialchars($item['Deskripsi']) ?></p>
                        <div class="price-section">
                            <div>
                                <div class="price-text">Harga Mulai Dari</div>
                                <div class="price">
                                    Rp<?= number_format($item['HargaMin'], 0, ',', '.') ?>/<?= htmlspecialchars($item['TipeBooking']) ?>
                                </div>
                            </div>
                            <a href="lihat_fasilitas.php?id=<?= urlencode($item['IdFasilitas']) ?>" class="lihat-btn">Lihat</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <!-- Pesan jika tidak ada -->
    <div id="no-result-message" style="text-align:center; margin-top: 20px; font-weight: bold; display: none;">
        Fasilitas tidak ditemukan.
    </div>    
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const tabButtons = document.querySelectorAll('.tab');
    const allCards = document.querySelectorAll('.card');
    const searchInput = document.getElementById('search-input');
    const filterProvinsi = document.getElementById('filter-provinsi');
    const column0 = document.querySelector('.card-column:nth-child(1)');
    const column1 = document.querySelector('.card-column:nth-child(2)');
    const noResultMessage = document.getElementById('no-result-message');

    let currentKategori = 'all';
    let currentProvinsi = 'all';

    function filterCards() {
        const searchText = searchInput.value.trim().toLowerCase();
        const visibleCards = [];

        allCards.forEach(card => {
            const cardKategori = card.getAttribute('data-kategori');
            const cardNama = card.getAttribute('data-nama');
            const cardProvinsi = card.getAttribute('data-provinsi');

            const matchKategori = (currentKategori === 'all' || cardKategori === currentKategori);
            const matchSearch = cardNama.includes(searchText);
            const matchProvinsi = (currentProvinsi === 'all' || cardProvinsi === currentProvinsi);

            if (matchKategori && matchSearch && matchProvinsi) {
                visibleCards.push(card);
            } else {
                card.style.display = 'none'; // sembunyikan yang tidak cocok
            }
        });

        // Kosongkan kolom dan isi ulang
        column0.innerHTML = '';
        column1.innerHTML = '';

        visibleCards.forEach((card, index) => {
            card.style.display = 'block';
            if (index % 2 === 0) {
                column0.appendChild(card);
            } else {
                column1.appendChild(card);
            }
        });

        // Tampilkan/ sembunyikan pesan "tidak ditemukan"
        noResultMessage.style.display = (visibleCards.length === 0) ? 'block' : 'none';
    }

    // Kategori tab
    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            tabButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentKategori = btn.getAttribute('data-kategori');
            filterCards();
        });
    });

    // Event pencarian dan filter lokasi
    searchInput.addEventListener('input', filterCards);
    filterProvinsi.addEventListener('change', () => {
        currentProvinsi = filterProvinsi.value.toLowerCase();
        filterCards();
    });

    // Panggil saat awal untuk load default
    filterCards();
});

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
