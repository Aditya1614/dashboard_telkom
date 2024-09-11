<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

include 'db_connect.php';

// Periksa peran pengguna
$role = $_SESSION['role'];

// Batasi akses dropdown untuk admin
$adminAccess = ($role === 'admin');

// Handle the current table (default 'days')
$currentTable = isset($_SESSION['currentTable']) ? $_SESSION['currentTable'] : 'days';

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = isset($_SESSION['limit']) ? intval($_SESSION['limit']) : 10;
$offset = ($page - 1) * $limit;

// Get columns from the database
$columnResult = $conn->query("SHOW COLUMNS FROM $currentTable");
if (!$columnResult) {
    die("Error executing query: " . $conn->error);
}
$columnNames = array();
while ($row = $columnResult->fetch_assoc()) {
    $columnNames[] = $row['Field'];
}

// Handle selected columns
if ($adminAccess) {
    // Jika admin baru saja memilih kolom melalui form dropdown
    if (isset($_POST['columns'])) {
        // Simpan pilihan kolom ke session
        $_SESSION['selectedColumns'] = $_POST['columns'];
        $selectedColumns = $_POST['columns'];
    } else {
        // Cek apakah admin sudah menyimpan kolom di session sebelumnya
        if (isset($_SESSION['selectedColumns']) && !empty($_SESSION['selectedColumns'])) {
            $selectedColumns = $_SESSION['selectedColumns'];
        } else {
            // Jika belum ada di session, cek di tabel 'selected_columns'
            $sql = "SELECT column_names FROM selected_columns WHERE table_name = '$currentTable'";
            $result = $conn->query($sql);

            if (!$result) {
                die("Error executing query: " . $conn->error);
            }

            if ($row = $result->fetch_assoc()) {
                // Jika ada data di database, gunakan kolom tersebut
                $selectedColumns = explode(',', $row['column_names']);
            } else {
                // Jika tidak ada data yang disimpan, tampilkan semua kolom
                $selectedColumns = $columnNames;
            }
        }
    }
} else {
    // Logika untuk user biasa (hanya lihat kolom yang disimpan di database)
    $sql = "SELECT column_names FROM selected_columns WHERE table_name = '$currentTable'";
    $result = $conn->query($sql);

    if (!$result) {
        die("Error executing query: " . $conn->error);
    }

    $selectedColumns = array();
    if ($row = $result->fetch_assoc()) {
        // Pecah string 'column_names' menjadi array
        $selectedColumns = explode(',', $row['column_names']);
    }

    // Jika tidak ada kolom yang tersimpan, tampilkan semua kolom
    if (empty($selectedColumns)) {
        $selectedColumns = $columnNames;
    }
}

// Query data with pagination
$totalResult = $conn->query("SELECT COUNT(*) as total FROM $currentTable");
if (!$totalResult) {
    die("Error executing query: " . $conn->error);
}
$row = $totalResult->fetch_assoc(); 
$totalRows = $row['total']; 
$totalPages = ceil($totalRows / $limit);

$sql = "SELECT " . implode(',', $selectedColumns) . " FROM $currentTable LIMIT $limit OFFSET $offset";
$dataResult = $conn->query($sql);
if (!$dataResult) {
    die("Error executing query: " . $conn->error);
}
$data = array();
while ($row = $dataResult->fetch_assoc()) {
    $data[] = $row;
}

