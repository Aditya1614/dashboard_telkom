<?php
session_start();
include 'db_connect.php'; // Pastikan jalur ke file koneksi benar

// test json
$jsonDir = 'selected_columns.json';

function getselectedTableFromJSON() {
    $file = 'users.json';
    if (!file_exists($file)) {
        die("Users file not found.");
    }
    $jsonData = file_get_contents($file);
    return json_decode($jsonData, true); // Decode JSON to associative array
}

if (isset($_POST['table'])){
    $_SESSION['currentTable'] = $_POST['table'];
    $currentTable = $_POST['table'];
    unset($_SESSION['selectedColumns']);

    $SelectedTableJson = getselectedTableFromJSON();

    $tableFound = false;

    foreach ($SelectedTableJson as $table){
        if ($table['table_name'] == $currentTable){
    }
}
}
// end test

// Cek apakah ada tabel yang dipilih dari form
// Path ke file JSON
$jsonFilePath = 'selected_columns.json';

// Cek apakah ada tabel yang dipilih dari form
if (isset($_POST['table'])) {
    // Simpan tabel yang dipilih ke session
    $_SESSION['currentTable'] = $_POST['table'];
    $currentTable = $_POST['table'];
    unset($_SESSION['selectedColumns']);

    // Baca file JSON
    if (file_exists($jsonFilePath)) {
        $jsonData = file_get_contents($jsonFilePath);
        $selectedColumns = json_decode($jsonData, true);
    } else {
        $selectedColumns = array(); // Buat array kosong jika file tidak ada
    }

    // Cari tabel dalam file JSON
    $tableFound = false;
    foreach ($selectedColumns as &$table) {
        if ($table['table_name'] === $currentTable) {
            $tableFound = true;
            // Jika tabel sudah ada, hanya update updated_at
            $table['updated_at'] = date('Y-m-d H:i:s');
            break;
        }
    }

    // Jika tabel belum ada, tambahkan ke JSON
    if (!$tableFound) {
        unset($_SESSION['selectedColumns']);
        // Dapatkan semua kolom dari tabel yang dipilih
        $sqlColumns = "SHOW COLUMNS FROM $currentTable";
        $resultColumns = $conn->query($sqlColumns);
        $columns = array();

        // Simpan nama kolom dalam array
        while ($row = $resultColumns->fetch_assoc()) {
            $columns[] = $row['Field'];
        }

        // Gabungkan nama kolom menjadi string
        $columnNames = implode(',', $columns);

        // Tambahkan tabel baru ke array
        $selectedColumns[] = array(
            'table_name' => $currentTable,
            'column_names' => $columnNames,
            'updated_at' => date('Y-m-d H:i:s')
        );
    }

    // Tulis kembali ke file JSON
    $jsonData = json_encode($selectedColumns);
    file_put_contents($jsonFilePath, $jsonData);

    // Redirect kembali ke halaman utama tanpa output apapun sebelumnya
    header('Location: index.php');
    exit(); // Penting untuk menghentikan eksekusi setelah redirect
} else {
    // Jika tidak ada tabel yang dipilih, redirect ke halaman utama
    header('Location: index.php');
    exit();
}
?>