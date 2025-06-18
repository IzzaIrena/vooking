<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['IdUser']) || $_SESSION['RoleAktif'] !== 'penyedia') {
    header("Location: login_register.php");
    exit();
}

$IdUser = $_SESSION['IdUser'];

// Proses update profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $nohp = mysqli_real_escape_string($conn, $_POST['nohp']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);

    if (!empty($_FILES['profile_photo']['name'])) {
        $dir = "uploads/profil_penyedia/";
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $filename = time() . "_" . basename($_FILES["profile_photo"]["name"]);
        $target_file = $dir . $filename;
        move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $target_file);
        mysqli_query($conn, "UPDATE user SET Username = '$username', NoHp = '$nohp', Deskripsi = '$deskripsi', FotoProfil = '$target_file' WHERE IdUser = $IdUser");
    } else {
        mysqli_query($conn, "UPDATE user SET Username = '$username', NoHp = '$nohp', Deskripsi = '$deskripsi' WHERE IdUser = $IdUser");
    }
}

// Proses unggah bukti pembayaran langganan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_langganan'])) {
    // Ambil IdPenyedia berdasarkan IdUser
    $getPenyedia = mysqli_query($conn, "SELECT IdPenyedia FROM penyediafasilitas WHERE IdUser = $IdUser");
    $rowPenyedia = mysqli_fetch_assoc($getPenyedia);
    $IdPenyedia = $rowPenyedia['IdPenyedia'] ?? null;

    if ($IdPenyedia && !empty($_FILES['bukti']['name'])) {
        $dir = "uploads/bukti_langganan/";
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $filename = time() . "_" . basename($_FILES["bukti"]["name"]);
        $target_file = $dir . $filename;
        move_uploaded_file($_FILES["bukti"]["tmp_name"], $target_file);

        $tanggalMulai = date("Y-m-d");
        $tanggalAkhir = date("Y-m-d", strtotime("+1 year"));

        mysqli_query($conn, "INSERT INTO langganan_penyedia (IdPenyedia, PeriodeMulai, PeriodeAkhir, StatusPembayaran, BuktiPembayaran)
            VALUES ($IdPenyedia, '$tanggalMulai', '$tanggalAkhir', 'menunggu', '$target_file')");
    }
}

$now = date('Y-m-d H:i:s');
mysqli_query($conn, "UPDATE user SET LastActive = '$now' WHERE IdUser = $IdUser");

$result = mysqli_query($conn, "SELECT * FROM user WHERE IdUser = $IdUser");
$user = mysqli_fetch_assoc($result);

function statusAktif($lastActive) {
    $last = strtotime($lastActive);
    $now = time();
    $selisih = $now - $last;

    if ($selisih < 300) return '<span class="status-online">Online</span>';
    elseif ($selisih < 3600) return '<span class="status-recent">Aktif ' . floor($selisih / 60) . ' menit lalu</span>';
    elseif ($selisih < 86400) return '<span class="status-recent">Aktif hari ini</span>';
    else return '<span class="status-offline">Terakhir aktif: ' . date("d M Y H:i", $last) . '</span>';
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

// Pastikan variabel $IdPenyedia sudah didefinisikan
$getPenyedia = mysqli_query($conn, "SELECT IdPenyedia FROM penyediafasilitas WHERE IdUser = $IdUser");
$rowPenyedia = mysqli_fetch_assoc($getPenyedia);
$IdPenyedia = $rowPenyedia['IdPenyedia'] ?? null;

if ($IdPenyedia) {
    $cekLangganan = mysqli_query($conn, "SELECT PeriodeAkhir FROM langganan_penyedia WHERE IdPenyedia = $IdPenyedia ORDER BY PeriodeAkhir DESC LIMIT 1");
    $dataLangganan = mysqli_fetch_assoc($cekLangganan);
    $periodeAkhir = $dataLangganan['PeriodeAkhir'] ?? null;
}

// Cek PeriodeAkhir terakhir
$cekPeriode = mysqli_query($conn, "SELECT PeriodeAkhir FROM langganan_penyedia 
    WHERE IdPenyedia = $IdPenyedia 
    ORDER BY PeriodeAkhir DESC 
    LIMIT 1");
$rowPeriode = mysqli_fetch_assoc($cekPeriode);
$periodeAkhirTerakhir = $rowPeriode['PeriodeAkhir'] ?? null;

$statusPembayaran = null;
if ($IdPenyedia) {
    $cekStatus = mysqli_query($conn, "SELECT StatusPembayaran 
        FROM langganan_penyedia 
        WHERE IdPenyedia = $IdPenyedia 
        ORDER BY PeriodeAkhir DESC 
        LIMIT 1");
    $rowStatus = mysqli_fetch_assoc($cekStatus);
    $statusPembayaran = $rowStatus['StatusPembayaran'] ?? null;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Profil Penyedia - Vooking</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="penyedia_home.css">
    <link rel="stylesheet" href="notif_chat.css">
    <style>
        #popup-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.4);
            backdrop-filter: blur(2px);
            z-index: 999;
        }

        /* Tampilan pop-up */
        #popup-periode {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: linear-gradient(145deg, #ffffff, #f0f0f0);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
            z-index: 1000;
            max-width: 400px;
            text-align: center;
            animation: fadeIn 0.3s ease;
        }

        #popup-periode p {
            font-size: 16px;
            margin-bottom: 20px;
            color: #333;
        }

        #popup-periode button {
            background-color: #007BFF;
            color: white;
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        #popup-periode button:hover {
            background-color: #0056b3;
        }

        /* Animasi muncul */
        @keyframes fadeIn {
            from {opacity: 0; transform: translate(-50%, -60%);}
            to {opacity: 1; transform: translate(-50%, -50%);}
        }
    </style>
