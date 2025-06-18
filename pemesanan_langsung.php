<?php
session_start();
include 'koneksi.php';
date_default_timezone_set('Asia/Makassar'); // sesuaikan dengan timezone lokal kamu

// Tangani request AJAX untuk ambil nomor ruang
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_nomor_ruang']) && isset($_POST['id_unit'])) {
    $idUnit = intval($_POST['id_unit']);
    $stmt = $conn->prepare("SELECT JumlahRuang FROM unit WHERE IdUnit = ?");
    $stmt->bind_param("i", $idUnit);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];

    if ($row = $result->fetch_assoc()) {
        $jumlah = intval($row['JumlahRuang']);
        for ($i = 1; $i <= $jumlah; $i++) {
            $data[] = $i;
        }
    }

    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Cek session dan role aktif
if (!isset($_SESSION['IdUser']) || $_SESSION['RoleAktif'] !== 'penyedia') {
    header("Location: login_register.php");
    exit();
}

$IdUser = $_SESSION['IdUser'];

// Ambil IdPenyedia dari tabel penyediafasilitas
$sqlPenyedia = "SELECT IdPenyedia FROM penyediafasilitas WHERE IdUser = ?";
$stmtPenyedia = $conn->prepare($sqlPenyedia);
$stmtPenyedia->bind_param("i", $IdUser);
$stmtPenyedia->execute();
$resultPenyedia = $stmtPenyedia->get_result();

if ($resultPenyedia->num_rows > 0) {
    $rowPenyedia = $resultPenyedia->fetch_assoc();
    $IdPenyedia = $rowPenyedia['IdPenyedia'];

    // Ambil semua fasilitas penyedia
    $sqlFasilitas = "SELECT * FROM fasilitas WHERE IdPenyedia = ?";
    $stmtFasilitas = $conn->prepare($sqlFasilitas);
    $stmtFasilitas->bind_param("i", $IdPenyedia);
    $stmtFasilitas->execute();
    $resultFasilitas = $stmtFasilitas->get_result();
} else {
    $resultFasilitas = false;
}

$selectedFasilitas = null;
$unitList = [];

// Jika ada parameter GET id, ambil data fasilitas dan unit-nya
if (isset($_GET['id'])) {
    $idFas = intval($_GET['id']);
    $stmtF = $conn->prepare("SELECT * FROM fasilitas WHERE IdFasilitas = ? AND IdPenyedia = ?");
    $stmtF->bind_param("ii", $idFas, $IdPenyedia);
    $stmtF->execute();
    $resultF = $stmtF->get_result();

    if ($resultF->num_rows > 0) {
        $selectedFasilitas = $resultF->fetch_assoc();

        // Ambil unit terkait fasilitas
        $stmtUnit = $conn->prepare("SELECT * FROM unit WHERE IdFasilitas = ?");
        $stmtUnit->bind_param("i", $idFas);
        $stmtUnit->execute();
        $resultUnit = $stmtUnit->get_result();
        while ($rowUnit = $resultUnit->fetch_assoc()) {
            $unitList[] = $rowUnit;
        }
    }
}

// Fungsi untuk generate pilihan jam booking (per jam)
function generateJamOptions($jamBuka, $jamTutup, $tanggal) {
    $options = [];
    
    // Gabungkan tanggal dan jam buka/tutup supaya strtotime lengkap tanggalnya
    $start = strtotime($tanggal . ' ' . $jamBuka);
    $end = strtotime($tanggal . ' ' . $jamTutup);
    
    if ($end <= $start) {
        $end = strtotime('+1 day', $end);
    }
    
    $now = time();
    $isToday = ($tanggal === date('Y-m-d'));
    
    while ($start < $end) {
        $jamAwal = date('H:i', $start);
        $next = strtotime('+1 hour', $start);
        $jamAkhir = date('H:i', min($next, $end));
        
        // Jika tanggal hari ini, tampilkan jam yang masih lewat sekarang
        if (!$isToday || $start > $now) {
            $options[] = $jamAwal . '-' . $jamAkhir;
        }
        
        $start = $next;
    }
    
    return $options;
}

$jamOptions = [];

