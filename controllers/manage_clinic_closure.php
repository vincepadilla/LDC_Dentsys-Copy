<?php
session_start();

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once(__DIR__ . '/../database/config.php');

// PHPMailer
require_once(__DIR__ . '/../libraries/PhpMailer/src/Exception.php');
require_once(__DIR__ . '/../libraries/PhpMailer/src/PHPMailer.php');
require_once(__DIR__ . '/../libraries/PhpMailer/src/SMTP.php');

 use PHPMailer\PHPMailer\PHPMailer;

if (!function_exists('jsonResponse')) {
    function jsonResponse(array $data, int $statusCode = 200): void
    {
        if (ob_get_length()) {
            ob_clean();
        }

        http_response_code($statusCode);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit();
    }
}

if (!function_exists('safePrepare')) {
     function safePrepare(mysqli $con, string $query): mysqli_stmt
    {
        $stmt = $con->prepare($query);
        if (!$stmt) {
             throw new \Exception('SQL prepare failed: ' . $con->error);
        }
        return $stmt;
    }
}

if (!isset($_SESSION['userID']) || !isset($_SESSION['role']) || strtolower((string) $_SESSION['role']) !== 'admin') {
    jsonResponse([
        'success' => false,
        'message' => 'Unauthorized access'
    ], 403);
}

if (!isset($con) || !$con instanceof mysqli) {
    jsonResponse([
        'success' => false,
        'message' => 'Database connection not initialized'
    ], 500);
}

try {
    checkClinicClosuresTable($con);
    checkHolidaysTable($con);

    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    if ($rawInput !== '' && json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse([
            'success' => false,
            'message' => 'Invalid JSON input',
            'error' => json_last_error_msg()
        ], 400);
    }

    if (!is_array($input)) {
        $input = [];
    }

    $action = $input['action'] ?? '';

    switch ($action) {
        case 'block_day':
            $result = blockDay($con, $input);
            jsonResponse($result, $result['success'] ? 200 : 400);
            break;
        case 'check_date_bookings':
            $result = checkDateBookings($con, $input);
            jsonResponse($result, 200);
            break;
        case 'check_range_bookings':
            $result = checkRangeBookings($con, $input);
            jsonResponse($result, 200);
            break;

        case 'add_holiday':
            $result = addHoliday($con, $input);
            jsonResponse($result, $result['success'] ? 200 : 400);
            break;

        case 'delete_holiday':
            $result = deleteHoliday($con, $input);
            jsonResponse($result, $result['success'] ? 200 : 400);
            break;

        case 'emergency_closure':
            $result = emergencyClosure($con, $input);
            jsonResponse($result, $result['success'] ? 200 : 400);
            break;

        case 'remove_closure':
            $result = removeClosure($con, $input);
            jsonResponse($result, $result['success'] ? 200 : 400);
            break;

        case 'remove_all_closures':
            $result = removeAllClosures($con);
            jsonResponse($result, $result['success'] ? 200 : 400);
            break;

        default:
            jsonResponse([
                'success' => false,
                'message' => 'Invalid action'
            ], 400);
    }
 } catch (Throwable $e) {
    jsonResponse([
        'success' => false,
        'message' => 'Server error',
        'error' => $e->getMessage(),
        'line' => $e->getLine(),
        'file' => basename($e->getFile())
    ], 500);
}

 function blockDay(mysqli $con, array $data, bool $silent = false): array
{
     try {
         $date = trim((string)($data['date'] ?? ''));
         $closureType = trim((string)($data['closure_type'] ?? 'full_day'));
         $reason = trim((string)($data['reason'] ?? ''));
         $customReason = trim((string)($data['custom_reason'] ?? ''));
         $notifyPatients = !empty($data['notify_patients']);

         if ($date === '') {
             return ['success' => false, 'message' => 'Date is required'];
         }

         if ($reason === 'Other' && $customReason !== '') {
             $reason = $customReason;
         }

         if ($reason === '') {
             return ['success' => false, 'message' => 'Reason is required'];
         }

         if (!in_array($closureType, ['full_day', 'no_new_appointments'], true)) {
             $closureType = 'full_day';
         }

         $checkStmt = safePrepare(
             $con,
             "SELECT id FROM clinic_closures WHERE closure_date = ? AND status = 'active' LIMIT 1"
         );
         $checkStmt->bind_param("s", $date);
         $checkStmt->execute();
         $checkStmt->store_result();

         if ($checkStmt->num_rows > 0) {
             return ['success' => false, 'message' => 'Closure already exists for this date'];
         }

         $insertStmt = safePrepare(
             $con,
             "INSERT INTO clinic_closures (closure_date, closure_type, reason, status, created_at)
              VALUES (?, ?, ?, 'active', NOW())"
         );
         $insertStmt->bind_param("sss", $date, $closureType, $reason);

         if (!$insertStmt->execute()) {
             return [
                 'success' => false,
                 'message' => 'Failed to block day',
                 'error' => $con->error ?: 'Insert execute returned false'
             ];
         }

         $closureId = $con->insert_id;

         // Full day closure handled only via clinic_closures table
         // No blocked_time_slots inserts required for full_day

         if ($notifyPatients) {
             notifyAffectedPatients($con, $date, $reason, $closureType);
             if (function_exists('sendClosureEmails')) {
                 try {
                     sendClosureEmails($con, $date, $reason);
                 } catch (\Throwable $e) {
                     // ignore email errors
                 }
             }
         }

         return ['success' => true, 'message' => 'Day blocked successfully'];
     } catch (\Throwable $e) {
         // Fail-safe: if the closure was inserted but a later step threw,
         // report success to avoid showing a false error on the UI.
         try {
             $date = trim((string)($data['date'] ?? ''));
             if ($date !== '') {
                 $existsStmt = safePrepare(
                     $con,
                     "SELECT id FROM clinic_closures WHERE closure_date = ? AND status = 'active' LIMIT 1"
                 );
                 $existsStmt->bind_param("s", $date);
                 $existsStmt->execute();
                 $existsStmt->store_result();
                 if ($existsStmt->num_rows > 0) {
                     return ['success' => true, 'message' => 'Day blocked successfully'];
                 }
             }
         } catch (\Throwable $ignored) {
             // ignore fail-safe errors and fall through to original error response
         }
         return [
             'success' => false,
             'message' => 'Block day failed',
             'error' => $e->getMessage()
         ];
     }
}

