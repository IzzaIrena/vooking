<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['IdUser'])) {
    echo "Anda belum login.";
    exit();
}

$idUser = $_SESSION['IdUser'];
$queryUser = "SELECT * FROM user WHERE IdUser = $idUser LIMIT 1";
$resultUser = mysqli_query($conn, $queryUser);
$user = mysqli_fetch_assoc($resultUser);

if (!$user) {
    echo "Data pengguna tidak ditemukan.";
    exit();
}

$formData = [
    'nama' => $user['Username'],
    'nomor_hp' => $user['NoHp'],
    'id_unit' => '',
    'nomor_ruang' => '',
    'tanggal' => '',
    'jam' => [],
];

$errorMsg = '';

if (isset($_SESSION['booking_form_data'])) {
    $formData = array_merge($formData, $_SESSION['booking_form_data']);
    unset($_SESSION['booking_form_data']);
}

if (isset($_SESSION['booking_error'])) {
    $errorMsg = $_SESSION['booking_error'];
    unset($_SESSION['booking_error']);
}

$tanggalErrors = $_SESSION['tanggal_errors'] ?? [];
unset($_SESSION['tanggal_errors']);

if (!isset($_GET['id'])) {
    echo "ID fasilitas tidak ditemukan.";
    exit();
}

$idFasilitas = (int)$_GET['id'];

$queryFasilitas = "SELECT * FROM fasilitas WHERE IdFasilitas = $idFasilitas AND Status = 'aktif'";
$resultFasilitas = mysqli_query($conn, $queryFasilitas);
$fasilitas = mysqli_fetch_assoc($resultFasilitas);

if (!$fasilitas) {
    echo "Fasilitas tidak ditemukan atau tidak aktif.";
    exit();
}

