<?php
session_start();

$errors = [
  'login' => $_SESSION['login_error'] ?? '',
  'register' => $_SESSION['register_error'] ?? ''
];
$activeForm = $_SESSION['active_form'] ?? 'login';

session_unset();

function showError($error) {
  return !empty($error) ? "<p class='error_message'>$error</p>" : '';
}

function isActiveForm($formName, $activeForm) {
  return $formName === $activeForm ? 'active' : '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Document</title>
  <link rel="stylesheet" href="style.css">
  <style>
    /* Removes any background gradient */
    body {
      background: #EEEEEE; /* Removes any background gradient */
      position: relative;
      overflow: hidden; /* Prevent scrolling */
    }

    .circle {
      position: absolute;
      width: 450px;
      height: 450px;
      background-color: #7494EC;
      border-radius: 50%;
      z-index: -1;
      animation: moveCircle 10s ease-in-out infinite alternate;
    } 

    .circle1 {
      bottom: 0px;
      right: -155px;
    }

    .circle2 {
      bottom: 50px;
      left: -155px;
    }

    .circle3 {
      top: -250px;
      right: 150px;
    }

    /* This is for Animation Background Circles*/ 
    @keyframes moveCircle {
      0%   { transform: translate(0px, 0px) scale(1); }
      25%  { transform: translate(20px, -10px) scale(1.05); }
      50%  { transform: translate(-15px, 15px) scale(1.03); }
      75%  { transform: translate(10px, -20px) scale(1.07); }
      100% { transform: translate(0px, 0px) scale(1); }
    }
    
  </style>
</head>

<body>
  <!-- Background Circles -->
  <div class="circle circle1"></div>
  <div class="circle circle2"></div>
  <div class="circle circle3"></div>
</a>
  <div class="container">
    <div class="form-box <?= isActiveForm('login', $activeForm); ?>" id="login-form">
      <form action="login_register.php" method="post">
        <h2>Login</h2>
        <?= showError($errors['login']); ?>
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit" name="login">Login</button>
        <p>Don't have an account? <a href="#" onclick="showForm('register-form')">Register</a></p>
      </form>
    </div>

    <div class="form-box <?= isActiveForm('register', $activeForm); ?>" id="register-form">
    <form action="login_register.php" method="post">
      <h2>Register</h2>
      <?= showError($errors['register']); ?>
      <input type="text" name="name" placeholder="Name" required>
      <input type="email" name="email" placeholder="Email" required>
      <input type="password" name="password" placeholder="Password" required>
      <input type="password" name="confirm_password" placeholder="Re-enter Password" required>

      <!-- Hidden input for role -->
      <input type="hidden" name="role" value="user">
      
      <button type="submit" name="register">Register</button>
      <p>Already have an account? <a href="#" onclick="showForm('login-form')">Login</a></p>
    </form>
  </div>

  <script src="script.js"></script>
</body>
</html>