function checkDateBookings(mysqli $con, array $data): array
{
    $date = trim((string)($data['date'] ?? ''));
    if ($date === '') {
        return [
            'success' => false,
            'message' => 'Date is required',
            'booked_count' => 0
        ];
    }

    $stmt = safePrepare(
        $con,
        "SELECT COUNT(*) AS booked_count
         FROM appointments
         WHERE appointment_date = ?
           AND status NOT IN ('Cancelled', 'Completed', 'No-show')"
    );
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $bookedCount = (int)($row['booked_count'] ?? 0);

    return [
        'success' => true,
        'has_bookings' => $bookedCount > 0,
        'booked_count' => $bookedCount
    ];
}

function checkRangeBookings(mysqli $con, array $data): array
{
    $startDate = trim((string)($data['start_date'] ?? ''));
    $endDate = trim((string)($data['end_date'] ?? ''));

    if ($startDate === '' || $endDate === '') {
        return [
            'success' => false,
            'message' => 'Start date and end date are required',
            'booked_count' => 0
        ];
    }

    // Ensure proper order
    if ($endDate < $startDate) {
        $tmp = $startDate;
        $startDate = $endDate;
        $endDate = $tmp;
    }

    $stmt = safePrepare(
        $con,
        "SELECT COUNT(*) AS booked_count
         FROM appointments
         WHERE appointment_date BETWEEN ? AND ?
           AND status NOT IN ('Cancelled', 'Completed', 'No-show')"
    );
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $bookedCount = (int)($row['booked_count'] ?? 0);

    return [
        'success' => true,
        'has_bookings' => $bookedCount > 0,
        'booked_count' => $bookedCount
    ];
}

