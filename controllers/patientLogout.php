<?php
session_start();
require_once(__DIR__ . "/../database/config.php");

if (isset($_SESSION['userID'])) {
    $logoutUid = (string) $_SESSION['userID'];
    $clearActivity = $con->prepare('UPDATE user_account SET last_activity = NULL WHERE user_id = ?');
    if ($clearActivity) {
        $clearActivity->bind_param('s', $logoutUid);
        $clearActivity->execute();
        $clearActivity->close();
    }
}

// Unset all session variables
$_SESSION = [];

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page or homepage
header("Location: /login");
exit;
?>