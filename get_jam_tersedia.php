<?php
include 'koneksi.php';
date_default_timezone_set('Asia/Makassar'); // Pastikan timezone sesuai Makassar

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idFasilitas = (int)$_POST['id_fasilitas'];
    $idUnit = (int)$_POST['id_unit'];
    $nomorRuang = (int)$_POST['nomor_ruang'];
    $tanggal = $_POST['tanggal'];

    // Ambil jam operasional fasilitas
    $queryFasilitas = "SELECT JamBuka, JamTutup FROM fasilitas WHERE IdFasilitas = ?";
    $stmtFasilitas = mysqli_prepare($conn, $queryFasilitas);
    mysqli_stmt_bind_param($stmtFasilitas, "i", $idFasilitas);
    mysqli_stmt_execute($stmtFasilitas);
    $resultFasilitas = mysqli_stmt_get_result($stmtFasilitas);
    $fasilitas = mysqli_fetch_assoc($resultFasilitas);

    $jamBuka = $fasilitas['JamBuka'];
    $jamTutup = $fasilitas['JamTutup'];

    // Ambil jam yang sudah dibooking
    $queryBooking = "SELECT Jam FROM booking WHERE IdUnit = ? AND NomorRuang = ? AND Tanggal = ? AND StatusBooking IN ('menunggu', 'diterima')";
    $stmtBooking = mysqli_prepare($conn, $queryBooking);
    mysqli_stmt_bind_param($stmtBooking, "iis", $idUnit, $nomorRuang, $tanggal);
    mysqli_stmt_execute($stmtBooking);
    $resultBooking = mysqli_stmt_get_result($stmtBooking);
    $bookedSlots = [];
    while ($row = mysqli_fetch_assoc($resultBooking)) {
        $jamArray = explode(',', $row['Jam']);
        foreach ($jamArray as $jam) {
            $bookedSlots[] = trim($jam);
        }
    }

    // Generate semua slot 1 jam
    $start = strtotime($tanggal . ' ' . $jamBuka);
    $end = strtotime($tanggal . ' ' . $jamTutup);
    $slots = [];
    while ($start + 3600 <= $end) {
        $slot = date('H:i', $start) . '-' . date('H:i', $start + 3600);
        if (!in_array($slot, $bookedSlots)) {
            $slots[] = $slot;
        }
        $start += 3600;
    }

    // Filter slot yang sudah lewat jika tanggal hari ini
    $finalSlots = [];
    $today = date('Y-m-d');
    $now = strtotime('now');
    foreach ($slots as $slot) {
        list($startTime, $endTime) = explode('-', $slot);
        $startSlotTime = strtotime($tanggal . ' ' . $startTime);

        if ($tanggal > $today || $startSlotTime > $now) {
            $finalSlots[] = $slot;
        }
    }

    if (!empty($finalSlots)) {
        echo '<label>Jam Booking:</label><br>';
        foreach ($finalSlots as $slot) {
            echo '<input type="checkbox" name="jam[]" value="' . $slot . '"> ' . $slot . '<br>';
        }
    } else {
        echo 'Tidak ada jam tersedia untuk tanggal dan ruangan ini.';
    }
}
?>