$queryUnit = "SELECT * FROM unit WHERE IdFasilitas = $idFasilitas";
$resultUnit = mysqli_query($conn, $queryUnit);
$units = [];
while ($row = mysqli_fetch_assoc($resultUnit)) {
    $units[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Booking Fasilitas - VOOKING</title>
    <link rel="stylesheet" href="home.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
<div class="wrapper">
    <div class="vooking-header">
        <div class="logo-title">
            <a href="lihat_fasilitas.php?id=<?= $idFasilitas ?>"><i class="fa-solid fa-arrow-left back-home-icon"></i></a>
            <img src="Vicon.png" class="logo-img" />
            <h1>VOOKING</h1>
        </div>
    </div>

    <div class="detail-container booking-container">
        <h2>Form Booking: <?= htmlspecialchars($fasilitas['NamaFasilitas']) ?></h2>
        <form action="proses_booking.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id_fasilitas" value="<?= $idFasilitas ?>">
            <input type="hidden" name="nama" value="<?= htmlspecialchars($user['Username']) ?>">
            <input type="hidden" name="nomor_hp" value="<?= htmlspecialchars($user['NoHp']) ?>">
            <p><strong>Nama:</strong> <?= htmlspecialchars($user['Username']) ?></p>
            <p><strong>Nomor HP:</strong> <?= htmlspecialchars($user['NoHp']) ?></p>

            <label for="id_unit">Tipe Unit:</label>
            <select name="id_unit" id="id_unit" required>
                <option value="">-- Pilih Tipe Unit --</option>
                <?php foreach ($units as $unit): ?>
                    <option value="<?= $unit['IdUnit'] ?>" data-jumlah="<?= $unit['JumlahRuang'] ?>" data-dp="<?= $unit['DP'] ?>" <?= $formData['id_unit'] == $unit['IdUnit'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($unit['NamaTipeUnit']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="nomor_ruang">Nomor Ruangan:</label>
            <select name="nomor_ruang" id="nomor_ruang" required>
                <option value="">-- Pilih Nomor Ruangan --</option>
                <?php
                if ($formData['id_unit']) {
                    $jumlahRuang = 0;
                    foreach ($units as $unit) {
                        if ($unit['IdUnit'] == $formData['id_unit']) {
                            $jumlahRuang = $unit['JumlahRuang'];
                            break;
                        }
                    }
                    for ($i = 1; $i <= $jumlahRuang; $i++) {
                        $selected = ($formData['nomor_ruang'] == $i) ? 'selected' : '';
                        echo "<option value=\"$i\" $selected>$i</option>";
                    }
                }
                ?>
            </select>

            <?php if ($fasilitas['TipeBooking'] === 'jam'): ?>
                <input type="hidden" name="tanggal" id="tanggal" value="<?= date('Y-m-d') ?>">
            <?php else: ?>
                <label for="tanggal">Tanggal Booking:</label>
                <div id="tanggal-wrapper">
                    <?php
                    $tanggalList = $formData['tanggal'] ?? [''];
                    if (!is_array($tanggalList)) {
                        $tanggalList = [$tanggalList];
                    }
                    foreach ($tanggalList as $index => $tanggalValue):
                        $error = '';
                        foreach ($tanggalErrors as $errTanggal => $msg) {
                            if ($tanggalValue === $errTanggal) {
                                $error = $msg;
                                break;
                            }
                        }
                    ?>
                    <div class="tanggal-group">
                        <input type="date" name="tanggal[]" value="<?= htmlspecialchars($tanggalValue) ?>" required>
                        <?php if ($index === 0): ?>
                            <button type="button" class="btn-tambah-tanggal">+</button>
                        <?php else: ?>
                            <button type="button" class="btn-hapus-tanggal">-</button>
                        <?php endif; ?>
                        <?php if ($error): ?>
                            <div class="error-message"><?= $error ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($errorMsg): ?>
                    <div class="error-message"><?= htmlspecialchars($errorMsg) ?></div>
                <?php endif; ?>
            <?php endif; ?>

            <div id="dp-info" style="margin-top: 10px; font-weight: bold; color: 333; display: none;">
                DP: Rp <span id="dp-amount"></span>
            </div>

            <div id="jam-container" style="<?= $fasilitas['TipeBooking'] === 'jam' ? '' : 'display:none;' ?>"></div>

            <div id="total-dp" style="margin-top:10px; font-weight:bold; color:green;"></div>

            <label for="bukti_dp">Upload Bukti DP:</label>
            <input type="file" name="bukti_dp" id="bukti_dp" accept="image/*" required>

            <button type="submit">Kirim Booking</button>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    var tipeBooking = "<?= $fasilitas['TipeBooking'] ?>";
    let dpPerJam = 0;
    $('#total-dp').hide();
    $('#dp-info').hide();

    function tampilkanDP() {
        dpPerJam = $('#id_unit option:selected').data('dp');
        if (dpPerJam) {
            $('#dp-amount').text(dpPerJam.toLocaleString('id-ID'));
            $('#dp-info').show();
        } else {
            $('#dp-info').hide();
        }
    }

    $('#id_unit').on('change', function() {
        var jumlah = $('#id_unit option:selected').data('jumlah');
        dpPerJam = $('#id_unit option:selected').data('dp');
        var options = '<option value="">-- Pilih Nomor Ruangan --</option>';
        for (var i = 1; i <= jumlah; i++) {
            options += '<option value="' + i + '">' + i + '</option>';
        }
        $('#nomor_ruang').html(options);
        $('#total-dp').hide();
        $('#jam-container').html("");
        $('#dp-info').hide();
    });

    $('#nomor_ruang').on('change', function() {
        if ($(this).val()) {
            tampilkanDP();
        } else {
            $('#dp-info').hide();
        }
    });

    function hitungDP() {
        const jumlahJam = $('input[name="jam[]"]:checked').length;
        if (jumlahJam > 0) {
            const totalDP = jumlahJam * dpPerJam;
            $('#total-dp').text("Total DP: Rp " + totalDP.toLocaleString('id-ID')).show();
        } else {
            $('#total-dp').hide();
        }
    }

    $(document).on('change', 'input[name="jam[]"]', function() {
        hitungDP();
    });

    if (tipeBooking === 'jam') {
        function loadJamTersedia() {
            var idFasilitas = <?= $idFasilitas ?>;
            var idUnit = $('#id_unit').val();
            var nomorRuang = $('#nomor_ruang').val();
            var tanggal = $('#tanggal').val();

            if (idUnit && nomorRuang && tanggal) {
                $.ajax({
                    url: 'get_jam_tersedia.php',
                    method: 'POST',
                    data: {
                        id_fasilitas: idFasilitas,
                        id_unit: idUnit,
                        nomor_ruang: nomorRuang,
                        tanggal: tanggal
                    },
                    success: function(response) {
                        $('#jam-container').html(response);
                        hitungDP();
                    }
                });
            } else {
                $('#jam-container').html('');
            }
        }

        $('#nomor_ruang, #tanggal').on('change', loadJamTersedia);
    }

    // Booking Harian - Tambah dan Hapus Tanggal
    function hitungDPHarian() {
        if (tipeBooking === 'harian') {
            let totalTanggal = $('input[name="tanggal[]"]').length;
            if (dpPerJam && totalTanggal > 0) {
                const totalDP = dpPerJam * totalTanggal;
                $('#total-dp').text("Total DP: Rp " + totalDP.toLocaleString('id-ID')).show();
            } else {
                $('#total-dp').hide();
            }
        }
    }

    // Tambah tanggal
    $(document).on('click', '.btn-tambah-tanggal', function() {
        const newTanggal = `
            <div class="tanggal-group">
                <input type="date" name="tanggal[]" required>
                <button type="button" class="btn-hapus-tanggal btn btn-danger btn-sm">-</button>
            </div>`;
        $('#tanggal-wrapper').append(newTanggal);
        hitungDPHarian();
    });

    // Hapus tanggal
    $(document).on('click', '.btn-hapus-tanggal', function() {
        $(this).closest('.tanggal-group').remove();
        hitungDPHarian();
    });

    // Tanggal diubah
    $(document).on('change', 'input[name="tanggal[]"]', function() {
        hitungDPHarian();
    });
});
</script>
</body>
</html>
