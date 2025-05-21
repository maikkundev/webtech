<?php
session_start();
require 'includes/db.php';

if (!isset($_SESSION['id'])) {
  header('Location: auth.php');
  exit;
}

$user_id = $_SESSION['id'];
$playlist_id = $_GET['id'] ?? null;
$playlist = null;
$message = '';
$error = '';

if (!$playlist_id) {
  header('Location: view-playlists.php'); // Redirect if no ID is provided
  exit;
}

// Fetch current playlist details to populate the form (GET request part)
if ($_SERVER["REQUEST_METHOD"] != "POST") {
  $stmt_fetch = $db->prepare("SELECT id, title, description FROM playlists WHERE id = ? AND user_id = ?");
  if ($stmt_fetch) {
    $stmt_fetch->bind_param('ii', $playlist_id, $user_id);
    $stmt_fetch->execute();
    $result = $stmt_fetch->get_result();
    if ($result->num_rows === 1) {
      $playlist = $result->fetch_assoc();
    } else {
      $error = "Playlist not found or you do not have permission to edit it.";
    }
    $stmt_fetch->close();
  } else {
    $error = "Error preparing to fetch playlist details: " . htmlspecialchars($db->error);
  }
}

// Handle form submission for updating the playlist (POST request part)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $new_title = trim($_POST['playlist_title'] ?? '');
  $new_description = trim($_POST['playlist_description'] ?? 'No description');
  // We still need playlist_id from GET or a hidden field, ensuring it's the correct one.
  $current_playlist_id = $_POST['playlist_id'] ?? $playlist_id; // Use hidden field or ensure GET param is still available

  if (empty($new_title)) {
    $error = "Playlist title cannot be empty.";
  } else {
    $stmt_update = $db->prepare("UPDATE playlists SET title = ?, description = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
    if ($stmt_update) {
      $stmt_update->bind_param('ssii', $new_title, $new_description, $current_playlist_id, $user_id);
      if ($stmt_update->execute()) {
        if ($stmt_update->affected_rows > 0) {
          $message = "Playlist updated successfully!";
          // Re-fetch to show updated data
          $stmt_fetch_updated = $db->prepare("SELECT id, title, description FROM playlists WHERE id = ? AND user_id = ?");
          $stmt_fetch_updated->bind_param('ii', $current_playlist_id, $user_id);
          $stmt_fetch_updated->execute();
          $playlist = $stmt_fetch_updated->get_result()->fetch_assoc();
          $stmt_fetch_updated->close();
        } else {
          $error = "No changes were made or playlist not found/no permission.";
        }
      } else {
        $error = "Error updating playlist: " . htmlspecialchars($stmt_update->error);
      }
      $stmt_update->close();
    } else {
      $error = "Error preparing update statement: " . htmlspecialchars($db->error);
    }
  }
  // If there was an error during POST, and $playlist wasn't re-fetched, ensure it has the submitted values for the form
  if ($error && !$playlist) {
    $playlist = ['id' => $current_playlist_id, 'title' => $new_title, 'description' => $new_description];
  }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Playlist - <?= $playlist ? htmlspecialchars($playlist['title']) : 'Playlist' ?></title>
  <link rel="stylesheet" href="assets/css/style.css"> <!-- Assuming you have a general stylesheet -->
</head>

<body>
  <header>
    <h1>Edit Playlist</h1>
    <nav>
      <a href="index.html">Home</a>
      <a href="view-playlists.php">My Playlists</a>
      <a href="create-playlist.php">Create Playlist</a>
      <a href="view-profile.php">Profile</a>
      <a href="logout.php">Logout</a>
    </nav>
  </header>

  <main>
    <div class="container">
      <?php if (!empty($message)): ?>
        <p class="message success-message"><?= $message ?></p>
      <?php endif; ?>
      <?php if (!empty($error)): ?>
        <p class="message error-message"><?= $error ?></p>
      <?php endif; ?>

      <?php if ($playlist): ?>
        <form action="edit-playlist.php?id=<?= htmlspecialchars($playlist['id']) ?>" method="POST">
          <input type="hidden" name="playlist_id" value="<?= htmlspecialchars($playlist['id']) ?>">

          <div class="form-group">
            <label for="playlist_title">Playlist Title:</label>
            <input type="text" id="playlist_title" name="playlist_title" value="<?= htmlspecialchars($playlist['title'] ?? '') ?>" required>
          </div>

          <div class="form-group">
            <label for="playlist_description">Playlist Description:</label>
            <textarea id="playlist_description" name="playlist_description" rows="4"><?= htmlspecialchars($playlist['description'] ?? '') ?></textarea>
          </div>

          <button type="submit" class="btn">Save Changes</button>
          <a href="view-playlist-details.php?id=<?= htmlspecialchars($playlist['id']) ?>" class="btn btn-secondary">Cancel / View Details</a>
        </form>
      <?php elseif (!$error): // If $playlist is null but no $error was set by fetch (e.g. direct access without ID before initial fetch attempt)
        echo "<p>Loading playlist details...</p>"; // Or handle as an error if ID was expected
      endif; ?>

      <?php if (!$playlist && $error): // If playlist could not be loaded and an error message is set 
      ?>
        <p><a href="view-playlists.php" class="btn">Back to My Playlists</a></p>
      <?php endif; ?>
    </div>
  </main>

  <footer>
    <p>&copy; <?= date("Y") ?> Your App Name</p>
  </footer>
</body>

</html>