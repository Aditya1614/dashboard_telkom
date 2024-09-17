<?php
session_start();
include 'db_connect.php'; // Pastikan jalur ke file koneksi benar

// Cek apakah ada tabel yang dipilih dari form
// Path ke file JSON
$jsonFilePath = 'selected_columns.json';

function getPrimaryKey($tableName, $conn) {
    $sql = "SHOW KEYS FROM $tableName WHERE Key_name = 'PRIMARY'";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['Column_name']; // Mengembalikan kolom primary key
    }
    return null; // Jika tidak ada primary key
}

// Fungsi untuk membaca JSON selected_columns.json
function getSelectedColumnsJSON($jsonFilePath) {
    if (file_exists($jsonFilePath)) {
        $jsonData = file_get_contents($jsonFilePath);
        return json_decode($jsonData, true);
    }
    return array(); // Kembalikan array kosong jika file tidak ada
}

// Fungsi untuk menulis ke JSON selected_columns.json
function writeSelectedColumnsJSON($jsonFilePath, $data) {
    $jsonData = json_encode($data, JSON_PRETTY_PRINT);
    if (file_put_contents($jsonFilePath, $jsonData) === false) {
        die("Error: Gagal menulis ke file JSON.");
    }
}

// Fungsi untuk menemukan tabel dalam JSON
function findTableInJSON(&$selectedColumnsData, $currentTable) {
    foreach ($selectedColumnsData as &$table) {
        if ($table['table_name'] === $currentTable) {
            return $table;
        }
    }
    return null;
}
// Cek apakah ada tabel yang dipilih dari form
if (isset($_POST['table'])) {
    $currentTable = $_POST['table'];
    $_SESSION['currentTable'] = $currentTable;
    unset($_SESSION['selectedColumns']); // Reset selectedColumns

    // Baca file JSON
    $selectedColumnsData = getSelectedColumnsJSON($jsonFilePath);

    // Cari tabel dalam file JSON
    $table = findTableInJSON($selectedColumnsData, $currentTable);
    $updateJson = false;

    if ($table) {
        // Jika tabel sudah ada, hanya update updated_at
        $table['updated_at'] = date('Y-m-d H:i:s');
        // Jika selected_ID belum ada, tetapkan
        if (!isset($table['selected_ID'])) {
            $table['selected_ID'] = getSelectedID($currentTable, $conn);
            $updateJson = true;
        }
    } else {
        // Jika tabel belum ada, tambahkan ke JSON
        // Dapatkan semua kolom dari tabel yang dipilih
        $sqlColumns = "SHOW COLUMNS FROM `$currentTable`";
        $resultColumns = $conn->query($sqlColumns);
        if (!$resultColumns) {
            die("Error retrieving columns: " . $conn->error);
        }

        $columns = array();
        while ($row = $resultColumns->fetch_assoc()) {
            $columns[] = $row['Field'];
        }

        // Gabungkan nama kolom menjadi string
        $columnNames = implode(',', $columns);

        // Dapatkan selected_ID, baik dari admin maupun default primary key
        $selectedID = getSelectedID($currentTable, $conn);
        if (!$selectedID) {
            die("Error: Tidak dapat menentukan selected_ID untuk tabel '$currentTable'.");
        }

        // Tambahkan tabel baru ke array
        $newTable = array(
            'table_name' => $currentTable,
            'column_names' => $columnNames,
            'selected_ID' => $selectedID,
            'updated_at' => date('Y-m-d H:i:s')
        );
        $selectedColumnsData[] = $newTable;
        $updateJson = true;
    }

    // Jika ada perubahan, tulis kembali ke file JSON
    if ($updateJson) {
        writeSelectedColumnsJSON($jsonFilePath, $selectedColumnsData);
    }

    // Redirect kembali ke halaman utama tanpa output apapun sebelumnya
    header('Location: index.php');
    exit();
} else {
    // Jika tidak ada tabel yang dipilih, redirect ke halaman utama
    header('Location: index.php');
    exit();
}
?>