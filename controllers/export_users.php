<?php
session_start();
include_once('../database/config.php');

// Check if admin is logged in
if (!isset($_SESSION['userID']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: ../views/login.php");
    exit();
}

// Get all users with appointment count
$usersQuery = "
    SELECT 
        ua.user_id,
        ua.username,
        ua.first_name,
        ua.last_name,
        ua.email,
        ua.phone,
        ua.role,
        ua.created_at,
        COALESCE(ua.status, 'active') as account_status,
        COUNT(DISTINCT a.appointment_id) as appointment_count,
        MAX(a.appointment_date) as last_appointment_date
    FROM user_account ua
    LEFT JOIN patient_information p ON ua.user_id = p.user_id
    LEFT JOIN appointments a ON p.patient_id = a.patient_id
    WHERE ua.role != 'admin'
    GROUP BY ua.user_id, ua.username, ua.first_name, ua.last_name, ua.email, ua.phone, ua.role, ua.created_at, ua.status
    ORDER BY ua.created_at DESC
";
$usersResult = mysqli_query($con, $usersQuery);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d_His') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8 Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write CSV header
fputcsv($output, [
    'User ID',
    'Username',
    'First Name',
    'Last Name',
    'Email',
    'Phone',
    'Role',
    'Account Status',
    'Appointment Count',
    'Last Appointment Date',
    'Account Created At'
]);

// Write user data
if (mysqli_num_rows($usersResult) > 0) {
    while ($user = mysqli_fetch_assoc($usersResult)) {
        $lastAppt = $user['last_appointment_date'] ? date('Y-m-d', strtotime($user['last_appointment_date'])) : 'N/A';
        $createdAt = $user['created_at'] ? date('Y-m-d H:i:s', strtotime($user['created_at'])) : 'N/A';
        
        fputcsv($output, [
            $user['user_id'],
            $user['username'],
            $user['first_name'],
            $user['last_name'],
            $user['email'],
            $user['phone'] ? $user['phone'] : 'N/A',
            $user['role'],
            ucfirst($user['account_status']),
            $user['appointment_count'],
            $lastAppt,
            $createdAt
        ]);
    }
}

fclose($output);
mysqli_close($con);
exit();
?>

