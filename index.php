<?php 
session_start();

// Cek apakah user sudah login
$loggedIn = isset($_SESSION['user']);

// Jika belum login, beri akses halaman tanpa hak admin
$role = $loggedIn ? $_SESSION['role'] : null;
$adminAccess = ($role === 'admin');

// Jika ingin mengarahkan ke halaman login untuk admin, uncomment baris berikut:
// if (!$loggedIn && !$adminAccess) {
//     header("Location: login.php");
//     exit();
// }

include 'db_connect.php';

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

// Debugging: Lihat session saat ini
// var_dump($_SESSION['currentTable']);

// Ambil tabel saat ini dari session, default adalah 'days'
// $currentTable = isset($_SESSION['currentTable']) ? $_SESSION['currentTable'] : 'days';
// Query untuk mendapatkan tabel yang terakhir diupdate
// Jika user memilih tabel dari form, gunakan tabel tersebut
// Handle switch table dari switch.php
if (isset($_POST['table'])) {
    $currentTable = $_POST['table'];
    $_SESSION['currentTable'] = $currentTable;
    // unset($_SESSION['selectedColumns']);
    // session_destroy();
} elseif (isset($_SESSION['currentTable'])) {
    $currentTable = $_SESSION['currentTable'];
} else {
    $sqlLastUpdatedTable = "SELECT table_name FROM selected_columns ORDER BY updated_at DESC LIMIT 1";
    $resultLastUpdatedTable = $conn->query($sqlLastUpdatedTable);

    if ($resultLastUpdatedTable && $rowLastUpdatedTable = $resultLastUpdatedTable->fetch_assoc()) {
        $currentTable = $rowLastUpdatedTable['table_name'];
    } else {
        $currentTable = 'days'; // Default tabel
    }
}

// Ambil kolom dari tabel yang dipilih
if (!isset($_SESSION['selectedColumns'])) {
    $sql = "SELECT column_names FROM selected_columns WHERE table_name = '$currentTable'";
    $result = $conn->query($sql);

    if ($result && $row = $result->fetch_assoc()) {
        $_SESSION['selectedColumns'] = explode(',', $row['column_names']);
    } else {
        // Jika tidak ada data di selected_columns, gunakan semua kolom dari tabel aktif
        $columnResult = $conn->query("SHOW COLUMNS FROM $currentTable");
        $columnNames = array();
        if ($columnResult) {
            while ($row = $columnResult->fetch_assoc()) {
                $columnNames[] = $row['Field'];
            }
            $_SESSION['selectedColumns'] = $columnNames; // Set ke semua kolom jika tidak ada di database
        } else {
            die("Error retrieving columns: " . $conn->error);
        }
    }
}
$selectedColumns = $_SESSION['selectedColumns'];


// Debugging: tampilkan nama tabel yang dipilih
echo "<p>Tabel yang terakhir diupdate: " . $currentTable . "</p>"; 
echo "<p>Tabel yang dipilih: " . $currentTable . "</p>";  // Debugging: tampilkan nama tabel yang aktif
echo "<ul>";
foreach ($_SESSION['selectedColumns'] as $column) {
    echo "<li>$column</li>";
}
echo "</ul>";

echo $currentTable;

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
}

// Kolom ID yang dipilih
$selectedIdColumn = isset($_SESSION['selected_id_column']) ? $_SESSION['selected_id_column'] : 'instant';

// Handle selected columns
if ($adminAccess) {
    if (isset($_POST['columns'])) {
        $_SESSION['selectedColumns'] = $_POST['columns'];
        $selectedColumns = $_POST['columns'];
    } elseif (isset($_SESSION['selectedColumns']) && !empty($_SESSION['selectedColumns'])) {
        $selectedColumns = $_SESSION['selectedColumns'];
    } else {
        // Ambil dari selected_columns jika belum ada di session
        $sql = "SELECT column_names FROM selected_columns WHERE table_name = '$currentTable'";
        $result = $conn->query($sql);
        if ($row = $result->fetch_assoc()) {
            $selectedColumns = explode(',', $row['column_names']);
        } else {
            $selectedColumns = $columnNames; // Default ke semua kolom
        }
    }
} else {
    // Ambil kolom yang disimpan di selected_columns jika user non-admin
    $sql = "SELECT column_names FROM selected_columns WHERE table_name = '$currentTable'";
    $result = $conn->query($sql);
    if ($row = $result->fetch_assoc()) {
        $selectedColumns = explode(',', $row['column_names']);
    } else {
        $selectedColumns = $columnNames; // Default ke semua kolom
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
    .styled-dropdown,
    .dropbtn {
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

    .styled-dropdown:hover,
    .dropbtn:hover {
        background-color: #45a049;
    }

    /* Mengatur dropdown content agar lebih rapi */
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
    </style>
</head>

<body>
    <h1>Anda masuk sebagai: <?php echo $loggedIn ? $_SESSION['user'] : 'Visitor'; ?></h1>

    <div class="form-container">
        <!-- Jika admin, tampilkan menu admin -->
        <?php if ($adminAccess) : ?>
        <div class="form-item">
            <form id="columnForm" action="show.php" method="POST">
                <div class="dropdown">
                    <label for="columns">Select Columns</label>
                    <button class="dropbtn">Select Columns</button>
                    <div class="dropdown-content">
                        <?php foreach ($columnNames as $column): ?>
                        <label>
                            <input type="checkbox" name="columns[]" value="<?php echo $column; ?>"
                                <?php echo in_array($column, $selectedColumns) ? 'checked' : ''; ?>>
                            <?php echo $column; ?>
                        </label><br>
                        <?php endforeach; ?>
                        <input type="submit" value="Update" class="dropdown-submit">
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
            <select name="selected_id_column" id="id_column" class="styled-dropdown"  onchange="this.form.submit()">
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
    <?php 
    if (empty($data)) {
        echo "No data found in the table.";
    } else {
        // Display the table with data
    echo "<table>";
    echo "<thead>";
    echo "<tr>";
    foreach ($selectedColumns as $column) {
        echo "<th>$column</th>";
    }
    if ($loggedIn){
        echo "<th>Action</th>";
    }
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";
    foreach ($data as $row) {
        echo "<tr>";
        foreach ($selectedColumns as $column) {
            echo "<td>" . htmlspecialchars($row[$column]) . "</td>";
        }
        if ($loggedIn){
            echo "<td><a href=\"edit.php?id=" . urlencode($row[$selectedIdColumn]) . "&columns=" . urlencode(implode(',', $selectedColumns)) . "\">Edit</a></td>";

        }
        echo "</tr>";
    }
    echo "</tbody>";
    echo "</table>";
    }
    ?>

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
</body>

</html>