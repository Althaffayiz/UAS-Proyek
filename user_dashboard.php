<?php
session_start();

// Cek jika belum login, arahkan ke halaman login
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

require_once "db.php";

// Proses keluar dari penelitian (menghapus tugas dari pengguna)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['keluar_tugas'])) {
    $kode_tugas = $_POST['keluar_tugas'];
    $username = $_SESSION['username'];

    // Hapus kode_tugas dari tbl_user_tugas
    $stmt = $conn->prepare("DELETE FROM tbl_user_tugas WHERE username_user = ? AND kode_tugas = ?");
    $stmt->bind_param('ss', $username, $kode_tugas);
    $stmt->execute();
    $stmt->close();

    // Menampilkan pesan konfirmasi
    $message = "Anda telah keluar dari penelitian dengan kode tugas $kode_tugas.";
}

// Proses input kode tugas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kode_tugas'])) {
    $kode_tugas_baru = array_map('trim', explode(',', $_POST['kode_tugas']));

    $username = $_SESSION['username'];
    $placeholders = implode(',', array_fill(0, count($kode_tugas_baru), '?'));

    // Menambahkan kode_tugas baru ke tbl_user_tugas
    $stmt = $conn->prepare("INSERT INTO tbl_user_tugas (username_user, kode_tugas) VALUES (?, ?)");
    foreach ($kode_tugas_baru as $kode) {
        $stmt->bind_param('ss', $username, $kode);
        $stmt->execute();
    }
    $stmt->close();

    $message = "Kode tugas berhasil disimpan.";
}

$username = $_SESSION['username'];
$stmt = $conn->prepare("SELECT kode_tugas FROM tbl_user_tugas WHERE username_user = ? ORDER BY id DESC");
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();

$postingan_tugas = [];
while ($row = $result->fetch_assoc()) {
    $postingan_tugas[] = $row['kode_tugas'];
}
$stmt->close();

