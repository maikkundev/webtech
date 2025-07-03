<?php
session_start();
require_once 'includes/db.php';
require_once 'auth.php'; // Ensures user is logged in and sets $user_id
global $db;

if (!isset($_SESSION['id'])) { // Reverted to use 'id'
    header('Location: index.html'); // Or your login page
    exit;
}

$user_id = $_SESSION['id']; // Reverted to use 'id'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['playlist_id'], $_POST['video_id'], $_POST['video_title'], $_POST['video_thumbnail'], $_POST['video_channel_title'])) {
        $playlist_id = (int)$_POST['playlist_id']; // Cast to integer for bind_param
        $video_id = $_POST['video_id'];
        $video_title = $_POST['video_title'];
        $video_thumbnail = $_POST['video_thumbnail'];
        $video_channel_title = $_POST['video_channel_title'];

        // Verify playlist ownership
        $stmt = $db->prepare("SELECT user_id FROM playlists WHERE id = ?");
        $stmt->bind_param('i', $playlist_id);
        $stmt->execute();
        $stmt->bind_result($playlist_owner_id);
        $stmt->fetch();
        $stmt->close();

        if ($playlist_owner_id !== $user_id) {
            header('Location: view-playlists.php?status=error&message=NotAuthorized');
            exit;
        }

        // Insert video into playlist
        // Assuming 'video_id' in the table is the YouTube video ID.
        // If your table column is named 'youtube_id', change 'video_id' in the query below.
        $stmt = $db->prepare("INSERT INTO playlist_videos (playlist_id, user_id, video_id, title, thumbnail_url, channel_title) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('iissss', $playlist_id, $user_id, $video_id, $video_title, $video_thumbnail, $video_channel_title);

        if ($stmt->execute()) {
            header('Location: view-playlist-details.php?id=' . $playlist_id . '&status=video_added');
            exit;
        } else {
            // Check for duplicate entry error (MySQL error code 1062)
            if ($db->errno === 1062) {
                header('Location: view-playlist-details.php?id=' . $playlist_id . '&status=video_exists');
            } else {
                // Log error: $stmt->error or $db->error
                header('Location: view-playlist-details.php?id=' . $playlist_id . '&status=error_adding_video');
            }
            exit;
        }
        $stmt->close(); // Close statement
    } else {
        header('Location: view-playlists.php?status=error&message=MissingData');
        exit;
    }
} else {
    header('Location: view-playlists.php');
    exit;
}