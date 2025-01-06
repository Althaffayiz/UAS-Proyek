<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: index.php");
    exit;
}

require_once "db.php";

// Fungsi untuk menghasilkan kode tugas
function generateKodeTugas() {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ123456789';
    $segments = [];

    for ($i = 0; $i < 3; $i++) {
        $segment = '';
        for ($j = 0; $j < 4; $j++) {
            $segment .= $characters[rand(0, strlen($characters) - 1)];
        }
        $segments[] = $segment;
    }

    return implode('-', $segments); // Format: XXXX-XXXX-XXXX
}

// Fungsi untuk memeriksa apakah data ada dalam POST dan mengembalikan nilai atau default null
function getPostData($key) {
    return isset($_POST[$key]) ? $_POST[$key] : null;
}

// Tambah tugas jika form di-submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_tugas'])) {
    $judul_tugas = $_POST['judul_tugas'];
    $deskripsi = $_POST['deskripsi'];
    $tenggat_waktu = $_POST['tenggat_waktu'];
    $kode_tugas = generateKodeTugas(); // Generate kode tugas

    // Masukkan tugas terlebih dahulu
    $stmt_tugas = $conn->prepare("INSERT INTO tbl_tugas (judul_tugas, deskripsi, tenggat_waktu, kode_tugas) VALUES (?, ?, ?, ?)");
    $stmt_tugas->bind_param("ssss", $judul_tugas, $deskripsi, $tenggat_waktu, $kode_tugas);

    if ($stmt_tugas->execute()) {
        $id_tugas = $stmt_tugas->insert_id; // Ambil ID tugas yang baru ditambahkan
        
        // Menambahkan sesi terkait tugas, hanya jika data valid
        $judul_sesi = getPostData('judul_sesi');
        $deskripsi_sesi = getPostData('deskripsi_sesi');
        $tenggat_sesi = getPostData('tenggat_sesi');

        if ($judul_sesi && $deskripsi_sesi && $tenggat_sesi) {
            $stmt_sesi = $conn->prepare("INSERT INTO tbl_sesi (id_tugas, judul_sesi, deskripsi_sesi, tenggat_sesi) VALUES (?, ?, ?, ?)");
            $stmt_sesi->bind_param("isss", $id_tugas, $judul_sesi, $deskripsi_sesi, $tenggat_sesi);
        }

        $stmt_tugas->close();
    } else {
        $_SESSION['message'] = "Gagal menambahkan tugas.";
    }

    header("Location: admin_dashboard.php");
    exit;
}

// Update tugas jika form di-submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_tugas'])) {
    $id_tugas = intval($_POST['id_tugas']);
    $judul_tugas = $_POST['judul_tugas'];
    $deskripsi = $_POST['deskripsi'];
    $tenggat_waktu = $_POST['tenggat_waktu'];

    // Update data tugas
    $stmt_update_tugas = $conn->prepare("UPDATE tbl_tugas SET judul_tugas = ?, deskripsi = ?, tenggat_waktu = ? WHERE id_tugas = ?");
    $stmt_update_tugas->bind_param("sssi", $judul_tugas, $deskripsi, $tenggat_waktu, $id_tugas);
    $stmt_update_tugas->execute();
    $stmt_update_tugas->close();

    // Update sesi terkait (jika ada)
    if (!empty($_POST['judul_sesi'])) {
        foreach ($_POST['judul_sesi'] as $id_sesi => $judul_sesi) {
            $deskripsi_sesi = $_POST['deskripsi_sesi'][$id_sesi];
            $tenggat_sesi = $_POST['tenggat_sesi'][$id_sesi];

            if ($judul_sesi && $deskripsi_sesi && $tenggat_sesi) {
                $stmt_update_sesi = $conn->prepare("UPDATE tbl_sesi SET judul_sesi = ?, deskripsi_sesi = ?, tenggat_sesi = ? WHERE id_sesi = ?");
                $stmt_update_sesi->bind_param("sssi", $judul_sesi, $deskripsi_sesi, $tenggat_sesi, $id_sesi);
                $stmt_update_sesi->execute();
                $stmt_update_sesi->close();
            }
        }
    }

    // Tambahkan sesi baru (jika diisi)
    if (!empty($_POST['judul_sesi_baru']) && !empty($_POST['deskripsi_sesi_baru']) && !empty($_POST['tenggat_sesi_baru'])) {
        $judul_sesi_baru = $_POST['judul_sesi_baru'];
        $deskripsi_sesi_baru = $_POST['deskripsi_sesi_baru'];
        $tenggat_sesi_baru = $_POST['tenggat_sesi_baru'];

        $stmt_insert_sesi = $conn->prepare("INSERT INTO tbl_sesi (id_tugas, judul_sesi, deskripsi_sesi, tenggat_sesi) VALUES (?, ?, ?, ?)");
        $stmt_insert_sesi->bind_param("isss", $id_tugas, $judul_sesi_baru, $deskripsi_sesi_baru, $tenggat_sesi_baru);
        $stmt_insert_sesi->execute();
        $stmt_insert_sesi->close();
    }

    $_SESSION['message'] = "Tugas dan sesi berhasil diperbarui!";
    header("Location: admin_dashboard.php");
    exit;
}