</head>
<body>
<div class="sidebar" style="display: none;"></div>
<div class="main">
    <div class="header">
        <div class="logo-container">
            <a href="javascript:history.back()" class="back-btn"><i class="fa-solid fa-arrow-left back-home-icon"></i></a>
            <img src="Vicon.png" alt="Logo Vooking" class="logo-img" />
            <span class="logo-text">VOOKING</span>
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
        </div>  
    </div>

    <div class="profile-container" id="profile-view" style="display: flex;">
        <div class="profile-sidebar">
            <?php if (!empty($user['FotoProfil'])): ?>
                <img src="<?= htmlspecialchars($user['FotoProfil']) ?>" class="profile-pic" alt="Foto Profil">
            <?php else: ?>
                <div class="profile-icon-default"><i class="fa-solid fa-user"></i></div>
            <?php endif; ?>
            <div class="profile-menu">
                <button class="menu-btn">Edit Profil</button>
                <button class="menu-btn" id="btn-langganan">Berlangganan</button>
                <!-- <button class="menu-btn" id="btn-riwayat">Riwayat</button> -->
                <button class="menu-btn logout" onclick="konfirmasiLogout(event)">Keluar</button>
            </div>
        </div>
        <div class="profile-content">
            <div class="profile-header">
                <h2>Profil Penyedia</h2>
                <div id="edit-profile-form" class="edit-profile-container" style="display: none;">
                    <form method="POST" enctype="multipart/form-data">
                        <label for="username">Nama Pengguna</label>
                        <input type="text" name="username" id="username" value="<?= htmlspecialchars($user['Username']) ?>" required>

                        <label for="nohp">Nomor HP</label>
                        <input type="text" name="nohp" id="nohp" value="<?= htmlspecialchars($user['NoHp'] ?? '') ?>">

                        <label for="deskripsi">Deskripsi</label>
                        <textarea name="deskripsi" id="deskripsi" rows="4"><?= htmlspecialchars($user['Deskripsi'] ?? '') ?></textarea>

                        <label for="profile_photo">Foto Profil</label>
                        <input type="file" name="profile_photo" id="profile_photo" accept="image/*">

                        <div class="form-buttons">
                            <button type="submit" name="update_profile" class="form-btn simpan">Simpan</button>
                            <button type="button" id="cancel-edit-btn" class="form-btn batal">Batal</button>
                        </div>
                    </form>
                </div>
                <div id="langganan-form" class="langganan-container" style="display: none;">
                    <h3>Perpanjang Langganan</h3>
                    <p>Upload bukti pembayaran untuk memperpanjang langganan selama <strong>1 tahun</strong>. Silakan lakukan pembayaran biaya aktivasi sebesar <strong>Rp100.000</strong>, ke nomor rekening BRI: <strong>6019-0221-6811-2222</strong> dan unggah bukti transfer.</p>
                    <form method="POST" enctype="multipart/form-data">
                        <label for="bukti">Bukti Pembayaran (jpg/png/jpeg):</label>
                        <input type="file" name="bukti" id="bukti" accept="image/*" required>

                        <div class="form-buttons">
                            <button type="submit" name="submit_langganan" class="form-btn kirim">Kirim</button>
                            <button type="button" onclick="document.getElementById('langganan-form').style.display='none';" class="form-btn batal">Batal</button>
                        </div>
                    </form>
                </div>
            </div>
            <h3><?= htmlspecialchars($user['Username']) ?></h3>
            <p><?= statusAktif($user['LastActive']) ?></p>
            <p>Bergabung sejak <?= date('d M Y', strtotime($user['BergabungSejak'])) ?></p>
            <p>Nomor HP: <?= htmlspecialchars($user['NoHp'] ?? '-') ?></p>
            <hr>
            <h4 id="judul-deskripsi">Deskripsi</h4>
            <div class="deskripsi">
                <?= nl2br(htmlspecialchars($user['Deskripsi'] ?? 'Belum ada deskripsi')) ?>
            </div>
            <!-- <div id="riwayat-booking" style="display:none; margin-top:20px;">
                <h4>Riwayat Booking</h4>
                <table border="1" cellpadding="8" cellspacing="0" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Fasilitas</th>
                            <th>Tanggal</th>
                            <th>Jam</th>
                            <th>Nomor Ruang</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        potongan riwayat
                    </tbody>
                </table>
            </div> -->
        </div>
    </div>
    <div id="popup-overlay" style="display: none;"></div>

    <!-- Pop-up Box -->
    <div id="popup-periode" style="display: none;">
        <p id="popup-message"></p>
        <button onclick="tutupPopup()">Tutup</button>
    </div>
