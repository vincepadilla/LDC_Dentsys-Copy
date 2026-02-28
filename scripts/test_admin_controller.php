<?php
// CLI smoke test for AdminDataController
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../controllers/AdminDataController.php';

if (!isset($con) && !isset($GLOBALS['con'])) {
    echo "Database connection not found. Ensure database/config.php defines \$con.\n";
    exit(1);
}

$connection = $con ?? $GLOBALS['con'];
$controller = new AdminDataController($connection);

function printResult($title, $data) {
    echo "---- $title ----\n";
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";
}

// Run a subset of methods to validate controller behavior
try {
    $dashboard = $controller->getDashboardStats();
    printResult('Dashboard Stats', $dashboard);

    $today = $controller->getTodayAppointments();
    printResult('Today Appointments (count)', ['count' => count($today)]);

    $upcoming = $controller->getUpcomingAppointments(5);
    printResult('Upcoming Appointments (sample)', $upcoming);

    $appointments = $controller->getAllAppointments();
    printResult('All Appointments (count)', ['count' => count($appointments)]);

    $patients = $controller->getAllPatients();
    printResult('All Patients (count)', ['count' => count($patients)]);

    $payments = $controller->getPaymentTransactions();
    printResult('Payments (count)', ['count' => count($payments)]);

    $services = $controller->getServicesList();
    printResult('Services (count)', ['count' => count($services)]);

    $blocked = $controller->getBlockedTimeSlots();
    printResult('Blocked Slots (count)', ['count' => count($blocked)]);

    echo "Smoke test completed. If outputs look correct, controller functions are reachable.\n";
} catch (Throwable $e) {
    echo "Error during test: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(2);
}
