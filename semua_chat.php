<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['IdUser'])) {
    header("Location: login.php");
    exit;
}

$IdUser = $_SESSION['IdUser'];

$stmt = $conn->prepare("SELECT IdPelanggan FROM pelanggan WHERE IdUser = ?");
$stmt->bind_param("i", $IdUser);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows == 0) {
    die("Data pelanggan tidak ditemukan.");
}
$IdPelanggan = $res->fetch_assoc()['IdPelanggan'];

$query = "
    SELECT 
        c.IdPenyedia, c.IdFasilitas, 
        MAX(c.Waktu) as LastTime, 
        SUBSTRING_INDEX(GROUP_CONCAT(c.Pesan ORDER BY c.Waktu DESC), ',', 1) as LastMessage,
        u.Username, u.FotoProfil,
        f.NamaFasilitas, f.FotoFasilitas
    FROM chat c
    JOIN penyediafasilitas p ON p.IdPenyedia = c.IdPenyedia
    JOIN user u ON u.IdUser = p.IdUser
    JOIN fasilitas f ON f.IdFasilitas = c.IdFasilitas
    WHERE c.IdPelanggan = ?
    GROUP BY c.IdPenyedia, c.IdFasilitas
    ORDER BY LastTime DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $IdPelanggan);
$stmt->execute();
$riwayat = $stmt->get_result();

$sqlUpdate = "UPDATE chat SET Status = 'dibaca' 
              WHERE IdPelanggan = ? AND Pengirim = 'penyedia' AND Status = 'belum'";
$stmtUpdate = $conn->prepare($sqlUpdate);
$stmtUpdate->bind_param("i", $IdPelanggan);
$stmtUpdate->execute();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Semua Chat</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: sans-serif; background: #f4f6fb; margin: 0; }
        .chat-list { max-width: 800px; margin: 30px auto; background: white; border-radius: 14px; padding: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.08); }

        .chat-header {
            position: relative;
            text-align: center;
            margin-bottom: 25px;
            padding-top: 5px;
        }

        .chat-header h2 {
            font-size: 20px;
            margin: 0;
            padding: 0;
            display: inline-block;
        }

        .back-arrow {
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            text-decoration: none;
            font-size: 20px;
            color: #0056b3; /* warna biru */
        }

        .chat-item { display: flex; align-items: center; padding: 12px; border-bottom: 1px solid #eee; text-decoration: none; color: #333; }
        .chat-item:hover { background: #f9f9f9; }
        .chat-item img { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; margin-right: 16px; }
        .chat-content { flex: 1; }
        .chat-content strong { font-size: 16px; display: block; }
        .chat-content small { color: #666; display: block; margin-top: 4px; }
        .chat-time { font-size: 12px; color: #888; margin-left: 10px; white-space: nowrap; }
        .chat-foto-fasilitas { width: 60px; height: 40px; object-fit: cover; border-radius: 6px; margin-left: 10px; }
    </style>
</head>
<body>

<div class="chat-list">
    <div class="chat-header">
        <a href="javascript:history.back()" class="back-arrow"><i class="fa fa-arrow-left"></i></a>
        <h2><i class="fa-solid fa-comments"></i> Chat Masuk</h2>
    </div>

    <?php while ($row = $riwayat->fetch_assoc()): ?>
        <a class="chat-item" href="chat.php?IdPenyedia=<?= $row['IdPenyedia'] ?>&IdFasilitas=<?= $row['IdFasilitas'] ?>">
            <img src="<?= htmlspecialchars($row['FotoProfil'] ?? 'default-profile.png') ?>">
            <div class="chat-content">
                <strong><?= htmlspecialchars($row['Username']) ?> - <?= htmlspecialchars($row['NamaFasilitas']) ?></strong>
                <small><?= htmlspecialchars($row['LastMessage']) ?></small>
            </div>
            <small class="chat-time"><?= date("d M H:i", strtotime($row['LastTime'])) ?></small>
            <img class="chat-foto-fasilitas" src="<?= htmlspecialchars($row['FotoFasilitas'] ?? 'default.jpg') ?>">
        </a>
    <?php endwhile; ?>
</div>
</body>
</html>
