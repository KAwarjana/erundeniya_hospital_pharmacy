<?php
require_once 'auth_manager.php';
require_once '../../connection/connection.php';

// Check authentication
if (!AuthManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

try {
    Database::setUpConnection();
    $conn = Database::$connection;

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        throw new Exception('Invalid JSON data');
    }

    // Validate required fields
    $requiredFields = ['title', 'name', 'mobile'];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Sanitize inputs
    $title   = $conn->real_escape_string(trim($data['title']));
    $name    = $conn->real_escape_string(trim($data['name']));
    $mobile  = $conn->real_escape_string(trim($data['mobile']));

    // Validate mobile number format
    if (!preg_match('/^[0-9]{10}$/', $mobile)) {
        throw new Exception('Invalid mobile number format. Must be 10 digits');
    }

    // Check if mobile already exists (only for new patients)
    if (empty($data['id'])) {
        $checkQuery = "SELECT id FROM patient WHERE mobile = '$mobile'";
        $checkResult = Database::search($checkQuery);
        if ($checkResult->num_rows > 0) {
            throw new Exception('A patient with this mobile number already exists');
        }
    }

    // Optional fields
    $gender   = !empty($data['gender'])  ? "'" . $conn->real_escape_string($data['gender']) . "'"  : "NULL";
    $age      = !empty($data['age'])     ? intval($data['age'])                                     : "NULL";
    $email    = !empty($data['email'])   ? "'" . $conn->real_escape_string(trim($data['email'])) . "'" : "NULL";
    $address  = !empty($data['address']) ? "'" . $conn->real_escape_string(trim($data['address'])) . "'" : "NULL";
    $province = !empty($data['province'])? "'" . $conn->real_escape_string(trim($data['province'])) . "'" : "NULL";
    $district = !empty($data['district'])? "'" . $conn->real_escape_string(trim($data['district'])) . "'" : "NULL";

    // Illnesses and medical notes
    $illnesses = !empty($data['illnesses']) ? $conn->real_escape_string(trim($data['illnesses'])) : "";
    $medical_notes = !empty($data['medical_notes']) ? $conn->real_escape_string(trim($data['medical_notes'])) : "";

    $fullMedicalNotes = "";
    if ($illnesses) {
        $fullMedicalNotes = "Medical Conditions: $illnesses";
    }
    if ($medical_notes) {
        $fullMedicalNotes .= ($fullMedicalNotes ? "\n\n" : "") . "Additional Notes:\n" . $medical_notes;
    }
    $fullMedicalNotes = $fullMedicalNotes ? "'" . $conn->real_escape_string($fullMedicalNotes) . "'" : "NULL";

    if (!empty($data['id'])) {
        // UPDATE existing patient
        $patientId = intval($data['id']);
        $query = "UPDATE patient SET
            title = '$title',
            name = '$name',
            gender = $gender,
            age = $age,
            mobile = '$mobile',
            email = $email,
            address = $address,
            province = $province,
            district = $district,
            medical_notes = $fullMedicalNotes
            WHERE id = $patientId";
        Database::iud($query);
        
        // Get registration number for existing patient
        $regResult = Database::search("SELECT registration_number FROM patient WHERE id = $patientId");
        $regRow = $regResult->fetch_assoc();
        $regNumber = $regRow['registration_number'];
        
        echo json_encode([
            'success' => true,
            'message' => 'Patient updated successfully',
            'patient_id' => $patientId,
            'registration_number' => $regNumber
        ]);
    } else {
        // INSERT new patient
        $query = "INSERT INTO patient (
            title, name, gender, age, mobile, email, address, province, district, medical_notes, created_at
        ) VALUES (
            '$title', '$name', $gender, $age, '$mobile', $email, $address, $province, $district, $fullMedicalNotes, NOW()
        )";
        if (Database::iud($query)) {
            $patientId = $conn->insert_id;

            // 🔥 NEW: Generate registration number from last REG number
            $lastRegResult = Database::search("SELECT registration_number FROM patient WHERE registration_number IS NOT NULL ORDER BY id DESC LIMIT 1");
            $lastRegNumber = null;

            if ($lastRegResult->num_rows > 0) {
                $lastRow = $lastRegResult->fetch_assoc();
                $lastRegNumber = $lastRow['registration_number'];
            }

            if ($lastRegNumber && preg_match('/REG(\d+)/', $lastRegNumber, $matches)) {
                $lastNumber = (int)$matches[1];
                $newNumber = $lastNumber + 1;
            } else {
                $newNumber = 1000; // Default start
            }

            $regNumber = 'REG' . str_pad($newNumber, 5, '0', STR_PAD_LEFT);

            $updateQuery = "UPDATE patient SET registration_number = '$regNumber' WHERE id = $patientId";
            Database::iud($updateQuery);

            echo json_encode([
                'success' => true,
                'message' => 'Patient registered successfully',
                'patient_id' => $patientId,
                'registration_number' => $regNumber
            ]);
        } else {
            throw new Exception('Failed to register patient: ' . $conn->error);
        }
    }

} catch (Exception $e) {
    error_log("Error in save_patient.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>