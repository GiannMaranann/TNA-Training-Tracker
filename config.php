<?php
$host     = "localhost";
$user     = "root";
$password = "";
$database = "user_db";

// Optional: show mysqli errors (useful for debugging)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Create connection
    $con = new mysqli($host, $user, $password, $database);

    // Set character encoding to utf8 (for special characters)
    $con->set_charset("utf8");

} catch (mysqli_sql_exception $e) {
    // Catch connection errors
    die("Connection failed: " . $e->getMessage());
}
?>
