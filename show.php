<?php
session_start();
include 'db_connect.php';  // Include database connection

// Hanya jalankan jika ada kolom yang dipilih
if (isset($_POST['columns'])) {
    // Ambil kolom yang dipilih
    $selectedColumns = $_POST['columns'];

    // Simpan kolom yang dipilih di session
    $_SESSION['selectedColumns'] = $selectedColumns;

    // Simpan juga pilihan kolom ke database jika pengguna adalah admin
    if ($_SESSION['role'] === 'admin') {
        $adminId = $_SESSION['user_id'];  // Asumsikan admin_id disimpan di session
        // $currentTable = $_SESSION['currentTable'] ?? 'days';  // Nama tabel saat ini
        $currentTable = isset($_SESSION['currentTable']) ? $_SESSION['currentTable'] : 'days';

        // Gabungkan kolom yang dipilih menjadi string yang dipisahkan koma
        $columnsString = implode(',', $selectedColumns);

        // Periksa apakah entri untuk tabel saat ini sudah ada
        $sqlCheck = "SELECT * FROM selected_columns WHERE table_name = '$currentTable'";
        $resultCheck = $conn->query($sqlCheck);

        if ($resultCheck->num_rows > 0) {
            // Jika entri sudah ada, perbarui kolom yang dipilih
            $sqlUpdate = "UPDATE selected_columns 
                          SET column_names = '$columnsString', updated_at = NOW() 
                          WHERE table_name = '$currentTable'";
            $conn->query($sqlUpdate);
        } else {
            // Jika entri belum ada, masukkan data baru
            $sqlInsert = "INSERT INTO selected_columns (table_name, column_names, updated_at) 
                          VALUES ('$currentTable', '$columnsString', NOW())";
            $conn->query($sqlInsert);
        }
    }

    // Ambil limit data dari session
    $limit = isset($_SESSION['limit']) ? intval($_SESSION['limit']) : 10;

    // Redirect kembali ke dashboard dengan limit yang sesuai
    header('Location: dashboard.php?limit=' . $limit);
    exit();
} else {
    echo "No columns selected.";
}

$conn->close();
?>
