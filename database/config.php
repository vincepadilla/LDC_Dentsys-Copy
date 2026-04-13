<?php

    $con = mysqli_connect("localhost", "root", "", "dental_clinic_db") or die("Couldn't connect");

    require_once __DIR__ . '/../includes/user_session_activity.php';
    ldcdents_register_user_activity_shutdown($con);

?>