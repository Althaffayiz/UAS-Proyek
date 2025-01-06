<?php
session_start();
$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_uas";

// Koneksi database
$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    die("Koneksi ke database gagal: " . mysqli_connect_error());
}

$form_type = isset($_GET['form']) ? $_GET['form'] : 'user';

// Proses login user biasa
if (isset($_POST['login_user'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);

    $query = "SELECT * FROM tbl_user WHERE username='$username'";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        if ($password === $user['password']) {
            $_SESSION['username'] = $username;
            header("Location: user_dashboard.php");
            exit();
        } else {
            $error_message = "Password salah!";
        }
    } else {
        $error_message = "Username tidak ditemukan!";
    }
}

// Proses login admin
if (isset($_POST['login_admin'])) {
    $admin_username = "admin";
    $admin_password = "admin123";

    $input_username = $_POST['admin_username'];
    $input_password = $_POST['admin_password'];

    if ($input_username === $admin_username && $input_password === $admin_password) {
        $_SESSION['admin'] = $input_username;
        header("Location: admin_dashboard.php");
        exit();
    } else {
        $error_message_admin = "Username atau Password Admin salah!";
    }
}

// Proses registrasi user biasa
if (isset($_POST['register_user'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $nim = mysqli_real_escape_string($conn, $_POST['nim']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);

    $check_query = "SELECT * FROM tbl_user WHERE username='$username' OR nim='$nim'";
    $check_result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($check_result) > 0) {
        $register_error = "Username atau NIM sudah digunakan!";
    } else {
        $insert_query = "INSERT INTO tbl_user (username, nim, password) VALUES ('$username', '$nim', '$password')";
        if (mysqli_query($conn, $insert_query)) {
            $register_success = "Registrasi berhasil! Silakan login.";
        } else {
            $register_error = "Terjadi kesalahan saat registrasi!";
        }
    }
}

$logo_query = "SELECT logo FROM tbl_website_info LIMIT 1";
$logo_result = mysqli_query($conn, $logo_query);
$website_logo = null;

if ($logo_result && mysqli_num_rows($logo_result) > 0) {
    $row = mysqli_fetch_assoc($logo_result);
    $website_logo = $row['logo'];
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Page</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .container {
            width: 100%;
            max-width: 400px;
            padding: 20px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        .container img {
            max-width: 100px;
            height: auto;
            margin-bottom: 20px;
        }
        .container form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .container input[type="text"],
        .container input[type="password"],
        .container input[type="submit"] {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .container input[type="submit"] {
            background-color: #007bff;
            color: white;
            cursor: pointer;
            border: none;
        }
        .container .switch-link a {
            color: #007bff;
            text-decoration: none;
        }
        .error-message, .success-message {
            margin-top: 10px;
        }
        .error-message {
            color: red;
            font-weight: bold;
        }
        .success-message {
            color: green;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
 
        <?php if ($website_logo): ?>
            <img src="data:image/jpeg;base64,<?= base64_encode($website_logo) ?>" alt="Website Logo">
        <?php endif; ?>

        <?php if ($form_type === 'admin') { ?>
            <h2>Login Admin</h2>
            <form method="POST" action="">
                <input type="text" name="admin_username" placeholder="Admin Username" required>
                <input type="password" name="admin_password" placeholder="Admin Password" required>
                <input type="submit" name="login_admin" value="Login Admin">
            </form>
            <?php if (isset($error_message_admin)) { ?>
                <div class="error-message"><?php echo $error_message_admin; ?></div>
            <?php } ?>
            <div class="switch-link">
                <a href="index.php?form=user">Login sebagai User Biasa</a>
            </div>
        <?php } elseif ($form_type === 'register') { ?>
            <h2>Register User</h2>
            <form method="POST" action="">
                <input type="text" name="username" placeholder="Username" required>
                <input type="text" name="nim" placeholder="NIM" required>
                <input type="password" name="password" placeholder="Password" required>
                <input type="submit" name="register_user" value="Register">
            </form>
            <?php if (isset($register_error)) { ?>
                <div class="error-message"><?php echo $register_error; ?></div>
            <?php } elseif (isset($register_success)) { ?>
                <div class="success-message"><?php echo $register_success; ?></div>
            <?php } ?>
            <div class="switch-link">
                <a href="index.php?form=user">Login sebagai User Biasa</a>
            </div>
        <?php } else { ?>
            <h2>Login User</h2>
            <form method="POST" action="">
                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Password" required>
                <input type="submit" name="login_user" value="Login User">
            </form>
            <?php if (isset($error_message)) { ?>
                <div class="error-message"><?php echo $error_message; ?></div>
            <?php } ?>
            <div class="switch-link">
                <a href="index.php?form=admin">Login sebagai Admin</a> |
                <a href="index.php?form=register">Register Akun Baru</a>
            </div>
        <?php } ?>
    </div>
</body>
</html>