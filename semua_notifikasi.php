<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['IdUser'])) {
    header("Location: login_register.php");
    exit();
}

$IdUser = $_SESSION['IdUser'];
$isPenyedia = false;

// Cek apakah user ini adalah penyedia
$sqlCheckPenyedia = "SELECT COUNT(*) AS jumlah FROM penyediafasilitas WHERE IdUser = ?";
$stmtCheck = $conn->prepare($sqlCheckPenyedia);
$stmtCheck->bind_param("i", $IdUser);
$stmtCheck->execute();
$resultCheck = $stmtCheck->get_result();
$rowCheck = $resultCheck->fetch_assoc();
$isPenyedia = $rowCheck['jumlah'] > 0;

// Ambil semua notifikasi user
$sql = "SELECT IdNotifikasi, IsiPesan, Tipe, Tanggal, StatusBaca FROM notifikasi WHERE IdUser = ?
        ORDER BY Tanggal DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $IdUser);
$stmt->execute();
$result = $stmt->get_result();

$notifikasi = [];
while ($row = $result->fetch_assoc()) {
    $notifikasi[] = $row;
}

if ($isPenyedia) {
    // Ambil fasilitas milik penyedia
    $sqlFasilitas = "SELECT IdFasilitas, NamaFasilitas FROM fasilitas WHERE IdPenyedia = ?";
    $stmtFas = $conn->prepare($sqlFasilitas);
    $stmtFas->bind_param("i", $IdUser);
    $stmtFas->execute();
    $resultFas = $stmtFas->get_result();

    $idFasilitasList = [];
    $namaFasilitasMap = [];

    while ($rowFas = $resultFas->fetch_assoc()) {
        $idFasilitasList[] = $rowFas['IdFasilitas'];
        $namaFasilitasMap[$rowFas['IdFasilitas']] = $rowFas['NamaFasilitas'];
    }

    if (!empty($idFasilitasList)) {
        $inQuery = implode(',', array_fill(0, count($idFasilitasList), '?'));
        $types = str_repeat('i', count($idFasilitasList));

        $sqlBooking = "SELECT IdFasilitas, TanggalBooking, JamMulai, JamSelesai FROM booking 
                       WHERE IdFasilitas IN ($inQuery) ORDER BY TanggalBooking DESC LIMIT 10";

        $stmtBook = $conn->prepare($sqlBooking);
        $stmtBook->bind_param($types, ...$idFasilitasList);
        $stmtBook->execute();
        $resultBook = $stmtBook->get_result();

        while ($rowBook = $resultBook->fetch_assoc()) {
            $fasilitasName = $namaFasilitasMap[$rowBook['IdFasilitas']];
            $tgl = date('d M Y', strtotime($rowBook['TanggalBooking']));
            $jam = $rowBook['JamMulai'] . " - " . $rowBook['JamSelesai'];

            $notifikasi[] = [
                'IsiPesan' => "Ada booking baru untuk <strong>$fasilitasName</strong> pada $tgl, pukul $jam.",
                'Tipe' => 'Booking',
                'Tanggal' => $rowBook['TanggalBooking'],
                'StatusBaca' => 1 // karena ditampilkan langsung, anggap sudah dibaca
            ];
        }
    }
}

// Update semua notifikasi yang belum dibaca menjadi sudah dibaca
$sqlUpdate = "UPDATE notifikasi SET StatusBaca = 1 WHERE IdUser = ? AND StatusBaca = 0";
$stmtUpdate = $conn->prepare($sqlUpdate);
$stmtUpdate->bind_param("i", $IdUser);
$stmtUpdate->execute();

// Setelah itu, ambil semua notifikasi untuk ditampilkan
$sqlNotif = "SELECT IsiPesan, Tipe, Tanggal, StatusBaca FROM notifikasi WHERE IdUser = ? ORDER BY Tanggal DESC";
$stmtNotif = $conn->prepare($sqlNotif);
$stmtNotif->bind_param("i", $IdUser);
$stmtNotif->execute();
$resNotif = $stmtNotif->get_result();

