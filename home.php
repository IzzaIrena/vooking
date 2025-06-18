<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['IdUser'])) {
    header("Location: login_register.php");
    exit();
}

$IdUser = $_SESSION['IdUser'];

$fasilitas_result = mysqli_query($conn, "
    SELECT f.*, 
           COALESCE(AVG(u.Rating), 0) AS AverageRating,
           COUNT(u.IdUlasan) AS ReviewCount,
           (SELECT MIN(Harga) FROM unit WHERE IdFasilitas = f.IdFasilitas) AS HargaMin
    FROM fasilitas f
    LEFT JOIN ulasan u ON u.IdFasilitas = f.IdFasilitas
    WHERE f.Status = 'aktif'
    GROUP BY f.IdFasilitas
");

$fasilitas_data = [];
while ($row = mysqli_fetch_assoc($fasilitas_result)) {
    // Pakai langsung HargaMin dari hasil query utama
    // Pastikan HargaMin bernilai 0 jika NULL
    $row['HargaMin'] = $row['HargaMin'] !== null ? $row['HargaMin'] : 0;

    // AverageRating biasanya decimal, bisa dibulatkan jika perlu
    $row['AverageRating'] = round($row['AverageRating'], 1);

    $fasilitas_data[] = $row;
}

$provinsi_result = mysqli_query($conn, "SELECT DISTINCT Provinsi FROM fasilitas WHERE Status = 'aktif'");
$provinsi_list = [];
while ($rowProvinsi = mysqli_fetch_assoc($provinsi_result)) {
    $provinsi_list[] = $rowProvinsi['Provinsi'];
}

$hasNewChat = false;

if (isset($_SESSION['IdUser'])) {
    // Dapatkan IdPelanggan dari IdUser session
    $sqlGetPelanggan = "SELECT IdPelanggan FROM pelanggan WHERE IdUser = ?";
    $stmtGet = $conn->prepare($sqlGetPelanggan);
    $stmtGet->bind_param("i", $IdUser);
    $stmtGet->execute();
    $resGet = $stmtGet->get_result();
    $dataPelanggan = $resGet->fetch_assoc();
    $IdPelanggan = $dataPelanggan['IdPelanggan'] ?? 0;

    $sqlUnread = "SELECT COUNT(*) AS jumlah FROM chat 
                WHERE IdPelanggan = ? AND Pengirim = 'penyedia' AND Status = 'belum'";
    $stmt = $conn->prepare($sqlUnread);
    $stmt->bind_param("i", $IdPelanggan);
    $stmt->execute();
    $res = $stmt->get_result();
    $data = $res->fetch_assoc();

    $hasNewChat = $data['jumlah'] > 0;
}

// Cek apakah ada notifikasi belum dibaca
$hasNewNotif = false;
$notifPesan = [];

$sqlNotif = "SELECT IsiPesan, Tipe, Tanggal FROM notifikasi WHERE IdUser = ? AND StatusBaca = 0 ORDER BY Tanggal DESC";
$stmtNotif = $conn->prepare($sqlNotif);
$stmtNotif->bind_param("i", $IdUser);
$stmtNotif->execute();
$resNotif = $stmtNotif->get_result();
$hasNewNotif = $resNotif->num_rows > 0;

while ($row = $resNotif->fetch_assoc()) {
    $notifPesan[] = $row;
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <title>Beranda - Vooking</title>
    <link rel="stylesheet" href="home.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="notif_chat.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        
    </style>
</head>
<body>
<div class="wrapper">
    <div class="vooking-header">
        <div class="logo-title">
            <img src="Vicon.png" class="logo-img" />
            <h1>VOOKING</h1>
        </div>
        <div class="search-section">
            <input type="text" id="search-input" class="search-input" placeholder="Cari..." style="border-radius: 30px; border: 1px solid #ccc; padding-left: 36px;">
            <i class="fa fa-search search-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#888;"></i>

            <select id="filter-provinsi" class="filter-provinsi" aria-label="Filter Provinsi">
                <option value="all">Semua Lokasi</option>
                <?php foreach ($provinsi_list as $lok): ?>
                    <option value="<?= htmlspecialchars(strtolower($lok)) ?>"><?= htmlspecialchars($lok) ?></option>
                <?php endforeach; ?>
            </select>
            <i class="fa-solid fa-filter filter-icon" aria-hidden="true"></i>
        </div>
        <div class="icon-section">
            <div class="chat-icon-wrapper">
                <a href="semua_chat.php" title="Chat Penyedia">
                    <i class="fa-solid fa-comments"></i>
                </a>
                <?php if ($hasNewChat): ?>
                    <span class="chat-badge" id="notif-badge"></span>
                <?php endif; ?>
            </div>
            <div class="notif-icon-wrapper">
                <a href="semua_notifikasi.php" title="Lihat Notifikasi">
                    <i class="fa fa-bell" style="cursor:pointer;"></i>
                </a>
                <?php if ($hasNewNotif): ?>
                    <span class="chat-badge" style="top:-5px; right:-5px;"></span>
                <?php endif; ?>
            </div>
            <a href="profil_pelanggan.php"><i class="fa fa-user-circle"></i></a>
        </div>
    </div>

    <div class="nav-tabs">
        <button class="tab active" data-kategori="all">Semua</button>
        <button class="tab" data-kategori="penginapan">Penginapan</button>
        <button class="tab" data-kategori="olahraga">Olahraga</button>
        <button class="tab" data-kategori="resto">Resto & Kuliner</button>
        <button class="tab" data-kategori="ruang">Ruang & Acara</button>
    </div>

    <div class="main-content card-grid" id="card-container">
        <?php foreach ($fasilitas_data as $item): ?>
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
                        <p class="location"><?= htmlspecialchars($item['Provinsi']) ?></p>
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
                                        <?php
                                        $tipe = strtolower($item['TipeBooking']);
                                        $labelTipe = ($tipe === 'harian') ? 'Hari' : (($tipe === 'jam') ? 'Jam' : ucfirst($tipe));
                                        ?>
                                        Rp<?= number_format($item['HargaMin'], 0, ',', '.') ?>/<?= $labelTipe ?>
                                    </div>
                            </div>
                            <a href="lihat_fasilitas.php?id=<?= $item['IdFasilitas'] ?>" class="lihat-btn">Lihat</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Pesan jika tidak ada hasil -->
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
    const noResultMessage = document.getElementById('no-result-message');

    let currentKategori = 'all';
    let currentProvinsi = 'all';

    function filterCards() {
        const searchText = searchInput.value.trim().toLowerCase();
        let visibleCount = 0;

        allCards.forEach(card => {
            const cardKategori = card.getAttribute('data-kategori');
            const cardNama = card.getAttribute('data-nama');
            const cardProvinsi = card.getAttribute('data-provinsi');

            const matchKategori = (currentKategori === 'all' || cardKategori === currentKategori);
            const matchSearch = (cardNama.includes(searchText));
            const matchProvinsi = (currentProvinsi === 'all' || cardProvinsi === currentProvinsi);

            if (matchKategori && matchSearch && matchProvinsi) {
                card.style.display = 'block';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        // Tampilkan pesan jika tidak ada hasil
        noResultMessage.style.display = (visibleCount === 0) ? 'block' : 'none';
    }

    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            tabButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            currentKategori = btn.getAttribute('data-kategori');
            filterCards();
        });
    });

    searchInput.addEventListener('input', () => {
        filterCards();
    });

    filterProvinsi.addEventListener('change', () => {
        currentProvinsi = filterProvinsi.value.toLowerCase();
        filterCards();
    });
});

function cekChatBaru() {
    fetch('cek_chat_baru_pelanggan.php')
        .then(res => res.json())
        .then(data => {
            const badge = document.getElementById('notif-badge');
            if (data.success && data.jumlah > 0) {
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        });
}

// Cek langsung dan ulangi tiap 10 detik
cekChatBaru();
setInterval(cekChatBaru, 10000);

function toggleNotifDropdown() {
    const dropdown = document.getElementById('notif-dropdown');
    dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
}
</script>
</body>
</html>