// Hapus tugas jika ada permintaan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_tugas'])) {
    $id_tugas = intval($_POST['id_tugas']);

    // Hapus tugas terkait sesi
    $query_delete_sesi = "DELETE FROM tbl_sesi WHERE id_tugas = ?";
    $stmt_delete_sesi = $conn->prepare($query_delete_sesi);
    $stmt_delete_sesi->bind_param("i", $id_tugas);
    $stmt_delete_sesi->execute();
    $stmt_delete_sesi->close();

    // Hapus tugas
    $query_delete = "DELETE FROM tbl_tugas WHERE id_tugas = ?";
    $stmt_delete = $conn->prepare($query_delete);
    $stmt_delete->bind_param("i", $id_tugas);
    $stmt_delete->execute();
    $stmt_delete->close();

    $_SESSION['message'] = "Tugas dengan ID $id_tugas berhasil dihapus.";
    header("Location: admin_dashboard.php");
    exit;
}

// Hapus sesi jika ada permintaan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_sesi'])) {
    $id_sesi = intval($_POST['id_sesi']);
    $id_tugas = intval($_POST['id_tugas']);

    // Hitung jumlah sesi pada tugas ini
    $stmt_count = $conn->prepare("SELECT COUNT(*) AS total FROM tbl_sesi WHERE id_tugas = ?");
    $stmt_count->bind_param("i", $id_tugas);
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
    $total_sesi = $result_count->fetch_assoc()['total'];
    $stmt_count->close();

    // Jika hanya ada satu sesi, jangan hapus
    if ($total_sesi > 1) {
        $stmt_delete_sesi = $conn->prepare("DELETE FROM tbl_sesi WHERE id_sesi = ?");
        $stmt_delete_sesi->bind_param("i", $id_sesi);
        $stmt_delete_sesi->execute();
        $stmt_delete_sesi->close();

        $_SESSION['message'] = "Sesi berhasil dihapus!";
    } else {
        $_SESSION['message'] = "Sesi tidak dapat dihapus karena hanya tersisa satu sesi.";
    }

    header("Location: admin_dashboard.php?edit_id=$id_tugas");
    exit;
}

// Ambil data tugas dan sesi
$query_tugas = "
    SELECT 
        t.id_tugas, t.judul_tugas, t.deskripsi, t.tenggat_waktu
    FROM tbl_tugas t
    ORDER BY t.tenggat_waktu DESC
";
$result_tugas = $conn->query($query_tugas);

// Ambil data sesi untuk setiap tugas
$query_sesi = "
    SELECT id_sesi, id_tugas, deskripsi_sesi, tenggat_sesi
    FROM tbl_sesi
";
$result_sesi = $conn->query($query_sesi);

// Ambil data pengguna
$query_users = "
    SELECT 
        id, username, password, nim
    FROM tbl_user
    ORDER BY id ASC
