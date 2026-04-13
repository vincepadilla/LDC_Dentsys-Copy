<?php
/**
 * Marks logged-in users as active on the web by updating user_account.last_activity
 * at the end of each request (after session_start has run).
 */
function ldcdents_ensure_last_activity_column(mysqli $con): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $check = mysqli_query($con, "SHOW COLUMNS FROM user_account LIKE 'last_activity'");
    if ($check && mysqli_num_rows($check) === 0) {
        @mysqli_query($con, "ALTER TABLE user_account ADD COLUMN last_activity TIMESTAMP NULL DEFAULT NULL AFTER last_login");
    }
}

function ldcdents_register_user_activity_shutdown(mysqli $con): void
{
    if (defined('LDCDENTS_USER_ACTIVITY_SHUTDOWN_REGISTERED')) {
        return;
    }
    define('LDCDENTS_USER_ACTIVITY_SHUTDOWN_REGISTERED', true);

    ldcdents_ensure_last_activity_column($con);

    register_shutdown_function(function () use ($con) {
        if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['userID'])) {
            return;
        }
        $uid = (string) $_SESSION['userID'];
        $stmt = $con->prepare('UPDATE user_account SET last_activity = NOW() WHERE user_id = ?');
        if ($stmt) {
            $stmt->bind_param('s', $uid);
            $stmt->execute();
            $stmt->close();
        }
    });
}
