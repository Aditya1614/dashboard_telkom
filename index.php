<?php 
session_start();

// Cek apakah user sudah login
$loggedIn = isset($_SESSION['user']);

// Jika belum login, beri akses halaman tanpa hak admin
$role = $loggedIn ? $_SESSION['role'] : null;
$adminAccess = ($role === 'admin');

include 'db_connect.php';
include 'functions.php';

// Handle search form
if (isset($_POST['searchColumn']) && isset($_POST['searchValue'])) {
    $_SESSION['searchColumn'] = $_POST['searchColumn'];
    $_SESSION['searchValue'] = $_POST['searchValue'];
    $searchColumn = $_POST['searchColumn'];
    $searchValue = $_POST['searchValue'];
} elseif (isset($_SESSION['searchColumn']) && isset($_SESSION['searchValue'])) {
    // Gunakan nilai dari session jika ada
    $searchColumn = $_SESSION['searchColumn'];
    $searchValue = $_SESSION['searchValue'];
} else {
    // Tidak ada pencarian yang dilakukan
    $searchColumn = null;
    $searchValue = null;
}

// Reset pencarian
if (isset($_GET['reset']) && $_GET['reset'] === 'true') {
    // Hapus pencarian dari session
    unset($_SESSION['searchColumn']);
    unset($_SESSION['searchValue']);
    // Redirect ke halaman tanpa parameter pencarian
    header("Location: " . basename(__FILE__));
    exit;
}

// Handle switch table dari switch.php
$jsonFilePath = 'selected_columns.json';

// Cek apakah ada tabel yang dipilih dari form
if (isset($_POST['table'])) {
    $currentTable = $_POST['table'];
    $_SESSION['currentTable'] = $currentTable;
// } elseif (isset($_SESSION['currentTable'])) {
//     $currentTable = $_SESSION['currentTable'];
} else {
    $currentTable = getCurrentTable(); // dari functions.php
    // // Jika tidak ada tabel yang dipilih dan tidak ada dalam session, ambil tabel terakhir yang diupdate
    // if (file_exists($jsonFilePath)) {
    //     $jsonData = file_get_contents($jsonFilePath);
    //     $selectedColumnsData = json_decode($jsonData, true);

    //     // Urutkan berdasarkan updated_at
    //     usort($selectedColumnsData, function($a, $b) {
    //         return strtotime($b['updated_at']) - strtotime($a['updated_at']);
    //     });

    //     // Ambil tabel dengan updated_at terakhir
    //     if (!empty($selectedColumnsData)) {
    //         $currentTable = $selectedColumnsData[0]['table_name'];
    //     } else {
    //         $currentTable = 'days'; // Default tabel jika file kosong
    //     }
    // } else {
    //     $currentTable = 'days'; // Default tabel jika file JSON tidak ada
    // }
}

// Ambil kolom dari tabel yang dipilih
$updateJson = false; // Flag untuk menandakan apakah JSON perlu ditulis ulang
if (file_exists($jsonFilePath)) {
    $jsonData = file_get_contents($jsonFilePath);
    $selectedColumnsData = json_decode($jsonData, true);
} else {
    $selectedColumnsData = array(); // Buat array kosong jika file tidak ada
}

$foundTable = false;
foreach ($selectedColumnsData as &$table) {
    if ($table['table_name'] == $currentTable) {
        // Jika tabel ditemukan, ambil kolomnya dan update updated_at
        $_SESSION['selectedColumns'] = explode(',', $table['column_names']);
        $_SESSION['selected_id_column'] = isset($table['selected_ID']) ? $table['selected_ID'] : 'instant'; // Default ID jika belum diset
        $table['updated_at'] = date('Y-m-d H:i:s'); // Update updated_at
        $foundTable = true;
        $updateJson = true; // JSON perlu diupdate
        break;
    }
}

