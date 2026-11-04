<?php
require_once 'db.php';

// Capture any active search or filter parameters from the URL
$search = trim($_GET['search'] ?? '');
$filter_level = $_GET['filter_level'] ?? '';

// Build the dynamic query matching your active table filters
$query = "SELECT id, full_name, year_of_study, level, email, created_at FROM alumni_registry WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (full_name LIKE ? OR year_of_study LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filter_level)) {
    $query .= " AND level = ?";
    $params[] = $filter_level;
}

$query .= " ORDER BY id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set HTTP headers to prompt a file download in CSV format
$filename = "SLECK_Student_Registry_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Open the PHP output stream
$output = fopen('php://output', 'w');

// Output column headers matching high school fields
fputcsv($output, ['ID', 'Full Name', 'Class / Year', 'Level', 'Email', 'Registration Date']);

// Output data rows
foreach ($students as $row) {
    fputcsv($output, $row);
}

fclose($output);
exit();
?>