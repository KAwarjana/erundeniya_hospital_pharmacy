<?php
// block_slots.php - For use in book_appointment.php admin panel
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../connection/connection.php';

// Simple authentication check - adjust as needed for your admin system
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

class SlotBlockManager {
    
    /**
     * Get all time slots for a specific date with their status
     */
    public static function getSlotsForDate($date) {
        try {
            Database::setUpConnection();
            
            $dayOfWeek = date('N', strtotime($date));
            if ($dayOfWeek != 3 && $dayOfWeek != 7) {
                return ['success' => false, 'message' => 'Not a consultation day'];
            }
            
            // Generate all possible slots
            $allSlots = [];
            $startTime = new DateTime($date . ' 09:00:00');
            $endTime = new DateTime($date . ' 20:00:00');
            $slotNumber = 1;
            
            while ($startTime <= $endTime) {
                $time = $startTime->format('H:i:s');
                $displayTime = $startTime->format('g:i A');
                
                // Check if booked
                $bookQuery = "SELECT appointment_number, status FROM appointment 
                             WHERE appointment_date = '$date' AND appointment_time = '$time' 
                             AND status NOT IN ('Cancelled', 'No-Show')";
                $bookResult = Database::search($bookQuery);
                $isBooked = $bookResult->num_rows > 0;
                $appointmentInfo = $isBooked ? $bookResult->fetch_assoc() : null;
                
                // Check if blocked
                $blockQuery = "SELECT reason, created_at FROM blocked_slots 
                              WHERE blocked_date = '$date' AND blocked_time = '$time'";
                $blockResult = Database::search($blockQuery);
                $isBlocked = $blockResult->num_rows > 0;
                $blockInfo = $isBlocked ? $blockResult->fetch_assoc() : null;
                
                $allSlots[] = [
                    'slot_number' => $slotNumber,
                    'time' => $time,
                    'display_time' => $displayTime,
                    'is_booked' => $isBooked,
                    'is_blocked' => $isBlocked,
                    'appointment_number' => $appointmentInfo ? $appointmentInfo['appointment_number'] : null,
                    'block_reason' => $blockInfo ? $blockInfo['reason'] : null,
                    'status' => $isBooked ? 'Booked' : ($isBlocked ? 'Blocked' : 'Available')
                ];
                
                $startTime->add(new DateInterval('PT10M'));
                $slotNumber++;
            }
            
            return ['success' => true, 'slots' => $allSlots];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Block multiple time slots
     */
    public static function blockSlots($date, $times, $reason = '') {
        try {
            Database::setUpConnection();
            Database::$connection->begin_transaction();
            
            $blockedCount = 0;
            $skippedCount = 0;
            $errors = [];
            
            foreach ($times as $time) {
                // Check if already booked
                $bookCheck = "SELECT appointment_number FROM appointment 
                             WHERE appointment_date = '$date' AND appointment_time = '$time' 
                             AND status NOT IN ('Cancelled', 'No-Show')";
                $bookResult = Database::search($bookCheck);
                
                if ($bookResult->num_rows > 0) {
                    $appointment = $bookResult->fetch_assoc();
                    $errors[] = "Slot $time is already booked (Appointment: {$appointment['appointment_number']})";
                    $skippedCount++;
                    continue;
                }
                
                // Check if already blocked
                $blockCheck = "SELECT id FROM blocked_slots 
                              WHERE blocked_date = '$date' AND blocked_time = '$time'";
                $blockResult = Database::search($blockCheck);
                
                if ($blockResult->num_rows > 0) {
                    $skippedCount++;
                    continue;
                }
                
                // Block the slot
                $reasonEscaped = Database::$connection->real_escape_string($reason);
                $userId = $_SESSION['user_id'] ?? null;
                
                $insertQuery = "INSERT INTO blocked_slots 
                               (blocked_date, blocked_time, reason, created_by, created_at) 
                               VALUES ('$date', '$time', '$reasonEscaped', $userId, NOW())";
                Database::iud($insertQuery);
                $blockedCount++;
            }
            
            Database::$connection->commit();
            
            $message = "$blockedCount slot(s) blocked successfully";
            if ($skippedCount > 0) {
                $message .= ", $skippedCount skipped";
            }
            
            return [
                'success' => true,
                'message' => $message,
                'blocked_count' => $blockedCount,
                'skipped_count' => $skippedCount,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            if (Database::$connection) {
                Database::$connection->rollback();
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Unblock time slots
     */
    public static function unblockSlots($date, $times) {
        try {
            Database::setUpConnection();
            $unblockedCount = 0;
            
            foreach ($times as $time) {
                $deleteQuery = "DELETE FROM blocked_slots 
                               WHERE blocked_date = '$date' AND blocked_time = '$time'";
                Database::iud($deleteQuery);
                
                if (Database::$connection->affected_rows > 0) {
                    $unblockedCount++;
                }
            }
            
            return [
                'success' => true,
                'message' => "$unblockedCount slot(s) unblocked successfully",
                'unblocked_count' => $unblockedCount
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Block all remaining slots after a specific time
     */
    public static function blockSlotsAfterTime($date, $afterTime, $reason = '') {
        try {
            Database::setUpConnection();
            
            // Get all slots after the specified time
            $allSlots = self::getSlotsForDate($date);
            if (!$allSlots['success']) {
                return $allSlots;
            }
            
            $timesToBlock = [];
            $foundStartTime = false;
            
            foreach ($allSlots['slots'] as $slot) {
                if ($slot['time'] == $afterTime) {
                    $foundStartTime = true;
                    continue; // Skip the exact time
                }
                
                if ($foundStartTime && !$slot['is_booked'] && !$slot['is_blocked']) {
                    $timesToBlock[] = $slot['time'];
                }
            }
            
            if (empty($timesToBlock)) {
                return ['success' => true, 'message' => 'No slots to block', 'blocked_count' => 0];
            }
            
            return self::blockSlots($date, $timesToBlock, $reason);
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Get blocked slots summary
     */
    public static function getBlockedSlotsSummary() {
        try {
            Database::setUpConnection();
            
            $query = "SELECT blocked_date, COUNT(*) as count 
                     FROM blocked_slots 
                     WHERE blocked_date >= CURDATE() 
                     GROUP BY blocked_date 
                     ORDER BY blocked_date ASC 
                     LIMIT 10";
            
            $result = Database::search($query);
            $summary = [];
            
            while ($row = $result->fetch_assoc()) {
                $dateObj = new DateTime($row['blocked_date']);
                $summary[] = [
                    'date' => $row['blocked_date'],
                    'display_date' => $dateObj->format('l, j M Y'),
                    'blocked_count' => $row['count']
                ];
            }
            
            return ['success' => true, 'summary' => $summary];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'get_slots':
                $date = $_POST['date'] ?? '';
                if (empty($date)) {
                    throw new Exception("Date is required");
                }
                $result = SlotBlockManager::getSlotsForDate($date);
                echo json_encode($result);
                break;
                
            case 'block_slots':
                $date = $_POST['date'] ?? '';
                $times = isset($_POST['times']) ? json_decode($_POST['times'], true) : [];
                $reason = $_POST['reason'] ?? '';
                
                if (empty($date) || empty($times)) {
                    throw new Exception("Date and times are required");
                }
                
                $result = SlotBlockManager::blockSlots($date, $times, $reason);
                echo json_encode($result);
                break;
                
            case 'unblock_slots':
                $date = $_POST['date'] ?? '';
                $times = isset($_POST['times']) ? json_decode($_POST['times'], true) : [];
                
                if (empty($date) || empty($times)) {
                    throw new Exception("Date and times are required");
                }
                
                $result = SlotBlockManager::unblockSlots($date, $times);
                echo json_encode($result);
                break;
                
            case 'block_after_time':
                $date = $_POST['date'] ?? '';
                $afterTime = $_POST['after_time'] ?? '';
                $reason = $_POST['reason'] ?? '';
                
                if (empty($date) || empty($afterTime)) {
                    throw new Exception("Date and time are required");
                }
                
                $result = SlotBlockManager::blockSlotsAfterTime($date, $afterTime, $reason);
                echo json_encode($result);
                break;
                
            case 'get_summary':
                $result = SlotBlockManager::getBlockedSlotsSummary();
                echo json_encode($result);
                break;
                
            default:
                throw new Exception("Invalid action");
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// If accessed via GET, show management interface
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Slot Blocking Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .slot-card {
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 10px;
            margin: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .slot-card.available {
            border-color: #28a745;
            background: #f8fff8;
        }
        .slot-card.booked {
            border-color: #dc3545;
            background: #fff5f5;
            cursor: not-allowed;
        }
        .slot-card.blocked {
            border-color: #ffc107;
            background: #fffbf0;
        }
        .slot-card.selected {
            border-color: #007bff;
            background: #e3f2fd;
            transform: scale(1.05);
        }
        .slot-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h2>Appointment Slot Blocking Management</h2>
        
        <div class="card mt-4">
            <div class="card-body">
                <h5>Select Date</h5>
                <input type="date" id="selectedDate" class="form-control" style="max-width: 300px;">
                <button class="btn btn-primary mt-2" onclick="loadSlots()">Load Slots</button>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-body">
                <h5>Time Slots</h5>
                <div id="slotsContainer" class="slot-grid"></div>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-body">
                <h5>Actions</h5>
                <div class="mb-3">
                    <label>Reason for blocking:</label>
                    <input type="text" id="blockReason" class="form-control" placeholder="e.g., Doctor unavailable">
                </div>
                <button class="btn btn-warning" onclick="blockSelected()">Block Selected Slots</button>
                <button class="btn btn-info" onclick="unblockSelected()">Unblock Selected Slots</button>
                <button class="btn btn-danger" onclick="blockAfterTime()">Block All After Selected Time</button>
                <button class="btn btn-secondary" onclick="clearSelection()">Clear Selection</button>
            </div>
        </div>
    </div>
    
    <script>
        let selectedSlots = new Set();
        let currentDate = '';
        
        async function loadSlots() {
            const date = document.getElementById('selectedDate').value;
            if (!date) {
                alert('Please select a date');
                return;
            }
            
            currentDate = date;
            
            const formData = new FormData();
            formData.append('action', 'get_slots');
            formData.append('date', date);
            
            const response = await fetch('block_slots.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                renderSlots(data.slots);
            } else {
                alert(data.message);
            }
        }
        
        function renderSlots(slots) {
            const container = document.getElementById('slotsContainer');
            container.innerHTML = '';
            
            slots.forEach(slot => {
                const card = document.createElement('div');
                card.className = `slot-card ${slot.status.toLowerCase()}`;
                card.innerHTML = `
                    <div><strong>${slot.display_time}</strong></div>
                    <div><small>Slot #${slot.slot_number}</small></div>
                    <div><small>${slot.status}</small></div>
                `;
                
                if (slot.status === 'Available' || slot.status === 'Blocked') {
                    card.onclick = () => toggleSlot(slot.time, card);
                }
                
                container.appendChild(card);
            });
        }
        
        function toggleSlot(time, element) {
            if (selectedSlots.has(time)) {
                selectedSlots.delete(time);
                element.classList.remove('selected');
            } else {
                selectedSlots.add(time);
                element.classList.add('selected');
            }
        }
        
        async function blockSelected() {
            if (selectedSlots.size === 0) {
                alert('Please select slots to block');
                return;
            }
            
            const reason = document.getElementById('blockReason').value;
            
            const formData = new FormData();
            formData.append('action', 'block_slots');
            formData.append('date', currentDate);
            formData.append('times', JSON.stringify(Array.from(selectedSlots)));
            formData.append('reason', reason);
            
            const response = await fetch('block_slots.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            alert(data.message);
            
            if (data.success) {
                selectedSlots.clear();
                loadSlots();
            }
        }
        
        async function unblockSelected() {
            if (selectedSlots.size === 0) {
                alert('Please select slots to unblock');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'unblock_slots');
            formData.append('date', currentDate);
            formData.append('times', JSON.stringify(Array.from(selectedSlots)));
            
            const response = await fetch('block_slots.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            alert(data.message);
            
            if (data.success) {
                selectedSlots.clear();
                loadSlots();
            }
        }
        
        async function blockAfterTime() {
            if (selectedSlots.size !== 1) {
                alert('Please select exactly one slot as the starting time');
                return;
            }
            
            const afterTime = Array.from(selectedSlots)[0];
            const reason = document.getElementById('blockReason').value;
            
            if (confirm(`Block all slots after ${afterTime}?`)) {
                const formData = new FormData();
                formData.append('action', 'block_after_time');
                formData.append('date', currentDate);
                formData.append('after_time', afterTime);
                formData.append('reason', reason);
                
                const response = await fetch('block_slots.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                alert(data.message);
                
                if (data.success) {
                    selectedSlots.clear();
                    loadSlots();
                }
            }
        }
        
        function clearSelection() {
            selectedSlots.clear();
            document.querySelectorAll('.slot-card.selected').forEach(card => {
                card.classList.remove('selected');
            });
        }
        
        // Set today's date as default
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('selectedDate').value = today;
        });
    </script>
</body>
</html>