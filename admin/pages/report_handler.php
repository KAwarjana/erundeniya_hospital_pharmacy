<?php
require_once 'page_guards.php';
require_once '../../connection/connection.php';

header('Content-Type: application/json');

if (!AuthManager::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'generateReport') {
    try {
        $reportType = $_POST['reportType'] ?? '';
        $dateFilter = $_POST['dateFilter'] ?? 'all';
        $startDate = $_POST['startDate'] ?? '';
        $endDate = $_POST['endDate'] ?? '';

        // Build date condition
        $dateCondition = buildDateCondition($dateFilter, $startDate, $endDate, '');

        switch ($reportType) {
            case 'appointments':
                $dateCondition = buildDateCondition($dateFilter, $startDate, $endDate, 'a');
                $result = generateAppointmentsReport($dateCondition);
                break;
            case 'bills':
                $dateCondition = buildDateCondition($dateFilter, $startDate, $endDate, 'b');
                $result = generateBillsReport($dateCondition);
                break;
            case 'patients':
                $dateCondition = buildDateCondition($dateFilter, $startDate, $endDate, 'p');
                $result = generatePatientsReport($dateCondition);
                break;
            case 'treatments':
                $dateCondition = buildDateCondition($dateFilter, $startDate, $endDate, 'tb');
                $result = generateTreatmentsReport($dateCondition);
                break;
            default:
                throw new Exception('Invalid report type');
        }

        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function buildDateCondition($filter, $startDate = '', $endDate = '', $tableAlias = '') {
    $today = date('Y-m-d');
    $column = $tableAlias ? "{$tableAlias}.created_at" : "created_at";
    
    switch ($filter) {
        case 'today':
            return "DATE($column) = '$today'";
        case 'yesterday':
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            return "DATE($column) = '$yesterday'";
        case 'thisWeek':
            $weekStart = date('Y-m-d', strtotime('monday this week'));
            return "DATE($column) >= '$weekStart'";
        case 'lastWeek':
            $lastWeekStart = date('Y-m-d', strtotime('monday last week'));
            $lastWeekEnd = date('Y-m-d', strtotime('sunday last week'));
            return "DATE($column) BETWEEN '$lastWeekStart' AND '$lastWeekEnd'";
        case 'thisMonth':
            $monthStart = date('Y-m-01');
            return "DATE($column) >= '$monthStart'";
        case 'lastMonth':
            $lastMonthStart = date('Y-m-01', strtotime('first day of last month'));
            $lastMonthEnd = date('Y-m-t', strtotime('last day of last month'));
            return "DATE($column) BETWEEN '$lastMonthStart' AND '$lastMonthEnd'";
        case 'custom':
            if ($startDate && $endDate) {
                return "DATE($column) BETWEEN '$startDate' AND '$endDate'";
            }
            return '1=1';
        case 'all':
        default:
            return '1=1';
    }
}

function generateAppointmentsReport($dateCondition) {
    $query = "SELECT 
                a.appointment_number as 'Appointment No',
                a.appointment_date as 'Date',
                a.appointment_time as 'Time',
                p.name as 'Patient Name',
                p.mobile as 'Mobile',
                a.channeling_fee as 'Channeling Fee',
                a.discount as 'Discount',
                a.total_amount as 'Total Amount',
                a.payment_status as 'Payment Status',
                a.payment_method as 'Payment Method',
                a.status as 'Status',
                a.created_at as 'Created At'
              FROM appointment a
              JOIN patient p ON a.patient_id = p.id
              WHERE $dateCondition
              ORDER BY a.created_at DESC";

    $result = Database::search($query);
    $data = [];
    $totalAmount = 0;
    $paidAmount = 0;
    $pendingAmount = 0;

    while ($row = $result->fetch_assoc()) {
        // Format the data for display
        $row['Date'] = date('M d, Y', strtotime($row['Date']));
        $row['Time'] = date('h:i A', strtotime($row['Time']));
        $row['Channeling Fee'] = 'Rs. ' . number_format($row['Channeling Fee'], 2);
        $row['Discount'] = 'Rs. ' . number_format($row['Discount'], 2);
        $row['Total Amount'] = 'Rs. ' . number_format($row['Total Amount'], 2);
        $row['Created At'] = date('M d, Y h:i A', strtotime($row['Created At']));
        
        $data[] = $row;
        
        // Calculate totals (using original numeric values)
        $totalAmount += floatval(str_replace(['Rs. ', ','], '', $row['Total Amount']));
        if ($row['Payment Status'] === 'Paid') {
            $paidAmount += floatval(str_replace(['Rs. ', ','], '', $row['Total Amount']));
        } else {
            $pendingAmount += floatval(str_replace(['Rs. ', ','], '', $row['Total Amount']));
        }
    }

    return [
        'success' => true,
        'title' => 'Appointments Report',
        'headers' => array_keys($data[0] ?? []),
        'data' => $data,
        'summary' => [
            'totalRecords' => count($data),
            'totalAmount' => $totalAmount,
            'paidAmount' => $paidAmount,
            'pendingAmount' => $pendingAmount
        ]
    ];
}

function generateBillsReport($dateCondition) {
    $query = "SELECT 
                b.bill_number as 'Bill No',
                a.appointment_number as 'Appointment No',
                p.name as 'Patient Name',
                p.mobile as 'Mobile',
                b.doctor_fee as 'Doctor Fee',
                b.medicine_cost as 'Medicine Cost',
                b.other_charges as 'Other Charges',
                b.discount_amount as 'Discount',
                b.total_amount as 'Total Amount',
                b.payment_status as 'Payment Status',
                u.user_name as 'Created By',
                b.created_at as 'Created At'
              FROM bills b
              JOIN appointment a ON b.appointment_id = a.id
              JOIN patient p ON a.patient_id = p.id
              JOIN user u ON b.created_by = u.id
              WHERE $dateCondition
              ORDER BY b.created_at DESC";

    $result = Database::search($query);
    $data = [];
    $totalAmount = 0;
    $paidAmount = 0;
    $pendingAmount = 0;

    while ($row = $result->fetch_assoc()) {
        $originalTotal = $row['Total Amount'];
        
        $row['Doctor Fee'] = 'Rs. ' . number_format($row['Doctor Fee'], 2);
        $row['Medicine Cost'] = 'Rs. ' . number_format($row['Medicine Cost'], 2);
        $row['Other Charges'] = 'Rs. ' . number_format($row['Other Charges'], 2);
        $row['Discount'] = 'Rs. ' . number_format($row['Discount'], 2);
        $row['Total Amount'] = 'Rs. ' . number_format($row['Total Amount'], 2);
        $row['Created At'] = date('M d, Y h:i A', strtotime($row['Created At']));
        
        $data[] = $row;
        
        $totalAmount += $originalTotal;
        if ($row['Payment Status'] === 'Paid') {
            $paidAmount += $originalTotal;
        } else {
            $pendingAmount += $originalTotal;
        }
    }

    return [
        'success' => true,
        'title' => 'Bills Report',
        'headers' => array_keys($data[0] ?? []),
        'data' => $data,
        'summary' => [
            'totalRecords' => count($data),
            'totalAmount' => $totalAmount,
            'paidAmount' => $paidAmount,
            'pendingAmount' => $pendingAmount
        ]
    ];
}

function generatePatientsReport($dateCondition) {
    $query = "SELECT 
                p.registration_number as 'Registration No',
                CONCAT(p.title, ' ', p.name) as 'Patient Name',
                p.gender as 'Gender',
                p.age as 'Age',
                p.mobile as 'Mobile',
                p.email as 'Email',
                p.province as 'Province',
                p.district as 'District',
                DATE(p.created_at) as 'Registration Date'
              FROM patient p
              WHERE $dateCondition
              ORDER BY p.created_at DESC";

    $result = Database::search($query);
    $data = [];

    while ($row = $result->fetch_assoc()) {
        $row['Registration Date'] = date('M d, Y', strtotime($row['Registration Date']));
        $row['Email'] = $row['Email'] ?? 'N/A';
        $row['Province'] = $row['Province'] ?? 'N/A';
        $row['District'] = $row['District'] ?? 'N/A';
        $row['Gender'] = $row['Gender'] ?? 'N/A';
        $row['Age'] = $row['Age'] ?? 'N/A';
        
        $data[] = $row;
    }

    return [
        'success' => true,
        'title' => 'Patients Report',
        'headers' => array_keys($data[0] ?? []),
        'data' => $data,
        'summary' => [
            'totalRecords' => count($data)
        ]
    ];
}

function generateTreatmentsReport($dateCondition) {
    $query = "SELECT 
                tb.bill_number as 'Bill No',
                tb.patient_name as 'Patient Name',
                tb.patient_mobile as 'Mobile',
                tb.total_amount as 'Total Amount',
                tb.discount_percentage as 'Discount %',
                tb.discount_amount as 'Discount Amount',
                tb.final_amount as 'Final Amount',
                tb.payment_status as 'Payment Status',
                u.user_name as 'Created By',
                tb.created_at as 'Created At'
              FROM treatment_bills tb
              JOIN user u ON tb.created_by = u.id
              WHERE $dateCondition
              ORDER BY tb.created_at DESC";

    $result = Database::search($query);
    $data = [];
    $totalAmount = 0;
    $paidAmount = 0;
    $pendingAmount = 0;

    while ($row = $result->fetch_assoc()) {
        $originalTotal = $row['Final Amount'];
        
        $row['Total Amount'] = 'Rs. ' . number_format($row['Total Amount'], 2);
        $row['Discount %'] = number_format($row['Discount %'], 2) . '%';
        $row['Discount Amount'] = 'Rs. ' . number_format($row['Discount Amount'], 2);
        $row['Final Amount'] = 'Rs. ' . number_format($row['Final Amount'], 2);
        $row['Created At'] = date('M d, Y h:i A', strtotime($row['Created At']));
        
        $data[] = $row;
        
        $totalAmount += $originalTotal;
        if ($row['Payment Status'] === 'Paid') {
            $paidAmount += $originalTotal;
        } else {
            $pendingAmount += $originalTotal;
        }
    }

    return [
        'success' => true,
        'title' => 'Treatment Bills Report',
        'headers' => array_keys($data[0] ?? []),
        'data' => $data,
        'summary' => [
            'totalRecords' => count($data),
            'totalAmount' => $totalAmount,
            'paidAmount' => $paidAmount,
            'pendingAmount' => $pendingAmount
        ]
    ];
}
?>