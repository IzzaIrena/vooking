<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['IdUser']) || $_SESSION['RoleAktif'] !== 'penyedia') {
    header("Location: login_register.php");
    exit();
}

if (isset($_GET['id']) && isset($_GET['status'])) {
    $IdFasilitas = intval($_GET['id']);
    $statusBaru = ($_GET['status'] === 'aktif') ? 'aktif' : 'non_aktif';

    // Cek apakah fasilitas milik penyedia yang login dan ambil status saat ini
    $stmt = $conn->prepare("
        SELECT f.Status 
        FROM fasilitas f 
        JOIN penyediafasilitas p ON f.IdPenyedia = p.IdPenyedia 
        WHERE f.IdFasilitas = ? AND p.IdUser = ?
    ");
    $stmt->bind_param("ii", $IdFasilitas, $_SESSION['IdUser']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        if ($row['Status'] === 'non_aktif_admin') {
            // Tidak boleh diubah jika dinonaktifkan oleh admin
            header("Location: edit_fasilitas.php?id=" . $IdFasilitas . "&error=admin_block");
            exit();
        }

        // Lanjutkan update jika tidak diblokir admin
        $stmtUpdate = $conn->prepare("UPDATE fasilitas SET Status = ? WHERE IdFasilitas = ?");
        $stmtUpdate->bind_param("si", $statusBaru, $IdFasilitas);
        if ($stmtUpdate->execute()) {
            header("Location: edit_fasilitas.php?id=" . $IdFasilitas . "&success=status_updated");
            exit();
        } else {
            echo "Gagal mengubah status fasilitas.";
        }
    } else {
        echo "Anda tidak memiliki akses ke fasilitas ini.";
    }
} else {
    echo "Parameter tidak lengkap.";
}
?>
