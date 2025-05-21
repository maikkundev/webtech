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
$playlist_items = []; // Placeholder for playlist items
$message = '';

if (!$playlist_id) {
  header('Location: view-playlists.php'); // Redirect if no ID is provided
  exit;
}

// Fetch playlist details
// Ensure the playlist belongs to the logged-in user
$stmt = $db->prepare("SELECT id, title, description, created_at FROM playlists WHERE id = ? AND user_id = ?");
if ($stmt) {
  $stmt->bind_param('ii', $playlist_id, $user_id);
  $stmt->execute();
  $result = $stmt->get_result();
  if ($result->num_rows === 1) {
    $playlist = $result->fetch_assoc();
  } else {
    // Playlist not found or doesn't belong to the user
    $message = "Playlist not found or you do not have permission to view it.";
    // Optionally, redirect:
    // header('Location: view-playlists.php');
    // exit;
  }
  $stmt->close();
} else {
  $message = "Error preparing statement to fetch playlist details: " . htmlspecialchars($db->error);
}

// --- Placeholder for fetching playlist items ---
// When you have a table for playlist items (e.g., 'playlist_items'),
// you would query it here based on $playlist_id.
// For example:
// if ($playlist) {
//     $items_stmt = $db->prepare("SELECT video_id, video_title, added_at FROM playlist_items WHERE playlist_id = ? ORDER BY added_at DESC");
//     if ($items_stmt) {
//         $items_stmt->bind_param('i', $playlist_id);
//         $items_stmt->execute();
//         $items_result = $items_stmt->get_result();
//         while ($row = $items_result->fetch_assoc()) {
//             $playlist_items[] = $row;
//         }
//         $items_stmt->close();
//     } else {
//         $message .= " Error fetching playlist items: " . htmlspecialchars($db->error);
//     }
// }
// --- End of placeholder ---

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $playlist ? htmlspecialchars($playlist['title']) : 'Playlist Details' ?> - Your App Name</title>
  <link rel="stylesheet" href="assets/css/style.css"> <!-- Assuming you have a general stylesheet -->
</head>

<body>
  <header>
    <h1><?= $playlist ? htmlspecialchars($playlist['title']) : 'Playlist Details' ?></h1>
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
      <?php if (!empty($message) && !$playlist): // Show general error if playlist couldn't be loaded 
      ?>
        <p class="message error-message"><?= $message ?></p>
        <p><a href="view-playlists.php" class="btn">Back to My Playlists</a></p>
      <?php elseif ($playlist): ?>
        <div class="playlist-meta">
          <h2><?= htmlspecialchars($playlist['title']) ?></h2>
          <p class="description"><?= nl2br(htmlspecialchars($playlist['description'] ?? 'No description provided.')) ?></p>
          <small>Created: <?= date('F j, Y', strtotime($playlist['created_at'])) ?></small>
          <div class="playlist-actions">
            <a href="edit-playlist.php?id=<?= $playlist['id'] ?>" class="btn btn-small">Edit Playlist</a>
            <a href="delete-playlist.php?id=<?= $playlist['id'] ?>" class="btn btn-small btn-danger" onclick="return confirm('Are you sure you want to delete this playlist?');">Delete Playlist</a>
          </div>
        </div>

        <hr>

        <h3>Playlist Items</h3>
        <div class="playlist-items-list">
          <?php if (!empty($playlist_items)): ?>
            <ul>
              <?php foreach ($playlist_items as $item): ?>
                <li class="playlist-item-entry">
                  <strong><?= htmlspecialchars($item['video_title']) ?></strong>
                  <?php /* Example: Link to YouTube video if you store video_id
                                    (assuming item_type is 'youtube')
                                    <a href="https://www.youtube.com/watch?v=<?= htmlspecialchars($item['video_id']) ?>" target="_blank">
                                        (<?= htmlspecialchars($item['video_id']) ?>)
                                    </a>
                                    */ ?>
                  <small>Added: <?= date('F j, Y', strtotime($item['added_at'])) ?></small>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p>This playlist currently has no items.</p>
            <?php // Placeholder for adding items - link or button
            // <p><a href="add-item-to-playlist.php?playlist_id=<?= $playlist['id'] 

            ?><a "class=" btn">Add Items</a></p>
            // ?>
          <?php endif; ?>
          <?php if (!empty($message) && $playlist): // Show item-related messages if any 
          ?>
            <p class="message info-message"><?= $message ?></p>
          <?php endif; ?>
        </div>
        <p style="margin-top: 20px;"><a href="view-playlists.php" class="btn">Back to My Playlists</a></p>
      <?php endif; ?>
    </div>
  </main>

  <footer>
    <p>&copy; <?= date("Y") ?> Your App Name</p>
  </footer>
</body>

</html>