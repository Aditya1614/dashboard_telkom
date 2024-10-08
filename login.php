<?php
session_start();

/**
 * Mendapatkan daftar pengguna dari file JSON.
 *
 * Fungsi ini memeriksa keberadaan file `users.json`, membaca kontennya, 
 * dan mengonversi data JSON menjadi array asosiatif PHP.
 * Jika file tidak ditemukan, fungsi akan menghentikan eksekusi dengan pesan error.
 *
 * @return array Mengembalikan array asosiatif berisi data pengguna dari file JSON.
 *               Jika file tidak ditemukan, skrip dihentikan.
 */
function getUsersFromJSON() {
    $file = 'users.json';
    if (!file_exists($file)) {
        die("Users file not found.");
    }
    $jsonData = file_get_contents($file);
    return json_decode($jsonData, true); // Decode JSON to associative array
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $users = getUsersFromJSON(); // Load users from the JSON file

    $userFound = false;
    
    foreach ($users as $user) {
        if ($user['username'] === $username) {
            $userFound = true;
            if ($user['password'] === $password) {
                session_regenerate_id(true);
                $_SESSION['user'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                header("Location: index.php");
                exit();
            } else {
                echo "<div class='error'>Password salah!</div>";
            }
        }
    }

    if (!$userFound) {
        echo "<div class='error'>Pengguna tidak ditemukan!</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f0f0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .login-container {
            background-color: #fff;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            width: 300px;
            text-align: center;
        }
        h2 {
            margin-bottom: 20px;
            color: #333;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box; 
        }
        button {
            width: 100%;
            padding: 10px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #218838;
        }
        .error {
            color: red;
            margin-bottom: 10px;
        }
        .register-link {
            margin-top: 15px;
            display: block;
        }
        .register-link a {
            color: #007bff;
            text-decoration: none;
        }
        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Login</h2>
        <form method="POST" action="">
            <input type="text" name="username" placeholder="Username" required><br>
            <input type="password" name="password" placeholder="Password" required><br>
            <button type="submit">Login</button>
        </form>
        <div class="register-link">
            Belum punya akun? <a href="register.php">Daftar</a>
        </div>
        <div class="register-link">
            <a href="index.php">Masuk sebagai visitor</a>
        </div>
    </div>
</body>
</html>
