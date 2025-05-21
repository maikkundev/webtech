<?php
session_start();
require 'includes/db.php'; // Adjusted path to db.php

// Check if the user is logged in
if (!isset($_SESSION['id'])) {
  header('Location: auth.php');
  exit;
}

$user_id = $_SESSION['id'];
$message = '';

// Handle form submission for creating the playlist
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['playlist_title'])) {
  $playlist_title = trim($_POST['playlist_title']);
  $playlist_description = trim($_POST['playlist_description'] ?? 'No description'); // Optional description

  if (!empty($playlist_title)) {
    // For now, we are not implementing the YouTube API search functionality.
    // We will just create the playlist with the given title and description.

    $stmt = $db->prepare("INSERT INTO playlists (user_id, title, description, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
    if ($stmt) {
      $stmt->bind_param('iss', $user_id, $playlist_title, $playlist_description);
      if ($stmt->execute()) {
        $message = "Playlist '" . htmlspecialchars($playlist_title) . "' created successfully!";
        // Optionally redirect to view-playlists.php or the new playlist's page
        // header('Location: view-playlists.php');
        // exit;
      } else {
        $message = "Error creating playlist: " . htmlspecialchars($stmt->error);
      }
      $stmt->close();
    } else {
      $message = "Error preparing statement: " . htmlspecialchars($db->error);
    }
  } else {
    $message = "Playlist title cannot be empty.";
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create New Playlist</title>
  <link rel="stylesheet" href="assets/css/style.css"> <!-- Assuming you have a general stylesheet -->
</head>

<body>
  <header>
    <h1>Create New Playlist</h1>
    <nav>
      <a href="index.html">Home</a>
      <a href="view-playlists.php">My Playlists</a>
      <a href="view-profile.php">Profile</a>
      <a href="logout.php">Logout</a>
    </nav>
  </header>

  <main>
    <div class="container">
      <?php if (!empty($message)): ?>
        <p class="message"><?= $message ?></p>
      <?php endif; ?>

      <form action="create-playlist.php" method="POST">
        <div class="form-group">
          <label for="playlist_title">Playlist Title:</label>
          <input type="text" id="playlist_title" name="playlist_title" required>
        </div>

        <div class="form-group">
          <label for="playlist_description">Playlist Description (Optional):</label>
          <textarea id="playlist_description" name="playlist_description" rows="3"></textarea>
        </div>

        <div class="form-group">
          <label for="youtube_search">Search YouTube (Placeholder):</label>
          <input type="text" id="youtube_search" name="youtube_search" placeholder="Search for songs/videos...">
          <small><i>YouTube API integration is not yet implemented. This field is a placeholder.</i></small>
        </div>

        <button type="submit" class="btn">Create Playlist</button>
      </form>
    </div>
  </main>

  <footer>
    <p>&copy; <?= date("Y") ?> Your App Name</p>
  </footer>
</body>

</html>