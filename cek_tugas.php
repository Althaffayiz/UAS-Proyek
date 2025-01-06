<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: index.php");
    exit;
}

require_once "db.php";

$id_tugas = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Ambil data tugas
$query_tugas = "SELECT judul_tugas, tenggat_waktu, kode_tugas FROM tbl_tugas WHERE id_tugas = ?";
$stmt_tugas = $conn->prepare($query_tugas);
$stmt_tugas->bind_param("i", $id_tugas);
$stmt_tugas->execute();
$result_tugas = $stmt_tugas->get_result();
$tugas = $result_tugas->fetch_assoc();
$stmt_tugas->close();

// Validasi tugas
if (!$tugas) {
    $_SESSION['message'] = "Tugas tidak ditemukan.";
    header("Location: admin_dashboard.php");
    exit;
}

// Ambil sesi tugas
$query_sesi = "SELECT id_sesi, tenggat_sesi, judul_sesi, deskripsi_sesi FROM tbl_sesi WHERE id_tugas = ? ORDER BY tenggat_sesi";
$stmt_sesi = $conn->prepare($query_sesi);
$stmt_sesi->bind_param("i", $id_tugas);
$stmt_sesi->execute();
$result_sesi = $stmt_sesi->get_result();

// Ambil data upload tugas per sesi
$query_uploads = "
    SELECT u.id_upload, u.username_user, u.nama_file, u.tanggal_upload, u.id_sesi, u.notifikasi, us.nim
    FROM tbl_uploads u
    JOIN tbl_user us ON u.username_user = us.username
    WHERE u.id_tugas = ?
    ORDER BY u.tanggal_upload DESC
";
$stmt_uploads = $conn->prepare($query_uploads);
$stmt_uploads->bind_param("i", $id_tugas);
$stmt_uploads->execute();
$result_uploads = $stmt_uploads->get_result();
$uploads = [];
while ($upload = $result_uploads->fetch_assoc()) {
    $uploads[$upload['id_sesi']][] = $upload;
}
$stmt_uploads->close();

// Update notifikasi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_upload'], $_POST['notifikasi'])) {
    $id_upload = intval($_POST['id_upload']);
    $notifikasi = $_POST['notifikasi'];

    $query_update = "UPDATE tbl_uploads SET notifikasi = ? WHERE id_upload = ?";
    $stmt_update = $conn->prepare($query_update);
    $stmt_update->bind_param("si", $notifikasi, $id_upload);
    $stmt_update->execute();
    $stmt_update->close();

    header("Location: cek_tugas.php?id=$id_tugas");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cek Tugas</title>
</head>
<body>
    <h1>Daftar Pengguna yang Mengunggah Tugas</h1>
    <h2><?= htmlspecialchars($tugas['judul_tugas']) ?></h2>
    <h3>Tenggat Waktu Tugas: <?= htmlspecialchars($tugas['tenggat_waktu']) ?></h3>
    <h4>Kode Tugas: <?= htmlspecialchars($tugas['kode_tugas']) ?></h4>

    <!-- Tampilkan Sesi jika ada -->
    <?php if ($result_sesi->num_rows > 0): ?>
        <?php while ($sesi = $result_sesi->fetch_assoc()): ?>
            <h3>Judul Sesi: <?= htmlspecialchars($sesi['judul_sesi']) ?></h3>
            <p>Deskripsi Sesi: <?= htmlspecialchars($sesi['deskripsi_sesi']) ?></p>
            <p>Tenggat Waktu Sesi: <?= htmlspecialchars($sesi['tenggat_sesi']) ?></p>
        
            <table border="1" cellpadding="10">
                <thead>
                    <tr>
                        <th>NIM</th>
                        <th>Username</th>
                        <th>File</th>
                        <th>Status</th>
                        <th>Notifikasi</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (isset($uploads[$sesi['id_sesi']])): ?>
                        <?php foreach ($uploads[$sesi['id_sesi']] as $upload): ?>
                            <tr>
                                <td><?= htmlspecialchars($upload['nim']) ?></td>
                                <td><?= htmlspecialchars($upload['username_user']) ?></td>
                                <td>
                                    <?php if (!empty($upload['nama_file'])): ?>
                                        <a href="download.php?file=<?= urlencode($upload['nama_file']) ?>" download>
                                            <?= htmlspecialchars($upload['nama_file']) ?>
                                        </a>
                                    <?php else: ?>
                                        Belum Upload
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $upload_time = new DateTime($upload['tanggal_upload']);
                                    $deadline_time = new DateTime($sesi['tenggat_sesi']);
                                    if ($upload_time <= $deadline_time): ?>
                                        <span style="color: green;">Tepat Waktu</span>
                                    <?php else: ?>
                                        <span style="color: red;">Terlambat</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($upload['notifikasi'] ?? 'Pending') ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="id_upload" value="<?= $upload['id_upload'] ?>">
                                        <button type="submit" name="notifikasi" value="Accepted" class="accepted">Accepted</button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="id_upload" value="<?= $upload['id_upload'] ?>">
                                        <button type="submit" name="notifikasi" value="Rejected" class="rejected">Rejected</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">Belum ada pengguna yang mengunggah tugas untuk sesi ini.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endwhile; ?>
    <?php else: ?>
        <p>Tugas ini tidak memiliki sesi khusus.</p>
    <?php endif; ?>

    <a href="admin_dashboard.php">Kembali ke Dashboard</a>
</body>
</html>
