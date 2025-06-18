<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['IdUser']) || $_SESSION['RoleAktif'] !== 'penyedia') {
    header("Location: login_register.php");
    exit();
}

$IdUser = $_SESSION['IdUser'];
$IdFasilitas = intval($_POST['IdFasilitas'] ?? 0);
$NamaFasilitas = trim($_POST['NamaFasilitas'] ?? '');
$Kategori = trim($_POST['Kategori'] ?? '');
$Lokasi = trim($_POST['Lokasi'] ?? '');
$Provinsi = trim($_POST['Provinsi'] ?? '');
$TipeBooking = trim($_POST['TipeBooking'] ?? '');
$JamBuka = trim($_POST['JamBuka'] ?? '');
$JamTutup = trim($_POST['JamTutup'] ?? '');
$Deskripsi = trim($_POST['Deskripsi'] ?? '');
$NoRekening = trim($_POST['NoRekening'] ?? '');

// Validasi sederhana
if (!$IdFasilitas || !$NamaFasilitas) {
    die("Data fasilitas tidak lengkap.");
}

// Cek kepemilikan fasilitas oleh penyedia
$sqlPenyedia = "SELECT IdPenyedia FROM penyediafasilitas WHERE IdUser = ?";
$stmt = $conn->prepare($sqlPenyedia);
$stmt->bind_param("i", $IdUser);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    die("User tidak terdaftar sebagai penyedia.");
}
$IdPenyedia = $res->fetch_assoc()['IdPenyedia'];

// Update data fasilitas termasuk NoRekening
$stmt = $conn->prepare("UPDATE fasilitas SET NamaFasilitas=?, Kategori=?, Lokasi=?, Provinsi=?, TipeBooking=?, JamBuka=?, JamTutup=?, Deskripsi=?, NoRekening=? WHERE IdFasilitas=? AND IdPenyedia=?");
$stmt->bind_param("ssssssssssi", $NamaFasilitas, $Kategori, $Lokasi, $Provinsi, $TipeBooking, $JamBuka, $JamTutup, $Deskripsi, $NoRekening, $IdFasilitas, $IdPenyedia);

if (!$stmt->execute()) {
    die("Gagal update fasilitas: " . $stmt->error);
}

// Upload foto jika ada
if (isset($_FILES['FotoFasilitas']) && $_FILES['FotoFasilitas']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileName = basename($_FILES['FotoFasilitas']['name']);
    $fileName = preg_replace("/[^a-zA-Z0-9.\-_]/", "", $fileName); // sanitize
    $targetFile = $uploadDir . time() . '_' . $fileName;

    if (move_uploaded_file($_FILES['FotoFasilitas']['tmp_name'], $targetFile)) {
        $stmt = $conn->prepare("UPDATE fasilitas SET FotoFasilitas=? WHERE IdFasilitas=? AND IdPenyedia=?");
        $stmt->bind_param("sii", $targetFile, $IdFasilitas, $IdPenyedia);
        $stmt->execute();
    }
}

// Update atau tambah unit
if (isset($_POST['Unit']) && is_array($_POST['Unit'])) {
    foreach ($_POST['Unit'] as $unit) {
        $IdUnit = isset($unit['IdUnit']) ? intval($unit['IdUnit']) : 0;
        $NamaTipeUnit = trim($unit['NamaTipeUnit'] ?? '');
        $JumlahRuang = intval($unit['JumlahRuang'] ?? 0);
        $Harga = intval($unit['Harga'] ?? 0);
        $DP = intval($unit['DP'] ?? 0);

        if ($NamaTipeUnit === '') continue; // Lewati jika kosong

        if ($IdUnit > 0) {
            // Update unit lama
            $stmt = $conn->prepare("UPDATE unit SET NamaTipeUnit=?, JumlahRuang=?, Harga=?, DP=? WHERE IdUnit=? AND IdFasilitas=?");
            $stmt->bind_param("siiiii", $NamaTipeUnit, $JumlahRuang, $Harga, $DP, $IdUnit, $IdFasilitas);
            $stmt->execute();
        } else {
            // Tambah unit baru
            $stmt = $conn->prepare("INSERT INTO unit (IdFasilitas, NamaTipeUnit, JumlahRuang, Harga, DP) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("isiii", $IdFasilitas, $NamaTipeUnit, $JumlahRuang, $Harga, $DP);
            $stmt->execute();
        }
    }
}

header("Location: edit_fasilitas.php?id=$IdFasilitas");
exit();
?>