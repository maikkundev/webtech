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
<html lang="el">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="assets/css/style.css">
  <title>Delete Profile</title>
</head>

<body>
  <header class="navbar">
    <div class="logo">
      <a href="index.html"><img src="assets/images/looplogo.png" alt="Loop Logo" /></a>
    </div>
    <button class="menu-toggle" aria-label="Μενού">&#9776;</button>

    <nav class="menu">
      <div class="search-toggle">
        <span class="search-icon">🔍</span>
        <input type="text" class="search-input" placeholder="Αναζήτησε τραγούδι..." />
      </div>
      <a href="index.html">Αρχική</a>
      <a href="help.html">Βοήθεια</a>
      <a href="about.html">Σχετικά</a>
      <a href="auth.php">Σύνδεση / Εγγραφή</a>
      <button class="theme-toggle">🌓 Θέμα</button>
    </nav>
  </header>
  <h1>Διαγραφή του προφίλ σου</h1>

  <div class="warning">
    <strong>Προειδοποίηση!</strong>
    Αυτή η ενέργεια δεν μπορεί να αναιρεθεί. Όλα τα δεδομένα σας, συμπεριλαμβανομένων των στοιχείων του προφίλ σας και όλων των λιστών που έχετε δημιουργήσει, θα διαγραφούν οριστικά.
  </div>

  <?php if ($error_message): ?>
    <div class="error"><?= htmlspecialchars($error_message) ?></div>
  <?php endif; ?>

  <form method="post" onsubmit="return confirm('Are you absolutely sure you want to delete your profile? This action cannot be undone.');">
    <div class="form-group">
      <label for="password">Εισαγάγετε τον κωδικό πρόσβασής σας για να επιβεβαιώσετε τη διαγραφή:</label>
      <input type="password" id="password" name="password" required>
    </div>
    <div>
      <button type="submit" name="confirm_delete" class="btn">Διαγραφή του προφίλ μου οριστικά</button>
      <a href="edit-profile.php" class="btn">Ακύρωση</a>
    </div>
  </form>
</body>

</html>