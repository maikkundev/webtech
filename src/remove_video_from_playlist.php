<?php
global $db;
session_start();
require_once 'includes/db.php'; // Assumes $db is a mysqli connection

if (!isset($_SESSION['id'])) { // Reverted to use 'id'
  // If not logged in, redirect to log in or home
  header('Location: auth.php');
  exit;
}

$user_id = $_SESSION['id']; // Reverted to use 'id'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['playlist_video_id'], $_POST['playlist_id'])) {
    $playlist_video_id = $_POST['playlist_video_id'];
    $playlist_id = $_POST['playlist_id']; // Get playlist_id for redirect

    // Validate that the playlist_video entry belongs to the current user
    // This is an important security check
    $stmt = $db->prepare("SELECT pv.id FROM playlist_videos pv JOIN playlists p ON pv.playlist_id = p.id WHERE pv.id = ? AND p.user_id = ?");
    if (!$stmt) {
      // Log error: $db->error
      header('Location: view-playlist-details.php?id=' . urlencode($playlist_id) . '&status=remove_error&message=stmt_error');
      exit;
    }
    $stmt->bind_param('ii', $playlist_video_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
      // User is authorized to delete this item, proceed with deletion
      $delete_stmt = $db->prepare("DELETE FROM playlist_videos WHERE id = ? AND user_id = ?");
      if (!$delete_stmt) {
        // Log error: $db->error
        header('Location: view-playlist-details.php?id=' . urlencode($playlist_id) . '&status=remove_error&message=delete_stmt_error');
        exit;
      }
      $delete_stmt->bind_param('ii', $playlist_video_id, $user_id);
      if ($delete_stmt->execute()) {
        header('Location: view-playlist-details.php?id=' . urlencode($playlist_id) . '&status=removed');
      } else {
        // Log error: $delete_stmt->error
        header('Location: view-playlist-details.php?id=' . urlencode($playlist_id) . '&status=remove_error');
      }
      $delete_stmt->close();
    } else {
      // Item not found or does not belong to the user
      header('Location: view-playlist-details.php?id=' . urlencode($playlist_id) . '&status=remove_error&message=not_authorized');
    }
    $stmt->close();
  } else {
    // Missing parameters
    // Try to get playlist_id from POST if available for a more graceful redirect, otherwise redirect to a general page
    $redirect_playlist_id = $_POST['playlist_id'] ?? null;
    if ($redirect_playlist_id) {
      header('Location: view-playlist-details.php?id=' . urlencode($redirect_playlist_id) . '&status=remove_error&message=missing_params');
    } else {
      header('Location: view-playlists.php?status=error&message=InvalidRequest');
    }
  }
} else {
  // Not a POST request, redirect
  header('Location: view-playlists.php');
}
exit;
