<?php
session_start();
include 'db_connect.php'; 

// Path ke file JSON
$jsonFilePath = 'selected_columns.json';

/**
 * Mengambil nilai selected_ID dari JSON atau primary key dari tabel.
 *
 * Fungsi ini pertama-tama mencoba untuk mengambil nilai selected_ID dari JSON.
 * Jika tidak ditemukan, maka mengambil primary key dari tabel.
 *
 * @param string $tableName Nama tabel yang ingin diambil selected_ID-nya.
 * @param array $selectedColumnsData Data JSON yang sudah didekode dari file.
 * @param mysqli $conn Koneksi database MySQL.
 * @return string|null Mengembalikan selected_ID dari JSON atau primary key jika tidak ada.
 */
function getSelectedID($tableName, $selectedColumnsData, $conn) {
    // Cek apakah tabel sudah ada di dalam data JSON
    $table = findTableInJSON($selectedColumnsData, $tableName);

    if ($table && isset($table['selected_ID'])) {
        // Jika tabel ditemukan di JSON dan selected_ID tersedia, kembalikan selected_ID dari JSON
        return $table['selected_ID'];
    } else {
        // Jika tidak ditemukan, ambil primary key dari tabel
        $primaryKey = getPrimaryKey($tableName, $conn);
        if ($primaryKey) {
            return $primaryKey; // Kembalikan primary key default jika selected_ID tidak ada di JSON
        }
    }

    return null; // Jika tidak ada primary key, kembalikan null
}

/**
 * Mengambil nama kolom primary key dari tabel yang diberikan.
 *
 * Fungsi ini menggunakan query SQL untuk mendapatkan primary key dari tabel.
 *
 * @param string $tableName Nama tabel yang ingin diambil primary key-nya.
 * @param mysqli $conn Koneksi database MySQL.
 * @return string|null Mengembalikan nama kolom primary key jika ada, atau null jika tidak ditemukan.
 */
function getPrimaryKey($tableName, $conn) {
    $sql = "SHOW KEYS FROM $tableName WHERE Key_name = 'PRIMARY'";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['Column_name']; // Mengembalikan kolom primary key
    }
    return null; // Jika tidak ada primary key
}

/**
 * Mengambil data kolom terpilih dari file JSON.
 *
 * Fungsi ini membaca file JSON dan mendekode isinya menjadi array asosiatif.
 *
 * @param string $jsonFilePath Jalur ke file JSON yang ingin dibaca.
 * @return array Mengembalikan array asosiatif dari data JSON, atau array kosong jika file tidak ditemukan.
 */
function getSelectedColumnsJSON($jsonFilePath) {
    if (file_exists($jsonFilePath)) {
        $jsonData = file_get_contents($jsonFilePath);
        return json_decode($jsonData, true); // Mengembalikan array asosiatif dari data JSON
    }
    return array(); // Kembalikan array kosong jika file tidak ada
}

/**
 * Menulis data kolom terpilih ke file JSON.
 *
 * Fungsi ini meng-encode data array menjadi format JSON dan menulisnya ke file.
 *
 * @param string $jsonFilePath Jalur ke file JSON yang akan ditulis.
 * @param array $data Data yang akan di-encode menjadi JSON dan disimpan di file.
 * @return void Tidak mengembalikan nilai apapun.
 */
function writeSelectedColumnsJSON($jsonFilePath, $data) {
    $jsonData = json_encode($data, JSON_PRETTY_PRINT);
    if (file_put_contents($jsonFilePath, $jsonData) === false) {
        die("Error: Gagal menulis ke file JSON.");
    }
}

/**
 * Mencari tabel dalam data JSON berdasarkan nama tabel yang diberikan.
 *
 * Fungsi ini mencari tabel dalam array data kolom terpilih berdasarkan 
 * nama tabel yang diberikan dan mengembalikan tabel tersebut jika ditemukan.
 *
 * @param array &$selectedColumnsData Array referensi dari data JSON kolom terpilih.
 * @param string $currentTable Nama tabel yang akan dicari dalam data JSON.
 * @return array|null Mengembalikan array tabel yang ditemukan, atau null jika tidak ada tabel yang cocok.
 */
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
            $table['selected_ID'] = getSelectedID($currentTable,$selectedColumnsData, $conn);
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
        $selectedID = getSelectedID($currentTable, $selectedColumnsData, $conn);
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