$notifPesan = [];
while ($row = $resNotif->fetch_assoc()) {
    $notifPesan[] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Semua Notifikasi - Vooking</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f0f4f8;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 700px;
            margin: 0 auto;
            background: white;
            padding: 25px 30px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        h1 {
            text-align: center;
            color: #004080;
            margin-bottom: 30px;
        }

        .notif-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        .notif-item:last-child {
            border-bottom: none;
        }

        .notif-item p {
            margin: 4px 0;
        }

        .notif-item .tipe {
            font-weight: bold;
            color: #004080;
            text-transform: capitalize;
        }

        .notif-item .tanggal {
            font-size: 13px;
            color: #777;
        }

        .notif-item.unread {
            background-color: #e6f3ff;
        }

        .back-btn {
            display: inline-block;
            margin-bottom: 20px;
            color: #004080;
            text-decoration: none;
        }

        .back-btn i {
            margin-right: 5px;
        }

        .hapus-notif {
            float: right;
            background: none;
            border: none;
            font-size: 18px;
            color: #888;
            cursor: pointer;
        }

        .hapus-notif:hover {
            color: red;
        }

        .btn-hapus-semua {
            display: block;
            margin: 20px auto 0;
            padding: 10px 20px;
            background-color: #c0392b;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        .btn-hapus-semua:hover {
            background-color: #a93226;
        }

        /* Warna latar berbeda untuk tiap tipe */
        .notif-sistem {
            background-color: #fff4e5; /* kuning muda */
            border-left: 4px solid #ffa500;
        }

        .notif-booking {
            background-color: #e6ffe6; /* hijau muda */
            border-left: 4px solid #28a745;
        }

        .notif-verifikasi {
            background-color: #e6f0ff; /* biru muda */
            border-left: 4px solid #007bff;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="javascript:history.back()" class="back-btn"><i class="fa fa-arrow-left"></i> Kembali</a>
        <h1>Semua Notifikasi</h1>

        <?php if (empty($notifikasi)): ?>
            <p style="text-align:center;">Tidak ada notifikasi.</p>
        <?php else: 
            usort($notifikasi, function($a, $b) {
                return strtotime($b['Tanggal']) - strtotime($a['Tanggal']);
            });
            ?>
            <?php foreach ($notifikasi as $notif): ?>
                <?php
                $tipeClass = 'notif-' . strtolower($notif['Tipe']); // hasil: notif-sistem, notif-booking, dll
                $statusClass = $notif['StatusBaca'] == 0 ? 'unread' : '';
                ?>
                <div class="notif-item <?= $statusClass . ' ' . $tipeClass ?>" id="notif-<?= $notif['IdNotifikasi'] ?>">
                    <button class="hapus-notif" data-id="<?= $notif['IdNotifikasi'] ?>" title="Hapus Notifikasi">&times;</button>
                    <p class="tipe"><?= htmlspecialchars($notif['Tipe']) ?></p>
                    <p><?= html_entity_decode($notif['IsiPesan']) ?></p>
                    <p class="tanggal"><?= date('d M Y, H:i', strtotime($notif['Tanggal'])) ?></p>
                </div>
            <?php endforeach; ?>
            <form method="POST" action="hapus_semua_notif.php" onsubmit="return confirm('Hapus semua notifikasi?')">
                <button type="submit" class="btn-hapus-semua">Hapus Semua Notifikasi</button>
            </form>
        <?php endif; ?>
    </div>
<script>
document.querySelectorAll('.hapus-notif').forEach(button => {
    button.addEventListener('click', function() {
        const id = this.dataset.id;
        fetch('hapus_notif.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + id
        })
        .then(response => response.text())
        .then(result => {
            if (result === 'success') {
                document.getElementById('notif-' + id).remove();
            }
        });
    });
});
</script>
</body>
</html>
