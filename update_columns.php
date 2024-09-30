<?php
session_start();
include 'db_connect.php';

// DEBUG
error_log('update_columns.php accessed');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['columns'])) {
    // Debug
    error_log('POST request received with columns');
    error_log('Columns: ' . print_r($_POST['columns'], true));

    $_SESSION['selectedColumns'] = $_POST['columns'];
    $selectedColumns = $_POST['columns'];
    // $currentTable = $_SESSION['currentTable'];

    // if (!isset($_SESSION['currentTable'])) {
    //     error_log('Current table not set in session');
    //     echo "Error: Current table not set";
    //     exit;
    // }

    if (isset($_SESSION['currentTable'])) {
        $currentTable = $_SESSION['currentTable'];
    } else {
        // Jika tidak ada tabel yang dipilih dan tidak ada dalam session, ambil tabel terakhir yang diupdate
        if (file_exists($jsonFilePath)) {
            $jsonData = file_get_contents($jsonFilePath);
            $selectedColumnsData = json_decode($jsonData, true);
    
            // Urutkan berdasarkan updated_at
            usort($selectedColumnsData, function($a, $b) {
                return strtotime($b['updated_at']) - strtotime($a['updated_at']);
            });
    
            // Ambil tabel dengan updated_at terakhir
            if (!empty($selectedColumnsData)) {
                $currentTable = $selectedColumnsData[0]['table_name'];
            } else {
                $currentTable = 'days'; // Default tabel jika file kosong
            }
        } else {
            $currentTable = 'days'; // Default tabel jika file JSON tidak ada
        }
    }

    // Update JSON file
    $jsonFilePath = 'selected_columns.json';
    if (file_exists($jsonFilePath)) {
        $jsonData = file_get_contents($jsonFilePath);
        $selectedColumnsData = json_decode($jsonData, true);
    } else {
        $selectedColumnsData = array();
    }

    // $currentTable = $_SESSION['currentTable'];
    $foundTable = false;
    foreach ($selectedColumnsData as &$table) {
        if ($table['table_name'] == $currentTable) {
            $table['column_names'] = implode(',', $selectedColumns);
            $table['updated_at'] = date('Y-m-d H:i:s');
            $foundTable = true;
            break;
        }
    }

    if (!$foundTable) {
        $selectedColumnsData[] = array(
            'table_name' => $currentTable,
            'column_names' => implode(',', $selectedColumns),
            'selected_ID' => isset($_SESSION['selected_id_column']) ? $_SESSION['selected_id_column'] : 'instant',
            'updated_at' => date('Y-m-d H:i:s')
        );
    }

    file_put_contents($jsonFilePath, json_encode($selectedColumnsData));

    // Fetch updated table content
    $limit = isset($_SESSION['limit']) ? intval($_SESSION['limit']) : 10;
    $page = isset($_SESSION['page']) ? intval($_SESSION['page']) : 1;
    $offset = ($page - 1) * $limit;

    // Handle search if it exists
    $searchQuery = "";
    if (isset($_SESSION['searchColumn']) && isset($_SESSION['searchValue'])) {
        $searchColumn = $_SESSION['searchColumn'];
        $searchValue = $_SESSION['searchValue'];
        if (is_numeric($searchValue)) {
            $tolerance = 0.0001;
            $searchValue = floatval($searchValue);
            $searchQuery = " WHERE $searchColumn BETWEEN " . ($searchValue - $tolerance) . " AND " . ($searchValue + $tolerance);
        } else {
            $searchQuery = " WHERE $searchColumn = '" . $conn->real_escape_string($searchValue) . "'";
        }
    }

    // Pastikan ada kolom yang dipilih
    if (empty($selectedColumns)) {
        error_log('No columns selected');
        echo "Error: No columns selected";
        exit;
    }

    $sql = "SELECT " . implode(',', $selectedColumns) . " FROM $currentTable" . $searchQuery . " LIMIT $limit OFFSET $offset";
    // Debug 
    error_log('SQL Query: ' . $sql);

    $result = $conn->query($sql);

    if ($result) {
        echo "<table class='table'>";
        echo "<thead><tr>";
        foreach ($selectedColumns as $column) {
            echo "<th>";
            echo $column;
            echo "<button class='btn btn-link'><a href='?sort=$column&order=asc&page=$page$searchParams'><i class='fas fa-sort-up'></i></a></button>";
            echo "<button class='btn btn-link'><a href='?sort=$column&order=desc&page=$page$searchParams'><i class='fas fa-sort-down'></i></a></button>";
            echo "</th>";
        }
        echo "<th>Action</th>";
        echo "</tr></thead><tbody>";
        
        while($row = $result->fetch_assoc()) {
            echo "<tr>";
            foreach ($selectedColumns as $column) {
                echo "<td>" . htmlspecialchars($row[$column]) . "</td>";
            }
            $selectedIdColumn = isset($_SESSION['selected_id_column']) ? $_SESSION['selected_id_column'] : 'instant';
            echo "<td><button data-id='" . htmlspecialchars($row[$selectedIdColumn]) . "' class='btn btn-primary edit-btn'>Edit</button></td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
    } else {
        // Debug 
        error_log('Error executing query: ' . $conn->error);
        echo "Error fetching data: " . $conn->error;
    }
} else {
    // Debug
    error_log('Invalid request to update_columns.php');
    echo "Invalid request";
}

$conn->close();
?>