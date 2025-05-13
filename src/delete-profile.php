<?php
session_start();
require 'db.php';

// Check if the user is logged in
if (!isset($_SESSION['id'])) {
  header('Location: auth.php');
  exit;
}

$user_id = $_SESSION['id'];
$error_message = '';

// Process deletion request
if (isset($_POST['confirm_delete'])) {
  $password = $_POST['password'] ?? '';

  // Verify password
  $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
  $stmt->bind_param('i', $user_id);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();

    if (password_verify($password, $user['password'])) {
      // Start transaction
      $db->begin_transaction();

      try {
        // Delete all lists created by the user (if you have a lists table)
        // Uncomment and modify this section based on your database schema
        /*
                $stmt = $db->prepare("DELETE FROM list_items WHERE list_id IN (SELECT id FROM lists WHERE user_id = ?)");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                
                $stmt = $db->prepare("DELETE FROM lists WHERE user_id = ?");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                */

        // Delete the user
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();

        // Commit the transaction
        $db->commit();

        // Destroy the session
        session_destroy();

        // Redirect to a confirmation page or login page
        header('Location: profile-deleted.php');
        exit;
      } catch (Exception $e) {
        // Roll back the transaction in case of error
        $db->rollback();
        $error_message = "An error occurred: " . $e->getMessage();
      }
    } else {
      $error_message = "Incorrect password. Account deletion canceled.";
    }
  } else {
    $error_message = "User not found.";
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Delete Profile</title>
</head>

<body>
  <h1>Delete Your Profile</h1>

  <div class="warning">
    <strong>Warning!</strong> This action cannot be undone. All your data, including your profile information and all lists you have created, will be permanently deleted.
  </div>

  <?php if ($error_message): ?>
    <div class="error"><?= htmlspecialchars($error_message) ?></div>
  <?php endif; ?>

  <form method="post" onsubmit="return confirm('Are you absolutely sure you want to delete your profile? This action cannot be undone.');">
    <div class="form-group">
      <label for="password">Enter your password to confirm deletion:</label>
      <input type="password" id="password" name="password" required>
    </div>


    <div>
      <button type="submit" name="confirm_delete" class="btn">Delete My Profile Permanently</button>
      <a href="edit-profile.php" class="btn">Cancel</a>
    </div>
  </form>
</body>

</html>