// Ambil tugas berdasarkan kode tugas di sesi
$tugas_info = [];
if (!empty($postingan_tugas)) {
    $placeholders = implode(',', array_fill(0, count($postingan_tugas), '?'));
    $stmt = $conn->prepare("SELECT t.*, s.id_sesi, s.judul_sesi, s.deskripsi_sesi, s.tenggat_sesi 
                            FROM tbl_tugas t
                            LEFT JOIN tbl_sesi s ON t.id_tugas = s.id_tugas
                            WHERE t.kode_tugas IN ($placeholders)
                            ORDER BY t.tanggal_dibuat DESC");
    $stmt->bind_param(str_repeat('s', count($postingan_tugas)), ...$postingan_tugas);
    $stmt->execute();
    $tugas_info = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Proses upload file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_tugas'])) {
    $id_tugas = $_POST['id_tugas'];
    $id_sesi = $_POST['id_sesi'] ?? null;
    $username_user = $_SESSION['username'];
    $nama_file = $_FILES['file']['name'];

    $target_dir = "uploads/";
    $target_file = $target_dir . basename($nama_file);

    if (move_uploaded_file($_FILES['file']['tmp_name'], $target_file)) {
        $stmt = $conn->prepare("INSERT INTO tbl_uploads (id_tugas, id_sesi, username_user, nama_file) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $id_tugas, $id_sesi, $username_user, $nama_file);
        $stmt->execute();
        echo "File berhasil diunggah!";
        $stmt->close();
    } else {
        echo "Gagal mengunggah file.";
    }
}

// Proses hapus file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_file'])) {
    $id_upload = $_POST['hapus_file'];

    // Ambil nama file dari database untuk dihapus dari server
    $stmt = $conn->prepare("SELECT nama_file FROM tbl_uploads WHERE id_upload = ?");
    $stmt->bind_param('i', $id_upload);
    $stmt->execute();
    $result = $stmt->get_result();
    $file = $result->fetch_assoc();

    if ($file) {
        // Hapus file dari server
        $file_path = "uploads/" . $file['nama_file'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }

        // Hapus entri file dari database
        $stmt = $conn->prepare("DELETE FROM tbl_uploads WHERE id_upload = ?");
        $stmt->bind_param('i', $id_upload);
        $stmt->execute();
        $stmt->close();

        $message = "File berhasil dihapus.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
        }

        header, footer {
            background-color: #f8f9fa;
            text-align: ;
            padding: 10px;
        }

        .main-container {
            display: flex;
            flex-wrap: wrap;
        }

        nav, article, aside {
            box-sizing: border-box;
            padding: 10px;
        }

        nav {
            background-color: #e3f2fd;
        }
        nav ul li {
            margin-bottom: 10px;
        }

        nav ul li a {
            font-size: 14px;
            text-decoration: none;
            color: #007bff;
        }

        nav ul li a:hover {
            text-decoration: underline;
        }

        article {
            background-color: #ffffff;
        }

        aside {
            background-color:rgb(58, 58, 58);
        }

        .content-table {
            border-collapse: collapse;
            margin: 10px
        }


        .content-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
        }

        .content-table th, .content-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #f1f1f1;
        }

        .content-table th {
            background-color:rgb(95, 153, 219);
            color: white;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .content-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .content-table tr:hover {
            background-color: #f1f1f1;
        }

        .content-table td {
            color: #555;
        }

        /* Button */
        button {
            padding: 6px 12px;
            font-size: 14px;
            cursor: pointer;
            border-radius: 4px;
            border: none;
            background-color: #ff6b6b;
            color: white;
            transition: background-color 0.3s ease;
        }

        button:hover {
            background-color: #ff4040;
        }

        /* Status (Accepted, Rejected) */
        td {
            font-size: 14px;
        }

        /* Style untuk notifikasi status */
        td[style*="color: green;"] {
            font-weight: bold;
        }

        td[style*="color: red;"] {
            font-weight: bold;
}

@media (max-width: 767px) {
    .content-table th, .content-table td {
        padding: 10px;
    }
    .content-table {
        margin: 15px;
    }
}
        @media (min-width: 1024px) {
            nav {
                flex: 0 0 25%;
            }

            article {
                flex: 0 0 50%;
            }

            aside {
                flex: 0 0 25%;
            }
        }

        @media (min-width: 768px) and (max-width: 1023px) {
            nav {
                flex: 0 0 25%;
            }

            article {
                flex: 0 0 75%;
            }

            aside {
                flex: 0 0 100%;
            }
        }

        @media (max-width: 767px) {
            nav, article, aside {
                flex: 0 0 100%;
            }
        }
    </style>
    <script>
        function confirmKeluar(kodeTugas) {
            if (confirm("Apakah Anda yakin ingin keluar dari penelitian ini?")) {
                var form = document.createElement("form");
                form.method = "POST";
                var input = document.createElement("input");
                input.type = "hidden";
                input.name = "keluar_tugas";
                input.value = kodeTugas;
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }

        function confirmHapusFile(idUpload) {
            if (confirm("Apakah Anda yakin ingin menghapus file ini?")) {
                var form = document.createElement("form");
                form.method = "POST";
                var input = document.createElement("input");
                input.type = "hidden";
                input.name = "hapus_file";
                input.value = idUpload;
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</head>
<body>
    <?php
    // Ambil data header dan footer dari tbl_website_info
    $stmt_website_info = $conn->prepare("SELECT * FROM tbl_website_info LIMIT 1");
    $stmt_website_info->execute();
    $website_info = $stmt_website_info->get_result()->fetch_assoc();
    $stmt_website_info->close();
    ?>

    <!-- Header -->
    <header style="padding: 20px; background-color: #f8f9fa; display: flex; align-items: center; justify-content: space-between;">
        <!-- Logo -->
        <div style="flex: 0 0 auto; margin-right: 20px;">
            <?php if ($website_info['logo']): ?>
                <img src="data:image/jpeg;base64,<?= base64_encode($website_info['logo']) ?>" alt="Logo" style="height: 100%; max-height: 80px; object-fit: contain;">
            <?php endif; ?>
        </div>
        <!-- Teks Header -->
        <div style="flex: 1; display: flex; flex-direction: column; justify-content: center;">
            <div style="font-weight: bold; font-size: 24px;"><?= htmlspecialchars($website_info['nama_web']) ?></div>
            <div style="font-size: 14px; font-style: italic;"><?= htmlspecialchars($website_info['slogan_web']) ?></div>
            <div style="font-size: 12px; color: #666;"><?= htmlspecialchars($website_info['alamat_lokasi']) ?></div>
        </div>
        <!-- Tombol Logout -->
        <div style="flex: 0 0 auto; margin-left: 20px;">
            <form action="logout.php" method="POST" style="display: inline;">
                <button type="submit" name="logout" style="padding: 8px 16px; background-color: #ff4d4d; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    Logout
                </button>
            </form>
        </div>
    </header>

    <div class="main-container">
    <nav>
    <h3>Daftar Tugas</h3>
        <ul style="list-style: none; padding: 0;">
            <?php 
            $unique_tasks = []; 
            foreach ($tugas_info as $tugas): 
                if (!isset($unique_tasks[$tugas['id_tugas']])): 
                    $unique_tasks[$tugas['id_tugas']] = true; 
            ?>
                <li>
                    <a href="#tugas-<?= htmlspecialchars($tugas['id_tugas']) ?>" style="text-decoration: none; color: blue;">
                        <?= htmlspecialchars($tugas['judul_tugas']) ?>
                    </a>
                </li>
            <?php 
                endif;
            endforeach; 
            ?>
        </ul>
    </nav>

    <article>
    <h1>Selamat Datang, <?= htmlspecialchars($_SESSION['username']) ?>!</h1>
    <?php if (isset($message)) echo "<p>$message</p>"; ?>

    <!-- Form Input Kode Tugas -->
    <form method="POST">
        <label for="kode_tugas">Masukkan Kode Tugas:</label><br>
        <input type="text" id="kode_tugas" name="kode_tugas" value="<?= isset($_POST['kode_tugas']) ? htmlspecialchars($_POST['kode_tugas']) : '' ?>" required>
        <button type="submit">Submit</button>
    </form>

    <?php if (!empty($tugas_info)): ?>
        <h2>Daftar Tugas</h2>
        <?php 
        $current_tugas = null; 
        foreach ($tugas_info as $row): 
            if ($current_tugas !== $row['id_tugas']): 
                $current_tugas = $row['id_tugas'];
        ?>
            <div id="tugas-<?= htmlspecialchars($row['id_tugas']) ?>" style="display: flex; margin-bottom: 20px;">
                <div style="flex: 1;">
                    <h3><?= htmlspecialchars($row['judul_tugas']) ?></h3>
                    <p><?= htmlspecialchars($row['deskripsi_sesi']) ?></p>
                    <p><strong>Tenggat Waktu:</strong> <?= htmlspecialchars($row['tenggat_sesi']) ?></p>
   
                    <form method="POST" style="display:inline;">
                        <button type="button" onclick="confirmKeluar('<?= htmlspecialchars($row['kode_tugas']) ?>')">Keluar</button>
                    </form>
                </div>
            </div>
            <hr> 

            <!-- Tampilkan tabel file yang sudah diupload untuk setiap sesi -->
            <?php 
            $id_tugas = $row['id_tugas']; 
            $query_sesi = "SELECT id_sesi, judul_sesi, deskripsi_sesi, tenggat_sesi FROM tbl_sesi WHERE id_tugas = ? ORDER BY tenggat_sesi";
            $stmt_sesi = $conn->prepare($query_sesi);
            $stmt_sesi->bind_param("i", $id_tugas);
            $stmt_sesi->execute();
            $result_sesi = $stmt_sesi->get_result();
            
            while ($sesi = $result_sesi->fetch_assoc()): 
            ?>
                <h4>Sesi: <?= htmlspecialchars($sesi['judul_sesi']) ?></h4>
                <p><strong></strong> <?= htmlspecialchars($sesi['deskripsi_sesi']) ?></p>
                <p><strong>Tenggat Waktu:</strong> <?= htmlspecialchars($sesi['tenggat_sesi']) ?></p>

                <!-- Tampilkan form upload file -->
                <form method="POST" enctype="multipart/form-data">
                    <input type="file" name="file" required>
                    <input type="hidden" name="id_tugas" value="<?= htmlspecialchars($row['id_tugas']) ?>">
                    <input type="hidden" name="id_sesi" value="<?= htmlspecialchars($sesi['id_sesi']) ?>">
                    <button type="submit">Upload File</button>
                </form>

                <!-- Tampilkan tabel upload file dengan status -->
                    <?php
                    $stmt_files = $conn->prepare("SELECT id_upload, nama_file, tanggal_upload, notifikasi 
                                                FROM tbl_uploads 
                                                WHERE id_tugas = ? AND id_sesi = ? AND username_user = ?");
                    $stmt_files->bind_param("iis", $row['id_tugas'], $sesi['id_sesi'], $_SESSION['username']);
                    $stmt_files->execute();
                    $files_result = $stmt_files->get_result();

                    // Jika tidak ada file, tampilkan pesan
                    if ($files_result->num_rows == 0) {
                        echo "<p>Belum ada file yang diupload.</p>";
                    } else {
                    ?>
                        <table border="1" cellpadding="10" class="content-table">
                            <thead>
                            <tr>
                                <th>Nama File</th>
                                <th>Status</th>
                                <th>Notifikasi</th>
                                <th>Action</th>
                            </tr>
                        
                            <?php while ($file = $files_result->fetch_assoc()):
                                $tanggal_upload = $file['tanggal_upload'];
                                $tenggat_sesi = $row['tenggat_sesi'];
                                $status = (strtotime($tanggal_upload) <= strtotime($tenggat_sesi)) ? "Tepat Waktu" : "Terlambat";
                                $notifikasi = $file['notifikasi'];
       
                                if ($notifikasi == "Accepted") {
                                    $notifikasi_style = "color: green;"; 
                                } elseif ($notifikasi == "Rejected") {
                                    $notifikasi_style = "color: red;";    
                                } else {
                                    $notifikasi_style = "color: black;"; 
                                }
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($file['nama_file']) ?></td>
                                    <td><?= $status ?></td>
                                    <td style="<?= $notifikasi_style ?>"><?= htmlspecialchars($notifikasi) ?></td>
                                    <td><form method="POST" style="display:inline;">
                                        <button type="button" onclick="confirmHapusFile(<?= $file['id_upload'] ?>)">Hapus</button></form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </table>
                    <?php } ?>

                                                   
            <?php endwhile; ?>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php else: ?>
        <p>Belum ada tugas yang dimasukkan.</p>
    <?php endif; ?>
    </article>
    <aside>
            
    </aside>
    </div>
    
    <!-- Footer -->
    <footer style="background-color: #f8f9fa; padding: 20px; border-top: 1px solid #ddd;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
        
            <div style="flex: 1; text-align: left;">
                <a target="_blank" style="text-decoration: none; color: #333;">
                    Instagram: @<?= htmlspecialchars($website_info['akun_ig']) ?>
                </a>
            </div>

            <div style="flex: 1; text-align: center;">
                <p>&copy; Copyright 2025. All Rights Reserved.</p>
            </div>
       
            <div style="flex: 1; text-align: right;">
                <div><?= htmlspecialchars($website_info['nama_web']) ?></div>
                <div style="font-size: 12px; font-style: italic;"><?= htmlspecialchars($website_info['slogan_web']) ?></div>
            </div>
        </div>
    </footer>

    </body>
    </html>