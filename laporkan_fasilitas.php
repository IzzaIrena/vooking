<?php
session_start();
include 'koneksi.php';

if (!isset($_GET['id']) || !isset($_SESSION['IdUser'])) {
    echo "Akses tidak valid.";
    exit();
}

$idFasilitas = (int)$_GET['id'];
$idUser = $_SESSION['IdUser'];

$stmt = $conn->prepare("SELECT IdPelanggan FROM pelanggan WHERE IdUser = ?");
$stmt->bind_param("i", $idUser);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$idPelanggan = $row['IdPelanggan'] ?? null;

if (!$idPelanggan) {
    echo "Anda bukan pelanggan.";
    exit();
}

$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deskripsi = trim($_POST['deskripsi']);
    $buktiFoto = null;

    if (!empty($_FILES['bukti']['name'])) {
        $uploadDir = "uploads/bukti_laporan/";
        $fileName = uniqid() . "_" . basename($_FILES["bukti"]["name"]);
        $targetFile = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES["bukti"]["tmp_name"], $targetFile)) {
            $buktiFoto = $fileName;  // simpan hanya nama file ke DB
        } else {
            // Handle error upload
            $buktiFoto = null;
        }
    }

    $stmt = $conn->prepare("INSERT INTO laporan (IdPelanggan, IdFasilitas, Deskripsi, BuktiFoto, StatusLaporan)
                            VALUES (?, ?, ?, ?, 'dikirim')");
    $stmt->bind_param("iiss", $idPelanggan, $idFasilitas, $deskripsi, $buktiFoto);
    $stmt->execute();

    $message = "Laporan berhasil dikirim.";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporkan Fasilitas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f2f2f2;
            margin: 0;
        }

        .vooking-header {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            background-color: white;
            color: #0056b3;
            font-family: 'Times New Roman', Times, serif;
        }

        .logo-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-img {
            width: 35px;
            height: 35px;
        }

        .back-home-icon {
            font-size: 20px;
            color: #0056b3;
            text-decoration: none;
            margin-right: 10px;
        }

        .form-container {
            background: #fff;
            max-width: 600px;
            margin: 40px auto;
            padding: 30px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            border-radius: 10px;
        }

        .form-container h2 {
            margin-top: 0;
            text-align: center;
            color: #333;
        }

        label {
            font-weight: 600;
            display: block;
            margin-top: 20px;
            color: #444;
        }

        textarea, input[type="file"] {
            width: 100%;
            margin-top: 8px;
            padding: 10px;
            font-size: 14px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }

        button {
            margin-top: 25px;
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 12px 25px;
            font-size: 16px;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.3s;
        }

        button:hover {
            background-color: #c0392b;
        }

        .message {
            text-align: center;
            color: green;
            font-weight: bold;
            margin-top: 15px;
        }

    </style>
</head>
<body>
    <div class="vooking-header">
        <div class="logo-title">
            <a href="lihat_fasilitas.php?id=<?= $_GET['id'] ?>"><i class="fa-solid fa-arrow-left back-home-icon"></i></a>
            <img src="Vicon.png" class="logo-img" />
            <h2>VOOKING</h2>
        </div>
    </div>

    <div class="form-container">
        <h2>Laporkan Fasilitas</h2>

        <?php if ($message): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <label for="deskripsi">Deskripsi Masalah:</label>
            <textarea name="deskripsi" id="deskripsi" rows="5" required></textarea>

            <label for="bukti">Unggah Bukti Foto (opsional):</label>
            <input type="file" name="bukti" id="bukti" accept="image/*">

            <button type="submit">Kirim Laporan</button>
        </form>
    </div>
</body>
</html>
