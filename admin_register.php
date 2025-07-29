<?php
session_start();
require_once 'config.php'; // Make sure this contains your DB connection

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

    // Sanitize and assign role & department
    $department = preg_replace("/[^a-zA-Z0-9]/", "", $roleInput);
    $role = strtolower($roleInput) === 'admin' ? 'admin' : 'admin_' . strtolower($department);
    $status = 'accepted'; // Default status for admins

    // Input validation
    if (!$name || !$email || !$passwordRaw || !$confirmPassword || !$roleInput) {
        $register_error = "Please fill in all fields.";
    } elseif (!$email) {
        $register_error = "Invalid email format.";
    } elseif ($passwordRaw !== $confirmPassword) {
        $register_error = "Passwords do not match.";
    } else {
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
                // Optional: Redirect to login or admin dashboard
                // header("Location: admin_login.php");
                // exit();
            } else {
                $register_error = "Failed to create admin. Please try again.";
            }
            $stmt->close();
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
      background-color: #eaf0fb; /* similar to your image */
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
      background: #f8d7da;
      border-radius: 6px;
      font-size: 16px;
      color: #a42834;
      text-align: center;
      margin-bottom: 20px;
    }

    .message.success {
      background: #d4edda;
      color: #155724;
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

  <div class="container">
  <div class="form-box">
    <h2>Admin Registration</h2>

    <?php if (isset($_SESSION['register_error'])): ?>
      <div class="message"><?= $_SESSION['register_error'] ?></div>
      <?php unset($_SESSION['register_error']); ?>
    <?php endif; ?>

    <form method="post" novalidate>
      <input type="text" name="name" placeholder="Full Name" required />

      <input type="email" name="email" placeholder="Email Address" required />

      <select name="role" required class="department-select">
        <option value="" disabled selected>Select Role</option>
        <option value="admin">Admin</option>
        <option value="CCS">College of Computer Studies</option>
        <option value="CAS">College of Arts and Sciences</option>
        <option value="CBAA">College of Business, Accountancy and Administration</option>
        <option value="CCJE">College of Criminal Justice Education</option>
        <option value="CFND">College of Fisheries and Natural Development</option>
        <option value="CHMT">College of Hospitality Management and Tourism</option>
        <option value="COF">College of Fisheries</option>
        <option value="CTE">College of Teacher Education</option>
      </select>

      <input type="password" name="password" placeholder="Password" required />

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
