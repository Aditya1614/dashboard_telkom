<?php
session_start();
include 'db_connect.php';

// Cek apakah user sudah login
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

if (isset($_SESSION['selectedColumns'])) {
    $selectedColumns = $_SESSION['selectedColumns'];
} else {
    // Handle the case when selectedColumns is not set
    $selectedColumns = array(); // Or provide a default array of columns
}


// Ambil tabel saat ini dari session, default adalah 'days'
if (isset($_SESSION['currentTable'])) {
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

// Ambil kolom ID yang dipilih (default = instant)
$selectedIdColumn = isset($_SESSION['selected_id_column']) ? $_SESSION['selected_id_column'] : 'instant';

// Ambil ID dari URL dengan parameter tetap 'id'
$selectedIdValue = isset($_GET['id']) ? $_GET['id'] : (isset($_GET['instant']) ? $_GET['instant'] : null);

// Cek apakah ID tersedia di URL
if (is_null($selectedIdValue)) {
    echo "ID tidak ditemukan di URL.";
    exit();
}

// Cek apakah nilai kolom ID unik
// $sqlCheckUnique = "SELECT 
//     CASE 
//         WHEN COUNT(*) = COUNT(DISTINCT $selectedIdColumn) THEN 'Unique' 
//         ELSE 'Not Unique' 
//     END AS uniqueness_check 
// FROM $currentTable;";

// $resultCheck = $conn->query($sqlCheckUnique);

// if ($resultCheck && $row = $resultCheck->fetch_assoc()) {
//     if ($row['uniqueness_check'] === 'Not Unique') {
//         echo "<script>alert('Kolom yang dipilih untuk ID tidak unik. Silakan pilih kolom lain.');</script>";
//     }
// }

// // Cek apakah query berhasil
// if (!$resultCheck) {
//     die("Error pada query pengecekan unik: " . $conn->error);
// }

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

// Bind the selected ID value (assuming it's a string, use "i" for integers)
$stmt->bind_param("s", $selectedIdValue);

// Execute the statement
$stmt->execute();

// Store the result manually for PHP 5.3
$stmt->store_result();

if ($stmt->num_rows == 0) {
    // echo "Table: $currentTable, Column: $selectedIdColumn, Value: $selectedIdValue<br>";
    // echo "Data not found.";
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

// Handle the case where there is no data
if (!$stmt->num_rows) {
    // echo "Table: $currentTable, Column: $selectedIdColumn, Value: $selectedIdValue<br>";
    // echo "Data not found.";
    exit();
}

// Debug output (optional)
// echo "Table: $currentTable, Column: $selectedIdColumn, Value: $selectedIdValue, SelectedColumns: $selectedColumns<br>";
// 


// $item = $result->fetch_assoc();


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updates = array();
    foreach ($_SESSION['selectedColumns'] as $column) {
        $value = $_POST[$column];
        $updates[] = "$column = ?";
    }
    $updateQuery = implode(", ", $updates);

    $sqlUpdate = "UPDATE $currentTable SET $updateQuery WHERE $selectedIdColumn = ?";
    $stmt = $conn->prepare($sqlUpdate);

    if ($stmt === false) {
        die("Error preparing SQL update statement: " . $conn->error);
    }

    // Bind values individually
    $params = array();
    foreach ($_SESSION['selectedColumns'] as $column) {
        $params[] = $_POST[$column];  // Add form values to params
    }
    $params[] = $selectedIdValue;  // Add the ID value at the end

    // Create a string of types based on the number of parameters (assuming all are strings)
    $types = str_repeat('s', count($params));

    // Prepare the parameters for binding (create an array of references)
    $bindParams = array_merge(array($types), $params);
    $refs = array();
    foreach ($bindParams as $key => $value) {
        $refs[$key] = &$bindParams[$key]; // Pass by reference
    }

    // Bind the parameters
    call_user_func_array(array($stmt, 'bind_param'), $refs);

    // Execute the statement
    if ($stmt->execute()) {
        header("Location: index.php");
        exit();
    } else {
        // echo "Error updating record: " . $stmt->error;
    }
}
 
// Debug
// echo "Table: $currentTable, Column: $selectedIdColumn, Value: $selectedIdValue<br>";


$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Bike Sharing Data</title>
    <style>
    <head><meta charset="UTF-8"><meta name="viewport"content="width=device-width, initial-scale=1.0"><title>Edit Bike Sharing Data</title><style>body {
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

    .not-editable {
        background-color: #f2f2f2;
        color: #888;
        cursor: not-allowed;
    }
    </style>
</head>

</style>
</head>

<body>
    <h1>Edit Bike Sharing Data</h1>
    <form action="edit.php?id=<?php echo $selectedIdValue ?>" method="POST">
        <?php foreach ($selectedColumns as $column): ?>
        <?php if (isset($item[$column])): ?>
        <?php if ($column === 'dteday'): ?>
        <label for="dteday">Date (dteday):</label>
        <input type="date" id="dteday" name="dteday"
            value="<?php echo htmlspecialchars(date('Y-m-d', strtotime($item['dteday']))) ?>">
        <?php elseif ($column === $selectedIdColumn): ?>
        <label for="<?php echo htmlspecialchars($column) ?>"><?php echo htmlspecialchars($column) ?>:</label>
        <input type="text" id="<?php echo htmlspecialchars($column) ?>"
            value="<?php echo htmlspecialchars($item[$column]) ?>" disabled class="not-editable">
        <input type="hidden" name="<?php echo htmlspecialchars($column) ?>"
            value="<?php echo htmlspecialchars($item[$column]) ?>">
        <?php else: ?>


        <label for="<?php echo htmlspecialchars($column) ?>"><?php echo htmlspecialchars($column) ?>:</label>
        <input type="text" id="<?php echo htmlspecialchars($column) ?>" name="<?php echo htmlspecialchars($column) ?>"
            value="<?php echo htmlspecialchars($item[$column]) ?>">
        <?php endif; ?>
        <?php else: ?>
        <input type="hidden" id="<?php echo htmlspecialchars($column) ?>" name="<?php echo htmlspecialchars($column) ?>"
            value="<?php echo htmlspecialchars($item[$column]) ?>">
        <?php endif; ?>
        <?php endforeach; ?>

        <button type="submit">Save</button>
    </form>
</body>

</html>