</div>
<script>
document.querySelector('.menu-btn').addEventListener('click', function () {
    const form = document.getElementById("edit-profile-form");
    form.style.display = form.style.display === "none" ? "block" : "none";
});
document.getElementById("cancel-edit-btn").addEventListener("click", function () {
    document.getElementById("edit-profile-form").style.display = "none";
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

document.getElementById('btn-langganan').addEventListener('click', function () { 
    <?php
    $hariIni = date('Y-m-d');
    if (!empty($periodeAkhirTerakhir) && $hariIni < $periodeAkhirTerakhir && $statusPembayaran === 'diterima') {
        // Langganan masih aktif dan sudah diverifikasi admin
        $popupMsg = "Periode langganan Anda belum berakhir sampai $periodeAkhirTerakhir.";
        echo 'document.getElementById("popup-message").textContent = "' . $popupMsg . '";
              document.getElementById("popup-periode").style.display = "block";';
    } elseif ($statusPembayaran === 'menunggu') {
        // Sudah ajukan, tapi belum diverifikasi
        $popupMsg = "Permintaan Anda sedang menunggu verifikasi admin.";
        echo 'document.getElementById("popup-message").textContent = "' . $popupMsg . '";
              document.getElementById("popup-periode").style.display = "block";';
    } else {
        // Belum langganan / sudah habis dan belum ajukan baru
        echo 'document.getElementById("langganan-form").style.display = "block";';
    }
    ?>
});


function tampilkanPopup(pesan) {
    document.getElementById('popup-message').textContent = pesan;
    document.getElementById('popup-overlay').style.display = 'block';
    document.getElementById('popup-periode').style.display = 'block';
}

function tutupPopup() {
    document.getElementById('popup-overlay').style.display = 'none';
    document.getElementById('popup-periode').style.display = 'none';
}

// document.getElementById('btn-riwayat').addEventListener('click', function () {
//     const riwayat = document.getElementById("riwayat-booking");
//     const deskripsi = document.querySelector(".deskripsi");
//     const editForm = document.getElementById("edit-profile-form");
//     const langgananForm = document.getElementById("langganan-form");

//     const judulDeskripsi = document.getElementById("judul-deskripsi");

//     if (riwayat.style.display === "none" || riwayat.style.display === "") {
//         riwayat.style.display = "block";
//         deskripsi.style.display = "none";
//         judulDeskripsi.style.display = "none";
//     } else {
//         riwayat.style.display = "none";
//         deskripsi.style.display = "block";
//         judulDeskripsi.style.display = "block";
//     }

// });
function konfirmasiLogout(e) {
  e.preventDefault(); // cegah langsung logout

  if (confirm("Apakah Anda yakin ingin logout?")) {
    window.location.href = "logout.php";
  }
}
</script>
</body>
</html>
