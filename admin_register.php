<?php
session_start();
require_once 'config.php'; // Make sure this contains your DB connection

// Password protection for the registration page
$registration_password = "admin123"; // Change this to your desired password

// Check if password is required and verify it
if (!isset($_SESSION['registration_authenticated'])) {
    if (isset($_POST['registration_password'])) {
        if ($_POST['registration_password'] === $registration_password) {
            $_SESSION['registration_authenticated'] = true;
        } else {
            $_SESSION['register_error'] = "Invalid registration password!";
            header("Location: admin_register.php");
            exit();
        }
    } else {
        // Show password form
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Admin Registration - Authentication Required</title>
            <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;700&display=swap" rel="stylesheet" />
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                    font-family: "Poppins", serif;
                }
                body {
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                    background: #EEEEEE;
                }
                .password-form {
                    background: white;
                    padding: 30px;
                    border-radius: 10px;
                    box-shadow: 0 0 10px rgba(0,0,0,0.1);
                    width: 100%;
                    max-width: 400px;
                }
                h2 {
                    text-align: center;
                    margin-bottom: 20px;
                }
                input {
                    width: 100%;
                    padding: 12px;
                    margin-bottom: 20px;
                    border: 1px solid #ddd;
                    border-radius: 6px;
                }
                button {
                    width: 100%;
                    padding: 12px;
                    background: #7494ec;
                    color: white;
                    border: none;
                    border-radius: 6px;
                    cursor: pointer;
                }
                .message {
                    padding: 10px;
                    background: #f8d7da;
                    color: #721c24;
                    border-radius: 6px;
                    margin-bottom: 15px;
                    text-align: center;
                }
            </style>
        </head>
        <body>
            <div class="password-form">
                <h2>Admin Registration Access</h2>
                <?php if (isset($_SESSION['register_error'])): ?>
                    <div class="message"><?= $_SESSION['register_error'] ?></div>
                    <?php unset($_SESSION['register_error']); ?>
                <?php endif; ?>
                <form method="post">
                    <input type="password" name="registration_password" placeholder="Enter registration password" required>
                    <button type="submit">Access Registration</button>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit();
    }
}

function sanitize($data) {
    return htmlspecialchars(trim($data));
}

