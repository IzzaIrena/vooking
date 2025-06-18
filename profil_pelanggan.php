<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['IdUser'])) {
    header("Location: login_register.php");
    exit();
}

$IdUser = $_SESSION['IdUser'];

// Proses update profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $nohp = mysqli_real_escape_string($conn, $_POST['nohp']);

    if (!empty($_FILES['profile_photo']['name'])) {
        $dir = "uploads/";
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $filename = time() . "_" . basename($_FILES["profile_photo"]["name"]);
        $target_file = $dir . $filename;
        move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $target_file);
        mysqli_query($conn, "UPDATE user SET Username = '$username', NoHp = '$nohp', FotoProfil = '$target_file' WHERE IdUser = $IdUser");
    } else {
        mysqli_query($conn, "UPDATE user SET Username = '$username', NoHp = '$nohp' WHERE IdUser = $IdUser");
    }
}

$now = date('Y-m-d H:i:s');
mysqli_query($conn, "UPDATE user SET LastActive = '$now' WHERE IdUser = $IdUser");

$result = mysqli_query($conn, "SELECT * FROM user WHERE IdUser = $IdUser");
$user = mysqli_fetch_assoc($result);
// Ambil riwayat booking berdasarkan IdPelanggan (harus sama dengan IdUser dari session)
// Dapatkan IdPelanggan berdasarkan IdUser
$sqlGetPelanggan = "SELECT IdPelanggan FROM pelanggan WHERE IdUser = ?";
$stmtGet = $conn->prepare($sqlGetPelanggan);
$stmtGet->bind_param("i", $IdUser);
$stmtGet->execute();
$resGet = $stmtGet->get_result();
$dataPelanggan = $resGet->fetch_assoc();
$IdPelanggan = $dataPelanggan['IdPelanggan'] ?? 0;