";
$result_users = $conn->query($query_users);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
</head>
<body>
    <h1>Selamat Datang, Admin!</h1>

    <!-- Pesan Notifikasi -->
    <?php if (isset($_SESSION['message'])): ?>
        <p><?= htmlspecialchars($_SESSION['message']) ?></p>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <!-- Form Tambah Tugas -->
        <?php
        // Jika ada permintaan untuk edit tugas
        if (isset($_GET['edit_id'])) {
            $edit_id = intval($_GET['edit_id']);

            // Ambil data tugas berdasarkan ID
            $query_edit_tugas = "SELECT * FROM tbl_tugas WHERE id_tugas = ?";
            $stmt_edit_tugas = $conn->prepare($query_edit_tugas);
            $stmt_edit_tugas->bind_param("i", $edit_id);
            $stmt_edit_tugas->execute();
            $result_edit_tugas = $stmt_edit_tugas->get_result();
            $tugas_edit = $result_edit_tugas->fetch_assoc();
            $stmt_edit_tugas->close();

            // Ambil sesi terkait tugas
            $query_edit_sesi = "SELECT * FROM tbl_sesi WHERE id_tugas = ?";
            $stmt_edit_sesi = $conn->prepare($query_edit_sesi);
            $stmt_edit_sesi->bind_param("i", $edit_id);
            $stmt_edit_sesi->execute();
            $result_edit_sesi = $stmt_edit_sesi->get_result();
            $sesi_edit_list = $result_edit_sesi->fetch_all(MYSQLI_ASSOC);
            $stmt_edit_sesi->close();
        }
        ?>

        <h2><?= isset($tugas_edit) ? "Edit Postingan Tugas" : "Tambah Postingan Tugas" ?></h2>
        <form method="POST">
            <input type="hidden" name="id_tugas" value="<?= isset($tugas_edit) ? htmlspecialchars($tugas_edit['id_tugas']) : '' ?>">

            <label for="judul_tugas">Judul Tugas:</label><br>
            <input type="text" id="judul_tugas" name="judul_tugas" value="<?= isset($tugas_edit) ? htmlspecialchars($tugas_edit['judul_tugas']) : '' ?>" required><br><br>

            <label for="deskripsi">Deskripsi Tugas:</label><br>
            <textarea id="deskripsi" name="deskripsi" rows="5" required><?= isset($tugas_edit) ? htmlspecialchars($tugas_edit['deskripsi']) : '' ?></textarea><br><br>

            <label for="tenggat_waktu">Tenggat Waktu:</label><br>
            <input type="datetime-local" id="tenggat_waktu" name="tenggat_waktu" value="<?= isset($tugas_edit) ? htmlspecialchars($tugas_edit['tenggat_waktu']) : '' ?>" required><br><br>

            <?php if (isset($sesi_edit_list)): ?>
                <h3>Sesi Tugas (Edit)</h3>
            <?php foreach ($sesi_edit_list as $sesi): ?>
                <label>Judul Sesi: 
                    <input type="text" name="judul_sesi[<?= $sesi['id_sesi'] ?>]" value="<?= htmlspecialchars($sesi['judul_sesi']) ?>">
                </label><br>
                <label>Deskripsi Sesi:
                    <textarea name="deskripsi_sesi[<?= $sesi['id_sesi'] ?>]" rows="2"><?= htmlspecialchars($sesi['deskripsi_sesi']) ?></textarea>
                </label><br>
                <label>Tenggat Waktu:
                    <input type="datetime-local" name="tenggat_sesi[<?= $sesi['id_sesi'] ?>]" value="<?= htmlspecialchars($sesi['tenggat_sesi']) ?>">
                </label><br>
                
                <?php if (count($sesi_edit_list) > 1): ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="id_tugas" value="<?= $edit_id ?>">
                        <input type="hidden" name="id_sesi" value="<?= $sesi['id_sesi'] ?>">
                        <button type="submit" name="hapus_sesi" onclick="return confirmDeletion()">Hapus Sesi</button>
                    </form>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php endif; ?>  
            <script>
                function confirmDeletion() {
                    return confirm("Apakah Anda yakin ingin menghapus data ini?");
                }
            </script>


            <!-- Bagian untuk menambahkan sesi baru -->
            <h3>Tambah Sesi Baru</h3>
            <label for="judul_sesi_baru">Judul Sesi Baru:</label><br>
            <input type="text" id="judul_sesi_baru" name="judul_sesi_baru" <?= isset($tugas_edit) ? '' : '' ?>><br><br>

            <label for="deskripsi_sesi_baru">Deskripsi Sesi Baru:</label><br>
            <textarea id="deskripsi_sesi_baru" name="deskripsi_sesi_baru" rows="5" <?= isset($tugas_edit) ? '' : '' ?>></textarea><br><br>

            <label for="tenggat_sesi_baru">Tenggat Waktu Sesi Baru:</label><br>
            <input type="datetime-local" id="tenggat_sesi_baru" name="tenggat_sesi_baru" <?= isset($tugas_edit) ? '' : '' ?>><br><br>

            <button type="submit" name="<?= isset($tugas_edit) ? 'update_tugas' : 'tambah_tugas' ?>">Submit</button>
        </form>
        


    <!-- Daftar Tugas -->
    <h2>Daftar Tugas</h2>
    <?php if ($result_tugas->num_rows > 0): ?>
        <table border="1" cellpadding="10">
            <thead>
                <tr>
                    <th>Judul Tugas</th>
                    <th>Deskripsi</th>
                    <th>Tenggat Waktu</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result_tugas->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['judul_tugas']) ?></td>
                        <td><?= htmlspecialchars($row['deskripsi']) ?></td>
                        <td><?= htmlspecialchars($row['tenggat_waktu']) ?></td>
                        <td>
                            <form method="POST" style="display: inline-block;">
                                <input type="hidden" name="id_tugas" value="<?= $row['id_tugas'] ?>">
                                <button type="submit" name="hapus_tugas" onclick="return confirmDeletion('Yakin ingin menghapus tugas ini?')">Hapus</button>
                            </form>
                            <a href="admin_dashboard.php?edit_id=<?= $row['id_tugas'] ?>">Edit</a>
                            <a href="cek_tugas.php?id=<?= $row['id_tugas'] ?>">Cek</a>

                            <!-- Daftar Sesi -->
                            <h4>Sesi untuk Tugas: <?= htmlspecialchars($row['judul_tugas']) ?></h4>
                            <?php
                            $result_sesi->data_seek(0); // Reset pointer result set sesi
                            while ($sesi = $result_sesi->fetch_assoc()) {
                                if ($sesi['id_tugas'] == $row['id_tugas']) {
                                    echo "<p>Sesi: " . htmlspecialchars($sesi['deskripsi_sesi']) . " - Tenggat Sesi: " . htmlspecialchars($sesi['tenggat_sesi']) . "</p>";
                                }
                            }
                            ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Tidak ada tugas tersedia.</p>
    <?php endif; ?>

    <!-- Daftar Pengguna -->
    <h2>Daftar Pengguna</h2>
    <?php if ($result_users->num_rows > 0): ?>
        <table border="1" cellpadding="10">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>NIM</th> <!-- Kolom NIM ditambahkan disini -->
                    <th>Username</th>
                    <th>Password</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result_users->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['id']) ?></td>
                        <td><?= htmlspecialchars($row['nim']) ?></td> <!-- Menampilkan NIM -->
                        <td><?= htmlspecialchars($row['username']) ?></td>
                        <td><?= htmlspecialchars($row['password']) ?></td>
                        <td>
                            <form method="POST" style="display: inline-block;">
                                <input type="hidden" name="user_id" value="<?= $row['id'] ?>">
                                <input type="text" name="new_password" placeholder="Password Baru" required>
                                <button type="submit" name="update_password">Ubah Password</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Tidak ada pengguna terdaftar.</p>
    <?php endif; ?>

    <!-- Tombol Logout -->
    <form action="logout.php" method="POST">
        <button type="submit" name="logout">Logout</button>
    </form>
    
</body>
</html>