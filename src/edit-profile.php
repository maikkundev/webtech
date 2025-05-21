<?php
session_start();
require 'db.php';

// Check if the user is logged in
if (!isset($_SESSION['id'])) {
  header('Location: auth.php');
  exit;
}

$user_id = $_SESSION['id'];
$success_message = '';
$error_message = '';

// Fetch user data
$stmt = $db->prepare("SELECT firstname, lastname, username, email FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  header('Location: auth.php');
  exit;
}

$user = $result->fetch_assoc();

// Process form submission for updating profile
if (isset($_POST['update_profile'])) {
  $firstname = trim($_POST['firstname'] ?? '');
  $lastname = trim($_POST['lastname'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $current_password = $_POST['current_password'] ?? '';
  $new_password = trim($_POST['new_password'] ?? '');

  // Verify if fields are not empty
  if ($firstname === '' || $lastname === '' || $email === '') {
    $error_message = 'First name, last name, and email are required.';
  } else {
    // Check if email already exists for another user
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->bind_param('si', $email, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
      $error_message = 'This email is already in use by another account.';
    } else {
      // Start building the SQL query
      $sql = "UPDATE users SET firstname = ?, lastname = ?, email = ?";
      $params = [$firstname, $lastname, $email];
      $types = 'sss';

      // If user wants to change password
      if ($new_password !== '') {
        // Verify current password
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();

        if (!password_verify($current_password, $user_data['password'])) {
          $error_message = 'Current password is incorrect.';
        } else {
          // Add password to the update query
          $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
          $sql .= ", password = ?";
          $params[] = $hashed_password;
          $types .= 's';
        }
      }

      // Only proceed if there's no error
      if ($error_message === '') {
        $sql .= " WHERE id = ?";
        $params[] = $user_id;
        $types .= 'i';

        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
          $success_message = 'Profile updated successfully!';
          // Update session data for immediately visible changes
          $_SESSION['email'] = $email;

          // Refresh user data after update
          $stmt = $db->prepare("SELECT firstname, lastname, username, email FROM users WHERE id = ?");
          $stmt->bind_param('i', $user_id);
          $stmt->execute();
          $result = $stmt->get_result();
          $user = $result->fetch_assoc();
        } else {
          $error_message = 'An error occurred while updating your profile.';
        }
      }
    }
  }
}
?>

<!DOCTYPE html>
<html lang="el">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="assets/css/style.css">
  <title>Edit Profile</title>
</head>

<body>
  <h1>Το προφίλ μου</h1>

  <?php if ($success_message): ?>
    <div class="success"><?= htmlspecialchars($success_message) ?></div>
  <?php endif; ?>

  <?php if ($error_message): ?>
    <div class="error"><?= htmlspecialchars($error_message) ?></div>
  <?php endif; ?>

  <form method="post">
    <div class="form-group">
      <label for="username">Όνομα χρήστη (δεν μπορεί να αλλάξει):</label>
      <input type="text" id="username" value="<?= htmlspecialchars($user['username']) ?>" readonly>
    </div>

    <div class="form-group">
      <label for="firstname">Όνομα:</label>
      <input type="text" id="firstname" name="firstname" value="<?= htmlspecialchars($user['firstname']) ?>" required>
    </div>

    <div class="form-group">
      <label for="lastname">Επίθετο:</label>
      <input type="text" id="lastname" name="lastname" value="<?= htmlspecialchars($user['lastname']) ?>" required>
    </div>

    <div class="form-group">
      <label for="email">Email:</label>
      <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
    </div>

    <div class="password-section">
      <h3>Αλλαγή κωδικού</h3>
      <p>(Κενό αν δεν θέλεις να αλλάξεις τον κωδικό σου)</p>

      <div class="form-group">
        <label for="current_password">Τρέχων κωδικός:</label>
        <input type="password" id="current_password" name="current_password">
      </div>

      <div class="form-group">
        <label for="new_password">Νέος κωδικός:</label>
        <input type="password" id="new_password" name="new_password">
      </div>
    </div>

    <div class="actions">
      <button type="submit" name="Upgrade profile" class="btn">Αποθήκευση αλλαγών</button>
      <a href="view-profile.php" class="btn">Εμφάνιση προφίλ</a>
      <a href="delete-profile.php" class="btn" onclick="return confirm('Είστε βέβαιοι ότι θέλετε να διαγράψετε το προφίλ σας; Αυτή η ενέργεια δεν μπορεί να αναιρεθεί.');">Διαγραφή προφίλ</a>
    </div>
  </form>

  <p><a href="index.html">Επιστροφή στην αρχική σελίδα</a></p>
</body>

</html>