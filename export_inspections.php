<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
include 'db.php';

// Prepare headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=inspections_report_' . date('Y-m-d') . '.csv');

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Output the column headings
fputcsv($output, array('ID', 'Batch Number', 'Material Type', 'Length (m)', 'Defect Count', 'Status', 'Inspection Date', 'Comments'));

// Fetch Data (applying same filters if passed would be ideal, but for now we export ALL or filtered)
// Let's replicate the basic filter logic from inspections.php to be helpful
$where_clauses = [];

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $where_clauses[] = "(batch_number LIKE '%$search%' OR material_type LIKE '%$search%')";
}

if (isset($_GET['status']) && !empty($_GET['status'])) {
    $status_filter = $conn->real_escape_string($_GET['status']);
    $where_clauses[] = "status = '$status_filter'";
}

if (isset($_GET['start_date']) && !empty($_GET['start_date'])) {
    $start_date = $conn->real_escape_string($_GET['start_date']);
    $where_clauses[] = "inspection_date >= '$start_date'";
}

if (isset($_GET['end_date']) && !empty($_GET['end_date'])) {
    $end_date = $conn->real_escape_string($_GET['end_date']);
    $where_clauses[] = "inspection_date <= '$end_date'";
}

$sql = "SELECT id, batch_number, material_type, length_meters, defect_count, status, inspection_date, comments FROM inspections";
if (count($where_clauses) > 0) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}
$sql .= " ORDER BY inspection_date DESC";

$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, $row);
}

fclose($output);
exit();
?>
