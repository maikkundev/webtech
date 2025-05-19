<?php
session_start();
require 'db.php';

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
<html lang="el">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>View Profile</title>
  <link rel="stylesheet" href="assets/css/style.css" />
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


  <main class="container fade-in">
    <h1>👤 Το προφίλ σου</h1>
    <section class="features">
      <div class="feature-box">
        <h3>Όνομα</h3>
        <p><?= htmlspecialchars($user['firstname']) ?></p>
      </div>
      <div class="feature-box">
        <h3>Επώνυμο</h3>
        <p><?= htmlspecialchars($user['lastname']) ?></p>
      </div>
      <div class="feature-box">
        <h3>Όνομα χρήστη</h3>
        <p><?= htmlspecialchars($user['username']) ?></p>
      </div>
      <div class="feature-box">
        <h3>Email</h3>
        <p><?= htmlspecialchars($user['email']) ?></p>
      </div>
      <div class="feature-box">
        <h3>Member Since</h3>
        <p><?= date('F j, Y', strtotime($user['created_at'])) ?></p>
      </div>
    </section>

    <div style="margin-top: 30px; text-align: center;">
      <a href="edit-profile.php" class="btn"> Επεξεργασία Προφίλ</a>
      <a href="index.html" class="btn"> Αρχική</a>
    </div>
  </main>

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

  <script src="assets/js/script.js"></script>
</body>

</html>