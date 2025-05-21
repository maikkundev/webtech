<?php
session_start();
require 'includes/db.php';

// Check if the user is logged in
if (!isset($_SESSION['id'])) {
  header('Location: auth.php');
  exit;
}

$user_id = $_SESSION['id'];

// Default redirect location
$redirect_url = 'view-playlists.php';

if (isset($_GET['id'])) {
  $playlist_id = $_GET['id'];

  // It's good practice to ensure the request method is appropriate for a delete action (e.g., POST)
  // However, the original setup used a GET link with JS confirm, so we'll proceed with GET for now.
  // Consider changing to POST for better security (to prevent CSRF).

  $stmt = $db->prepare("DELETE FROM playlists WHERE id = ? AND user_id = ?");
  if ($stmt) {
    $stmt->bind_param('ii', $playlist_id, $user_id);
    if ($stmt->execute()) {
      if ($stmt->affected_rows > 0) {
        $redirect_url .= '?deleted=true';
      } else {
        // Playlist not found, or no permission, or already deleted
        $redirect_url .= '?error=delete_failed_permission';
      }
    } else {
      // SQL execution error
      $redirect_url .= '?error=delete_failed_sql';
    }
    $stmt->close();
  } else {
    // Statement preparation error
    $redirect_url .= '?error=delete_failed_prepare';
  }
} else {
  // No ID provided
  $redirect_url .= '?error=missing_id';
}

header('Location: ' . $redirect_url);
exit;
