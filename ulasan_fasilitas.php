<?php
session_start();
include 'koneksi.php';

if (!isset($_GET['id'])) {
    echo "ID fasilitas tidak ditemukan.";
    exit();
}

$idFasilitas = (int)$_GET['id'];

// Sorting
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'terbaru';
$order = ($sort == 'tertinggi') ? 'ul.Rating DESC' : 'ul.Tanggal DESC';

// Query ulasan berdasarkan fasilitas dan sorting
$queryUlasan = "
    SELECT u.Username, u.FotoProfil, ul.Rating, ul.Komentar, ul.Tanggal
    FROM ulasan ul
    JOIN pelanggan p ON ul.IdPelanggan = p.IdPelanggan
    JOIN user u ON p.IdUser = u.IdUser
    WHERE ul.IdFasilitas = ?
    ORDER BY $order
";

$stmt = $conn->prepare($queryUlasan);
$stmt->bind_param("i", $idFasilitas);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Semua Ulasan Fasilitas</title>
    <link rel="stylesheet" href="home.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="ulasan.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <div class="wrapper">
        <div class="vooking-header">
            <div class="logo-title">
                <a href="lihat_fasilitas.php?id=<?= $idFasilitas ?>"><i class="fa fa-arrow-left back-home-icon"></i></a>
                <img src="Vicon.png" class="logo-img" />
                <h1>Semua Ulasan</h1>
            </div>
        </div>

        <div class="sort-container">
            <form method="get">
                <input type="hidden" name="id" value="<?= $idFasilitas ?>">
                <label for="sort">Urutkan:</label>
                <select name="sort" onchange="this.form.submit()">
                    <option value="terbaru" <?= $sort == 'terbaru' ? 'selected' : '' ?>>Terbaru</option>
                    <option value="tertinggi" <?= $sort == 'tertinggi' ? 'selected' : '' ?>>Rating Tertinggi</option>
                </select>
            </form>
        </div>

        <div class="ulasan-section">
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <div class="ulasan-item">
                        <?php if (!empty($row['FotoProfil'])): ?>
                            <img src="<?= htmlspecialchars($row['FotoProfil']) ?>" alt="Foto Profil" class="ulasan-foto">
                        <?php else: ?>
                            <div class="ulasan-foto default-icon">
                                <i class="fa-solid fa-user"></i>
                            </div>
                        <?php endif; ?>
                        <div class="ulasan-content">
                            <strong><?= htmlspecialchars($row['Username']) ?></strong><br>
                            <div class="rating-stars">
                                <?php
                                $rating = (int)$row['Rating'];
                                for ($i = 1; $i <= 5; $i++) {
                                    echo '<i class="fa fa-star' . ($i <= $rating ? '' : '-o') . '"></i>';
                                }
                                ?>
                                <small><?= number_format($row['Rating'], 1) ?> / 5</small>
                            </div>
                            <small><?= date("d M Y H:i", strtotime($row['Tanggal'])) ?></small>
                            <p><?= nl2br(htmlspecialchars($row['Komentar'])) ?></p>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="padding: 0 24px;">Belum ada ulasan untuk fasilitas ini.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
