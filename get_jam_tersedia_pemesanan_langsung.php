<?php
include 'koneksi.php';
date_default_timezone_set('Asia/Makassar');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idFasilitas = (int)$_POST['id_fasilitas'];
    $idUnit = (int)$_POST['id_unit'];
    $nomorRuang = (int)$_POST['nomor_ruang'];
    $tanggal = $_POST['tanggal'];

    $queryFasilitas = "SELECT JamBuka, JamTutup FROM fasilitas WHERE IdFasilitas = ?";
    $stmtFasilitas = mysqli_prepare($conn, $queryFasilitas);
    mysqli_stmt_bind_param($stmtFasilitas, "i", $idFasilitas);
    mysqli_stmt_execute($stmtFasilitas);
    $resultFasilitas = mysqli_stmt_get_result($stmtFasilitas);
    $fasilitas = mysqli_fetch_assoc($resultFasilitas);

    $jamBuka = $fasilitas['JamBuka'];
    $jamTutup = $fasilitas['JamTutup'];

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

    $finalSlots = [];
    $today = date('Y-m-d');
    $now = time();
    foreach ($slots as $slot) {
        list($startTime, ) = explode('-', $slot);
        $startSlotTime = strtotime($tanggal . ' ' . $startTime);
        if ($tanggal > $today || $startSlotTime > $now) {
            $finalSlots[] = $slot;
        }
    }

    if (!empty($finalSlots)) {
        foreach ($finalSlots as $slot) {
            echo '<label><input type="checkbox" name="jam[]" value="' . $slot . '"> ' . $slot . '</label><br>';
        }
    } else {
        echo '<p>Tidak ada jam tersedia untuk tanggal dan ruangan ini.</p>';
    }
}
?>
