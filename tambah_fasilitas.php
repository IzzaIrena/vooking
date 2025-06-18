<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['IdUser']) || $_SESSION['RoleAktif'] !== 'penyedia') {
    header("Location: login_register.php");
    exit();
}
$IdUser = $_SESSION['IdUser'];
$IdPenyedia = $_SESSION['IdUser'];

// Daftar provinsi
$provinsiList = [ "Aceh", "Sumatera Utara", "Sumatera Barat", "Riau", "Jambi", "Sumatera Selatan",
    "Bengkulu", "Lampung", "Kepulauan Bangka Belitung", "Kepulauan Riau", "DKI Jakarta",
    "Jawa Barat", "Jawa Tengah", "DI Yogyakarta", "Jawa Timur", "Banten", "Bali", 
    "Nusa Tenggara Barat", "Nusa Tenggara Timur", "Kalimantan Barat", "Kalimantan Tengah", 
    "Kalimantan Selatan", "Kalimantan Timur", "Kalimantan Utara", "Sulawesi Utara", 
    "Sulawesi Tengah", "Sulawesi Selatan", "Sulawesi Tenggara", "Gorontalo", 
    "Sulawesi Barat", "Maluku", "Maluku Utara", "Papua Barat", "Papua"
];
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
    <meta charset="UTF-8">
    <title>Tambah Fasilitas - Vooking</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="penyedia_home.css">
    <link rel="stylesheet" href="notif_chat.css">
</head>
<body>
<?php include "sidebar_penyedia.php"; ?>

<div class="main">
    <div class="header-fixed">
        <?php include "header_penyedia.php";?> 
    </div>

    <div class="content-scrollable">
        <div id="form-fasilitas">
            <form method="POST" action="simpan_fasilitas.php" enctype="multipart/form-data">
                <h3>Tambah Fasilitas</h3>
                <label>Nama Fasilitas:</label>
                <input type="text" name="NamaFasilitas" required>

                <label>Foto Fasilitas:</label>
                <input type="file" name="FotoFasilitas" accept="image/*">

                <label>Kategori:</label>
                <select name="Kategori" required>
                    <option value="penginapan">Penginapan</option>
                    <option value="olahraga">Olahraga</option>
                    <option value="resto">Resto & Kuliner</option>
                    <option value="ruang">Ruang & Acara</option>
                </select>

                <label>Lokasi (Kota/Kabupaten):</label>
                <input type="text" name="Lokasi" required>

                <label>Provinsi:</label>
                <select name="Provinsi" required>
                    <option value="" disabled selected>Pilih Provinsi</option>
                    <?php foreach ($provinsiList as $prov): ?>
                        <option value="<?= htmlspecialchars($prov) ?>"><?= htmlspecialchars($prov) ?></option>
                    <?php endforeach; ?>
                </select>

                <label>Tipe Booking:</label>
                <select name="TipeBooking" required>
                    <option value="jam">Jam</option>
                    <option value="harian">Harian</option>
                </select>

                <label>Jam Buka:</label>
                <input type="time" name="JamBuka" required>

                <label>Jam Tutup:</label>
                <input type="time" name="JamTutup" required>

                <label>No Rekening:</label>
                <input type="text" name="NoRekening" required>

                <label>Deskripsi:</label>
                <textarea name="Deskripsi"></textarea>

                <label>Jumlah Unit:</label>
                <input type="number" name="JumlahUnit" id="JumlahUnit" min="1" required>

                <div id="unit-table-container" style="margin-top: 10px;"></div>

                <button type="submit">Simpan</button>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById("JumlahUnit").addEventListener("input", function () {
    let jumlah = parseInt(this.value);
    let container = document.getElementById("unit-table-container");
    container.innerHTML = "";

    if (jumlah > 0) {
        let table = "<table border='1' cellpadding='6'><tr><th>Nama Tipe Unit</th><th>Jumlah Ruang</th><th>Harga</th><th>DP</th></tr>";
        for (let i = 0; i < jumlah; i++) {
            table += "<tr>" +
                "<td><input type='text' name='unit_nama[]' required></td>" +
                "<td><input type='number' name='unit_jumlah[]' required></td>" +
                "<td><input type='number' name='unit_harga[]' required></td>" +
                "<td><input type='number' name='unit_dp[]' required></td>" +
                "</tr>";
        }
        table += "</table>";
        container.innerHTML = table;
    }
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
