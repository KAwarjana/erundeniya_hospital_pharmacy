<?php
require_once 'auth_manager.php';
require_once '../../connection/connection.php';

if (!AuthManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

try {
    $patient = intval($_GET['patient'] ?? 0);
    $type = $_GET['type'] ?? '';

    if (!$patient || !in_array($type, ['appointments', 'prescriptions', 'bills'])) {
        throw new Exception('Invalid request parameters');
    }

    Database::setUpConnection();
    $conn = Database::$connection;
    $data = [];

    switch ($type) {
        case 'appointments':
            $sql = "SELECT a.id, a.appointment_number, a.appointment_date, a.appointment_time, 
                           a.status, a.payment_status, a.total_amount, a.note
                    FROM appointment a
                    WHERE a.patient_id = ?
                    ORDER BY a.appointment_date DESC, a.appointment_time DESC
                    LIMIT 20";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $patient);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                // Add URL for each appointment
                $row['url'] = 'appointment_single_view.php?appointment=' . urlencode($row['appointment_number']);
                $data[] = $row;
            }
            break;

        case 'prescriptions':
            $sql = "SELECT p.id, p.appointment_id, p.created_at, p.prescription_text,
                           CONCAT('PRX', LPAD(p.id, 5, '0')) as prescription_number
                    FROM prescriptions p
                    WHERE p.patient_id = ?
                    ORDER BY p.created_at DESC
                    LIMIT 20";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $patient);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                // Add URL for each prescription
                $row['url'] = 'prescription.php?view=' . $row['id'];
                $data[] = $row;
            }
            break;

        case 'bills':
            // Get both appointment bills and treatment bills
            $appointmentBills = [];
            $treatmentBills = [];
            
            // Appointment bills
            $sql1 = "SELECT b.id, b.bill_number, b.created_at, b.total_amount, 
                            b.discount_amount, b.payment_status, 'appointment' as bill_type,
                            a.appointment_number
                     FROM bills b
                     INNER JOIN appointment a ON b.appointment_id = a.id
                     WHERE a.patient_id = ?
                     ORDER BY b.created_at DESC
                     LIMIT 20";
            $stmt1 = $conn->prepare($sql1);
            $stmt1->bind_param('i', $patient);
            $stmt1->execute();
            $result1 = $stmt1->get_result();
            while ($row = $result1->fetch_assoc()) {
                // Add URL for appointment bills
                $row['url'] = 'create_bill.php?bill=' . urlencode($row['bill_number']);
                $appointmentBills[] = $row;
            }
            
            // Treatment bills
            $sql2 = "SELECT id, bill_number, created_at, total_amount, 
                            discount_amount, final_amount, payment_status, 'treatment' as bill_type
                     FROM treatment_bills
                     WHERE patient_id = ?
                     ORDER BY created_at DESC
                     LIMIT 20";
            $stmt2 = $conn->prepare($sql2);
            $stmt2->bind_param('i', $patient);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            while ($row = $result2->fetch_assoc()) {
                // Add URL for treatment bills
                $row['url'] = 'opd.php?bill=' . urlencode($row['bill_number']);
                $treatmentBills[] = $row;
            }
            
            // Merge and sort by date
            $data = array_merge($appointmentBills, $treatmentBills);
            usort($data, function($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });
            $data = array_slice($data, 0, 20);
            break;
    }

    echo json_encode([
        'success' => true,
        'data' => $data
    ]);

} catch (Exception $e) {
    error_log("Error in get_patient_related.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>