if (!$foundTable) {
    // Jika tabel tidak ditemukan, ambil semua kolom dari tabel baru dari database
    $columnResult = $conn->query("SHOW COLUMNS FROM $currentTable");
    $columnNames = array();
    if ($columnResult) {
        while ($row = $columnResult->fetch_assoc()) {
            $columnNames[] = $row['Field'];
        }
        $_SESSION['selectedColumns'] = $columnNames;
        $_SESSION['selected_id_column'] = 'instant'; // Default ID

        // Tambahkan tabel baru ke JSON
        $selectedColumnsData[] = array(
            'table_name' => $currentTable,
            'column_names' => implode(',', $columnNames),
            'selected_ID' => 'instant', // Default ID
            'updated_at' => date('Y-m-d H:i:s')
        );
        $updateJson = true; // JSON perlu diupdate
    } else {
        die("Error retrieving columns: " . $conn->error);
    }
}

// Jika JSON perlu diupdate, tulis ulang file JSON
if ($updateJson) {
    file_put_contents($jsonFilePath, json_encode($selectedColumnsData));
}

// Simpan kolom yang dipilih ke dalam variabel
$selectedColumns = $_SESSION['selectedColumns'];

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = isset($_SESSION['limit']) ? intval($_SESSION['limit']) : 10;
$offset = ($page - 1) * $limit;

// Ambil kolom dari tabel saat ini
$columnResult = $conn->query("SHOW COLUMNS FROM $currentTable");
if (!$columnResult) {
    die("Error executing query: " . $conn->error);
}
$columnNames = array();
while ($row = $columnResult->fetch_assoc()) {
    $columnNames[] = $row['Field'];
}

// Set kolom ID yang dipilih
if (isset($_POST['selected_id_column'])) {
    $_SESSION['selected_id_column'] = $_POST['selected_id_column'];
    $selectedIdColumn = $_POST['selected_id_column'];

    // Update file JSON dengan selected ID column
    if (file_exists($jsonFilePath)) {
        $jsonData = file_get_contents($jsonFilePath);
        $selectedColumnsData = json_decode($jsonData, true);
    } else {
        $selectedColumnsData = array(); // Buat array kosong jika file tidak ada
    }

    // Temukan tabel saat ini di JSON dan update selected_ID serta updated_at
    $foundTable = false;
    foreach ($selectedColumnsData as &$table) {
        if ($table['table_name'] == $currentTable) {
            $table['selected_ID'] = $selectedIdColumn; // Update selected_ID
            $table['updated_at'] = date('Y-m-d H:i:s');
            $foundTable = true;
            break;
        }
    }

    // Jika tabel belum ditemukan, tambahkan tabel baru
    if (!$foundTable) {
        $selectedColumnsData[] = array(
            'table_name' => $currentTable,
            'column_names' => implode(',', $selectedColumns),
            'selected_ID' => $selectedIdColumn,
            'updated_at' => date('Y-m-d H:i:s')
        );
    }

    // Simpan perubahan ke file JSON
    if (file_put_contents($jsonFilePath, json_encode($selectedColumnsData)) === false) {
        die("Error: Failed to write to JSON file.");
    }
} else {
    // Ambil kolom ID dari session jika sudah diset sebelumnya
    $selectedIdColumn = isset($_SESSION['selected_id_column']) ? $_SESSION['selected_id_column'] : 'instant'; // default ID
}

