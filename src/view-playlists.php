<?php
session_start();
require 'includes/db.php';
global $db;

// Check if the user is logged in
if (!isset($_SESSION['id'])) {
  header('Location: auth.php');
  exit;
}

$user_id = $_SESSION['id'];

$alert_message = '';
$alert_type = ''; // 'success' or 'error'

if (isset($_GET['deleted']) && $_GET['deleted'] == 'true') {
  $alert_message = 'Playlist deleted successfully!';
  $alert_type = 'success';
}
if (isset($_GET['error'])) {
  $alert_type = 'error';
  switch ($_GET['error']) {
    case 'delete_failed_permission':
      $alert_message = 'Failed to delete playlist: Not found, no permission, or already deleted.';
      break;
    case 'delete_failed_sql':
      $alert_message = 'Failed to delete playlist: A database error occurred.';
      break;
    case 'delete_failed_prepare':
      $alert_message = 'Failed to delete playlist: A server error occurred.';
      break;
    case 'missing_id':
      $alert_message = 'Failed to delete playlist: Playlist ID was not specified.';
      break;
    default:
      $alert_message = 'An unknown error occurred while trying to delete the playlist.';
      break;
  }
}

// Fetch user's playlists
$stmt = $db->prepare("SELECT id, title, description, created_at FROM playlists WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$playlists_result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Your Playlists</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
  <header>
    <h1>Your Playlists</h1>
    <nav>
      <a href="index.html">Home</a>
      <a href="view-playlists.php">My Playlists</a>
      <a href="view-profile.php">Profile</a>
      <a href="logout.php">Logout</a>
    </nav>
  </header>

  <main>
    <div class="container">
      <?php if ($alert_message): ?>
        <div class="alert-message <?= $alert_type === 'success' ? 'alert-success' : 'alert-error' ?>" id="flash-alert">
          <?= htmlspecialchars($alert_message) ?>
        </div>
        <script>
          // Optional: Hide the message after a few seconds
          setTimeout(function() {
            var flashAlert = document.getElementById('flash-alert');
            if (flashAlert) {
              flashAlert.style.display = 'none';
            }
            // Clean the URL query parameters to prevent message re-showing on refresh
            if (window.history.replaceState) {
              window.history.replaceState(null, null, window.location.pathname);
            }
          }, 5000); // 5 seconds
        </script>
      <?php endif; ?>

      <div class="actions">
        <a href="create-playlist.php" class="btn">Create New Playlist</a>
      </div>

      <h2>My Playlists</h2>
      <?php if ($playlists_result->num_rows > 0): ?>
        <ul class="playlist-list">
          <?php while ($playlist = $playlists_result->fetch_assoc()): ?>
            <li class="playlist-item">
              <h3><?= htmlspecialchars($playlist['title']) ?></h3>
              <p><?= htmlspecialchars($playlist['description'] ?? 'No description') ?></p>
              <small>Created: <?= date('F j, Y', strtotime($playlist['created_at'])) ?></small>
              <div>
                <a href="view-playlist-details.php?id=<?= $playlist['id'] ?>" class="btn-small">View Details</a>
                <a href="edit-playlist.php?id=<?= $playlist['id'] ?>" class="btn-small">Edit</a>
                <a href="delete-playlist.php?id=<?= $playlist['id'] ?>" class="btn-small btn-danger" onclick="return confirm('Are you sure you want to delete this playlist?');">Delete</a>
              </div>
            </li>
          <?php endwhile; ?>
        </ul>
      <?php else: ?>
        <p>You haven't created any playlists yet. <a href="create-playlist.php">Create one now!</a></p>
      <?php endif; ?>
    </div>
  </main>

  <footer>
    <p>&copy; <?= date("Y") ?> Your App Name</p>
  </footer>
</body>

</html>