$register_error = "";
$register_success = "";

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $emailRaw = $_POST['email'] ?? '';
    $email = filter_var($emailRaw, FILTER_VALIDATE_EMAIL);
    $passwordRaw = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $roleInput = $_POST['role'] ?? '';

    // Input validation
    if (!$name || !$email || !$passwordRaw || !$confirmPassword || !$roleInput) {
        $register_error = "Please fill in all fields.";
    } elseif (!$email) {
        $register_error = "Invalid email format.";
    } elseif ($passwordRaw !== $confirmPassword) {
        $register_error = "Passwords do not match.";
    } elseif (strlen($passwordRaw) < 6) {
        $register_error = "Password must be at least 6 characters long.";
    } else {
        // Set role, department, and status based on selection
        if ($roleInput === 'admin') {
            $role = 'admin';
            $department = 'Main Admin';
            $status = 'accepted'; // Main admin gets accepted immediately
        } else {
            // Map department codes to full names and roles
            $departmentMap = [
                'CA' => ['name' => 'College of Agriculture', 'role' => 'admin_ca'],
                'CAS' => ['name' => 'College of Arts and Sciences', 'role' => 'admin_cas'],
                'CBAA' => ['name' => 'College of Business, Administration and Accountancy', 'role' => 'admin_cbaa'],
                'CCS' => ['name' => 'College of Computer Studies', 'role' => 'admin_ccs'],
                'CCJE' => ['name' => 'College of Criminal Justice Education', 'role' => 'admin_ccje'],
                'COE' => ['name' => 'College of Engineering', 'role' => 'admin_coe'],
                'CIT' => ['name' => 'College of Industrial Technology', 'role' => 'admin_cit'],
                'CFND' => ['name' => 'College of Food, Nutrition and Dietetics', 'role' => 'admin_cfnd'],
                'COF' => ['name' => 'College of Fisheries', 'role' => 'admin_cof'],
                'CIHTM' => ['name' => 'College of International Hospitality and Tourism Management', 'role' => 'admin_cihtm'],
                'CTE' => ['name' => 'College of Teacher Education', 'role' => 'admin_cte'],
                'CONAH' => ['name' => 'College of Nursing and Allied Health', 'role' => 'admin_conah'],
                'COL' => ['name' => 'College of Law', 'role' => 'admin_col']
            ];
            
            if (isset($departmentMap[$roleInput])) {
                $role = $departmentMap[$roleInput]['role'];
                $department = $departmentMap[$roleInput]['name'];
                $status = 'pending'; // Department admins are pending
            } else {
                $register_error = "Invalid department selected.";
            }
        }

        if (empty($register_error)) {
            // Hash the password
            $password = password_hash($passwordRaw, PASSWORD_DEFAULT);

            // Check for existing email
            $checkEmail = $con->prepare("SELECT email FROM users WHERE email = ?");
            $checkEmail->bind_param("s", $email);
            $checkEmail->execute();
            $result = $checkEmail->get_result();

            if ($result->num_rows > 0) {
                $register_error = "Email already exists!";
                $checkEmail->close();
            } else {
                $checkEmail->close();

                // Insert new admin user
                $stmt = $con->prepare("INSERT INTO users (name, email, password, role, department, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssss", $name, $email, $password, $role, $department, $status);

                if ($stmt->execute()) {
                    $register_success = "Admin account created successfully.";
                    $_SESSION['register_success'] = $register_success;
                    
                    // Redirect to homepage.php after successful registration
                    header("Location: homepage.php");
                    exit();
                } else {
                    $register_error = "Failed to create admin. Please try again. Error: " . $con->error;
                }
                $stmt->close();
            }
        }
    }

    if (!empty($register_error)) {
        $_SESSION['register_error'] = $register_error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Admin Registration</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;700&display=swap" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: "Poppins", serif;
    }

    body, html {
      height: 100%;
      overflow: hidden;
    }

    body {
      display: flex;
      justify-content: center;
      align-items: center;
      background: #EEEEEE;
      position: relative;
      color: #333;
    }

    /* Particles background */
    #particles-js {
      position: absolute;
      width: 100%;
      height: 100%;
      background-color: #eaf0fb;
      z-index: 0;
    }

    .container {
      z-index: 10;
      margin: 0 15px;
    }

    .form-box {
      width: 100%;
      max-width: 450px;
      padding: 30px;
      background: #fff;
      border-radius: 10px;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    }

    h2 {
      font-size: 28px;
      text-align: center;
      margin-bottom: 20px;
    }

    input, .department-select {
      width: 100%;
      padding: 12px;
      background: #eee;
      border-radius: 6px;
      border: none;
      outline: none;
      font-size: 16px;
      color: #333;
      margin-bottom: 20px;
    }

    .department-select {
      appearance: none;
      background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg width='10' height='7' viewBox='0 0 10 7' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1L5 6L9 1' stroke='%23333' stroke-width='2'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 12px center;
      background-size: 10px 7px;
      cursor: pointer;
    }

    button {
      width: 100%;
      padding: 12px;
      background-color: #7494ec;
      border-radius: 6px;
      border: none;
      cursor: pointer;
      font-size: 16px;
      color: #fff;
      font-weight: 500;
      margin-bottom: 20px;
      transition: 0.3s;
    }

    button:hover {
      background: #6884d3;
    }

    .message {
      padding: 12px;
      border-radius: 6px;
      font-size: 16px;
      text-align: center;
      margin-bottom: 20px;
    }

    .message.error {
      background: #f8d7da;
      color: #a42834;
    }

    .message.success {
      background: #d4edda;
      color: #155724;
    }

    .message.info {
      background: #d1ecf1;
      color: #0c5460;
    }

    .home-button {
      position: fixed;
      top: 20px;
      left: 20px;
      background-color: #7494EC;
      color: white;
      padding: 10px 15px;
      text-decoration: none;
      border-radius: 5px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.2);
      z-index: 1000;
      font-weight: bold;
    }
  </style>
</head>