// Close connection
$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bike Sharing Data</title>
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
            text-align: center;
            margin-bottom: 20px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            font-size: 16px;
            border-radius: 5px;
        }
        button:hover {
            background-color: #45a049;
        }
        table {
            width: 80%;
            margin: 0 auto;
            border-collapse: collapse;
            background-color: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #4CAF50;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        td a {
            color: #3498db;
            text-decoration: none;
        }
        td a:hover {
            text-decoration: underline;
        }
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px 0;
        }
        .pagination ul {
            display: flex;
            list-style-type: none;
            padding: 0;
            margin: 0;
        }
        .pagination li {
            margin: 0 5px;
        }
        .pagination a {
            display: block;
            padding: 8px 16px;
            text-decoration: none;
            background-color: #f1f1f1;
            color: #333;
            border: 1px solid #ddd;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }
        .pagination a:hover {
            background-color: #ddd;
        }
        .pagination .active {
            background-color: #4CAF50;
            color: white;
            border: 1px solid #4CAF50;
        }
        .pagination .disabled {
            color: #ccc;
            pointer-events: none;
        }
        .pagination .ellipsis {
            padding: 8px 16px;
            color: #777;
            border: none;
        }
        .dropdown {
            position: relative;
            display: inline-block;
        }
        .dropbtn {
            background-color: #4CAF50;
            color: white;
            padding: 10px 16px;
            font-size: 16px;
            border: none;
            cursor: pointer;
            margin-right: 10px;
        }
        .dropbtn:hover {
            background-color: #3e8e41;
        }
        .dropdown-content {
            display: none;
            position: absolute;
            background-color: #f9f9f9;
            min-width: 160px;
            box-shadow: 0px 8px 16px 0px rgba(0, 0, 0, 0.2);
            padding: 12px 16px;
            z-index: 1;
            overflow-y: auto;
            max-height: 300px;
        }
        .dropdown:hover .dropdown-content {
            display: block;
        }
        .dropdown-content label {
            display: block;
            margin-bottom: 8px;
            cursor: pointer;
        }
        .dropdown-submit {
            background-color: #4CAF50;
            color: white;
            padding: 5px 10px;
            border: none;
            cursor: pointer;
            margin-top: 10px;
        }
        .dropdown-submit:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
<h1>Selamat Datang, <?= htmlspecialchars($_SESSION['user']) ?></h1>

    <!-- Dropdown untuk mengganti tabel -->
    
    <div style="text-align: center;">
        <!-- Dropdown untuk memilih tabel -->
        <!-- <form id="tableForm" action="switch.php" method="POST" style="display: inline-block;">
            <label for="table" style="margin-right: 10px;">Select Table:</label>
            <select id="table" name="table" class="dropbtn" onchange="this.form.submit()">
                <option value="days" <?= $currentTable === 'days' ? 'selected' : '' ?>>Days</option>
                <option value="hours" <?= $currentTable === 'hours' ? 'selected' : '' ?>>Hours</option>
            </select>
        </form> -->

        <!-- Dropdown untuk memilih kolom -->
        <?php if ($adminAccess) : ?>
        <div style="display: inline-block;">
            <label class="label-column" for="columnForm">Select Columns to Display:</label>
            <div class="dropdown" style="display: inline-block;">
                <button class="dropbtn">Select Columns</button>
                <div class="dropdown-content">
                    <form id="columnForm" action="show.php" method="POST">
                        <?php foreach ($columnNames as $column) : ?>
                            <label>
                                <input type="checkbox" name="columns[]" value="<?= $column ?>"
                                       <?= in_array($column, $selectedColumns) ? 'checked' : '' ?>>
                                <?= $column ?>
                            </label><br>
                        <?php endforeach; ?>
                        <input type="submit" value="Update" class="dropdown-submit">
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>


        <!-- Dropdown untuk memilih limit data -->
        <div style="display: inline-block; margin-left: 20px;">
            <label for="limit">Select Data Limit:</label>
            <form id="limitForm" action="updateLimit.php" method="POST">
                <select id="limit" name="limit" class="dropbtn" onchange="this.form.submit()">
                    <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                    <option value="20" <?= $limit == 20 ? 'selected' : '' ?>>20</option>
                    <option value="30" <?= $limit == 30 ? 'selected' : '' ?>>30</option>
                </select>
            </form>
        </div>
    </div>
    
    <!-- Tabel data -->
    <div id="tableData">
        <table border="1" cellpadding="10">
            <thead>
                <tr>
                    <?php foreach ($selectedColumns as $column) : ?>
                        <th><?= htmlspecialchars($column) ?></th>
                    <?php endforeach; ?>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $item) : ?>
                    <tr>
                        <?php foreach ($selectedColumns as $column) : ?>
                            <td><?= htmlspecialchars($item[$column]) ?></td>
                        <?php endforeach; ?>
                        <td><a href="edit.php?instant=<?= urlencode($item['instant']) ?>&columns=<?= urlencode(implode(',', $selectedColumns)) ?>">Edit</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<!-- Pagination -->
<div class="pagination">
    <ul>
        <?php if ($page > 1) : ?>
            <li><a href="?page=<?= $page - 1 ?>">Previous</a></li>
        <?php else : ?>
            <li><span class="disabled">Previous</span></li>
        <?php endif; ?>

        <?php if ($page > 3) : ?>
            <li><a href="?page=1">1</a></li>
            <li><span class="ellipsis">...</span></li>
        <?php endif; ?>

        <?php for ($i = max(1, $page - 2); $i <= min($page + 2, $totalPages); $i++) : ?>
            <?php if ($i === $page) : ?>
                <li><a class="active"><?= $i ?></a></li>
            <?php else : ?>
                <li><a href="?page=<?= $i ?>"><?= $i ?></a></li>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($page < $totalPages - 2) : ?>
            <li><span class="ellipsis">...</span></li>
            <li><a href="?page=<?= $totalPages ?>"><?= $totalPages ?></a></li>
        <?php endif; ?>

        <?php if ($page < $totalPages) : ?>
            <li><a href="?page=<?= $page + 1 ?>">Next</a></li>
        <?php else : ?>
            <li><span class="disabled">Next</span></li>
        <?php endif; ?>
    </ul>
</div>

<a href="logout.php">Logout</a>

</body>
<script>
document.getElementById('mainForm').addEventListener('change', function (event) {
            const formData = new FormData(this);
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'show.php', true);
            xhr.onload = function () {
                if (xhr.status === 200) {
                    document.getElementById('tableData').innerHTML = xhr.responseText;
                } else {
                    console.error('Error loading data');
                }
            };
            xhr.send(formData);
        });

</script>

</html>