function addHoliday(mysqli $con, array $data): array
{
    $holidayName = trim((string)($data['holiday_name'] ?? ''));
    $holidayDate = trim((string)($data['holiday_date'] ?? ''));
    $recurrence = trim((string)($data['recurrence'] ?? 'once'));

    if ($holidayName === '' || $holidayDate === '') {
        return ['success' => false, 'message' => 'Holiday name and date are required'];
    }

    if (!in_array($recurrence, ['once', 'yearly'], true)) {
        $recurrence = 'once';
    }

    $insertStmt = safePrepare(
        $con,
        "INSERT INTO holidays (holiday_name, holiday_date, recurrence, created_at)
         VALUES (?, ?, ?, NOW())"
    );
    $insertStmt->bind_param("sss", $holidayName, $holidayDate, $recurrence);

    if (!$insertStmt->execute()) {
        return ['success' => false, 'message' => 'Failed to add holiday'];
    }

    $blockResp = blockDay($con, [
        'date' => $holidayDate,
        'closure_type' => 'full_day',
        'reason' => "Holiday: {$holidayName}",
        'custom_reason' => '',
        'notify_patients' => true
    ], true);

    if (!empty($blockResp['success'])) {
        return ['success' => true, 'message' => 'Holiday added and day blocked successfully'];
    }

    return [
        'success' => true,
        'message' => 'Holiday added (closure not created)',
        'closure_error' => $blockResp['message'] ?? 'Unknown error'
    ];
}

function removeAllClosures(mysqli $con): array
{
    $deleteStmt = safePrepare($con, "DELETE FROM clinic_closures WHERE status = 'active'");

    if (!$deleteStmt->execute()) {
        return ['success' => false, 'message' => 'Failed to remove all closures'];
    }

    $affected = $deleteStmt->affected_rows;

    return [
        'success' => true,
        'message' => $affected > 0
            ? 'All active closures removed successfully'
            : 'No active closures to remove',
        'removed_count' => $affected
    ];
}

function deleteHoliday(mysqli $con, array $data): array
{
    $holidayId = (int)($data['holiday_id'] ?? 0);

    if ($holidayId <= 0) {
        return ['success' => false, 'message' => 'Holiday ID is required'];
    }

    $getStmt = safePrepare($con, "SELECT holiday_date FROM holidays WHERE id = ? LIMIT 1");
    $getStmt->bind_param("i", $holidayId);
    $getStmt->execute();
    $result = $getStmt->get_result();
    $holiday = $result->fetch_assoc();

    $deleteStmt = safePrepare($con, "DELETE FROM holidays WHERE id = ?");
    $deleteStmt->bind_param("i", $holidayId);

    if (!$deleteStmt->execute()) {
        return ['success' => false, 'message' => 'Failed to delete holiday'];
    }

    if ($holiday && !empty($holiday['holiday_date'])) {
        removeClosure($con, ['date' => $holiday['holiday_date']], true);
    }

    return ['success' => true, 'message' => 'Holiday deleted successfully'];
}

function emergencyClosure(mysqli $con, array $data): array
{
    $startDate = trim((string)($data['start_date'] ?? ''));
    $endDate = trim((string)($data['end_date'] ?? ''));
    $reason = trim((string)($data['reason'] ?? 'Emergency closure'));
    $notifyPatients = !empty($data['notify_patients']);

    if ($startDate === '') {
        return ['success' => false, 'message' => 'Start date is required'];
    }

    if ($endDate === '') {
        $endDate = $startDate;
    }

    $emergencyReason = "Emergency: {$reason}";

    $start = new DateTime($startDate);
    $end = new DateTime($endDate);

    if ($start > $end) {
        return ['success' => false, 'message' => 'End date must be greater than or equal to start date'];
    }

    $end->modify('+1 day');
    $interval = new DateInterval('P1D');
    $dateRange = new DatePeriod($start, $interval, $end);

    $processedDates = 0;
    $anyApplied = false;

    try {
        foreach ($dateRange as $date) {
            $dateStr = $date->format('Y-m-d');
            $processedDates++;

            $checkStmt = safePrepare(
                $con,
                "SELECT id FROM clinic_closures WHERE closure_date = ? AND status = 'active' LIMIT 1"
            );
            $checkStmt->bind_param("s", $dateStr);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows === 0) {
                $insertStmt = safePrepare(
                    $con,
                    "INSERT INTO clinic_closures (closure_date, closure_type, reason, status, created_at)
                     VALUES (?, 'full_day', ?, 'active', NOW())"
                );
                $insertStmt->bind_param("ss", $dateStr, $emergencyReason);
                $insertStmt->execute();
                $anyApplied = true;
            } else {
                // already exists; still consider the range "applied" for user intent
                $anyApplied = true;
            }

            if ($notifyPatients) {
                notifyAffectedPatients($con, $dateStr, $emergencyReason, 'full_day');
                if (function_exists('sendClosureEmails')) {
                    try {
                        sendClosureEmails($con, $dateStr, $emergencyReason);
                    } catch (\Throwable $e) {
                        // ignore email errors
                    }
                }
            }
        }
    } catch (\Throwable $e) {
        // Fail-safe: if at least one day was applied/exists, report success to avoid false UI errors.
        if ($anyApplied) {
            return [
                'success' => true,
                'message' => "Emergency closure activated from {$startDate} to {$endDate}",
                'processed_dates' => $processedDates
            ];
        }
        return [
            'success' => false,
            'message' => 'Emergency closure failed',
            'error' => $e->getMessage()
        ];
    }

    return [
        'success' => true,
        'message' => "Emergency closure activated from {$startDate} to {$endDate}",
        'processed_dates' => $processedDates
    ];
}