<body>
  <!-- Particles background -->
  <div id="particles-js"></div>

  <a href="homepage.php" class="home-button">Home</a>

  <div class="container">
    <div class="form-box">
      <h2>Admin Registration</h2>

      <?php if (isset($_SESSION['register_error'])): ?>
        <div class="message error"><?= $_SESSION['register_error'] ?></div>
        <?php unset($_SESSION['register_error']); ?>
      <?php endif; ?>

      <?php if (isset($_SESSION['register_success'])): ?>
        <div class="message success"><?= $_SESSION['register_success'] ?></div>
        <?php unset($_SESSION['register_success']); ?>
      <?php endif; ?>

      <div class="message info">
        Note: Department admins will have "pending" status until approved by main admin.
      </div>

      <form method="post" novalidate>
        <input type="text" name="name" placeholder="Full Name" required value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>" />

        <input type="email" name="email" placeholder="Email Address" required value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" />

        <select name="role" required class="department-select">
          <option value="" disabled selected>Select Role</option>
          <option value="admin" <?= (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'selected' : '' ?>>Main Admin</option>
          <option value="CA" <?= (isset($_POST['role']) && $_POST['role'] === 'CA') ? 'selected' : '' ?>>College of Agriculture (CA)</option>
          <option value="CAS" <?= (isset($_POST['role']) && $_POST['role'] === 'CAS') ? 'selected' : '' ?>>College of Arts and Sciences (CAS)</option>
          <option value="CBAA" <?= (isset($_POST['role']) && $_POST['role'] === 'CBAA') ? 'selected' : '' ?>>College of Business, Administration and Accountancy (CBAA)</option>
          <option value="CCS" <?= (isset($_POST['role']) && $_POST['role'] === 'CCS') ? 'selected' : '' ?>>College of Computer Studies (CCS)</option>
          <option value="CCJE" <?= (isset($_POST['role']) && $_POST['role'] === 'CCJE') ? 'selected' : '' ?>>College of Criminal Justice Education (CCJE)</option>
          <option value="COE" <?= (isset($_POST['role']) && $_POST['role'] === 'COE') ? 'selected' : '' ?>>College of Engineering (COE)</option>
          <option value="CIT" <?= (isset($_POST['role']) && $_POST['role'] === 'CIT') ? 'selected' : '' ?>>College of Industrial Technology (CIT)</option>
          <option value="CFND" <?= (isset($_POST['role']) && $_POST['role'] === 'CFND') ? 'selected' : '' ?>>College of Food, Nutrition and Dietetics (CFND)</option>
          <option value="COF" <?= (isset($_POST['role']) && $_POST['role'] === 'COF') ? 'selected' : '' ?>>College of Fisheries (COF)</option>
          <option value="CIHTM" <?= (isset($_POST['role']) && $_POST['role'] === 'CIHTM') ? 'selected' : '' ?>>College of International Hospitality and Tourism Management (CIHTM)</option>
          <option value="CTE" <?= (isset($_POST['role']) && $_POST['role'] === 'CTE') ? 'selected' : '' ?>>College of Teacher Education (CTE)</option>
          <option value="CONAH" <?= (isset($_POST['role']) && $_POST['role'] === 'CONAH') ? 'selected' : '' ?>>College of Nursing and Allied Health (CONAH)</option>
          <option value="COL" <?= (isset($_POST['role']) && $_POST['role'] === 'COL') ? 'selected' : '' ?>>College of Law (COL)</option>
        </select>

        <input type="password" name="password" placeholder="Password (min. 6 characters)" required />

        <input type="password" name="confirm_password" placeholder="Re-enter Password" required />

        <button type="submit">Register as Admin</button>
      </form>
    </div>
  </div>

  <!-- Particle.js Library -->
  <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
  <script>
particlesJS('particles-js', {
  particles: {
    number: { value: 80, density: { enable: true, value_area: 800 } },
    color: { value: '#4F46E5' },
    shape: { type: 'circle', stroke: { width: 0, color: '#000000' } },
    opacity: { value: 0.6, random: false, anim: { enable: false } },
    size: { value: 3, random: true, anim: { enable: false } },
    line_linked: {
      enable: true,
      distance: 150,
      color: '#4F46E5',
      opacity: 0.4,
      width: 1
    },
    move: {
      enable: true,
      speed: 3,
      direction: 'none',
      random: false,
      straight: false,
      out_mode: 'bounce',
      bounce: true,
      attract: { enable: false }
    }
  },
  interactivity: {
    detect_on: 'canvas',
    events: {
      onhover: { enable: true, mode: 'grab' },
      onclick: { enable: true, mode: 'push' },
      resize: true
    },
    modes: {
      grab: { distance: 140, line_linked: { opacity: 1 } },
      push: { particles_nb: 4 }
    }
  },
  retina_detect: true
});
  </script>

</body>
</html>