<?php
// Start output buffering to prevent any accidental output
ob_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../libraries/PhpMailer/src/Exception.php';
require_once __DIR__ . '/../libraries/PhpMailer/src/PHPMailer.php';
require_once __DIR__ . '/../libraries/PhpMailer/src/SMTP.php';

include_once __DIR__ . '/../database/config.php';

// Clear any output that might have been generated
ob_clean();

header('Content-Type: application/json');

// Helper function to send JSON response and exit
function sendJsonResponse($data) {
    // Clear and end output buffer if active
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode($data);
    exit();
}

// Function to generate new prefixed ID
if (!function_exists('generateID')) {
    function generateID($prefix, $table, $column, $con) {
        try {
            $query = "SELECT $column FROM $table ORDER BY $column DESC LIMIT 1";
            $result = mysqli_query($con, $query);
            // With mysqli_report enabled, mysqli_query throws exceptions, so this may not be reached
            // But keep it for backward compatibility
            if (!$result) {
                // Table doesn't exist or query failed, return default ID
                return $prefix . str_pad(1, 3, '0', STR_PAD_LEFT);
            }
            $row = mysqli_fetch_assoc($result);
            if ($row && !empty($row[$column])) {
                $lastNum = intval(substr($row[$column], strlen($prefix))) + 1;
            } else {
                $lastNum = 1;
            }
            return $prefix . str_pad($lastNum, 3, '0', STR_PAD_LEFT);
        } catch (mysqli_sql_exception $e) {
            // Catch mysqli exceptions (table doesn't exist, etc.)
            return $prefix . str_pad(1, 3, '0', STR_PAD_LEFT);
        } catch (Exception $e) {
            // Catch any other exceptions
            return $prefix . str_pad(1, 3, '0', STR_PAD_LEFT);
        }
    }
}

// Set error handler to catch fatal errors and return JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Only handle fatal errors that would break JSON output
    if (error_reporting() === 0) {
        return false;
    }
    return false; // Let PHP handle it normally, but we'll catch it in try-catch
});

// Set exception handler
set_exception_handler(function($exception) {
    ob_clean();
    sendJsonResponse([
        'success' => false,
        'message' => 'An error occurred: ' . $exception->getMessage()
    ]);
});

