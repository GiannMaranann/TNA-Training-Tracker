<?php
// Database configuration
$host     = "localhost";
$user     = "root";       // Consider using a less privileged user in production
$password = "";           // Never leave this empty in production!
$database = "user_db";

// Error reporting settings
error_reporting(E_ALL);
ini_set('display_errors', 0);         // Don't show errors to users
ini_set('log_errors', 1);             // Log errors to file
ini_set('error_log', __DIR__ . '/php-errors.log'); // Error log file location

// Enable strict MySQLi error reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Create database connection
try {
    $con = new mysqli($host, $user, $password, $database);
    
    if ($con->connect_error) {
        throw new Exception("Database connection failed: " . $con->connect_error);
    }
    
    // Set character encoding to utf8mb4 (supports full Unicode including emojis)
    if (!$con->set_charset("utf8mb4")) {
        throw new Exception("Error loading character set utf8mb4: " . $con->error);
    }
    
    // Set timezone
    date_default_timezone_set('Asia/Manila');
    
} catch (Exception $e) {
    // Log the error with timestamp
    error_log(date('[Y-m-d H:i:s] ') . $e->getMessage());
    
    // Display a generic error message without exposing details
    header('HTTP/1.1 503 Service Unavailable');
    die("<h1>Service Temporarily Unavailable</h1><p>We're currently experiencing technical difficulties. Please try again later.</p>");
}

/**
 * Sanitizes input data for database insertion
 * 
 * @param mixed $data The input data to be sanitized
 * @return string The sanitized data
 */
function sanitize_input($data) {
    global $con;
    
    if (!isset($data)) {
        return '';
    }
    
    // Convert to string if not already
    $data = (string)$data;
    
    // Trim whitespace
    $data = trim($data);
    
    // Remove slashes if magic quotes are on (deprecated in PHP 5.4+)
    if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
        $data = stripslashes($data);
    }
    
    // Escape special characters for SQL
    $data = $con->real_escape_string($data);
    
    // Convert special characters to HTML entities
    return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Closes the database connection
 */
function close_db_connection() {
    global $con;
    if (isset($con) && $con instanceof mysqli) {
        $con->close();
        $con = null;
    }
}

// Register shutdown function to close connection automatically
register_shutdown_function('close_db_connection');

/**
 * Prepares and executes a parameterized query safely
 * 
 * @param string $sql The SQL query with placeholders
 * @param array $params The parameters to bind
 * @return mysqli_stmt The prepared statement
 */
function execute_query($sql, $params = []) {
    global $con;
    
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $con->error);
    }
    
    if (!empty($params)) {
        $types = '';
        $bindParams = [];
        
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } elseif (is_string($param)) {
                $types .= 's';
            } else {
                $types .= 'b'; // blob
            }
            
            $bindParams[] = $param;
        }
        
        array_unshift($bindParams, $types);
        call_user_func_array([$stmt, 'bind_param'], $bindParams);
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    return $stmt;
}
?>