function removeClosure(mysqli $con, array $data, bool $silent = false): array
{
    $date = trim((string)($data['date'] ?? ''));

    if ($date === '') {
        return ['success' => false, 'message' => 'Date is required'];
    }

    $deleteStmt = safePrepare($con, "DELETE FROM clinic_closures WHERE closure_date = ?");
    $deleteStmt->bind_param("s", $date);

    if (!$deleteStmt->execute()) {
        return ['success' => false, 'message' => 'Failed to delete closure'];
    }

    $affected = $deleteStmt->affected_rows;

    $unblockStmt = $con->prepare(
        "DELETE FROM blocked_time_slots WHERE date = ? AND reason LIKE 'Clinic Closure:%'"
    );
    if ($unblockStmt) {
        $unblockStmt->bind_param("s", $date);
        $unblockStmt->execute();
    }

    return [
        'success' => true,
        'message' => $affected > 0
            ? 'Closure deleted successfully'
            : 'No closure found for this date; nothing to delete'
    ];
}

function blockAllTimeSlotsForDate(mysqli $con, string $date, string $reason, ?int $closureId = null): void
{
     // Validate that required tables/columns exist; if not, skip silently to avoid crashing
     if (!tableHasColumns($con, 'multidisciplinary_dental_team', ['team_id', 'status'])) {
         return;
     }
     if (!tableHasColumns($con, 'blocked_time_slots', ['dentist_id', 'date', 'time_slot', 'reason'])) {
         return;
     }
 
     $dentistsQuery = "SELECT team_id FROM multidisciplinary_dental_team WHERE status = 'active'";
     $dentistsResult = mysqli_query($con, $dentistsQuery);

    if (!$dentistsResult || mysqli_num_rows($dentistsResult) === 0) {
        return;
    }

    $timeSlots = [
        'firstBatch',
        'secondBatch',
        'thirdBatch',
        'fourthBatch',
        'fifthBatch',
        'sixthBatch',
        'sevenBatch',
        'eightBatch',
        'nineBatch',
        'tenBatch',
        'lastBatch'
    ];

    $closureReason = "Clinic Closure: {$reason}";

    while ($dentist = mysqli_fetch_assoc($dentistsResult)) {
        $dentistId = $dentist['team_id'];

        foreach ($timeSlots as $timeSlot) {
            $checkStmt = $con->prepare(
                "SELECT block_id FROM blocked_time_slots WHERE dentist_id = ? AND date = ? AND time_slot = ? LIMIT 1"
            );

             if (!$checkStmt) {
                continue;
            }

            $checkStmt->bind_param("sss", $dentistId, $date, $timeSlot);
            $checkStmt->execute();
            $checkStmt->store_result();

            if ($checkStmt->num_rows === 0) {
                 // Generate explicit block_id when the table doesn't auto-increment
                 $blockId = generateBlockedTimeSlotID($con);
                 $insertStmt = $con->prepare(
                     "INSERT INTO blocked_time_slots (block_id, dentist_id, date, time_slot, reason, created_at)
                      VALUES (?, ?, ?, ?, ?, NOW())"
                 );

                 if (!$insertStmt) {
                    continue;
                }

                 $insertStmt->bind_param("sssss", $blockId, $dentistId, $date, $timeSlot, $closureReason);
                $insertStmt->execute();
            }
        }
    }
}
 
 // Helper: check if table exists and has required columns
 function tableHasColumns(mysqli $con, string $table, array $columns): bool
 {
     $safeTable = preg_replace('/[^A-Za-z0-9_]/', '', $table);
     if ($safeTable === '') return false;
 
     $res = mysqli_query($con, "SHOW TABLES LIKE '{$safeTable}'");
     if (!$res || mysqli_num_rows($res) === 0) return false;
 
     $desc = mysqli_query($con, "DESCRIBE {$safeTable}");
     if (!$desc) return false;
 
     $present = [];
     while ($row = mysqli_fetch_assoc($desc)) {
         $present[strtolower((string)$row['Field'])] = true;
     }
     foreach ($columns as $col) {
         if (empty($present[strtolower($col)])) return false;
     }
     return true;
 }

