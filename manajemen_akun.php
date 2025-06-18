<?php
include 'koneksi.php';
$result = $conn->query("SELECT * FROM user");


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['nonaktifkan'])) {
        $id = $_POST['user_id'];
        $conn->query("UPDATE user SET StatusAkun = 'non_aktif' WHERE IdUser = '$id'");
    } elseif (isset($_POST['aktifkan'])) {
        $id = $_POST['user_id'];
        $conn->query("UPDATE user SET StatusAkun = 'aktif' WHERE IdUser = '$id'");
    }
    header("Location: manajemen_akun.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Akun - Admin Vooking</title>
    <link rel="stylesheet" href="admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<?php include "sidebar_admin.php"; ?>

    <div class="main">
        <div class="header">
            <div class="logo-container">
                <img src="Vicon.png" alt="Logo Vooking" class="logo-img">
                <div class="logo-text">VOOKING</div>
            </div>
        </div>

        <div class="content">
            <h1>Daftar Pengguna</h1>
            <table>
                <thead>
                    <tr>
                        <th>Foto</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>No HP</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td>
                        <?php if (!empty($row['FotoProfil'])): ?>
                            <img src="<?= htmlspecialchars($row['FotoProfil']) ?>" class="profile-pic" alt="Foto Profil">
                        <?php else: ?>
                            <i class="fa-solid fa-user-circle default-user-icon"></i>
                        <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($row['Username']) ?></td>
                        <td><?= htmlspecialchars($row['Email']) ?></td>
                        <td><?= $row['RoleAktif'] ?></td>
                        <td><?= $row['StatusAkun'] ?></td>
                        <td><?= htmlspecialchars($row['NoHp']) ?></td>
                        <td>
                            <form method="POST" style="margin: 0;">
                                <input type="hidden" name="user_id" value="<?= $row['IdUser'] ?>">
                                <?php if ($row['StatusAkun'] === 'aktif'): ?>
                                    <button type="submit" name="nonaktifkan" onclick="return confirm('Yakin ingin menonaktifkan akun ini?')" 
                                        style="background-color: #ff4d4d; color: white; border: none; padding: 6px 12px; border-radius: 6px; min-width: 110px; cursor: pointer; font-size: 13px;">
                                        <i class="fa fa-user-slash"></i> Nonaktifkan
                                    </button>
                                <?php else: ?>
                                    <button type="submit" name="aktifkan" onclick="return confirm('Aktifkan kembali akun ini?')" 
                                        style="background-color: #28a745; color: white; border: none; padding: 6px 12px; border-radius: 6px; min-width: 110px; cursor: pointer; font-size: 13px;">
                                        <i class="fa fa-user-check"></i> Aktifkan
                                    </button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
