<?php
session_start();
include 'db_connect.php';

// Cek apakah user sudah login
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

// Ambil ID (instant) dari URL
$instant = isset($_GET['instant']) ? intval($_GET['instant']) : null;
// $currentTable = $_SESSION['currentTable'] ?? 'days';
$currentTable = isset($_SESSION['currentTable']) ? $_SESSION['currentTable'] : 'days';

// Ambil daftar kolom yang dipilih admin
$selectedColumns = isset($_SESSION['selectedColumns']) ? $_SESSION['selectedColumns'] : array();

// Jika admin tidak memilih kolom, ambil semua kolom dari tabel 'selected_columns'
if (empty($selectedColumns)) {
    $sql = "SELECT column_names FROM selected_columns WHERE table_name = '$currentTable'";
    $result = $conn->query($sql);

    if ($result && $row = $result->fetch_assoc()) {
        // Jika ada data kolom yang tersimpan, gunakan kolom tersebut
        $selectedColumns = explode(',', $row['column_names']);
    } else {
        // Jika tidak ada data kolom yang tersimpan, tampilkan semua kolom
        $columnResult = $conn->query("SHOW COLUMNS FROM $currentTable");
        $selectedColumns = array();
        while ($row = $columnResult->fetch_assoc()) {
            $selectedColumns[] = $row['Field'];
        }
    }
}

// Jika instant tidak valid, redirect kembali ke dashboard
if (!$instant) {
    header("Location: dashboard.php");
    exit();
}

// Ambil data berdasarkan 'instant'
$sql = "SELECT * FROM $currentTable WHERE instant = $instant";
$result = $conn->query($sql);
if (!$result || $result->num_rows == 0) {
    echo "Data tidak ditemukan.";
    exit();
}
$item = $result->fetch_assoc();

// Jika form disubmit (POST), lakukan update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Buat array untuk menyimpan nilai yang akan diupdate
    $updates = array();
    foreach ($selectedColumns as $column) {
        $value = $_POST[$column];
        $updates[] = "$column = '" . $conn->real_escape_string($value) . "'";
    }

    // Gabungkan kolom dan nilai yang diupdate menjadi satu string
    $updateQuery = implode(", ", $updates);

    // Query untuk update data
    $sqlUpdate = "UPDATE $currentTable SET $updateQuery WHERE instant = $instant";
    if ($conn->query($sqlUpdate)) {
        header("Location: dashboard.php");  // Redirect kembali ke dashboard setelah update berhasil
        exit();
    } else {
        echo "Error updating record: " . $conn->error;
    }
}

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Bike Sharing Data</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            padding: 0;
            background-color: #f4f4f9;
        }

        h1 {
            text-align: center;
            color: #333;
        }

        form {
            max-width: 600px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: #555;
        }

        input[type="text"],
        input[type="date"] {
            width: calc(100% - 20px);
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        input[type="text"]:focus,
        input[type="date"]:focus {
            border-color: #4CAF50;
            outline: none;
        }

        button {
            width: 100%;
            padding: 12px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        button:hover {
            background-color: #45a049;
        }
    </style>
</head>

<body>
    <h1>Edit Bike Sharing Data</h1>
    <form action="edit.php?instant=<?= $instant ?>" method="POST">
        <?php foreach ($selectedColumns as $column): ?>
            <?php if (isset($item[$column])): ?>
                <?php if ($column === 'dteday'): ?>
                    <label for="dteday">Date (dteday):</label>
                    <input type="date" id="dteday" name="dteday" value="<?= htmlspecialchars($item['dteday']) ?>">
                <?php else: ?>
                    <label for="<?= htmlspecialchars($column) ?>"><?= htmlspecialchars($column) ?>:</label>
                    <input type="text" id="<?= htmlspecialchars($column) ?>" name="<?= htmlspecialchars($column) ?>" value="<?= htmlspecialchars($item[$column]) ?>">
                <?php endif; ?>
            <?php else: ?>
                <input type="hidden" id="<?= htmlspecialchars($column) ?>" name="<?= htmlspecialchars($column) ?>" value="<?= htmlspecialchars($item[$column]) ?>">
            <?php endif; ?>
        <?php endforeach; ?>

        <button type="submit">Save</button>
    </form>
</body>

</html>
