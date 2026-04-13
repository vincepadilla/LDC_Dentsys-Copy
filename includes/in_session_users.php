<?php
/**
 * Shared logic for "In Session" users (matches views/userControl.php display rules).
 */
function ldcdents_in_session_seconds_default(): int
{
    return 120;
}

/**
 * @return list<string> user_id values considered online (non–super-admin, not blocked, recent last_activity).
 */
function ldcdents_get_in_session_user_ids(mysqli $con, ?int $onlineWindowSeconds = null): array
{
    $window = $onlineWindowSeconds ?? ldcdents_in_session_seconds_default();
    if ($window < 1) {
        $window = 120;
    }

    $sql = "
        SELECT ua.user_id
        FROM user_account ua
        WHERE ua.role != 'super-admin'
          AND LOWER(COALESCE(ua.status, 'active')) != 'blocked'
          AND ua.last_activity IS NOT NULL
          AND TIMESTAMPDIFF(SECOND, ua.last_activity, NOW()) <= ?
    ";
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $window);
    $stmt->execute();
    $res = $stmt->get_result();
    $ids = [];
    while ($row = $res->fetch_assoc()) {
        if (!empty($row['user_id'])) {
            $ids[] = (string) $row['user_id'];
        }
    }
    $stmt->close();
    return $ids;
}

function ldcdents_count_in_session_users(mysqli $con, ?int $onlineWindowSeconds = null): int
{
    return count(ldcdents_get_in_session_user_ids($con, $onlineWindowSeconds));
}