// Handle selected columns
if ($adminAccess) {
    if (isset($_POST['columns'])) {
        // Admin mengirimkan kolom yang dipilih
        $_SESSION['selectedColumns'] = $_POST['columns'];
        $selectedColumns = $_POST['columns'];

        // Update file JSON dengan kolom baru
        if (file_exists($jsonFilePath)) {
            $jsonData = file_get_contents($jsonFilePath);
            $selectedColumnsData = json_decode($jsonData, true);
        } else {
            $selectedColumnsData = array(); // Buat array kosong jika file tidak ada
        }

        // Temukan tabel saat ini di JSON dan update kolom serta updated_at
        $foundTable = false;
        foreach ($selectedColumnsData as &$table) {
            if ($table['table_name'] == $currentTable) {
                $table['column_names'] = implode(',', $selectedColumns);
                $table['updated_at'] = date('Y-m-d H:i:s');
                $foundTable = true;
                break;
            }
        }

        // Jika tabel belum ditemukan, tambahkan tabel baru
        if (!$foundTable) {
            $selectedColumnsData[] = array(
                'table_name' => $currentTable,
                'column_names' => implode(',', $selectedColumns),
                'updated_at' => date('Y-m-d H:i:s')
            );
        }

        // Simpan // Simpan perubahan ke file JSON
        if (file_put_contents($jsonFilePath, json_encode($selectedColumnsData)) === false) {
            die("Error: Failed to write to JSON file.");
        }

    } elseif (isset($_SESSION['selectedColumns']) && !empty($_SESSION['selectedColumns'])) {
        // Ambil dari session jika sudah diset sebelumnya
        $selectedColumns = $_SESSION['selectedColumns'];
    } else {
        // Ambil dari JSON jika belum ada di session
        if (file_exists($jsonFilePath)) {
            $jsonData = file_get_contents($jsonFilePath);
            $selectedColumnsData = json_decode($jsonData, true);

            foreach ($selectedColumnsData as $table) {
                if ($table['table_name'] == $currentTable) {
                    $selectedColumns = explode(',', $table['column_names']);
                    $_SESSION['selectedColumns'] = $selectedColumns; // Simpan ke session
                    break;
                }
            }
        }

        // Jika tabel belum ada di JSON, default ke semua kolom
        if (!isset($selectedColumns)) {
            $selectedColumns = $columnNames;
            $_SESSION['selectedColumns'] = $selectedColumns; // Set ke semua kolom
        }
    }
} else {
    // User non-admin: Ambil kolom dari file JSON
    if (file_exists($jsonFilePath)) {
        $jsonData = file_get_contents($jsonFilePath);
        $selectedColumnsData = json_decode($jsonData, true);

        foreach ($selectedColumnsData as $table) {
            if ($table['table_name'] == $currentTable) {
                $selectedColumns = explode(',', $table['column_names']);
                break;
            }
        }
    }

    // Jika tabel belum ada di JSON, default ke semua kolom
    if (!isset($selectedColumns)) {
        $selectedColumns = $columnNames;
    }
}

// Query pencarian
if (isset($_GET['searchColumn']) && isset($_GET['searchValue'])) {
    $searchColumn = $_GET['searchColumn'];
    $searchValue = $_GET['searchValue'];

    if (is_numeric($searchValue)) {
        $tolerance = 0.0001;
        $searchValue = floatval($searchValue);
        $searchQuery = " WHERE $searchColumn BETWEEN " . ($searchValue - $tolerance) . " AND " . ($searchValue + $tolerance);
    } else {
        $searchQuery = " WHERE $searchColumn = '" . $conn->real_escape_string($searchValue) . "'";
    }

    $searchParams = "&searchColumn=" . urlencode($searchColumn) . "&searchValue=" . urlencode($searchValue);
} else {
    // Jika tidak ada pencarian
    $searchQuery = "";
    $searchParams = "";
}

// Query untuk pagination
// Eksekusi query untuk menghitung total baris
$totalResult = $conn->query("SELECT COUNT(*) as total FROM $currentTable" . $searchQuery);

// Cek apakah query berhasil
if (!$totalResult) {
    die("Error in totalResult query: " . $conn->error);
}

$row = $totalResult->fetch_assoc();
$totalRows = $row['total'];
$totalPages = ceil($totalRows / $limit);

// Eksekusi query untuk mendapatkan data
$sql = "SELECT " . implode(',', $selectedColumns) . " FROM $currentTable" . $searchQuery . " LIMIT $limit OFFSET $offset";
$dataResult = $conn->query($sql);

// Cek apakah query berhasil
if (!$dataResult) {
    die("Error in dataResult query: " . $conn->error);
}

$data = array();
while ($row = $dataResult->fetch_assoc()) {
    $data[] = $row;
}

// Get the sort parameter from the URL
$sort = isset($_GET['sort']) ? $_GET['sort'] : null;
$order = isset($_GET['order']) ? $_GET['order'] : 'asc';