if ($selectedFasilitas && $selectedFasilitas['TipeBooking'] === 'jam') {
    $tanggalHariIni = date('Y-m-d');

    $jamBuka = $selectedFasilitas['JamBuka'] ?? null;
    $jamTutup = $selectedFasilitas['JamTutup'] ?? null;

    if ($jamBuka && $jamTutup) {
        $jamOptions = generateJamOptions($jamBuka, $jamTutup, $tanggalHariIni);

        // Cek jam yang sudah dibooking juga (lanjutan)
        $bookedJams = [];
        if (isset($idUnit)) {
            $stmtBooked = $conn->prepare("
                SELECT Jam FROM booking 
                WHERE IdUnit = ? AND Tanggal = ? AND StatusBooking IN ('diterima', 'menunggu')
            ");
            $stmtBooked->bind_param("is", $idUnit, $tanggalHariIni);
            $stmtBooked->execute();
            $resultBooked = $stmtBooked->get_result();

            while ($row = $resultBooked->fetch_assoc()) {
                $bookedJams = array_merge($bookedJams, explode(',', $row['Jam']));
            }

            // Filter jam bentrok
            $jamOptions = array_filter($jamOptions, function ($opt) use ($bookedJams) {
                return !in_array($opt, $bookedJams);
            });
        }
    }
}

// Proses submit form pemesanan langsung
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_fasilitas'])) {
    $idFasilitas = intval($_POST['id_fasilitas']);
    $idUnit = intval($_POST['id_unit']);

    // Validasi nomor ruang
    if (!isset($_POST['nomor_ruang']) || !is_numeric($_POST['nomor_ruang']) || intval($_POST['nomor_ruang']) < 1) {
        echo "<script>alert('Nomor ruang belum dipilih atau tidak valid'); window.history.back();</script>";
        exit();
    }

    $nomorRuang = intval($_POST['nomor_ruang']);

    // Cek apakah nomor ruang sesuai dengan JumlahRuang di database
    $stmt = $conn->prepare("SELECT JumlahRuang FROM unit WHERE IdUnit = ?");
    $stmt->bind_param("i", $idUnit);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $jumlahRuang = intval($row['JumlahRuang']);
        if ($nomorRuang > $jumlahRuang) {
            echo "<script>alert('Nomor ruang melebihi jumlah ruang yang tersedia untuk unit ini'); window.history.back();</script>";
            exit();
        }
    } else {
        echo "<script>alert('Unit tidak ditemukan'); window.history.back();</script>";
        exit();
    }

    // Ubah array tanggal menjadi string jika harian
    $tanggal = $_POST['tanggal'];
    if (is_array($tanggal)) {
        $tanggal = implode(',', $tanggal); // jadikan string "2025-08-27,2025-08-28"
    }
    $nama = trim($_POST['nama']);
    $nomorHp = trim($_POST['nomor_hp']);
    $jamArr = isset($_POST['jam']) ? $_POST['jam'] : [];

    $jam = !empty($jamArr) ? implode(',', $jamArr) : null;

    $idPelanggan = NULL;  // Karena ini pemesanan langsung oleh penyedia
    $statusBooking = 'diterima';

    // Ambil ulang data fasilitas jika belum tersedia
    if (!isset($selectedFasilitas)) {
        $stmtF = $conn->prepare("SELECT * FROM fasilitas WHERE IdFasilitas = ?");
        $stmtF->bind_param("i", $idFasilitas);
        $stmtF->execute();
        $resultF = $stmtF->get_result();
        if ($resultF->num_rows > 0) {
            $selectedFasilitas = $resultF->fetch_assoc();
        } else {
            echo "<script>alert('Fasilitas tidak ditemukan'); window.history.back();</script>";
            exit();
        }
    }

    // Validasi jika tipe booking jam dan jam kosong
    if ($selectedFasilitas['TipeBooking'] === 'jam' && empty($jam)) {
        echo "<script>alert('Pilih minimal satu jam pemesanan.'); window.history.back();</script>";
        exit();
    }

    // Cek konflik booking
    $conflict = false;
    if ($selectedFasilitas['TipeBooking'] === 'jam') {
        $stmtCheck = $conn->prepare("SELECT Jam FROM booking WHERE IdUnit = ? AND NomorRuang = ? AND Tanggal = ? AND StatusBooking IN ('diterima', 'menunggu')");
        $stmtCheck->bind_param("iis", $idUnit, $nomorRuang, $tanggal);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();

        $newJamArray = $jamArr;
        while ($rowCheck = $resultCheck->fetch_assoc()) {
            $existingJamArr = explode(',', $rowCheck['Jam']);
            foreach ($newJamArray as $jamBaru) {
                if (in_array($jamBaru, $existingJamArr)) {
                    $conflict = true;
                    break 2;
                }
            }
        }
    } else {
    // Ambil semua tanggal yang diminta
    $tanggalList = explode(',', $tanggal);
    $tanggalSet = array_flip($tanggalList); // untuk pencarian cepat

    // Ambil semua booking eksisting pada ruang yang sama
    $stmtCheck = $conn->prepare("SELECT Tanggal FROM booking WHERE IdUnit = ? AND NomorRuang = ? AND StatusBooking IN ('diterima', 'menunggu')");
    $stmtCheck->bind_param("ii", $idUnit, $nomorRuang);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();

    while ($rowCheck = $resultCheck->fetch_assoc()) {
        $existingDates = explode(',', $rowCheck['Tanggal']);
        foreach ($existingDates as $tglBooked) {
            if (isset($tanggalSet[$tglBooked])) {
                $conflict = true;
                break 2; // Langsung keluar jika bentrok
            }
        }
    }
}

    if ($conflict) {
        echo "<script>alert('Jadwal sudah terpakai. Silakan pilih waktu atau ruang lain.'); window.history.back();</script>";
        exit();
    }

    // Insert booking ke database
    $stmtInsert = $conn->prepare("INSERT INTO booking (IdPelanggan, Nama, IdUnit, NomorRuang, Tanggal, Jam, NomorHp, StatusBooking) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmtInsert->bind_param("isisssss", $idPelanggan, $nama, $idUnit, $nomorRuang, $tanggal, $jam, $nomorHp, $statusBooking);

    if ($stmtInsert->execute()) {
        echo "<script>alert('Pemesanan berhasil dicatat'); window.location.href='pemesanan_langsung.php?id=$idFasilitas';</script>";
        exit();
    } else {
        echo "<script>alert('Gagal menyimpan pemesanan: " . $conn->error . "');</script>";
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
    <title>Pemesanan Langsung - Vooking</title>
    <link rel="stylesheet" href="penyedia_home.css">
    <link rel="stylesheet" href="notif_chat.css">
    <link rel="stylesheet" href="button.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body>
<?php include "sidebar_penyedia.php"; ?>

<div class="main">
    <?php include "header_penyedia.php";?> 

    <div class="content" style="display: flex; gap: 20px;">
        <div style="flex: 1;">
            <h3>Daftar Fasilitas</h3>
            <?php if (!$resultFasilitas || $resultFasilitas->num_rows === 0): ?>
                <p>Belum ada fasilitas.</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <?php
                    $resultFasilitas->data_seek(0);
                    while ($row = $resultFasilitas->fetch_assoc()):
                        $isSelected = ($selectedFasilitas && $selectedFasilitas['IdFasilitas'] == $row['IdFasilitas']);
                    ?>
                        <div onclick="window.location.href='pemesanan_langsung.php?id=<?= $row['IdFasilitas'] ?>'"
                            style="cursor: pointer; display: flex; border: 1px solid #ccc; border-radius: 8px; overflow: hidden; box-shadow: 2px 2px 5px rgba(0,0,0,0.1); background: <?= $isSelected ? '#f0f8ff' : '#fff' ?>;">
                            <?php if (!empty($row['FotoFasilitas'])): ?>
                                <img src="<?= htmlspecialchars($row['FotoFasilitas']) ?>" alt="Foto"
                                    style="width: 80px; height: 80px; object-fit: cover;">
                            <?php else: ?>
                                <div style="width: 80px; height: 80px; background: #eee; display: flex; align-items: center; justify-content: center;">
                                    <i class="fa-solid fa-image"></i>
                                </div>
                            <?php endif; ?>
                            <div style="padding: 10px;">
                                <strong><?= htmlspecialchars($row['NamaFasilitas']) ?></strong><br>
                                <span style="font-size: 0.9em; color: #666;"><?= htmlspecialchars($row['Kategori']) ?></span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </div>

        <div style="flex: 2; border-left: 1px solid #ddd; padding-left: 20px;">
            <?php if ($selectedFasilitas): ?>
                <h3>Pemesanan Langsung: <?= htmlspecialchars($selectedFasilitas['NamaFasilitas']) ?></h3>
                <form method="post" action="pemesanan_langsung.php">
                    <input type="hidden" name="id_fasilitas" value="<?= $selectedFasilitas['IdFasilitas'] ?>" />
                    
                    <label for="id_unit">Pilih Unit:</label>
                    <select name="id_unit" id="id_unit" required>
                        <option value="">-- Pilih Unit --</option>
                        <?php foreach ($unitList as $unit): ?>
                            <option value="<?= $unit['IdUnit'] ?>"><?= htmlspecialchars($unit['NamaTipeUnit']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="nomor_ruang">Nomor Ruang:</label>
                    <select name="nomor_ruang" id="nomor_ruang" disabled>
                        <option value="">-- Pilih Nomor Ruang --</option>
                    </select>

                    <?php if ($selectedFasilitas['TipeBooking'] === 'harian'): ?>
                        <label>Tanggal:</label>
                        <div id="tanggal-container">
                            <div class="tanggal-item">
                                <input type="date" name="tanggal[]" class="tanggal-field" required>
                            </div>
                        </div>
                        <button type="button" class="btn btn-tambah" onclick="tambahTanggal()">
                            <i class="fa-solid fa-calendar-plus"></i> Tambah Tanggal
                        </button>
                    <?php else: ?>
                        <input type="hidden" name="tanggal[]" value="<?= date('Y-m-d') ?>" />
                    <?php endif; ?>


                    <?php if ($selectedFasilitas['TipeBooking'] === 'jam'): ?>
                        <label for="jam">Pilih Jam:</label>
                        <div class="jam-checkboxes">
                            <p style="color: #888;">Silakan pilih unit dan nomor ruang terlebih dahulu.</p>
                        </div>
                    <?php endif; ?>

                    <label for="nama">Nama Pemesan:</label>
                    <input type="text" name="nama" id="nama" required />

                    <label for="nomor_hp">Nomor HP:</label>
                    <input type="tel" name="nomor_hp" id="nomor_hp" pattern="[0-9+]+" required />

                    <button type="submit" class="btn btn-catat"><i class="fa fa-check-circle"></i> Catat Pemesanan</button>
                </form>
            <?php else: ?>
                <p>Pilih fasilitas untuk memulai pemesanan.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
    
document.addEventListener('DOMContentLoaded', function () {
    const unitSelect = document.getElementById('id_unit');
    const nomorRuangSelect = document.getElementById('nomor_ruang');
    const tanggalInput = document.getElementById('tanggal');
    const tipeBooking = <?= json_encode($selectedFasilitas['TipeBooking']) ?>;
    const jamContainer = document.querySelector('.jam-checkboxes');
    const idFasilitas = <?= json_encode($selectedFasilitas['IdFasilitas']) ?>;

    // Ambil nomor ruang saat unit berubah
    unitSelect.addEventListener('change', function () {
        const selectedUnitId = this.value;
        nomorRuangSelect.innerHTML = '<option value="">-- Pilih Nomor Ruang --</option>';
        nomorRuangSelect.disabled = true;

        if (!selectedUnitId) return;

        fetch('pemesanan_langsung.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `get_nomor_ruang=1&id_unit=${encodeURIComponent(selectedUnitId)}`
        })
        .then(res => res.json())
        .then(data => {
            data.forEach(n => {
                const opt = document.createElement('option');
                opt.value = n;
                opt.textContent = 'Ruang ' + n;
                nomorRuangSelect.appendChild(opt);
            });
            nomorRuangSelect.disabled = false;
        })
        .catch(err => {
            nomorRuangSelect.innerHTML = '<option value="">-- Gagal Memuat --</option>';
            console.error('Error ambil nomor ruang:', err);
        });
    });

    // Ambil jam tersedia saat nomor ruang berubah
    nomorRuangSelect.addEventListener('change', function () {
        const idUnit = unitSelect.value;
        const nomorRuang = this.value;
        const tanggal = tanggalInput.value;

        if (!idUnit || !nomorRuang || !tanggal) return;

        fetch('get_jam_tersedia_pemesanan_langsung.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `id_fasilitas=${idFasilitas}&id_unit=${idUnit}&nomor_ruang=${nomorRuang}&tanggal=${tanggal}`
        })
        .then(res => res.text()) // PHP mengembalikan HTML
        .then(html => {
            jamContainer.innerHTML = html;
        })
        .catch(err => {
            console.error('Gagal ambil jam:', err);
            jamContainer.innerHTML = '<p style="color:red;">Gagal memuat jam tersedia.</p>';
        });
    });

    // Jika booking per jam, set tanggal ke hari ini dan sembunyikan input
    if (tipeBooking === 'jam') {
        const today = new Date().toISOString().split('T')[0];
        tanggalInput.value = today;
        tanggalInput.type = 'hidden';
    }
});

function tambahTanggal() {
    const container = document.getElementById('tanggal-container');

    const wrapper = document.createElement('div');
    wrapper.className = 'tanggal-item';

    const input = document.createElement('input');
    input.type = 'date';
    input.name = 'tanggal[]';
    input.className = 'tanggal-field';
    input.required = true;

    const btnHapus = document.createElement('button');
    btnHapus.type = 'button';
    btnHapus.className = 'btn btn-hapus';
    btnHapus.innerHTML = '<i class="fa-solid fa-trash"></i> Hapus';
    btnHapus.onclick = () => container.removeChild(wrapper);

    wrapper.appendChild(input);
    wrapper.appendChild(btnHapus);
    container.appendChild(wrapper);
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
