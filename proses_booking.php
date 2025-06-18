<?php
session_start();
include 'koneksi.php';
date_default_timezone_set('Asia/Makassar');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idFasilitas = $_POST['id_fasilitas'];
    $idUnit = $_POST['id_unit'];
    $nomorRuang = $_POST['nomor_ruang'];
    $nama = $_POST['nama'];
    $nomorHp = $_POST['nomor_hp'];

    if (!isset($_SESSION['IdUser'])) {
        echo "Anda harus login dulu!";
        exit;
    }

    $idUser = $_SESSION['IdUser'];

    $queryPelanggan = "SELECT IdPelanggan FROM pelanggan WHERE IdUser = ?";
    $stmtPelanggan = mysqli_prepare($conn, $queryPelanggan);
    mysqli_stmt_bind_param($stmtPelanggan, "i", $idUser);
    mysqli_stmt_execute($stmtPelanggan);
    $resultPelanggan = mysqli_stmt_get_result($stmtPelanggan);

    if ($rowPelanggan = mysqli_fetch_assoc($resultPelanggan)) {
        $idPelanggan = $rowPelanggan['IdPelanggan'];
    } else {
        echo "IdPelanggan tidak ditemukan. Silakan login ulang.";
        exit;
    }

    // Ambil tipe booking
    $queryTipe = "SELECT TipeBooking FROM fasilitas WHERE IdFasilitas = ?";
    $stmtTipe = mysqli_prepare($conn, $queryTipe);
    mysqli_stmt_bind_param($stmtTipe, "i", $idFasilitas);
    mysqli_stmt_execute($stmtTipe);
    $resultTipe = mysqli_stmt_get_result($stmtTipe);
    $tipe = mysqli_fetch_assoc($resultTipe);

    // Ambil IdUser pemilik fasilitas
    $queryPemilik = "SELECT p.IdUser FROM fasilitas f JOIN penyediafasilitas p ON f.IdPenyedia = p.IdPenyedia WHERE f.IdFasilitas = ?";
    $stmtPemilik = mysqli_prepare($conn, $queryPemilik);
    mysqli_stmt_bind_param($stmtPemilik, "i", $idFasilitas);
    mysqli_stmt_execute($stmtPemilik);
    $resultPemilik = mysqli_stmt_get_result($stmtPemilik);
    $dataPemilik = mysqli_fetch_assoc($resultPemilik);

    if (!$dataPemilik) {
        echo "Pemilik fasilitas tidak ditemukan.";
        exit;
    }
    $idPemilik = $dataPemilik['IdUser'];

    // Upload bukti DP
    $buktiDPName = $_FILES['bukti_dp']['name'];
    $buktiDPTmp = $_FILES['bukti_dp']['tmp_name'];
    $uploadDir = 'uploads/';
    $uploadPath = $uploadDir . time() . '_' . basename($buktiDPName);

    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    if (!move_uploaded_file($buktiDPTmp, $uploadPath)) {
        echo "Upload bukti DP gagal.";
        exit;
    }

    // Ambil DP untuk hari
    $queryDP = "SELECT DP FROM unit WHERE IdUnit = ?";
    $stmtDP = mysqli_prepare($conn, $queryDP);
    mysqli_stmt_bind_param($stmtDP, "i", $idUnit);
    mysqli_stmt_execute($stmtDP);
    $resultDP = mysqli_stmt_get_result($stmtDP);
    $dataDP = mysqli_fetch_assoc($resultDP);
    $dpPerHari = $dataDP ? $dataDP['DP'] : 0;

    // Ambil DP untuk jam
    $queryDP = "SELECT DP FROM unit WHERE IdUnit = ?";
    $stmtDP = mysqli_prepare($conn, $queryDP);
    mysqli_stmt_bind_param($stmtDP, "i", $idUnit);
    mysqli_stmt_execute($stmtDP);
    $resultDP = mysqli_stmt_get_result($stmtDP);
    $dataDP = mysqli_fetch_assoc($resultDP);
    $dpPerSatuan = $dataDP ? $dataDP['DP'] : 0;

    // Booking Harian
    if ($tipe && $tipe['TipeBooking'] === 'harian') {
        $tanggalArray = $_POST['tanggal'];
        $tanggalBermasalah = [];

        // Ambil semua data booking aktif untuk unit dan ruang ini
        $queryBooking = "SELECT Tanggal FROM booking 
                        WHERE IdUnit = ? AND NomorRuang = ? 
                        AND StatusBooking IN ('menunggu', 'diterima')";
        $stmtBooking = mysqli_prepare($conn, $queryBooking);
        mysqli_stmt_bind_param($stmtBooking, "ii", $idUnit, $nomorRuang);
        mysqli_stmt_execute($stmtBooking);
        $resultBooking = mysqli_stmt_get_result($stmtBooking);

        // Ambil semua tanggal yang sudah dibooking
        $tanggalTerpakai = [];
        while ($row = mysqli_fetch_assoc($resultBooking)) {
            $listTanggal = explode(",", $row['Tanggal']);
            foreach ($listTanggal as $t) {
                $trimmed = trim($t);
                if (!in_array($trimmed, $tanggalTerpakai)) {
                    $tanggalTerpakai[] = $trimmed;
                }
            }
        }

        // Bandingkan dengan tanggal yang diminta user
        foreach ($tanggalArray as $tgl) {
            if (in_array($tgl, $tanggalTerpakai)) {
                $tanggalBermasalah[] = $tgl;
            }
        }


        if (!empty($tanggalBermasalah)) {
            $_SESSION['booking_form_data'] = $_POST;
            $tanggalErrors = [];
            foreach ($tanggalBermasalah as $tgl) {
                $tanggalErrors[$tgl] = "Tanggal $tgl sudah dibooking.";
            }
            $_SESSION['tanggal_errors'] = $tanggalErrors;
            header("Location: booking_fasilitas.php?id=$idFasilitas");
            exit; // <- ini penting untuk menghentikan eksekusi
        }

        // Gabungkan tanggal jadi satu string
        $tanggalGabung = implode(',', array_map(function($tgl) use ($conn) {
            return mysqli_real_escape_string($conn, $tgl);
        }, $tanggalArray));

        $totalDP = $dpPerHari * count($tanggalArray); // total DP untuk semua hari

        $query = "INSERT INTO booking (IdPelanggan, Nama, IdUnit, NomorRuang, Tanggal, Jam, NomorHp, StatusBooking, BuktiDP, TotalDP)
                VALUES (?, ?, ?, ?, ?, '', ?, 'menunggu', ?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'isiisssd',
            $idPelanggan, $nama, $idUnit, $nomorRuang, $tanggalGabung, $nomorHp, $uploadPath, $totalDP);
        mysqli_stmt_execute($stmt);

        // Tambahkan notifikasi ke pemilik
        $isiPesan = "Ada permintaan booking baru untuk fasilitas Anda.";
        $tipeNotifikasi = 'booking';
        $tanggal = date('Y-m-d H:i:s');
        $statusBaca = 0;

        $queryNotif = "INSERT INTO notifikasi (IdUser, IsiPesan, Tipe, Tanggal, StatusBaca) VALUES (?, ?, ?, ?, ?)";
        $stmtNotif = mysqli_prepare($conn, $queryNotif);
        mysqli_stmt_bind_param($stmtNotif, "isssi", $idPemilik, $isiPesan, $tipeNotifikasi, $tanggal, $statusBaca);
        mysqli_stmt_execute($stmtNotif);        

    // Booking per jam
    } elseif ($tipe && $tipe['TipeBooking'] === 'jam') {
        $tanggal = date('Y-m-d');
        $jamArray = $_POST['jam'];
        $jamGabung = implode(',', $jamArray);
        $jumlahJam = count($jamArray);

        // Cek konflik jam
        $queryCek = "SELECT 1 FROM booking WHERE IdUnit = ? AND NomorRuang = ? AND Tanggal = ?";
        $stmtCek = mysqli_prepare($conn, $queryCek);
        mysqli_stmt_bind_param($stmtCek, "iis", $idUnit, $nomorRuang, $tanggal);
        mysqli_stmt_execute($stmtCek);
        $resultCek = mysqli_stmt_get_result($stmtCek);
        $jamBentrok = false;

        while ($row = mysqli_fetch_assoc($resultCek)) {
            $bookedJam = explode(',', $row['Jam'] ?? '');
            if (array_intersect($bookedJam, $jamArray)) {
                $jamBentrok = true;
                break;
            }
        }

        if ($jamBentrok) {
            $_SESSION['jam_errors'] = "Beberapa jam sudah dibooking.";
            $_SESSION['booking_form_data'] = $_POST;
            header("Location: booking_fasilitas.php?id=$idFasilitas");
            exit;
        }

        $totalDP = $dpPerSatuan * $jumlahJam;

        $query = "INSERT INTO booking (IdPelanggan, Nama, IdUnit, NomorRuang, Tanggal, Jam, NomorHp, StatusBooking, BuktiDP, TotalDP)
                  VALUES (?, ?, ?, ?, ?, ?, ?, 'menunggu', ?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'isiissssd',
            $idPelanggan, $nama, $idUnit, $nomorRuang, $tanggal, $jamGabung, $nomorHp, $uploadPath, $totalDP);
        mysqli_stmt_execute($stmt);

        // Tambahkan notifikasi ke pemilik
        $isiPesan = "Ada permintaan booking baru untuk fasilitas Anda.";
        $tipeNotifikasi = 'booking';
        $tanggal = date('Y-m-d H:i:s');
        $statusBaca = 0;

        $queryNotif = "INSERT INTO notifikasi (IdUser, IsiPesan, Tipe, Tanggal, StatusBaca) VALUES (?, ?, ?, ?, ?)";
        $stmtNotif = mysqli_prepare($conn, $queryNotif);
        mysqli_stmt_bind_param($stmtNotif, "isssi", $idPemilik, $isiPesan, $tipeNotifikasi, $tanggal, $statusBaca);
        mysqli_stmt_execute($stmtNotif);
    }

    echo "<script>alert('Booking berhasil dikirim!'); window.location.href='lihat_fasilitas.php?id=$idFasilitas';</script>";
} else {
    echo "Akses tidak valid.";
}
?>