// Validate the sort parameter to prevent SQL injection
if ($sort && in_array($sort, $columnNames)) {

    // Sort the data based on the value of the sort column
    $sql = "SELECT " . implode(',', $selectedColumns) . " FROM $currentTable" . $searchQuery . " ORDER BY $sort $order LIMIT $limit OFFSET $offset";
    // Execute the query and get the results
    $result = $conn->query($sql);
    $data = array();
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
} else {
    $sql = "SELECT " . implode(',', $selectedColumns) . " FROM $currentTable" . $searchQuery . " LIMIT $limit OFFSET $offset";
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bike Sharing Data</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
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

    th,
    td {
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

    /* Mengatur container agar elemen berada dalam satu baris */
    .form-container {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap;
    }

    /* Mengatur gaya tiap item di dalam container */
    .form-item {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* Mengatur dropdown dan tombol agar seragam */
    .styled-dropdown {
        padding: 10px 16px;
        font-size: 16px;
        color: white;
        background-color: #4CAF50;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        appearance: none;
        background-repeat: no-repeat;
        background-position: right 10px center;
        background-size: 10px;
        min-width: 150px;
        text-align: center;
    }

    .dropbtn {
        padding: 10px 16px;
        font-size: 16px;
        color: white;
        background-color: #4CAF50;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        min-width: 150px;
        text-align: center;
    }

    .styled-dropdown:hover,
    .dropbtn:hover {
        background-color: #45a049;
    }

    /* Mengatur dropdown content agar lebih rapi */
    .dropdown-content {
        display: none;
        position: absolute;
        background-color: #f9f9f9;
        min-width: 200px;
        box-shadow: 0px 8px 16px 0px rgba(0, 0, 0, 0.2);
        padding: 12px;
        z-index: 1;
        overflow-y: auto;
        max-height: 300px;
        border-radius: 5px;
        border: 1px solid #ccc;
    }

    .dropdown:hover .dropdown-content {
        display: block;
    }

    .dropdown-content label {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 5px 0;
        cursor: pointer;
    }

    /* Submit button in dropdown */
    .dropdown-submit {
        background-color: #4CAF50;
        color: white;
        padding: 8px 12px;
        border: none;
        cursor: pointer;
        width: 100%;
        margin-top: 10px;
        border-radius: 5px;
    }

    .dropdown-submit:hover {
        background-color: #45a049;
    }

    button,
    input[type="text"] {
        padding: 10px 16px;
        font-size: 16px;
        border-radius: 5px;
        border: 1px solid #ccc;
    }

    button {
        background-color: #4CAF50;
        color: white;
        border: none;
    }

    button:hover {
        background-color: #45a049;
    }

    .forbidden-cursor {
        cursor: not-allowed;
    }

    .column-header {
        display: flex;
        align-items: center;
        justify-content: flex-start;
        padding: 0;
        gap: 5px;
    }

    .sort-buttons {
        display: flex;
        flex-direction: column;
        margin: left 0;
    }

    .sort-buttons .btn {
        padding: 0;
        margin: 0;
        line-height: 1;
    }

    .sort-buttons .btn a {
        color: white;
    }

    .upload-container {
        width: 50%;
        margin: 40px auto;
        padding: 20px;
        background-color: #f9f9f9;
        border: 1px solid #ddd;
        border-radius: 10px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        text-align: center;
    }

    .upload-container h2 {
        margin-bottom: 20px;
    }

    .drag-area {
        width: 100%;
        padding: 20px;
        border: 2px dashed #4CAF50;
        border-radius: 10px;
        background-color: #f0f0f0;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }

    .drag-area:hover {
        background-color: #e9f7ef;
    }

    .drag-text {
        color: #666;
        font-size: 16px;
    }

    .upload-input {
        display: none;
    }

    .file-name {
        margin-top: 15px;
        font-size: 14px;
        color: #333;
    }

    .upload-button {
        margin-top: 20px;
        width: 100%;
        padding: 10px;
        background-color: #4CAF50;
        color: #fff;
        border: none;
        border-radius: 5px;
        cursor: pointer;
    }

    .upload-button:disabled {
        background-color: #aaa;
        cursor: not-allowed;
    }

    .upload-status {
        margin-top: 20px;
    }

    .upload-message {
        font-size: 16px;
        color: #666;
    }

    .notification-button-form {
    position: absolute;
    top: 10px; /* Sesuaikan nilai ini untuk posisi vertikal */
    right: 10px; /* Sesuaikan nilai ini untuk posisi horizontal */
}
    </style>
</head>

<body>

<?php if ($adminAccess) : ?>
    <form action="notifications.php" method="get" class="notification-button-form">
        <button type="submit">Go to Notification Page</button>
    </form>
<?php endif; ?>
    <h1>Anda masuk sebagai: <?php echo $loggedIn ? $_SESSION['user'] : 'Visitor'; ?></h1>

    <div class="form-container">
        <!-- Jika admin, tampilkan menu admin -->
        <?php if ($adminAccess) : ?>
        <div class="form-item">
            <form id="columnForm" action="index.php" method="POST">
                <div class="dropdown">
                    <label for="dropdownButton">Select Columns</label>
                    <button type="button" class="dropbtn" id="dropdownButton">Select Columns</button>
                    <div class="dropdown-content" id="dropdownContent" style="display: none;">
                        <?php foreach ($columnNames as $column): ?>
                        <label>
                            <input type="checkbox" name="columns[]" value="<?php echo $column; ?>"
                                <?php echo in_array($column, $selectedColumns) ? 'checked' : ''; ?>
                                class="column-checkbox">
                            <?php echo $column; ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </form>
        </div>



        <div class="form-item">
            <form action="switch.php" method="POST">
                <label for="table">Pilih Tabel:</label>
                <select name="table" id="table" class="styled-dropdown" onchange="this.form.submit()">
                    <option value="days" <?php echo $currentTable == 'days' ? 'selected' : ''; ?>>Days</option>
                    <option value="hours" <?php echo $currentTable == 'hours' ? 'selected' : ''; ?>>Hours</option>
                </select>
            </form>
        </div>

        <form method="POST" action="index.php">
            <label for="id_column">Pilih Kolom ID:</label>
            <select name="selected_id_column" id="id_column" class="styled-dropdown" onchange="this.form.submit()">
                <?php foreach ($selectedColumns as $column): ?>
                <option value="<?php echo htmlspecialchars($column); ?>"
                    <?php if ($column == $selectedIdColumn) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($column); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>



        <?php endif; ?>

        <!-- Dropdown untuk memilih limit data -->
        <div class="form-item">
            <form id="limitForm" action="updateLimit.php" method="POST">
                <label for="limit">Select Data Limit:</label>
                <select id="limit" name="limit" class="styled-dropdown" onchange="this.form.submit()">
                    <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10</option>
                    <option value="20" <?php echo $limit == 20 ? 'selected' : ''; ?>>20</option>
                    <option value="30" <?php echo $limit == 30 ? 'selected' : ''; ?>>30</option>
                </select>
            </form>
        </div>

        <!-- Form for searching data by selected column -->
        <div class="form-item">
            <form id="searchForm" action="" method="GET">
                <label for="searchColumn">Search by:</label>
                <select id="searchColumn" name="searchColumn" class="styled-dropdown">
                    <?php foreach ($selectedColumns as $column) : ?>
                    <option value="<?php echo $column; ?>" <?php echo ($searchColumn == $column) ? 'selected' : ''; ?>>
                        <?php echo $column; ?>
                    </option>
                    <?php endforeach; ?>
                </select>

                <label for="searchValue" style="margin-top: 10px;">Search Value:</label>
                <input type="text" id="searchValue" name="searchValue"
                    value="<?php echo htmlspecialchars($searchValue); ?>" required>

                <button type="submit" style="margin-top: 10px;">Search</button>

                <!-- Tombol Reset hanya ditampilkan jika sedang menampilkan hasil pencarian -->
                <?php if ($searchColumn !== null && $searchValue !== null): ?>
                <a href="?reset=true" class="reset-btn" style="margin-left: 10px; margin-top: 10px;">
                    <button type="button">Reset</button>
                </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div id="tableContainer">
        <?php 
if (empty($data)) {
    echo "No data found in the table.";
} else {
    // Display the table with data
    echo "<table class='table'>";
    echo "<thead>";
    echo "<tr>";
    foreach ($selectedColumns as $column) {
        echo "<th>";
        echo "<div class='column-header'>";
    echo $column;
    echo "<div class='sort-buttons'>";
    echo "<button class='btn btn-link'><a href='?sort=$column&order=asc&page=$page$searchParams'><i class='fas fa-sort-up'></i></a></button>";
    echo "<button class='btn btn-link'><a href='?sort=$column&order=desc&page=$page$searchParams'><i class='fas fa-sort-down'></i></a></button>";
    echo "</div>";
    echo "</div>";
    echo "</th>";
    }
    echo "<th>Action</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";
    foreach ($data as $row) {
        echo "<tr>";
        foreach ($selectedColumns as $column) {
            echo "<td>" . htmlspecialchars($row[$column]) . "</td>";
        }

        // Add the Edit button, but don't redirect, let AJAX handle it
        echo "<td><button data-id='" . htmlspecialchars($row[$selectedIdColumn]) . "' class='btn btn-primary edit-btn'>Edit</button></td>";
        echo "</tr>";
    }
    echo "</tbody>";
    echo "</table>";
}
?>
    </div>

    <!-- Modal HTML -->
    <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="editModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Edit Data</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- Form will be loaded here via AJAX -->
                    <form id="editForm">
                        <!-- Dynamic content goes here -->
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveChanges">Save changes</button>
                </div>
            </div>
        </div>
    </div>



    <!-- Pagination -->
    <div class="pagination">
        <ul>
            <?php
        // Link "Previous"
        if ($page > 1) : ?>
            <li><a href="?page=<?php echo $page - 1 . $searchParams; ?>">Previous</a></li>
            <?php else : ?>
            <li><span class="disabled">Previous</span></li>
            <?php endif; ?>

            <?php if ($page > 3) : ?>
            <li><a href="?page=1<?php echo $searchParams; ?>">1</a></li>
            <li><span class="ellipsis">...</span></li>
            <?php endif; ?>

            <?php for ($i = max(1, $page - 2); $i <= min($page + 2, $totalPages); $i++) : ?>
            <?php if ($i === $page) : ?>
            <li><a class="active"><?php echo $i; ?></a></li>
            <?php else : ?>
            <li><a href="?page=<?php echo $i . $searchParams; ?>"><?php echo $i; ?></a></li>
            <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $totalPages - 2) : ?>
            <li><span class="ellipsis">...</span></li>
            <li><a href="?page=<?php echo $totalPages . $searchParams; ?>"><?php echo $totalPages; ?></a></li>
            <?php endif; ?>

            <!-- Link "Next" -->
            <?php if ($page < $totalPages) : ?>
            <li><a href="?page=<?php echo $page + 1 . $searchParams; ?>">Next</a></li>
            <?php else : ?>
            <li><span class="disabled">Next</span></li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Tampilkan tombol Login/Logout sesuai dengan status login -->
    <div style="text-align: center; margin-top: 20px;">
        <?php if ($loggedIn): ?>
        <a href="logout.php">Logout</a>
        <?php else: ?>
        <a href="login.php">Login</a>
        <?php endif; ?>
    </div>

    <h2>Upload File</h2>
    <form action="upload.php" method="post" enctype="multipart/form-data" id="uploadForm">
        <div class="drag-area" id="dragArea">
            <span class="drag-text">Drag & Drop atau Klik untuk Pilih File CSV/Excel</span>
            <input type="file" name="file" id="fileInput" class="upload-input" accept=".csv, .xlsx">
        </div>
        <div class="file-name" id="fileName"></div>
        <input type="submit" value="Upload" class="upload-button" id="uploadButton" disabled>
    </form>
    <div class="upload-status">
        <?php if (isset($_SESSION['upload_status'])) : ?>
        <p class="upload-message"><?= $_SESSION['upload_status'] ?></p>
        <?php endif; ?>
    </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>

    <script>
    // Get dropdown elements
    const dropdownButton = document.getElementById('dropdownButton');
    const dropdownContent = document.getElementById('dropdownContent');

    // Toggle dropdown on button click (dropdown for selectedColumns)
    if (dropdownButton && dropdownContent) {
        dropdownButton.addEventListener('click', function(event) {
            event.preventDefault(); // Prevent form submission when clicking the button
            dropdownContent.style.display = (dropdownContent.style.display === 'none' || dropdownContent.style
                .display === '') ? 'block' : 'none';
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const isClickInsideDropdown = dropdownButton.contains(event.target) || dropdownContent.contains(
                event.target);
            const isEditButton = event.target.classList.contains('edit-btn');

            // Only close dropdown if the click is outside and it's not an edit button
            if (!isClickInsideDropdown && !isEditButton) {
                dropdownContent.style.display = 'none';
            }
        });
    }

    // Edit button logic
    $(document).ready(function() {
        var selectedId = null; // Declare a variable to store the ID

        // Handle checkbox changes
        $(document).on('change', '.column-checkbox', function() {
            console.log('Checkbox changed'); // Debug log
            var formData = $('#columnForm').serialize();

            $.ajax({
                url: 'update_columns.php',
                method: 'POST',
                data: formData,
                success: function(response) {
                    console.log('AJAX success'); // Debug log
                    // Refresh only the table content
                    $('#tableContainer').html(response);
                },
                error: function(xhr, status, error) {
                    console.log("AJAX Error: " + error);
                    alert('Failed to update columns. Please try again.');
                }
            });
        });

        // When "Edit" button is clicked
        $('body').on('click', '.edit-btn', function(e) {
            e.preventDefault(); // Prevent default link behavior

            selectedId = $(this).data('id'); // Get the ID from the button's data-id attribute

            // Debug: Check if the ID is being correctly fetched
            console.log("Selected ID: " + selectedId);

            if (!selectedId) {
                alert('ID tidak ditemukan!');
                return;
            }

            // Load the edit form via AJAX into the modal
            $.ajax({
                url: 'edit.php',
                method: 'GET',
                data: {
                    id: selectedId
                }, // Pass the ID to the server
                success: function(response) {
                    console.log("AJAX response: " + response);
                    $('#editForm').html(response); // Load form content
                    $('#editModal').modal('show'); // Show the modal
                },
                error: function(xhr, status, error) {
                    console.log("AJAX Error: " + error);
                    alert('Gagal memuat form. Silakan coba lagi.');
                }
            });
        });

        // When "Save changes" button is clicked
        $('#saveChanges').on('click', function() {

            var formData = $('#editForm').serialize(); // Serialize form data
            formData += '&id=' + selectedId; // Add the selectedId to the form data

            // Debugging: Log the formData to see what's being sent
            console.log("Form Data being sent: " + formData);

            $.ajax({
                url: 'edit.php',
                method: 'POST',
                data: formData,
                success: function(response) {
                    alert(response); // Show the response from the server
                    $('#editModal').modal('hide'); // Hide the modal
                    location.reload(); // Reload the page to reflect changes
                },
                error: function(xhr, status, error) {
                    console.log("AJAX Error: " + error);
                    alert('Gagal menyimpan perubahan. Silakan coba lagi.');
                }
            });
        });
    });

    // upload excel/csv
    document.addEventListener('DOMContentLoaded', function() {
        const dragArea = document.getElementById('dragArea');
        const fileInput = document.getElementById('fileInput');
        const fileNameDisplay = document.getElementById('fileName');
        const uploadButton = document.getElementById('uploadButton');

        // Klik untuk memilih file
        dragArea.addEventListener('click', function() {
            fileInput.click();
        });

        // Ketika file dipilih
        fileInput.addEventListener('change', function() {
            if (fileInput.files.length > 0) {
                const fileName = fileInput.files[0].name;
                fileNameDisplay.textContent = 'File dipilih: ' + fileName;
                uploadButton.disabled = false; // Aktifkan tombol upload
            }
        });

        // Drag-and-drop event
        dragArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            dragArea.style.backgroundColor = '#e9f7ef';
        });

        dragArea.addEventListener('dragleave', function() {
            dragArea.style.backgroundColor = '#f0f0f0';
        });

        dragArea.addEventListener('drop', function(e) {
            e.preventDefault();
            dragArea.style.backgroundColor = '#f0f0f0';
            const file = e.dataTransfer.files[0];
            fileInput.files = e.dataTransfer.files; // Pasang file ke input
            fileNameDisplay.textContent = 'File dipilih: ' + file.name;
            uploadButton.disabled = false; // Aktifkan tombol upload
        });
    });
    </script>
</body>

</html>