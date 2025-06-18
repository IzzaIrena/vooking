<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['IdUser']) || $_SESSION['RoleAktif'] !== 'penyedia') {
    header("Location: login_register.php");
    exit();
}

$IdUser = $_SESSION['IdUser'];

$sqlPenyedia = "SELECT IdPenyedia FROM penyediafasilitas WHERE IdUser = ?";
$stmtPenyedia = $conn->prepare($sqlPenyedia);
$stmtPenyedia->bind_param("i", $IdUser);
$stmtPenyedia->execute();
$resultPenyedia = $stmtPenyedia->get_result();

if ($resultPenyedia->num_rows > 0) {
    $rowPenyedia = $resultPenyedia->fetch_assoc();
    $IdPenyedia = $rowPenyedia['IdPenyedia'];

    $sqlFasilitas = "SELECT * FROM fasilitas WHERE IdPenyedia = ?";
    $stmtFasilitas = $conn->prepare($sqlFasilitas);
    $stmtFasilitas->bind_param("i", $IdPenyedia);
    $stmtFasilitas->execute();
    $resultFasilitas = $stmtFasilitas->get_result();
} else {
    $resultFasilitas = false;
}

$editFasilitas = null;
$unitList = [];

if (isset($_GET['id'])) {
    $idFas = intval($_GET['id']);
    $stmtEdit = $conn->prepare("SELECT * FROM fasilitas WHERE IdFasilitas = ? AND IdPenyedia = ?");
    $stmtEdit->bind_param("ii", $idFas, $IdPenyedia);
    $stmtEdit->execute();
    $resultEdit = $stmtEdit->get_result();
    if ($resultEdit->num_rows > 0) {
        $editFasilitas = $resultEdit->fetch_assoc();

        $stmtUnit = $conn->prepare("SELECT * FROM unit WHERE IdFasilitas = ?");
        $stmtUnit->bind_param("i", $idFas);
        $stmtUnit->execute();
        $resultUnit = $stmtUnit->get_result();
        while ($rowUnit = $resultUnit->fetch_assoc()) {
            $unitList[] = $rowUnit;
        }
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
    <title>Edit Fasilitas - Vooking</title>
    <link rel="stylesheet" href="penyedia_home.css">
    <link rel="stylesheet" href="notif_chat.css">
    <link rel="stylesheet" href="button.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        form input[type="text"],
        form input[type="number"],
        form input[type="time"],
        form select,
        form textarea {
            width: 100%;
            padding: 8px 10px;
            margin-top: 6px;
            margin-bottom: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 14px;
        }

        form label {
            font-weight: 600;
            display: block;
            margin-bottom: 4px;
        }

        .unit-row {
            margin-bottom: 20px;
            padding: 10px;
            border: 1px dashed #aaa;
            border-radius: 6px;
            background: #fafafa;
        }
    </style>
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
                    ?>
                        <div onclick="window.location.href='edit_fasilitas.php?id=<?= $row['IdFasilitas'] ?>'"
                            style="cursor: pointer; display: flex; border: 1px solid #ccc; border-radius: 8px; overflow: hidden; box-shadow: 2px 2px 5px rgba(0,0,0,0.1); background: <?= ($editFasilitas && $editFasilitas['IdFasilitas'] == $row['IdFasilitas']) ? '#f0f8ff' : '#fff' ?>">
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

        <div style="flex: 2; border: 1px solid #ddd; padding: 20px; border-radius: 5px; max-height: 80vh; overflow-y: auto;">
            <?php if ($editFasilitas): ?>
                <h3>Edit Fasilitas: <?= htmlspecialchars($editFasilitas['NamaFasilitas']) ?></h3>
                <form method="POST" action="update_fasilitas.php" enctype="multipart/form-data">
                    <div style="display: flex; gap: 10px; margin-top: 20px; margin-bottom: 20px;">
                        <?php if ($editFasilitas['Status'] === 'non_aktif_admin'): ?>
                            <p style="color: red;">Fasilitas ini telah dinonaktifkan oleh admin dan tidak dapat dibuka kembali.</p>
                        <?php else: ?>
                            <div style="margin-bottom: 20px;">
                                <?php
                                    $status = $editFasilitas['Status'];
                                    $nextStatus = $status === 'aktif' ? 'non_aktif' : 'aktif';
                                    $btnClass = $status === 'aktif' ? 'btn-red' : 'btn-blue';
                                    $btnLabel = $status === 'aktif' ? 'Tutup Fasilitas' : 'Buka Fasilitas';
                                ?>
                                <button type="button" class="btn <?= $btnClass ?>" onclick="confirmStatusChange('<?= $nextStatus ?>', <?= $editFasilitas['IdFasilitas'] ?>)">
                                    <i class="fa-solid fa-power-off"></i> <?= $btnLabel ?>
                                </button>
                            </div>
                        <?php endif; ?>
                        <script>
                        function confirmStatusChange(status, id) {
                            if (confirm('Yakin ingin mengubah status fasilitas ini?')) {
                                window.location.href = 'ubah_status_fasilitas.php?id=' + id + '&status=' + status;
                            }
                        }
                        </script>
                    </div>
                    <input type="hidden" name="IdFasilitas" value="<?= $editFasilitas['IdFasilitas'] ?>">

                    <label>Nama Fasilitas:</label><br>
                    <input type="text" name="NamaFasilitas" value="<?= htmlspecialchars($editFasilitas['NamaFasilitas']) ?>" required><br><br>

                    <label>Kategori:</label><br>
                    <select name="Kategori" required>
                        <?php
                        $kategoriEnum = ['penginapan', 'olahraga', 'resto', 'ruang'];
                        foreach ($kategoriEnum as $kategori) {
                            $selected = ($editFasilitas['Kategori'] == $kategori) ? 'selected' : '';
                            echo "<option value=\"$kategori\" $selected>" . ucfirst($kategori) . "</option>";
                        }
                        ?>
                    </select><br><br>

                    <label>Lokasi:</label><br>
                    <input type="text" name="Lokasi" value="<?= htmlspecialchars($editFasilitas['Lokasi']) ?>"><br><br>

                    <label>Provinsi:</label><br>
                    <select name="Provinsi" required>
                        <?php
                        $provinsiList = [ "Aceh", "Sumatera Utara", "Sumatera Barat", "Riau", "Jambi", "Sumatera Selatan",
                            "Bengkulu", "Lampung", "Kepulauan Bangka Belitung", "Kepulauan Riau", "DKI Jakarta",
                            "Jawa Barat", "Jawa Tengah", "DI Yogyakarta", "Jawa Timur", "Banten", "Bali", 
                            "Nusa Tenggara Barat", "Nusa Tenggara Timur", "Kalimantan Barat", "Kalimantan Tengah", 
                            "Kalimantan Selatan", "Kalimantan Timur", "Kalimantan Utara", "Sulawesi Utara", 
                            "Sulawesi Tengah", "Sulawesi Selatan", "Sulawesi Tenggara", "Gorontalo", 
                            "Sulawesi Barat", "Maluku", "Maluku Utara", "Papua Barat", "Papua"
                        ];
                        foreach ($provinsiList as $prov) {
                            $selected = ($editFasilitas['Provinsi'] == $prov) ? 'selected' : '';
                            echo "<option value=\"$prov\" $selected>$prov</option>";
                        }
                        ?>
                    </select><br><br>

                    <label>Tipe Booking:</label><br>
                    <select name="TipeBooking">
                        <option value="jam" <?= $editFasilitas['TipeBooking'] == 'jam' ? 'selected' : '' ?>>Jam</option>
                        <option value="harian" <?= $editFasilitas['TipeBooking'] == 'harian' ? 'selected' : '' ?>>Harian</option>
                    </select><br><br>

                    <label>Jam Buka:</label><br>
                    <input type="time" name="JamBuka" value="<?= htmlspecialchars($editFasilitas['JamBuka']) ?>"><br><br>

                    <label>Jam Tutup:</label><br>
                    <input type="time" name="JamTutup" value="<?= htmlspecialchars($editFasilitas['JamTutup']) ?>"><br><br>

                    <label>No Rekening:</label><br>
                    <input type="text" name="NoRekening" value="<?= htmlspecialchars($editFasilitas['NoRekening'] ?? '') ?>"><br><br>

                    <label>Deskripsi:</label><br>
                    <textarea name="Deskripsi" rows="4" cols="50"><?= htmlspecialchars($editFasilitas['Deskripsi']) ?></textarea><br><br>

                    <label>Foto Fasilitas (upload baru jika ingin ganti):</label><br>
                    <?php if (!empty($editFasilitas['FotoFasilitas'])): ?>
                        <img src="<?= htmlspecialchars($editFasilitas['FotoFasilitas']) ?>" alt="Foto Fasilitas" style="max-width: 200px; display:block; margin-bottom:10px;">
                    <?php endif; ?>
                    <input type="file" name="FotoFasilitas"><br><br>

                    <label>Jumlah Unit:</label><br>
                    <input type="number" name="JumlahUnit" id="JumlahUnit" value="<?= count($unitList) ?>" readonly><br><br>

                    <div id="unitContainer">
                        <?php foreach ($unitList as $index => $unit): ?>
                            <div class="unit-row">
                                <input type="hidden" name="Unit[<?= $index ?>][IdUnit]" value="<?= $unit['IdUnit'] ?>">
                                <label>Nama Tipe Unit:</label><br>
                                <input type="text" name="Unit[<?= $index ?>][NamaTipeUnit]" value="<?= htmlspecialchars($unit['NamaTipeUnit']) ?>" required><br>

                                <label>Jumlah Ruang:</label><br>
                                <input type="number" name="Unit[<?= $index ?>][JumlahRuang]" value="<?= $unit['JumlahRuang'] ?>" required><br>

                                <label>Harga:</label><br>
                                <input type="number" name="Unit[<?= $index ?>][Harga]" value="<?= $unit['Harga'] ?>" required><br>

                                <label>DP:</label><br>
                                <input type="number" name="Unit[<?= $index ?>][DP]" value="<?= $unit['DP'] ?>" required><br><br>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <button type="button" onclick="addUnit()" class="btn btn-blue"><i class="fa-solid fa-plus"></i>Tambah Unit</button>

                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" class="btn btn-green"><i class="fa-solid fa-floppy-disk"></i>Simpan Perubahan</button>
                        <a href="edit_fasilitas.php" class="btn btn-red" style="text-decoration: none;"><i class="fa-solid fa-xmark"></i>Batal</a>                      
                    </div>
                    
                    <a href="hapus_fasilitas.php?id=<?= $editFasilitas['IdFasilitas'] ?>" 
                    class="btn btn-delete-facility"
                    onclick="return confirm('Yakin ingin menghapus fasilitas ini? Tindakan ini tidak dapat dibatalkan.')">
                        <i class="fa-solid fa-trash-can"></i> Hapus Fasilitas
                    </a>

                <script>
                    let unitIndex = <?= count($unitList) ?>;

                    function addUnit() {
                        const container = document.getElementById('unitContainer');
                        const div = document.createElement('div');
                        div.classList.add('unit-row');
                        div.innerHTML = `
                            <label>Nama Tipe Unit:</label><br>
                            <input type="text" name="Unit[${unitIndex}][NamaTipeUnit]" required><br>
                            <label>Jumlah Ruang:</label><br>
                            <input type="number" name="Unit[${unitIndex}][JumlahRuang]" required><br>
                            <label>Harga:</label><br>
                            <input type="number" name="Unit[${unitIndex}][Harga]" required><br>
                            <label>DP:</label><br>
                            <input type="number" name="Unit[${unitIndex}][DP]" required><br><br>
                        `;
                        container.appendChild(div);
                        unitIndex++;
                        document.getElementById('JumlahUnit').value = unitIndex;
                    }
                </script>

            <?php else: ?>
                <p>Pilih fasilitas untuk diedit dari daftar di sebelah kiri.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
    <script>
        function confirmStatusChange(statusBaru, idFasilitas) {
            let pesan = statusBaru === 'non_aktif'
                ? "Apakah Anda yakin ingin menutup fasilitas ini? Fasilitas tidak akan bisa dipesan."
                : "Apakah Anda yakin ingin membuka kembali fasilitas ini?";
            if (confirm(pesan)) {
                window.location.href = "ubah_status_fasilitas.php?id=" + idFasilitas + "&status=" + statusBaru;
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