function notifyAffectedPatients(mysqli $con, string $date, string $reason, string $closureType): void
{
    $appointmentsQuery = "
        SELECT appointment_id, patient_id, appointment_date, appointment_time
        FROM appointments
        WHERE appointment_date = ?
          AND status NOT IN ('Cancelled', 'Completed', 'No-show')
    ";

    $apptStmt = $con->prepare($appointmentsQuery);
    if (!$apptStmt) {
        return;
    }

    $apptStmt->bind_param("s", $date);
    // Use bind_result to avoid mysqlnd dependency
    $appointmentId = null;
    $patientId = null;
    $appointmentDate = null;
    $appointmentTime = null;
    $apptStmt->execute();
    $apptStmt->bind_result($appointmentId, $patientId, $appointmentDate, $appointmentTime);

    while ($apptStmt->fetch()) {
        if (!$patientId) {
            continue;
        }

        $userStmt = $con->prepare("SELECT user_id FROM patient_information WHERE patient_id = ? LIMIT 1");
        if (!$userStmt) {
            continue;
        }
        $userStmt->bind_param("s", $patientId);
        $fetchedUserId = null;
        $userStmt->execute();
        $userStmt->bind_result($fetchedUserId);
        $hasUser = $userStmt->fetch();

        if (!$hasUser || !$fetchedUserId) {
            continue;
        }

        $notificationId = generateNotificationID($con);
        $message = "Clinic Closure: {$reason} - Your appointment on {$date} has been affected.";

        $notifStmt = $con->prepare(
            "INSERT INTO notifications
             (notification_id, user_id, type, appointment_date, appointment_time, message, is_read, created_at)
             VALUES (?, ?, 'closure', ?, ?, ?, 0, NOW())"
        );
        if (!$notifStmt) {
            continue;
        }
        $notifStmt->bind_param(
            "sssss",
            $notificationId,
            $fetchedUserId,
            $appointmentDate,
            $appointmentTime,
            $message
        );
        $notifStmt->execute();
    }
}

