<?php

require_once '../../connection/connection.php';

class HolidayManager
{
    /**
     * Add a holiday with optional custom reschedule date
     * 
     * @param string $date Holiday date (YYYY-MM-DD)
     * @param string $reason Reason for holiday
     * @param bool $reschedule Whether to reschedule
     * @param string|null $customRescheduleDate Custom date to reschedule to (YYYY-MM-DD)
     * @return array Result array with success status and message
     */
    public static function addHoliday($date, $reason, $reschedule = false, $customRescheduleDate = null)
    {
        try {
            Database::setUpConnection();
            Database::$connection->begin_transaction();

            $date = Database::$connection->real_escape_string($date);
            $reason = Database::$connection->real_escape_string($reason);
            $dayOfWeek = date('l', strtotime($date));

            // Validate: Check if it's a consultation day (Wednesday or Sunday)
            if ($dayOfWeek !== 'Wednesday' && $dayOfWeek !== 'Sunday') {
                throw new Exception("This is not a regular consultation day. Only Wednesday and Sunday can be marked as holidays.");
            }

            // Check if holiday already exists
            $checkQuery = "SELECT id FROM holidays WHERE holiday_date = '$date'";
            $existing = Database::search($checkQuery);

            if ($existing->num_rows > 0) {
                throw new Exception("Holiday already exists for this date");
            }

            // Get user ID from session if available
            $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

            // Insert holiday record
            $insertHoliday = "INSERT INTO holidays (holiday_date, day_of_week, reason, created_by, created_at) 
                             VALUES ('$date', '$dayOfWeek', '$reason', " . ($userId ? $userId : "NULL") . ", NOW())";
            Database::iud($insertHoliday);

            // Block all existing slots for that day
            self::blockAllSlotsForDate($date, "Holiday: $reason");

            $rescheduledDate = null;

            // If reschedule option is selected
            if ($reschedule) {
                // Use custom date if provided, otherwise use next day
                $rescheduleDate = $customRescheduleDate ? $customRescheduleDate : date('Y-m-d', strtotime($date . ' +1 day'));
                
                // Validate reschedule date
                $rescheduleTimestamp = strtotime($rescheduleDate);
                $holidayTimestamp = strtotime($date);
                
                // Check if reschedule date is not in the past
                if ($rescheduleTimestamp < strtotime('today')) {
                    throw new Exception("Reschedule date cannot be in the past");
                }
                
                // Check if reschedule date is not the holiday itself
                if ($rescheduleDate === $date) {
                    throw new Exception("Reschedule date cannot be the same as holiday date");
                }
                
                // Check if reschedule date is already a regular consultation day
                $rescheduleDayOfWeek = date('N', $rescheduleTimestamp); // 1=Monday, 7=Sunday
                if ($rescheduleDayOfWeek == 3 || $rescheduleDayOfWeek == 7) {
                    throw new Exception("Selected date is already a regular consultation day (Wednesday/Sunday)");
                }
                
                // Check if already marked as holiday
                $holidayCheckQuery = "SELECT id FROM holidays WHERE holiday_date = '$rescheduleDate'";
                $holidayCheckResult = Database::search($holidayCheckQuery);
                if ($holidayCheckResult->num_rows > 0) {
                    throw new Exception("Selected date is already marked as a holiday");
                }
                
                // Check if already exists as temporary consultation day
                $tempCheckQuery = "SELECT id FROM temporary_consultation_days WHERE consultation_date = '$rescheduleDate'";
                $tempCheckResult = Database::search($tempCheckQuery);
                if ($tempCheckResult->num_rows > 0) {
                    throw new Exception("Selected date is already a temporary consultation day");
                }

                $rescheduleDayName = date('l', $rescheduleTimestamp);
                $rescheduleDate = Database::$connection->real_escape_string($rescheduleDate);

                // Insert temporary consultation day
                $insertTemp = "INSERT INTO temporary_consultation_days 
                              (consultation_date, day_of_week, reason, created_by, created_at) 
                              VALUES ('$rescheduleDate', '$rescheduleDayName', 
                              'Rescheduled from $dayOfWeek (" . date('M d', $holidayTimestamp) . ") - $reason', 
                              " . ($userId ? $userId : "NULL") . ", NOW())";
                Database::iud($insertTemp);

                $rescheduledDate = $rescheduleDate;
            }

            Database::$connection->commit();

            // Log success
            error_log("Holiday added successfully: $date - $reason");

            return [
                'success' => true,
                'message' => 'Holiday added successfully',
                'holiday_date' => $date,
                'rescheduled_to' => $rescheduledDate
            ];
        } catch (Exception $e) {
            if (Database::$connection) {
                Database::$connection->rollback();
            }
            error_log("Holiday add error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get available dates in a month for rescheduling
     * Excludes: Wednesdays, Sundays, existing holidays, existing temp days
     * 
     * @param string $holidayDate The holiday date to get available dates for
     * @return array Available dates in the same month
     */
    public static function getAvailableDatesForRescheduling($holidayDate)
    {
        try {
            Database::setUpConnection();
            
            $year = date('Y', strtotime($holidayDate));
            $month = date('m', strtotime($holidayDate));
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            
            $availableDates = [];
            
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $currentDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $timestamp = strtotime($currentDate);
                
                // Skip if in the past
                if ($timestamp < strtotime('today')) {
                    continue;
                }
                
                // Skip if it's the holiday date itself
                if ($currentDate === $holidayDate) {
                    continue;
                }
                
                $dayOfWeek = date('N', $timestamp); // 1=Monday, 7=Sunday
                
                // Skip regular consultation days (Wednesday=3, Sunday=7)
                if ($dayOfWeek == 3 || $dayOfWeek == 7) {
                    continue;
                }
                
                // Check if already a holiday
                $holidayCheck = "SELECT id FROM holidays WHERE holiday_date = '$currentDate'";
                $holidayResult = Database::search($holidayCheck);
                if ($holidayResult->num_rows > 0) {
                    continue;
                }
                
                // Check if already a temporary consultation day
                $tempCheck = "SELECT id FROM temporary_consultation_days WHERE consultation_date = '$currentDate'";
                $tempResult = Database::search($tempCheck);
                if ($tempResult->num_rows > 0) {
                    continue;
                }
                
                // This date is available
                $availableDates[] = [
                    'date' => $currentDate,
                    'display' => date('l, M d', $timestamp),
                    'day_name' => date('l', $timestamp)
                ];
            }
            
            return [
                'success' => true,
                'dates' => $availableDates
            ];
        } catch (Exception $e) {
            error_log("Get available dates error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Remove a holiday
     * 
     * @param string $date Holiday date to remove
     * @return array Result array with success status
     */
    public static function removeHoliday($date)
    {
        try {
            Database::setUpConnection();
            Database::$connection->begin_transaction();

            $date = Database::$connection->real_escape_string($date);

            // Delete holiday record
            $deleteQuery = "DELETE FROM holidays WHERE holiday_date = '$date'";
            Database::iud($deleteQuery);

            if (Database::$connection->affected_rows === 0) {
                throw new Exception("Holiday not found for the specified date");
            }

            // Unblock slots for that date (if no appointments booked)
            $unblockQuery = "DELETE FROM blocked_slots 
                            WHERE blocked_date = '$date' 
                            AND reason LIKE 'Holiday:%'
                            AND blocked_time NOT IN (
                                SELECT appointment_time FROM appointment 
                                WHERE appointment_date = '$date' 
                                AND status NOT IN ('Cancelled', 'No-Show')
                            )";
            Database::iud($unblockQuery);

            // Find and remove related temporary consultation day
            // Look for temp days that mention this holiday
            $findTempQuery = "SELECT consultation_date FROM temporary_consultation_days 
                             WHERE reason LIKE '%Rescheduled from%' 
                             AND reason LIKE '%" . date('M d', strtotime($date)) . "%'";
            $tempResult = Database::search($findTempQuery);
            
            if ($tempResult->num_rows > 0) {
                while ($row = $tempResult->fetch_assoc()) {
                    $tempDate = $row['consultation_date'];
                    $deleteTempQuery = "DELETE FROM temporary_consultation_days 
                                       WHERE consultation_date = '$tempDate'";
                    Database::iud($deleteTempQuery);
                }
            }

            Database::$connection->commit();

            error_log("Holiday removed successfully: $date");

            return [
                'success' => true,
                'message' => 'Holiday removed successfully'
            ];
        } catch (Exception $e) {
            if (Database::$connection) {
                Database::$connection->rollback();
            }
            error_log("Holiday remove error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get all holidays
     * 
     * @param bool $futureOnly Whether to get only future holidays
     * @return array Result array with holidays list
     */
    public static function getAllHolidays($futureOnly = true)
    {
        try {
            Database::setUpConnection();

            $whereClause = $futureOnly ? "WHERE holiday_date >= CURDATE()" : "";

            $query = "SELECT * FROM holidays 
                     $whereClause 
                     ORDER BY holiday_date ASC";

            $result = Database::search($query);
            $holidays = [];

            while ($row = $result->fetch_assoc()) {
                $holidays[] = $row;
            }

            return ['success' => true, 'holidays' => $holidays];
        } catch (Exception $e) {
            error_log("Get holidays error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get temporary consultation days
     * 
     * @return array Result array with temporary days list
     */
    public static function getTemporaryConsultationDays()
    {
        try {
            Database::setUpConnection();

            $query = "SELECT * FROM temporary_consultation_days 
                     WHERE consultation_date >= CURDATE() 
                     ORDER BY consultation_date ASC";

            $result = Database::search($query);
            $days = [];

            while ($row = $result->fetch_assoc()) {
                $days[] = $row;
            }

            return ['success' => true, 'days' => $days];
        } catch (Exception $e) {
            error_log("Get temp days error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Check if a date is a consultation day (regular or temporary)
     * 
     * @param string $date Date to check (YYYY-MM-DD)
     * @return bool True if consultation day, false otherwise
     */
    public static function isConsultationDay($date)
    {
        try {
            Database::setUpConnection();

            $date = Database::$connection->real_escape_string($date);
            $dayOfWeek = date('N', strtotime($date)); // 1=Monday, 7=Sunday

            // Check if it's a holiday
            $holidayCheck = "SELECT id FROM holidays WHERE holiday_date = '$date'";
            $holidayResult = Database::search($holidayCheck);

            if ($holidayResult->num_rows > 0) {
                return false; // It's a holiday, not available
            }

            // Check if it's a regular consultation day (Wednesday=3 or Sunday=7)
            if ($dayOfWeek == 3 || $dayOfWeek == 7) {
                return true;
            }

            // Check if it's a temporary consultation day
            $tempCheck = "SELECT id FROM temporary_consultation_days 
                         WHERE consultation_date = '$date'";
            $tempResult = Database::search($tempCheck);

            return $tempResult->num_rows > 0;
        } catch (Exception $e) {
            error_log("Consultation day check error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Block all slots for a specific date
     * Private method used internally
     * 
     * @param string $date Date to block
     * @param string $reason Reason for blocking
     */
    private static function blockAllSlotsForDate($date, $reason)
    {
        try {
            $reason = Database::$connection->real_escape_string($reason);
            
            // Generate all time slots for the day (9 AM to 8 PM, 10-minute intervals)
            $startTime = new DateTime($date . ' 09:00:00');
            $endTime = new DateTime($date . ' 20:00:00');

            while ($startTime <= $endTime) {
                $time = $startTime->format('H:i:s');

                // Check if slot is already booked
                $bookCheck = "SELECT id FROM appointment 
                             WHERE appointment_date = '$date' 
                             AND appointment_time = '$time'
                             AND status NOT IN ('Cancelled', 'No-Show')";
                $bookResult = Database::search($bookCheck);

                // Only block if not already booked
                if ($bookResult->num_rows === 0) {
                    $blockQuery = "INSERT INTO blocked_slots 
                                  (blocked_date, blocked_time, reason, created_at) 
                                  VALUES ('$date', '$time', '$reason', NOW())
                                  ON DUPLICATE KEY UPDATE reason = '$reason'";
                    Database::iud($blockQuery);
                }

                $startTime->add(new DateInterval('PT10M')); // Add 10 minutes
            }
        } catch (Exception $e) {
            error_log("Block slots error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get next available consultation dates (including temporary days, excluding holidays)
     * 
     * @param int $limit Maximum number of dates to return
     * @return array Result array with consultation dates
     */
    public static function getNextConsultationDates($limit = 10)
    {
        try {
            Database::setUpConnection();

            $dates = [];
            $currentDate = new DateTime();
            $daysChecked = 0;
            $maxDays = 90; // Check up to 90 days ahead

            while (count($dates) < $limit && $daysChecked < $maxDays) {
                $dateStr = $currentDate->format('Y-m-d');

                if (self::isConsultationDay($dateStr)) {
                    $dayOfWeek = $currentDate->format('N');
                    $isTemporary = ($dayOfWeek != 3 && $dayOfWeek != 7);

                    // Get reason if temporary consultation day
                    $reason = '';
                    if ($isTemporary) {
                        $query = "SELECT reason FROM temporary_consultation_days 
                                 WHERE consultation_date = '$dateStr'";
                        $result = Database::search($query);
                        if ($result->num_rows > 0) {
                            $row = $result->fetch_assoc();
                            $reason = $row['reason'];
                        }
                    }

                    $dates[] = [
                        'date' => $dateStr,
                        'display_date' => $currentDate->format('l, j M Y'),
                        'day_name' => $currentDate->format('l'),
                        'is_temporary' => $isTemporary,
                        'reason' => $reason
                    ];
                }

                $currentDate->add(new DateInterval('P1D'));
                $daysChecked++;
            }

            return ['success' => true, 'dates' => $dates];
        } catch (Exception $e) {
            error_log("Get consultation dates error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}

// ============================================
// API ENDPOINTS
// ============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add_holiday':
            $date = $_POST['date'] ?? '';
            $reason = $_POST['reason'] ?? '';
            $reschedule = isset($_POST['reschedule']) && $_POST['reschedule'] === 'true';
            $customDate = $_POST['custom_reschedule_date'] ?? null;

            if (empty($date) || empty($reason)) {
                echo json_encode(['success' => false, 'message' => 'Date and reason are required']);
                exit;
            }

            $result = HolidayManager::addHoliday($date, $reason, $reschedule, $customDate);
            echo json_encode($result);
            break;

        case 'get_available_reschedule_dates':
            $holidayDate = $_POST['holiday_date'] ?? '';
            
            if (empty($holidayDate)) {
                echo json_encode(['success' => false, 'message' => 'Holiday date is required']);
                exit;
            }
            
            $result = HolidayManager::getAvailableDatesForRescheduling($holidayDate);
            echo json_encode($result);
            break;

        case 'remove_holiday':
            $date = $_POST['date'] ?? '';

            if (empty($date)) {
                echo json_encode(['success' => false, 'message' => 'Date is required']);
                exit;
            }

            $result = HolidayManager::removeHoliday($date);
            echo json_encode($result);
            break;

        case 'get_holidays':
            $result = HolidayManager::getAllHolidays(true);
            echo json_encode($result);
            break;

        case 'get_temp_days':
            $result = HolidayManager::getTemporaryConsultationDays();
            echo json_encode($result);
            break;

        case 'get_consultation_dates':
            $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;
            $result = HolidayManager::getNextConsultationDates($limit);
            echo json_encode($result);
            break;

        case 'check_consultation_day':
            $date = $_POST['date'] ?? '';
            if (empty($date)) {
                echo json_encode(['success' => false, 'message' => 'Date is required']);
                exit;
            }

            $isConsultation = HolidayManager::isConsultationDay($date);
            echo json_encode([
                'success' => true,
                'is_consultation_day' => $isConsultation,
                'date' => $date
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
            break;
    }
    exit;
}
?>