// Wrap everything in try-catch to handle any fatal errors
try {
    // Get appointment_id from POST or JSON body
    $appointment_id = null;
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        if (isset($_POST['appointment_id']) && trim($_POST['appointment_id']) !== '') {
            $appointment_id = trim($_POST['appointment_id']);
        } else {
            // Try JSON body (e.g. when Content-Type is application/json)
            $input = json_decode(file_get_contents('php://input'), true);
            if (is_array($input) && isset($input['appointment_id']) && trim($input['appointment_id']) !== '') {
                $appointment_id = trim($input['appointment_id']);
            }
        }
    }

    if ($appointment_id) {

    // Get appointment details with joins to patient_information, services, and multidisciplinary_dental_team
    $stmt = $con->prepare("SELECT a.*, 
                                   p.first_name, p.last_name, p.email, p.user_id,
                                   s.service_category, s.sub_service,
                                   d.first_name as dentist_first, d.last_name as dentist_last
                           FROM appointments a 
                           LEFT JOIN patient_information p ON a.patient_id = p.patient_id 
                           LEFT JOIN services s ON a.service_id = s.service_id
                           LEFT JOIN multidisciplinary_dental_team d ON a.team_id = d.team_id
                           WHERE a.appointment_id = ?");
    $stmt->bind_param("s", $appointment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointment = $result->fetch_assoc();
    $stmt->close();

    if ($appointment) {
        // Update status to Confirmed
        $stmtUpdate = $con->prepare("UPDATE appointments SET status = 'Confirmed' WHERE appointment_id = ?");
        $stmtUpdate->bind_param("s", $appointment_id);

        if ($stmtUpdate->execute()) {
            // Prepare patient name and service details (null-safe for LEFT JOIN)
            $first = $appointment['first_name'] ?? '';
            $last = $appointment['last_name'] ?? '';
            $patient_name = trim($first . ' ' . $last);
            $service = !empty($appointment['sub_service']) ? $appointment['sub_service'] : ($appointment['service_category'] ?? 'Dental Service');
            $dentistFirst = $appointment['dentist_first'] ?? '';
            $dentistLast = $appointment['dentist_last'] ?? '';
            $dentist = trim($dentistFirst . ' ' . $dentistLast);
            $email = $appointment['email'] ?? '';
            $user_id = $appointment['user_id'] ?? null;
            
            // === NOTIFICATION INSERT ===
            // Try to insert notification, but don't fail if table doesn't exist
            if (!empty($user_id)) {
                try {
                    // Check if notifications table exists
                    $tableCheck = mysqli_query($con, "SHOW TABLES LIKE 'notifications'");
                    if (mysqli_num_rows($tableCheck) > 0) {
                        $notification_id = generateID('N', 'notifications', 'notification_id', $con);
                        $dentistName = 'Dr. ' . $dentist;
                        $dentistName = mysqli_real_escape_string($con, $dentistName);
                        $appointment_date = mysqli_real_escape_string($con, $appointment['appointment_date']);
                        $appointment_time = mysqli_real_escape_string($con, $appointment['appointment_time']);
                        $user_id_escaped = mysqli_real_escape_string($con, $user_id);
                        
                        $insertNotification = "INSERT INTO notifications 
                            (notification_id, user_id, type, appointment_date, appointment_time, dentist_name, is_read, created_at)
                            VALUES 
                            ('$notification_id', '$user_id_escaped', 'confirmed', '$appointment_date', '$appointment_time', '$dentistName', 0, NOW())";
                        
                        $notificationResult = mysqli_query($con, $insertNotification);
                        if (!$notificationResult) {
                            // Log error but don't fail the appointment confirmation
                            error_log("Failed to insert notification: " . mysqli_error($con));
                        }
                    }
                } catch (Exception $e) {
                    // Log error but don't fail the appointment confirmation
                    error_log("Notification insert error: " . $e->getMessage());
                }
            }
            
            // Send email (only if patient has email)
            $emailSent = false;
            if (!empty($email)) {
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'mlanderodentalclinic@gmail.com';
                    $mail->Password = 'xrfp cpvv ckdv jmht'; 
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;

                    $mail->setFrom('mlanderodentalclinic@gmail.com', 'Landero Dental Clinic');
                    $mail->addAddress($email, $patient_name);
                    $mail->isHTML(true);
                    $mail->Subject = 'Appointment Confirmed';

                    $branch = $appointment['branch'] ?? 'Main Branch';
                    $mail->Body = "
                        <h3>Hi {$patient_name},</h3>
                        <p>Your appointment has been <strong>confirmed</strong>.</p>
                        <p><strong>Service:</strong> {$service}<br>
                        <strong>Dentist:</strong> {$dentist}<br>
                        <strong>Date:</strong> " . date('F j, Y', strtotime($appointment['appointment_date'])) . "<br>
                        <strong>Time:</strong> {$appointment['appointment_time']}<br>
                        <strong>Branch:</strong> {$branch}</p>
                        <p>Thank you for choosing our clinic!</p>
                    ";

                    $mail->send();
                    $emailSent = true;
                } catch (Exception $e) {
                    error_log("Email sending failed: " . $mail->ErrorInfo);
                }
            }

            // Return JSON success response
            sendJsonResponse([
                'success' => true,
                'status' => 'success',
                'message' => $emailSent ? 'Appointment confirmed and email sent successfully.' : 'Appointment confirmed successfully.'
            ]);
        } else {
            sendJsonResponse([
                'success' => false,
                'message' => 'Error updating appointment: ' . $stmtUpdate->error
            ]);
        }

        $stmtUpdate->close();
    } else {
        sendJsonResponse([
            'success' => false,
            'message' => 'Appointment not found.'
        ]);
    }
    
    // Don't close connection here as it might be used elsewhere
    // $con->close();
    } else {
        sendJsonResponse([
            'success' => false,
            'message' => 'Invalid request. Appointment ID is required.'
        ]);
    }
} catch (Exception $e) {
    // Catch any unhandled exceptions and return JSON error
    sendJsonResponse([
        'success' => false,
        'message' => 'An error occurred while processing your request: ' . $e->getMessage()
    ]);
} catch (Error $e) {
    // Catch fatal errors (like missing tables)
    sendJsonResponse([
        'success' => false,
        'message' => 'A system error occurred. Please contact support.'
    ]);
}
