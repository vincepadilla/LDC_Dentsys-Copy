<?php
include_once('../database/config.php');

header('Content-Type: application/json');

if (isset($_GET['team_id'])) {
    $team_id = $_GET['team_id'];
    
    // Query to get staff details with user information
    $query = "SELECT 
                t.team_id,
                t.user_id,
                t.first_name,
                t.last_name,
                t.specialization,
                t.email,
                t.phone,
                t.status,
                u.user_id as user_user_id,
                u.first_name as user_first_name,
                u.last_name as user_last_name,
                u.email as user_email,
                u.phone as user_phone
              FROM multidisciplinary_dental_team t
              LEFT JOIN user_account u ON t.user_id = u.user_id
              WHERE t.team_id = ?";
    
    if ($stmt = $con->prepare($query)) {
        $stmt->bind_param("s", $team_id);
        
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $staff = $result->fetch_assoc();
                echo json_encode([
                    'success' => true,
                    'data' => $staff
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Staff not found'
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Query execution error: ' . $stmt->error
            ]);
        }
        
        $stmt->close();
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Database preparation error: ' . $con->error
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Team ID is required'
    ]);
}

$con->close();
?>

