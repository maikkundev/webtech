<?php
session_start();
require 'db.php';

$error = '';
if (isset($_POST['login'])) {
  $username = $_POST['username'] ?? '';
  $password = $_POST['password'] ?? '';

  $stmt = $db->prepare("SELECT id, username, password FROM users WHERE username = ?");
  $stmt->bind_param('s', $username);
  $stmt->execute();
  $res = $stmt->get_result();

  if ($row = $res->fetch_assoc()) {
    if (password_verify($password, $row['password'])) {
      $_SESSION['id']       = $row['id'];
      $_SESSION['username'] = $row['username'];
      header('Location: home.php');
      exit;
    }
  }
  $error = 'Wrong username or password.';
}

if (isset($_POST['signup'])) {
  $firstname = trim($_POST['firstname'] ?? '');
  $lastname  = trim($_POST['lastname'] ?? '');
  $username  = trim($_POST['username'] ?? '');
  $email     = trim($_POST['email'] ?? '');
  $password  = trim($_POST['password'] ?? '');

  if ($firstname === '' || $lastname === '' || $username === '' || $email === '' || $password === '') {
    $error = 'All fields are required.';
  } else {
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
      $error = 'This email is used! Please try logging in instead or try a different one.';
    } else {
      $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
      $stmt = $db->prepare("INSERT INTO users (firstname, lastname, username, email, password) VALUES (?, ?, ?, ?, ?)");
      $stmt->bind_param('sssss', $firstname, $lastname, $username, $email, $hashedPassword);

      if ($stmt->execute()) {
        $success = 'Sign up successful! You may log in now.';
      } else {
        $error = 'An error occurred while signing up.';
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="assets/css/style.css">
  <title>Authentication Form</title>
</head>

<body>
  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif ?>
  <?php if (isset($success)): ?>
    <div class="success"><?= htmlspecialchars($success) ?></div>
  <?php endif ?>

  <h2>Login</h2>
  <form method="post">
    <label for="login_username">Username:</label>
    <input type="text" id="login_username" name="username" required>
    <br>
    <label for="login_password">Password:</label>
    <input type="password" id="login_password" name="password" required>
    <br>
    <input type="submit" name="login" value="Login">
  </form>

  <h2>Signup</h2>
  <form method="post">
    <label for="signup_firstname">First Name:</label>
    <input type="text" id="signup_firstname" name="firstname" required>
    <br>
    <label for="signup_lastname">Last Name:</label>
    <input type="text" id="signup_lastname" name="lastname" required>
    <br>
    <label for="signup_username">Username:</label>
    <input type="text" id="signup_username" name="username" required>
    <br>
    <label for="signup_email">Email:</label>
    <input type="email" id="signup_email" name="email" required>
    <br>
    <label for="signup_password">Password:</label>
    <input type="password" id="signup_password" name="password" required>
    <br>
    <input type="submit" name="signup" value="Sign Up">
  </form>
</body>

</html>