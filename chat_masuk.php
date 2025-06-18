<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['IdUser'])) {
    header("Location: login.php");
    exit;
}

$IdChat = $_GET['IdChat'] ?? null;
if (!$IdChat) {
    die("Chat tidak ditemukan.");
}

// Ambil user login (penyedia)
$IdUser = $_SESSION['IdUser'];
$dataPenyedia = $conn->query("SELECT * FROM penyediafasilitas WHERE IdUser = $IdUser")->fetch_assoc();
if (!$dataPenyedia) die("Akses ditolak");

$IdPenyedia = $dataPenyedia['IdPenyedia'];

// Ambil data chat berdasarkan IdChat
$chatInfo = $conn->query("SELECT * FROM chat WHERE IdChat = $IdChat")->fetch_assoc();
if (!$chatInfo) die("Data chat tidak ditemukan");

$IdPelanggan = $chatInfo['IdPelanggan'];
$IdFasilitas = $chatInfo['IdFasilitas'];

// Validasi: pastikan penyedia yang login memang pihak dalam chat ini
if ($chatInfo['IdPenyedia'] != $IdPenyedia) die("Anda tidak memiliki akses ke chat ini.");
// Tandai semua pesan dari pelanggan sebagai 'dibaca' jika belum
$conn->query("
    UPDATE chat 
    SET Status = 'dibaca' 
    WHERE IdPenyedia = $IdPenyedia 
      AND IdPelanggan = $IdPelanggan 
      AND IdFasilitas = $IdFasilitas 
      AND Pengirim = 'pelanggan' 
      AND Status = 'belum'
");

// Ambil data fasilitas
$fasilitas = $conn->query("SELECT * FROM fasilitas WHERE IdFasilitas = $IdFasilitas")->fetch_assoc();

// Ambil data user lawan chat (pelanggan)
$pelanggan = $conn->query("
    SELECT u.Username, u.FotoProfil, u.LastActive 
    FROM pelanggan p 
    JOIN user u ON p.IdUser = u.IdUser 
    WHERE p.IdPelanggan = $IdPelanggan
")->fetch_assoc();

// Fungsi format LastActive
function formatLastActive($datetime) {
    if (!$datetime) return "Tidak diketahui";
    $diff = time() - strtotime($datetime);
    if ($diff < 300) return "Online";
    elseif ($diff < 3600) return "Aktif " . floor($diff / 60) . " menit lalu";
    elseif ($diff < 86400) return "Aktif " . floor($diff / 3600) . " jam lalu";
    else return "Aktif terakhir " . date("d M Y H:i", strtotime($datetime));
}

// Kirim pesan jika ada input
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['Pesan'])) {
    $Pesan = $_POST['Pesan'];
    $Pengirim = 'penyedia';
    $stmt = $conn->prepare("INSERT INTO chat (IdPenyedia, IdPelanggan, IdFasilitas, Pesan, Pengirim) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiss", $IdPenyedia, $IdPelanggan, $IdFasilitas, $Pesan, $Pengirim);
    $stmt->execute();
}

// Ambil seluruh isi chat
$stmt = $conn->prepare("SELECT * FROM chat WHERE IdPenyedia = ? AND IdPelanggan = ? AND IdFasilitas = ? ORDER BY Waktu ASC");
$stmt->bind_param("iii", $IdPenyedia, $IdPelanggan, $IdFasilitas);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Chat Masuk</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="chat_penyedia.css">
</head>
<body>

<!-- <div class="vooking-header">
    <div class="logo-title">
        <a href="javascript:history.back()"><i class="fa-solid fa-arrow-left back-home-icon"></i></a>
        <img src="Vicon.png" class="logo-img" />
        <h1>VOOKING</h1>
    </div>
    <div class="icon-section">
        <i class="fa fa-bell"></i>
        <i class="fa-solid fa-gear"></i>
        <a href="profil_penyedia.php"><i class="fa fa-user-circle"></i></a>
    </div>
</div> -->

<div class="chat-container">
    <!-- Info lawan chat (pelanggan) -->
    <div class="pelanggan-info">
        <a href="javascript:history.back()" class="back-icon">
            <i class="fa-solid fa-arrow-left back-home-icon"></i>
        </a>
        <img src="<?= htmlspecialchars($pelanggan['FotoProfil'] ?? 'default-profile.png') ?>" alt="Pelanggan">
        <div>
            <strong><?= htmlspecialchars($pelanggan['Username']) ?></strong><br>
            <small><?= formatLastActive($pelanggan['LastActive']) ?></small>
        </div>
    </div>

    <!-- Info fasilitas -->
    <div class="fasilitas-info">
        <img src="<?= htmlspecialchars($fasilitas['FotoFasilitas'] ?? 'default.jpg') ?>" alt="Fasilitas">
        <div>
            <strong><?= htmlspecialchars($fasilitas['NamaFasilitas']) ?></strong><br>
            <small><?= htmlspecialchars($fasilitas['Lokasi']) ?></small>
        </div>
    </div>

    <!-- Chat -->
    <div class="chat-box">
        <?php
        $lastDate = '';
        while ($row = $result->fetch_assoc()) :
            $messageDate = date('d M Y', strtotime($row['Waktu']));
            $messageTime = date('H:i', strtotime($row['Waktu']));
            if ($messageDate !== $lastDate) {
                echo '<div class="tanggal-chat">' . $messageDate . '</div>';
                $lastDate = $messageDate;
            }
        ?>
            <div class="pesan <?= $row['Pengirim'] == 'pelanggan' ? 'pelanggan' : 'penyedia' ?>">
                <?= htmlspecialchars($row['Pesan']) ?>
                <small><?= $messageTime ?></small>
            </div>
        <?php endwhile; ?>
    </div>

    <form method="post" class="form-chat">
        <textarea name="Pesan" required placeholder="Tulis pesan..."></textarea>
        <button type="submit">Kirim</button>
    </form>
</div>
<script>
    const form = document.querySelector('.form-chat');
    const textarea = form.querySelector('textarea');
    const chatBox = document.querySelector('.chat-box');

    form.addEventListener('submit', function(e) {
        e.preventDefault(); // Mencegah reload
        const pesan = textarea.value.trim();
        if (pesan === '') return;

        const formData = new FormData();
        formData.append('Pesan', pesan);

        fetch(location.href, {
            method: 'POST',
            body: formData
        })
        .then(res => res.ok ? res.text() : Promise.reject('Gagal kirim'))
        .then(() => {
            textarea.value = '';
            loadChat(); // Refresh chat setelah kirim
        })
        .catch(err => console.error(err));
    });

    function loadChat() {
        fetch(location.href + '&ajax=1') // Tambahan URL agar dibedakan
            .then(res => res.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newChatBox = doc.querySelector('.chat-box');
                if (newChatBox) {
                    chatBox.innerHTML = newChatBox.innerHTML;
                    chatBox.scrollTop = chatBox.scrollHeight; // auto scroll
                }
            });
    }

    setInterval(loadChat, 2000); // Refresh setiap 2 detik
    loadChat(); // Load pertama kali
</script>
</body>
</html>
