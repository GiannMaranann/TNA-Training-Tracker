<?php
session_start();
require_once 'config.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Helper function to sanitize input
function sanitize($data) {
    return htmlspecialchars(trim($data));
}

// Handle Registration
if (isset($_POST['register'])) {
    $name = sanitize($_POST['name'] ?? '');
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $passwordRaw = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role = sanitize($_POST['role'] ?? 'user');

    // Validate required fields
    if (!$name || !$email || !$passwordRaw || !$confirmPassword) {
        $_SESSION['register_error'] = 'Please fill in all required fields with valid information.';
        $_SESSION['active_form'] = 'register';
        header("Location: homepage.php");
        exit();
    }

    // Password match check
    if ($passwordRaw !== $confirmPassword) {
        $_SESSION['register_error'] = 'Passwords do not match.';
        $_SESSION['active_form'] = 'register';
        header("Location: homepage.php");
        exit();
    }

    // Password length check
    if (strlen($passwordRaw) < 6) {
        $_SESSION['register_error'] = 'Password must be at least 6 characters long.';
        $_SESSION['active_form'] = 'register';
        header("Location: homepage.php");
        exit();
    }

    // Check if email already exists
    $checkEmail = $con->prepare("SELECT email FROM users WHERE email = ?");
    if (!$checkEmail) {
        $_SESSION['register_error'] = 'Database error: ' . $con->error;
        $_SESSION['active_form'] = 'register';
        header("Location: homepage.php");
        exit();
    }

    $checkEmail->bind_param("s", $email);
    $checkEmail->execute();
    $result = $checkEmail->get_result();

    if ($result && $result->num_rows > 0) {
        $_SESSION['register_error'] = 'Email is already registered!';
        $_SESSION['active_form'] = 'register';
        $checkEmail->close();
        header("Location: homepage.php");
        exit();
    }
    $checkEmail->close();

    // Hash password securely
    $password = password_hash($passwordRaw, PASSWORD_DEFAULT);
    $status = 'pending'; // All new registrations are pending (admin or user)

    // Set department based on role
    $department = '';
    $departmentMap = [
        'admin' => 'Main Admin',
        'admin_ca' => 'College of Agriculture',
        'admin_cas' => 'College of Arts and Sciences',
        'admin_cbaa' => 'College of Business, Administration and Accountancy',
        'admin_ccs' => 'College of Computer Studies',
        'admin_ccje' => 'College of Criminal Justice Education',
        'admin_coe' => 'College of Engineering',
        'admin_cit' => 'College of Industrial Technology',
        'admin_cfnd' => 'College of Food, Nutrition and Dietetics',
        'admin_cof' => 'College of Fisheries',
        'admin_chmt' => 'College of International Hospitality and Tourism Management',
        'admin_cte' => 'College of Teacher Education',
        'admin_conah' => 'College of Nursing and Allied Health',
        'admin_col' => 'College of Law'
    ];

    if (isset($departmentMap[$role])) {
        $department = $departmentMap[$role];
    }

    // Save new user
    $stmt = $con->prepare("INSERT INTO users (name, email, password, role, status, department) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        $_SESSION['register_error'] = 'Database error: ' . $con->error;
        $_SESSION['active_form'] = 'register';
        header("Location: homepage.php");
        exit();
    }

    $stmt->bind_param("ssssss", $name, $email, $password, $role, $status, $department);

    if ($stmt->execute()) {
        $_SESSION['register_success'] = 'Registration successful! Awaiting admin approval.';
        $_SESSION['active_form'] = 'login';
    } else {
        $_SESSION['register_error'] = 'Registration failed. Please try again. Error: ' . $con->error;
        $_SESSION['active_form'] = 'register';
    }
    $stmt->close();

    header("Location: homepage.php");
    exit();
}

// Handle Login
if (isset($_POST['login'])) {
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';

    // Validate inputs
    if (!$email || !$password) {
        $_SESSION['login_error'] = 'Please enter a valid email and password.';
        $_SESSION['active_form'] = 'login';
        header("Location: homepage.php");
        exit();
    }

    $stmt = $con->prepare("SELECT id, name, email, password, role, status, department FROM users WHERE email = ?");
    if (!$stmt) {
        $_SESSION['login_error'] = 'Database error: ' . $con->error;
        $_SESSION['active_form'] = 'login';
        header("Location: homepage.php");
        exit();
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            // Check account status
            if ($user['status'] === 'accepted') {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['department'] = $user['department'];

                // Redirect based on role
                switch ($user['role']) {
                    case 'admin': 
                        header("Location: admin_page.php"); 
                        break;
                    case 'user': 
                        header("Location: user_page.php"); 
                        break;
                    case 'admin_ca': 
                        header("Location: CA.php"); 
                        break;
                    case 'admin_cas': 
                        header("Location: CAS.php"); 
                        break;
                    case 'admin_cbaa': 
                        header("Location: CBAA.php"); 
                        break;
                    case 'admin_ccs': 
                        header("Location: CCS.php"); 
                        break;
                    case 'admin_ccje': 
                        header("Location: CCJE.php"); 
                        break;
                    case 'admin_coe': 
                        header("Location: COE.php"); 
                        break;
                    case 'admin_cit': 
                        header("Location: CIT.php"); 
                        break;
                    case 'admin_cfnd': 
                        header("Location: CFND.php"); 
                        break;
                    case 'admin_cof': 
                        header("Location: COF.php"); 
                        break;
                    case 'admin_chmt': 
                        header("Location: CHMT.php"); 
                        break;
                    case 'admin_cte': 
                        header("Location: CTE.php"); 
                        break;
                    case 'admin_conah': 
                        header("Location: CONAH.php"); 
                        break;
                    case 'admin_col': 
                        header("Location: COL.php"); 
                        break;
                    default: 
                        header("Location: homepage.php"); 
                        break;
                }
                $stmt->close();
                exit();
            } elseif ($user['status'] === 'pending') {
                $_SESSION['login_error'] = "Your account is pending approval. Please wait for admin confirmation.";
            } elseif ($user['status'] === 'declined') {
                $_SESSION['login_error'] = "Your account has been declined. Please contact administrator.";
            } else {
                $_SESSION['login_error'] = "Your account status is invalid. Please contact administrator.";
            }
        } else {
            $_SESSION['login_error'] = "Incorrect email or password.";
        }
    } else {
        $_SESSION['login_error'] = "Incorrect email or password.";
    }

    $stmt->close();

    $_SESSION['active_form'] = 'login';
    header("Location: homepage.php");
    exit();
}

// Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: homepage.php");
    exit();
}
?>