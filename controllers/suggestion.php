<?php
include_once("../database/config.php");

// TEST MODE FLAG
$testingMode = true;

// Fetch all appointments
$query = "SELECT appointment_date, appointment_time FROM appointments";
$result = mysqli_query($con, $query);

$appointments = [];
while ($row = mysqli_fetch_assoc($result)) {
    $date = $row['appointment_date'];
    $time = $row['appointment_time'];
    $appointments[$date][$time] = isset($appointments[$date][$time]) ? $appointments[$date][$time] + 1 : 1;
}

// Generate suggestion (simple logic: find the date/time with fewest appointments)
$suggestedDate = '';
$suggestedTime = '';
$minCount = PHP_INT_MAX;

foreach ($appointments as $date => $times) {
    foreach ($times as $time => $count) {
        if ($count < $minCount) {
            $minCount = $count;
            $suggestedDate = $date;
            $suggestedTime = $time;
        }
    }
}

// If no appointments exist yet, suggest today
if (empty($appointments)) {
    $suggestedDate = date('Y-m-d');
    $suggestedTime = '09:00 AM';
}
?>

<!-- Popup modal for testing suggestion -->
<div id="suggestionPopup" style="
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.5);
    display: none;
    justify-content: center; align-items: center;
    z-index: 1000;
">
    <div style="
        background: #fff;
        padding: 30px;
        border-radius: 10px;
        width: 300px;
        text-align: center;
        box-shadow: 0 0 10px rgba(0,0,0,0.2);
    ">
        <h2>Suggested Schedule</h2>
        <p><strong>Date:</strong> <?php echo htmlspecialchars($suggestedDate); ?></p>
        <p><strong>Time:</strong> <?php echo htmlspecialchars($suggestedTime); ?></p>
        <?php if ($testingMode): ?>
            <p style="color: green; font-size: 12px;">(Testing Mode: No data saved)</p>
        <?php endif; ?>
        <button onclick="closeSuggestionPopup()" style="
            background-color: #007bff;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
        ">Close</button>
    </div>
</div>

<script>
function openSuggestionPopup() {
    document.getElementById('suggestionPopup').style.display = 'flex';
}

function closeSuggestionPopup() {
    document.getElementById('suggestionPopup').style.display = 'none';
}
</script>
