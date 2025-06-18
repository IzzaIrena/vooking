<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['IdUser'])) {
    header("Location: login_register.php");
    exit();
}

$idUser = $_SESSION['IdUser'];

// Cek apakah user sudah pernah mengajukan jadi penyedia
$stmt = $conn->prepare("SELECT StatusVerifikasi FROM penyediafasilitas WHERE IdUser = ?");
$stmt->bind_param("i", $idUser);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->bind_result($statusVerifikasi);
    $stmt->fetch();

    if ($statusVerifikasi === 'disetujui') {
        // Update RoleAktif user
        $updateUser = $conn->prepare("UPDATE user SET RoleAktif = 'penyedia' WHERE IdUser = ?");
        $updateUser->bind_param("i", $idUser);
        $updateUser->execute();

        // Update status penyedia
        $updateLangganan = $conn->prepare("UPDATE penyediafasilitas SET StatusLangganan = 'aktif', TanggalAktivasi = CURDATE() WHERE IdUser = ?");
        $updateLangganan->bind_param("i", $idUser);
        $updateLangganan->execute();

        $_SESSION['RoleAktif'] = 'penyedia';
        header("Location: penyedia_home.php");
        exit();
    } else {
        $message = "Permintaan Anda sedang menunggu verifikasi admin.";
    }
} else {
    // Tambahkan ke penyediafasilitas
    $insert = $conn->prepare("INSERT INTO penyediafasilitas (IdUser, StatusVerifikasi, StatusLangganan) VALUES (?, 'menunggu', 'non_aktif')");
    $insert->bind_param("i", $idUser);
    $insert->execute();
    $idPenyediaBaru = $conn->insert_id;

    // Proses upload bukti
    $tanggalSekarang = date("Y-m-d H:i:s");

    if (isset($_FILES['bukti']) && $_FILES['bukti']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/bukti_pembayaran/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $fileName = basename($_FILES['bukti']['name']);
        $newFileName = time() . '_' . preg_replace("/[^a-zA-Z0-9\.]/", "_", $fileName);
        $uploadPath = $uploadDir . $newFileName;

        if (move_uploaded_file($_FILES['bukti']['tmp_name'], $uploadPath)) {
            // Insert dengan bukti dan tanggal
            $insertVerif = $conn->prepare("INSERT INTO verifikasipenyedia (IdPenyedia, TanggalPembayaran, BuktiPembayaran, Status) VALUES (?, ?, ?, 'menunggu')");
            $insertVerif->bind_param("iss", $idPenyediaBaru, $tanggalSekarang, $newFileName);
            $insertVerif->execute();
        } else {
            // Gagal upload, tanpa bukti
            $insertVerif = $conn->prepare("INSERT INTO verifikasipenyedia (IdPenyedia, TanggalPembayaran, Status) VALUES (?, ?, 'menunggu')");
            $insertVerif->bind_param("is", $idPenyediaBaru, $tanggalSekarang);
            $insertVerif->execute();
        }
    } else {
        // Tidak ada file diupload
        $insertVerif = $conn->prepare("INSERT INTO verifikasipenyedia (IdPenyedia, TanggalPembayaran, Status) VALUES (?, ?, 'menunggu')");
        $insertVerif->bind_param("is", $idPenyediaBaru, $tanggalSekarang);
        $insertVerif->execute();
    }

    $message = "Permintaan aktivasi sebagai penyedia telah dikirim. Tunggu verifikasi admin.";
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="home.css">
    <title>Aktivasi Penyedia</title>
</head>
<body>

<div class="popup-overlay" id="popup">
    <div class="popup-content">
        <p><?= htmlspecialchars($message); ?></p>
        <button onclick="window.location.href='profil_pelanggan.php'">OK</button>
    </div>
</div>

</body>
</html>