$riwayatQuery = mysqli_query($conn, "
    SELECT b.Nama, b.*, u.NamaTipeUnit, f.NamaFasilitas 
    FROM booking b 
    JOIN unit u ON b.IdUnit = u.IdUnit 
    JOIN fasilitas f ON u.IdFasilitas = f.IdFasilitas
    WHERE b.IdPelanggan = $IdPelanggan
    ORDER BY b.IdBooking DESC
    LIMIT 3
");

function statusAktif($lastActive) {
    $last = strtotime($lastActive);
    $now = time();
    $diff = $now - $last;
    if ($diff < 300) return '<span class="status-online">Online</span>';
    elseif ($diff < 3600) return '<span class="status-recent">Aktif ' . floor($diff / 60) . ' menit lalu</span>';
    elseif ($diff < 86400) return '<span class="status-recent">Aktif hari ini</span>';
    else return '<span class="status-offline">Terakhir aktif: ' . date("d M Y H:i", $last) . '</span>';
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
    <title>Profil Saya</title>
    <link rel="stylesheet" href="home.css?v=<?= time(); ?>" />
    <link rel="stylesheet" href="notif_chat.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body>
<div class="wrapper">
    <div class="vooking-header">
        <div class="logo-title">
            <a href="home.php"><i class="fa-solid fa-arrow-left back-home-icon"></i></a>
            <img src="Vicon.png" class="logo-img" />
            <h1>VOOKING</h1>
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
                    <span class="notif-badge" style="top:-5px; right:-5px;"></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="profile-container">
        <div class="profile-sidebar">
            <?php if (!empty($user['FotoProfil'])): ?>
                <img src="<?= htmlspecialchars($user['FotoProfil']) ?>" class="profile-pic" alt="Foto Profil" />
            <?php else: ?>
                <div class="profile-icon-default"><i class="fa-solid fa-user"></i></div>
            <?php endif; ?>
            <div class="profile-menu">
                <button class="menu-btn" id="edit-profile-btn">Edit Profil</button>
                <button class="menu-btn" type="button" id="show-provider-form">Jadi Penyedia</button>
                <form action="logout.php" method="POST" onsubmit="return konfirmasiLogoutForm(event)">
                    <button class="menu-btn logout">Keluar</button>
                </form>
            </div>
        </div>

        <div class="profile-content">
            <div class="profile-header">
                <h2>Profil Pelanggan</h2>
                <div id="edit-profile-form" class="edit-profile-container" style="display: none;">
                    <form method="POST" enctype="multipart/form-data">
                        <label for="username">Nama Pengguna</label>
                        <input type="text" name="username" id="username" value="<?= htmlspecialchars($user['Username']) ?>" required>

                        <label for="nohp">Nomor HP</label>
                        <input type="text" name="nohp" id="nohp" value="<?= htmlspecialchars($user['NoHp'] ?? '') ?>">

                        <label for="profile_photo">Foto Profil</label>
                        <input type="file" name="profile_photo" id="profile_photo" accept="image/*">

                        <div class="form-buttons">
                        <button type="submit" name="update_profile" class="form-btn simpan">
                            <i class="fas fa-save" style="margin-right: 8px;"></i> Simpan
                        </button>
                        </div>
                        <div class="form-buttons">
                        <button type="button" id="cancel-edit-btn" class="form-btn batal">
                            <i class="fas fa-times-circle" style="margin-right: 8px;"></i> Batal
                        </button>
                        </div>
                    </form>
                </div>
                <div id="provider-form" class="provider-form-container" style="display: none; margin-top: 20px;">
                    <form action="aktifkan_penyedia.php" method="POST" enctype="multipart/form-data">
                        <h3>Aktivasi Sebagai Penyedia</h3>
                        <p>Untuk menjadi penyedia fasilitas, silakan lakukan pembayaran biaya aktivasi sebesar <strong>Rp200.000</strong>, ke nomor rekening BRI: <strong>6019-0221-6811-2222</strong> dan unggah bukti transfer.</p>

                        <label for="bukti">Upload Bukti Pembayaran:</label>
                        <input type="file" name="bukti" id="bukti" accept="image/*" required>

                        <div class="form-buttons">
                        <button type="submit" class="form-btn simpan">
                            <i class="fas fa-paper-plane" style="margin-right: 8px;"></i> Kirim Permintaan
                        </button>
                        </div>
                        <div class="form-buttons">
                        <button type="button" class="form-btn batal" id="cancel-provider-form">
                            <i class="fas fa-times-circle" style="margin-right: 8px;"></i> Batal
                        </button>
                        </div>

                    </form>
                </div>
            </div>
            <h3><?= htmlspecialchars($user['Username']) ?></h3>
            <p><?= statusAktif($user['LastActive']) ?></p>
            <p>Bergabung sejak: <?= date("d M Y", strtotime($user['BergabungSejak'])) ?></p>
            <p>Nomor HP: <?= htmlspecialchars($user['NoHp'] ?? '-') ?></p>
            <hr />
            <div class="info-box">
                <h3>Riwayat Booking</h3>
                <?php if (mysqli_num_rows($riwayatQuery) > 0): ?>
                    <table class="riwayat-table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Fasilitas</th>
                                <th>Unit - Ruang</th>
                                <th>Jam</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($riwayatQuery)): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['Tanggal']) ?></td>
                                    <td><?= htmlspecialchars($row['NamaFasilitas']) ?></td>
                                    <td><?= htmlspecialchars($row['NamaTipeUnit']) ?> - <?= htmlspecialchars($row['NomorRuang']) ?></td>
                                    <td><?= htmlspecialchars($row['Jam'] ?? '') ?></td>
                                    <td><?= ucfirst($row['StatusBooking']) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <div style="text-align: right; margin-top: 12px;">
                        <a href="riwayat_lengkap.php" class="form-btn">Lihat Selengkapnya</a>
                    </div>
                <?php else: ?>
                    <p>Tidak ada riwayat booking ditemukan.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById("edit-profile-btn").addEventListener("click", function () {
    const form = document.getElementById("edit-profile-form");
    form.style.display = form.style.display === "none" ? "block" : "none";
});
document.getElementById("cancel-edit-btn").addEventListener("click", function () {
    document.getElementById("edit-profile-form").style.display = "none";
});

document.getElementById("show-provider-form").addEventListener("click", function () {
    const form = document.getElementById("provider-form");
    form.style.display = form.style.display === "none" ? "block" : "none";
});

document.getElementById("cancel-provider-form").addEventListener("click", function () {
    document.getElementById("provider-form").style.display = "none";
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

function konfirmasiLogoutForm(e) {
  if (!confirm("Apakah Anda yakin ingin logout?")) {
    e.preventDefault(); // batalkan pengiriman form
    return false;
  }
  return true; // lanjutkan submit form
}
</script>
</body>
</html>
