<?php

session_start();
include 'db_connect.php';
include 'functions.php';

$currentTable = getCurrentTable();
$selectedIdColumn = isset($_SESSION['selected_id_column']) ? $_SESSION['selected_id_column'] : 'instant'; // default ID
function uploadFile($file, $currentTable, $selectedIdColumn) {
    global $conn; 
    // DEBUG
    if (!$conn) {
        die("Koneksi ke database tidak valid.");
    }

    $allowedExtensions = array('xlsx', 'csv');
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);

    if (!in_array($extension, $allowedExtensions)) {
        showErrorAndRedirect('Ekstensi file tidak diizinkan');
    }

    

    // Proses data
    $errors = array();
    $columnNames = array();

    // Ambil kolom dari tabel saat ini
    $columnResult = $conn->query("SHOW COLUMNS FROM $currentTable");
    if (!$columnResult) {
        showErrorAndRedirect("Gagal mengambil kolom tabel: $currentTable :" . $conn->error);
    }
    while ($row = $columnResult->fetch_assoc()) {
        $columnNames[] = $row['Field'];
    }

    // Cek kolom ID
    if (!in_array($selectedIdColumn, $columnNames)) {
        showErrorAndRedirect('Kolom ID tidak ditemukan');
    }

    $data = array();
    if ($extension == 'xlsx') {
        require_once 'PHPExcel/PHPExcel.php';
        $objPHPExcel = PHPExcel_IOFactory::load($file['tmp_name']);
        $sheetData = $objPHPExcel->getActiveSheet()->toArray(null, true, true, true);
        $data = $sheetData;
    } elseif ($extension == 'csv') {
        $file = fopen($file['tmp_name'], 'r');
        while (($row = fgetcsv($file)) !== FALSE) {
            $data[] = $row;
        }
        fclose($file);
    }

    // Cek data
    foreach ($data as $key => $row) {
        if (count($row) != count($columnNames)) {
            $errors[] = 'Baris ' . ($key + 1) . ' memiliki jumlah kolom yang tidak sesuai';
            continue;
        }

        // Cek kolom ID
        $id = $row[array_search($selectedIdColumn, $columnNames)];
        if (empty($id)) {
            $errors[] = 'Baris ' . ($key + 1) . ' memiliki kolom ID yang kosong';
            continue;
        }

        // Cek duplikat ID
        $query = "SELECT * FROM $currentTable WHERE $selectedIdColumn = '$id'";
        $result = $conn->query($query);
        if ($result->num_rows > 0) {
            $errors[] = 'Baris ' . ($key + 1) . ' memiliki kolom ID yang duplikat';
            continue;
        }

        // Cek tipe data
        foreach ($row as $columnKey => $value) {
            $columnName = $columnNames[$columnKey];
            $query = "SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '$currentTable' AND COLUMN_NAME = '$columnName'";
            $result = $conn->query($query);
            if (!$result) {
                $errors[] = 'Gagal mengambil tipe data kolom ' . $columnName;
                continue;
            }
            $row = $result->fetch_assoc();
            $dataType = $row['DATA_TYPE'];

            if ($dataType == 'int' && !is_numeric($value)) {
                $value = (int) $value; // konversi nilai ke integer
            } elseif ($dataType == 'date' && !strtotime($value)) {
                $value = date('Y-m-d', strtotime($value)); // konversi nilai ke tanggal
            } elseif ($dataType == 'datetime' && !strtotime($value)) {
                $value = date('Y-m-d H:i:s', strtotime($value)); // konversi nilai ke tanggal dan waktu
            }
        }
    }

    // // Cek duplikat ID
    // $query = "SELECT * FROM $currentTable WHERE $selectedIdColumn = '$id'";
    // $result = $conn->query($query);

    // // Periksa hasil query
    // if ($result->num_rows > 0) {
    // echo "Error: Baris " . ($key + 1) . " memiliki kolom ID yang duplikat";
    // echo "Query: " . $query;
    // echo "Hasil Query: ";
    // print_r($result->fetch_assoc());
    // exit;
    // }


    // if (!empty($errors)) {
    //     return array('error' => 'Gagal mengupload file', 'details' => $errors);
    // }

    // Simpan data ke tabel
    foreach ($data as $row) {
        // Debugging output
        echo "Jumlah kolom di database: " . count($columnNames) . "<br>";
        echo "Jumlah nilai dari CSV: " . count($row) . "<br>";
        print_r($columnNames);
        print_r($row);
    
        if (count($row) != count($columnNames)) {
            $errors[] = "Jumlah kolom tidak sesuai pada baris: " . implode(', ', $row);
            continue;
        }

        // Prepare statement
        $stmt = $conn->prepare("INSERT INTO $currentTable (" . implode(', ', $columnNames) . ") VALUES (" . implode(', ', array_fill(0, count($columnNames), '?')) . ")");
        
        if (!$stmt) {
            $errors[] = "Prepare failed: (" . $conn->errno . ") " . $conn->error;
            continue;
        }
    
        // Create an array of references
        $params = array(str_repeat('s', count($columnNames)));
        foreach ($row as $key => $value) {
            $params[] = &$row[$key];
        }
    
        // Bind parameters
        call_user_func_array(array($stmt, 'bind_param'), $params);
    
        // Execute the statement
        if (!$stmt->execute()) {
            $errors[] = "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
            continue;
        }
    
        $stmt->close();
    }

    // $uploadDir = 'file_upload/';
    // $fileName = basename($file['name']);
    // $filePath = $uploadDir . $fileName;

    // if (!move_uploaded_file($file['tmp_name'], $filePath)) {
    //     return array('error' => 'Gagal mengupload file');
    // }

    if (!empty($errors)) {
        $errorMessage = implode("\\n", $errors);
        showErrorAndRedirect($errorMessage);
    } else {
        showSuccessAndRedirect("File berhasil diupload!");
    }
}

function showErrorAndRedirect($message) {
    echo "<script>
        alert('Gagal menambahkan data: " . addslashes($message) . "');
        window.location.href = 'index.php';
    </script>";
    exit;
}

function showSuccessAndRedirect($message) {
    echo "<script>
        alert('" . addslashes($message) . "');
        window.location.href = 'index.php';
    </script>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $file = $_FILES['file'];
    uploadFile($file, $currentTable, $selectedIdColumn);
}
?>