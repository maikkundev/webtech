<?php
session_start();
require 'includes/db.php';

// Check if the user is logged in
if (!isset($_SESSION['id'])) {
  header('Location: auth.php');
  exit;
}

$user_id = $_SESSION['id'];

// Fetch user data
$stmt = $db->prepare("SELECT firstname, lastname, username, email, created_at FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  header('Location: auth.php');
  exit;
}

$user = $result->fetch_assoc();

// Fetch user's lists if you have a lists table
// This is commented out as we don't know if you have a lists table
// Modify as needed based on your database schema
/*
$stmt = $db->prepare("SELECT id, title, created_at FROM lists WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$lists_result = $stmt->get_result();
*/
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>View Profile</title>
</head>

<body>
  <h1>Your Profile</h1>

  <div class="profile-info">
    <div class="profile-field">
      <label>Username:</label>
      <div><?= htmlspecialchars($user['username']) ?></div>
    </div>

    <div class="profile-field">
      <label>First Name:</label>
      <div><?= htmlspecialchars($user['firstname']) ?></div>
    </div>

    <div class="profile-field">
      <label>Last Name:</label>
      <div><?= htmlspecialchars($user['lastname']) ?></div>
    </div>

    <div class="profile-field">
      <label>Email:</label>
      <div><?= htmlspecialchars($user['email']) ?></div>
    </div>

    <div class="profile-field">
      <label>Member Since:</label>
      <div><?= date('F j, Y', strtotime($user['created_at'])) ?></div>
    </div>
  </div>

  <div>
    <a href="edit-profile.php" class="btn">Edit Profile</a>
    <a href="index.html" class="btn">Homepage</a>
  </div>

  <!-- If you have lists to display, uncomment and modify as needed -->
  <!--
    <div class="lists-section">
        <h2>Your Lists</h2>
        
        <?php if (isset($lists_result) && $lists_result->num_rows > 0): ?>
            <?php while ($list = $lists_result->fetch_assoc()): ?>
                <div class="list-item">
                    <div class="list-title"><?= htmlspecialchars($list['title']) ?></div>
                    <div class="list-date">Created: <?= date('F j, Y', strtotime($list['created_at'])) ?></div>
                    <a href="view-list.php?id=<?= $list['id'] ?>">View</a>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>You haven't created any lists yet.</p>
        <?php endif; ?>
    </div>
    -->
</body>

</html>