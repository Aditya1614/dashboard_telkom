<?php
session_start();
include 'db_connect.php'; // Pastikan jalur ke file koneksi benar

// Cek apakah ada tabel yang dipilih dari form
if (isset($_POST['table'])) {
    // Simpan tabel yang dipilih ke session
    $_SESSION['currentTable'] = $_POST['table'];
    $currentTable = $_POST['table'];
    unset($_SESSION['selectedColumns']);
    
    // Cek apakah tabel ada di database
    $sqlCheck = "SELECT table_name FROM selected_columns WHERE table_name = ?";
    $stmt = $conn->prepare($sqlCheck);
    $stmt->bind_param("s", $currentTable);
    $stmt->execute();
    $stmt->store_result();

    // Jika tabel belum ada di database, ambil semua kolom dari tabel tersebut
    if ($stmt->num_rows == 0) {
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

        // Masukkan tabel baru dengan column_names dan updated_at
        $sqlInsert = "INSERT INTO selected_columns (table_name, column_names, updated_at) 
                      VALUES (?, ?, NOW())";
        $stmtInsert = $conn->prepare($sqlInsert);
        $stmtInsert->bind_param("ss", $currentTable, $columnNames);
        $stmtInsert->execute();
        $stmtInsert->close();
    } else {
        unset($_SESSION['selectedColumns']);
        // Jika tabel sudah ada, hanya update updated_at
        $sqlUpdate = "UPDATE selected_columns SET updated_at = NOW() WHERE table_name = ?";
        $stmtUpdate = $conn->prepare($sqlUpdate);
        $stmtUpdate->bind_param("s", $currentTable);
        $stmtUpdate->execute();
        $stmtUpdate->close();
    }

    $stmt->close(); // Tutup statement
    
    // Redirect kembali ke halaman utama tanpa output apapun sebelumnya
    header('Location: index.php');
    exit(); // Penting untuk menghentikan eksekusi setelah redirect
} else {
    // Jika tidak ada tabel yang dipilih, redirect ke halaman utama
    header('Location: index.php');
    exit();
}
?>
