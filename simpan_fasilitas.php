<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['IdUser'])) {
    header("Location: login_register.php");
    exit();
}

$IdUser = $_SESSION['IdUser'];

$sql = "SELECT IdPenyedia FROM penyediafasilitas WHERE IdUser = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $IdUser);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $IdPenyedia = $row['IdPenyedia'];
} else {
    die("Data penyedia tidak ditemukan.");
}

// Upload Foto
$fotoPath = null;
if (isset($_FILES['FotoFasilitas']) && $_FILES['FotoFasilitas']['error'] === UPLOAD_ERR_OK) {
    $targetDir = "uploads/";
    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0755, true)) {
            die("Gagal membuat folder upload.");
        }
    }

    $ext = strtolower(pathinfo($_FILES['FotoFasilitas']['name'], PATHINFO_EXTENSION));
    $allowedTypes = ['jpg', 'jpeg', 'png', 'webp'];
    if (in_array($ext, $allowedTypes)) {
        $filename = uniqid() . "_" . basename($_FILES['FotoFasilitas']['name']);
        $targetFilePath = $targetDir . $filename;

        if (move_uploaded_file($_FILES['FotoFasilitas']['tmp_name'], $targetFilePath)) {
            $fotoPath = $targetFilePath;
        } else {
            die("Gagal mengupload file foto fasilitas.");
        }
    } else {
        die("Tipe file foto fasilitas tidak diizinkan. Hanya jpg, jpeg, png, webp.");
    }
}

// Simpan fasilitas
$stmt = $conn->prepare("INSERT INTO fasilitas 
    (IdPenyedia, NamaFasilitas, Kategori, Lokasi, Provinsi, TipeBooking, JamBuka, JamTutup, NoRekening, Deskripsi, FotoFasilitas, Status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'aktif')");

if (!$stmt) {
    die("Prepare insert fasilitas failed: " . $conn->error);
}

$stmt->bind_param(
    "issssssssss",
    $IdPenyedia,
    $_POST['NamaFasilitas'],
    $_POST['Kategori'],
    $_POST['Lokasi'],
    $_POST['Provinsi'],
    $_POST['TipeBooking'],
    $_POST['JamBuka'],
    $_POST['JamTutup'],
    $_POST['NoRekening'],
    $_POST['Deskripsi'],
    $fotoPath
);


if (!$stmt->execute()) {
    die("Gagal menyimpan data fasilitas: " . $stmt->error);
}

$IdFasilitas = $conn->insert_id;

// Simpan unit
$unit_nama = $_POST['unit_nama'] ?? [];
$unit_jumlah = $_POST['unit_jumlah'] ?? [];
$unit_harga = $_POST['unit_harga'] ?? [];
$unit_dp = $_POST['unit_dp'] ?? [];

$stmtUnit = $conn->prepare("INSERT INTO unit (IdFasilitas, NamaTipeUnit, JumlahRuang, Harga, DP) VALUES (?, ?, ?, ?, ?)");
if (!$stmtUnit) {
    die("Prepare insert unit failed: " . $conn->error);
}

for ($i = 0; $i < count($unit_nama); $i++) {
    $nama = $unit_nama[$i];
    $jumlah = (int)$unit_jumlah[$i];
    $harga = (int)$unit_harga[$i];
    $dp = (int)$unit_dp[$i];

    $stmtUnit->bind_param("isiii", $IdFasilitas, $nama, $jumlah, $harga, $dp);

    if (!$stmtUnit->execute()) {
        die("Gagal menyimpan data unit: " . $stmtUnit->error);
    }
}

header("Location: penyedia_home.php");
exit();
?>
