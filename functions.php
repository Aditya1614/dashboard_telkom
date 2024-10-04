<?php
include 'db_connect.php';
function getCurrentTable() {
    // Pastikan session telah dimulai
    // if (session_status() === PHP_SESSION_NONE) {
    //     session_start();
    // }

    // Tentukan jalur file JSON
    $jsonFilePath = 'selected_columns.json';

    // Tentukan $currentTable menggunakan logika yang sama
    if (isset($_SESSION['currentTable'])) {
        return $_SESSION['currentTable'];
    } else {
        if (file_exists($jsonFilePath)) {
            $jsonData = file_get_contents($jsonFilePath);
            $selectedColumnsData = json_decode($jsonData, true);
            usort($selectedColumnsData, function($a, $b) {
                return strtotime($b['updated_at']) - strtotime($a['updated_at']);
            });
            if (!empty($selectedColumnsData)) {
                return $selectedColumnsData[0]['table_name'];
            } else {
                return 'days'; // Default tabel
            }
        } else {
            return 'days'; // Default tabel
        }
    }
}

function isUniqueIdColumn($conn, $currentTable, $selectedIdColumn) {
    $sqlCheckUnique = "SELECT 
        CASE 
            WHEN COUNT(*) = COUNT(DISTINCT `$selectedIdColumn`) THEN 'Unique' 
            ELSE 'Not Unique' 
        END AS uniqueness_check 
    FROM `$currentTable`;";

    $resultCheck = $conn->query($sqlCheckUnique);

    if ($resultCheck && $row = $resultCheck->fetch_assoc()) {
        return $row['uniqueness_check'] === 'Unique';
    } else {
        die("Error on uniqueness check query: " . $conn->error);
    }
}
?>