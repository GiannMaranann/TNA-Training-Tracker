<?php
require_once 'config.php';

$result = $con->query("SELECT submission_deadline FROM settings WHERE id = 1");
if ($row = $result->fetch_assoc()) {
    // Format the date into a human-readable format
    $formattedDeadline = date("F j, Y, g:i a", strtotime($row['submission_deadline']));
    echo $formattedDeadline;  // Display the formatted date
}
?>