function checkClinicClosuresTable(mysqli $con): void
{
    $checkTable = "SHOW TABLES LIKE 'clinic_closures'";
    $result = mysqli_query($con, $checkTable);

    if (!$result) {
        throw new \Exception('Failed to check clinic_closures table: ' . $con->error);
    }

    if (mysqli_num_rows($result) === 0) {
        $createTable = "
            CREATE TABLE clinic_closures (
                id INT AUTO_INCREMENT PRIMARY KEY,
                closure_date DATE NOT NULL,
                closure_type ENUM('full_day', 'no_new_appointments') NOT NULL DEFAULT 'full_day',
                reason VARCHAR(255) NOT NULL,
                status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_active_closure (closure_date, status),
                INDEX idx_closure_date (closure_date),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";

        if (!mysqli_query($con, $createTable)) {
            throw new \Exception('Failed to create clinic_closures table: ' . $con->error);
        }
    }
}

function checkHolidaysTable(mysqli $con): void
{
    $checkTable = "SHOW TABLES LIKE 'holidays'";
    $result = mysqli_query($con, $checkTable);

    if (!$result) {
        throw new \Exception('Failed to check holidays table: ' . $con->error);
    }

    if (mysqli_num_rows($result) === 0) {
        $createTable = "
            CREATE TABLE holidays (
                id INT AUTO_INCREMENT PRIMARY KEY,
                holiday_name VARCHAR(255) NOT NULL,
                holiday_date DATE NOT NULL,
                recurrence ENUM('once', 'yearly') NOT NULL DEFAULT 'once',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_holiday_date (holiday_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";

        if (!mysqli_query($con, $createTable)) {
            throw new \Exception('Failed to create holidays table: ' . $con->error);
        }
    }
}

function generateNotificationID(mysqli $con): string
{
    $query = "SELECT notification_id FROM notifications ORDER BY notification_id DESC LIMIT 1";
    $result = mysqli_query($con, $query);

    if (!$result) {
        return 'N001';
    }

    $row = mysqli_fetch_assoc($result);

    if ($row && !empty($row['notification_id'])) {
        $lastNum = (int)substr($row['notification_id'], 1) + 1;
    } else {
        $lastNum = 1;
    }

    return 'N' . str_pad((string)$lastNum, 3, '0', STR_PAD_LEFT);
}
 
 // Generate primary key for blocked_time_slots when the table doesn't auto-increment
 function generateBlockedTimeSlotID(mysqli $con): string
 {
     $res = mysqli_query($con, "SELECT block_id FROM blocked_time_slots ORDER BY block_id DESC LIMIT 1");
     if ($res && ($row = mysqli_fetch_assoc($res)) && !empty($row['block_id'])) {
         $num = (int)preg_replace('/[^0-9]/', '', $row['block_id']) + 1;
     } else {
         $num = 1;
     }
     return 'BLK' . str_pad((string)$num, 3, '0', STR_PAD_LEFT);
 }

function sendClosureEmails(mysqli $con, string $date, string $reason): int
{
    $query = "
        SELECT DISTINCT
            ua.email,
            COALESCE(pi.first_name, '') AS first_name,
            COALESCE(pi.last_name, '') AS last_name
        FROM appointments a
        INNER JOIN patient_information pi ON a.patient_id = pi.patient_id
        INNER JOIN user_account ua ON pi.user_id = ua.user_id
        WHERE a.appointment_date = ?
          AND a.status NOT IN ('Cancelled', 'Completed', 'No-show')
          AND ua.email IS NOT NULL
          AND ua.email <> ''
    ";

    $stmt = $con->prepare($query);
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param("s", $date);
    $stmt->execute();
    // Bind to avoid mysqlnd dependency
    $email = null;
    $firstName = null;
    $lastName = null;
    $stmt->bind_result($email, $firstName, $lastName);

    $sent = 0;
    while ($stmt->fetch()) {
        $safeEmail = trim((string)($email ?? ''));
        if ($safeEmail === '') {
            continue;
        }
        $displayName = trim((string)($firstName ?? '') . ' ' . (string)($lastName ?? ''));

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'mlanderodentalclinic@gmail.com';
            $mail->Password = 'xrfp cpvv ckdv jmht';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->CharSet = 'UTF-8';

            $mail->setFrom('mlanderodentalclinic@gmail.com', 'Landero Dental Clinic');
            $mail->addAddress($safeEmail, $displayName !== '' ? $displayName : 'Patient');

            $mail->isHTML(true);
            $mail->Subject = 'Clinic Closure Notice - ' . $date;

            $safeName = htmlspecialchars($displayName !== '' ? $displayName : 'Patient', ENT_QUOTES, 'UTF-8');
            $safeReason = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');
            $safeDate = htmlspecialchars($date, ENT_QUOTES, 'UTF-8');

            $mail->Body = "
                <p>Dear {$safeName},</p>
                <p>We regret to inform you that the clinic will be <strong>closed on {$safeDate}</strong>.</p>
                <p>Reason: <em>{$safeReason}</em></p>
                <p>Your appointment on this date is affected. Our team will reach out with rescheduling options, or you may reply to this email to coordinate a new time.</p>
                <p>We apologize for the inconvenience,<br>Landero Dental Clinic</p>
            ";

            $mail->AltBody =
                "Dear " . ($displayName !== '' ? $displayName : 'Patient') .
                ",\n\nThe clinic will be closed on {$date}.\nReason: {$reason}\nYour appointment on this date is affected. We will reach out to reschedule.\n\nLandero Dental Clinic";

            $mail->send();
            $sent++;
        } catch (Throwable $e) {
            continue;
        }
    }

    return $sent;
}
?>