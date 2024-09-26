<?php
session_start();
include 'db_connect.php';

// Handle the session and fetch selectedColumns
$selectedColumns = isset($_SESSION['selectedColumns']) ? $_SESSION['selectedColumns'] : array();


// Ambil tabel saat ini dari session, default adalah 'days'
$jsonFilePath = 'selected_columns.json';
if (isset($_SESSION['currentTable'])) {
    $currentTable = $_SESSION['currentTable'];
} else {
    if (file_exists($jsonFilePath)) {
        $jsonData = file_get_contents($jsonFilePath);
        $selectedColumnsData = json_decode($jsonData, true);
        usort($selectedColumnsData, function($a, $b) {
            return strtotime($b['updated_at']) - strtotime($a['updated_at']);
        });
        $currentTable = !empty($selectedColumnsData) ? $selectedColumnsData[0]['table_name'] : 'days';
    } else {
        $currentTable = 'days';
    }
}

// Ambil kolom ID yang dipilih (default = instant)
$selectedIdColumn = isset($_SESSION['selected_id_column']) ? $_SESSION['selected_id_column'] : 'instant';

// Get ID from URL
$selectedIdValue = isset($_GET['id']) ? $_GET['id'] : null;

if (isset($_POST['id'])) {
    $selectedIdValue = $_POST['id'];
}

if (is_null($selectedIdValue)) {
    echo "ID tidak ditemukan di URL";
    exit();
}


// Check if the selected column is unique
$sqlCheckUnique = "SELECT 
    CASE 
        WHEN COUNT(*) = COUNT(DISTINCT $selectedIdColumn) THEN 'Unique' 
        ELSE 'Not Unique' 
    END AS uniqueness_check 
FROM $currentTable;";

$resultCheck = $conn->query($sqlCheckUnique);

if ($resultCheck && $row = $resultCheck->fetch_assoc()) {
    if ($row['uniqueness_check'] === 'Not Unique') {
        echo "<script>alert('Kolom yang dipilih untuk ID tidak unik. Silakan pilih kolom lain.'); window.location.href='index.php';</script>";
        exit();
    }
}

if (!$resultCheck) {
    die("Error on uniqueness check query: " . $conn->error);
}

// Ensure the selectedIdValue is quoted properly
$sql = "SELECT * FROM $currentTable WHERE $selectedIdColumn = ?";
$stmt = $conn->prepare($sql);

// Check if statement was prepared successfully
if ($stmt === false) {
    die("Error preparing SQL statement: " . $conn->error);
}


// Query the database to get the column type for the selected ID column
$sqlColumnType = "SELECT DATA_TYPE 
                  FROM INFORMATION_SCHEMA.COLUMNS 
                  WHERE TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1";
$stmtType = $conn->prepare($sqlColumnType);
$stmtType->bind_param("ss", $currentTable, $selectedIdColumn);
$stmtType->execute();
$stmtType->bind_result($idColumnType);
$stmtType->fetch();
$stmtType->close();

// Now use the fetched column type to bind the ID value
if (strpos($idColumnType, 'int') !== false) {
    $stmt->bind_param("i", $selectedIdValue); // Bind as integer if the column is of integer type
} else {
    $stmt->bind_param("s", $selectedIdValue); // Bind as string otherwise
}


// Execute the statement
$stmt->execute();

// Store the result manually for PHP 5.3
$stmt->store_result();

if ($stmt->num_rows == 0) {
    exit();
}

// Bind result to variables
$meta = $stmt->result_metadata();
$fields = array();
$data = array();

while ($field = $meta->fetch_field()) {
    $fields[] = &$data[$field->name]; // Bind by reference
}

// Bind result columns to variables
call_user_func_array(array($stmt, 'bind_result'), $fields);

// Fetch the data
$stmt->fetch();

$item = array();
foreach ($data as $key => $value) {
    $item[$key] = $value;
}

// Handle POST request for updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Debugging: Check if ID is received
    // if (!isset($_POST['id'])) {
    //     die('ID tidak ditemukan di POST data!');
    // } else {
    //     $selectedIdValue = $_POST['id']; // Get the ID
    //     echo "Updating for $selectedIdColumn == " . htmlspecialchars($selectedIdValue); // For debugging
    // }

    $updates = array();
    foreach ($selectedColumns as $column) {
        $value = $_POST[$column];
        $updates[] = "$column = ?";
    }
    $updateQuery = implode(", ", $updates);
    
    $sqlUpdate = "UPDATE $currentTable SET $updateQuery WHERE $selectedIdColumn = ?";
    $stmt = $conn->prepare($sqlUpdate);
    if ($stmt === false) {
        die("Error preparing SQL update statement: " . $conn->error);
    }

    // Bind values
    $params = array();
    foreach ($selectedColumns as $column) {
        $params[] = $_POST[$column];
    }
    $params[] = $selectedIdValue;

    // Dynamically bind parameter types
    $types = '';
    foreach ($params as $param) {
        $types .= is_numeric($param) ? 'i' : 's';
    }

    // Bind the values
    $bindParams = array_merge(array($types), $params);
    $refs = array();
    foreach ($bindParams as $key => $value) {
        $refs[$key] = &$bindParams[$key];
    }
    call_user_func_array(array($stmt, 'bind_param'), $refs);

    if ($stmt->execute()) {
        echo " Data updated successfully!";
        exit();
    } else {
        echo "Error updating data.";
        exit();
    }
}

// Render the form for AJAX (GET request)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    ob_start(); 
    ?>
    <div class="form-group">
        <?php foreach ($selectedColumns as $column): ?>
            <?php if (isset($item[$column])): ?>
                <?php if ($column === 'dteday'): ?>
                    <label for="dteday">Date (dteday):</label>
                    <input type="date" id="dteday" name="dteday" class="form-control"
                        value="<?php echo htmlspecialchars(date('Y-m-d', strtotime($item['dteday']))) ?>">
                <?php elseif ($column === $selectedIdColumn): ?>
                    <label for="<?php echo htmlspecialchars($column) ?>"><?php echo htmlspecialchars($column) ?>:</label>
                    <input type="text" id="<?php echo htmlspecialchars($column) ?>"
                        value="<?php echo htmlspecialchars($item[$column]) ?>" disabled class="form-control not-editable forbidden-cursor">
                    <input type="hidden" name="<?php echo htmlspecialchars($column) ?>"
                        value="<?php echo htmlspecialchars($item[$column]) ?>">
                <?php else: ?>
                    <label for="<?php echo htmlspecialchars($column) ?>"><?php echo htmlspecialchars($column) ?>:</label>
                    <input type="text" id="<?php echo htmlspecialchars($column) ?>" name="<?php echo htmlspecialchars($column) ?>"
                        value="<?php echo htmlspecialchars($item[$column]) ?>" class="form-control">
                <?php endif; ?>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php
    echo ob_get_clean();
    exit();
}

$conn->close();
?>
