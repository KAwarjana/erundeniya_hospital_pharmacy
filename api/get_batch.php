<?php
require_once '../config.php';
require_once '../auth.php';

Auth::requireAuth();
header('Content-Type: application/json');

$batchId = $_GET['id'] ?? 0;

$conn = getDBConnection();
$stmt = $conn->prepare("SELECT * FROM product_batches WHERE batch_id = ?");
$stmt->bind_param("i", $batchId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Batch not found']);
    exit;
}

$batch = $result->fetch_assoc();
echo json_encode(['success' => true, 'batch' => $batch]);
?>