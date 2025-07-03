<?php
session_start();
require 'includes/db.php'; // Assumes $db is a mysqli connection
require_once __DIR__ . '/vendor/autoload.php'; // For Google API client and Dotenv

// Load .env file variables
if (file_exists(__DIR__ . '/.env')) { // Corrected path
  $dotenv = Dotenv\Dotenv::createImmutable(__DIR__); // Corrected path
  $dotenv->load();
} else {
  die('Error: .env file not found. Please ensure it exists in the /var/www/html directory.');
}

if (!isset($_SESSION['id'])) { // Reverted to use 'id'
  header('Location: auth.php');
  exit;
}

$user_id = $_SESSION['id']; // Reverted to use 'id'
$playlist_id = $_GET['id'] ?? null;

$playlist = null;
$existing_playlist_videos = [];
$search_results = [];
$page_messages = []; // For displaying various messages: ['type' => 'success|error|info', 'text' => 'message']
$youtube_search_query_value = '';

if (!$playlist_id) {
  header('Location: view-playlists.php');
  exit;
}

// --- YouTube API Client Setup ---
$CLIENT_ID = $_ENV['CLIENT_ID'] ?? null;
$CLIENT_SECRET = $_ENV['CLIENT_SECRET'] ?? null;
$REDIRECT_URI = 'http://localhost:8181/edit-playlist.php'; // IMPORTANT: This page is now the redirect URI

$google_client = null;
if ($CLIENT_ID && $CLIENT_SECRET) {
  $google_client = new Google_Client();
  $google_client->setClientId($CLIENT_ID);
  $google_client->setClientSecret($CLIENT_SECRET);
  $google_client->setRedirectUri($REDIRECT_URI);
  $google_client->setScopes(['https://www.googleapis.com/auth/youtube.readonly']);
  $google_client->setAccessType('offline');
  $google_client->setPrompt('select_account consent');
} else {
  $page_messages[] = ['type' => 'error', 'text' => 'YouTube API credentials (CLIENT_ID, CLIENT_SECRET) are not set in .env file. YouTube search functionality will be disabled.'];
}

// Handle OAuth 2.0 callback for YouTube
if (isset($_GET['code']) && $google_client) {
  try {
    $token = $google_client->fetchAccessTokenWithAuthCode($_GET['code']);
    if (isset($token['error'])) {
      $page_messages[] = ['type' => 'error', 'text' => 'Error fetching YouTube access token: ' . htmlspecialchars($token['error_description'] ?? $token['error'])];
    } else {
      $google_client->setAccessToken($token);
      $_SESSION['youtube_access_token'] = $token;

      // Retrieve playlist_id from state query parameter
      $state_str = $_GET['state'] ?? '';
      parse_str(urldecode($state_str), $state_params);
      $original_playlist_id = $state_params['playlist_id'] ?? $playlist_id;

      header('Location: edit-playlist.php?id=' . urlencode($original_playlist_id)); // Redirect back to this page with original playlist ID
      exit;
    }
  } catch (Exception $e) {
    $page_messages[] = ['type' => 'error', 'text' => 'Exception during YouTube token fetch: ' . htmlspecialchars($e->getMessage())];
  }
}

// Check and refresh YouTube access token
if (isset($_SESSION['youtube_access_token']) && $google_client) {
  $google_client->setAccessToken($_SESSION['youtube_access_token']);
  if ($google_client->isAccessTokenExpired()) {
    $refreshToken = $google_client->getRefreshToken();
    if ($refreshToken) {
      try {
        $google_client->fetchAccessTokenWithRefreshToken($refreshToken);
        $_SESSION['youtube_access_token'] = $google_client->getAccessToken();
      } catch (Exception $e) {
        $page_messages[] = ['type' => 'error', 'text' => 'Error refreshing YouTube token: ' . htmlspecialchars($e->getMessage())];
        unset($_SESSION['youtube_access_token']);
      }
    } else {
      $page_messages[] = ['type' => 'info', 'text' => 'YouTube access token expired, and no refresh token available. Please re-authenticate.'];
      unset($_SESSION['youtube_access_token']);
    }
  }
}

// Handle YouTube logout
if (isset($_GET['yt_logout'])) {
  unset($_SESSION['youtube_access_token']);
  header('Location: edit-playlist.php?id=' . urlencode($playlist_id));
  exit;
}

// Handle status messages from redirects (e.g., after adding/removing video)
if (isset($_GET['status'])) {
  $status_map = [
    'video_added' => ['type' => 'success', 'text' => 'Video successfully added to the playlist!'],
    'video_exists' => ['type' => 'info', 'text' => 'This video is already in the playlist.'],
    'error_adding_video' => ['type' => 'error', 'text' => 'Error adding video. Please try again.'],
    'removed' => ['type' => 'success', 'text' => 'Video removed from playlist.'],
    'remove_error' => ['type' => 'error', 'text' => 'Error removing video.'],
    'details_updated' => ['type' => 'success', 'text' => 'Playlist details updated successfully!'],
    'details_update_error' => ['type' => 'error', 'text' => 'Error updating playlist details.'],
    'details_no_change' => ['type' => 'info', 'text' => 'No changes were made to playlist details.']
  ];
  if (array_key_exists($_GET['status'], $status_map)) {
    $page_messages[] = $status_map[$_GET['status']];
  }
}

// --- Handle POST Requests ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $action = $_POST['action'] ?? '';

  if ($action === 'edit_playlist_details') {
    $new_title = trim($_POST['playlist_title'] ?? '');
    $new_description = trim($_POST['playlist_description'] ?? ''); // Keep empty string if not provided, DB default or NULL can handle it
    $current_playlist_id_form = $_POST['playlist_id'] ?? $playlist_id; // Ensure we are updating the correct playlist

    if (empty($new_title)) {
      $page_messages[] = ['type' => 'error', 'text' => 'Playlist title cannot be empty.'];
    } else {
      $stmt_update = $db->prepare("UPDATE playlists SET title = ?, description = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
      if ($stmt_update) {
        $stmt_update->bind_param('ssii', $new_title, $new_description, $current_playlist_id_form, $user_id);
        if ($stmt_update->execute()) {
          if ($stmt_update->affected_rows > 0) {
            header('Location: edit-playlist.php?id=' . urlencode($current_playlist_id_form) . '&status=details_updated');
            exit;
          } else {
            header('Location: edit-playlist.php?id=' . urlencode($current_playlist_id_form) . '&status=details_no_change');
            exit;
          }
        } else {
          $page_messages[] = ['type' => 'error', 'text' => 'Error updating playlist: ' . htmlspecialchars($stmt_update->error)];
        }
        $stmt_update->close();
      } else {
        $page_messages[] = ['type' => 'error', 'text' => 'Error preparing update statement: ' . htmlspecialchars($db->error)];
      }
    }
    // Values for form repopulation on error
    $playlist = ['id' => $current_playlist_id_form, 'title' => $new_title, 'description' => $new_description];
  } elseif ($action === 'add_youtube_video') {
    $video_playlist_id = $_POST['playlist_id'] ?? null;
    $video_id_to_add = $_POST['video_id'] ?? null;
    $video_title = $_POST['video_title'] ?? null;
    $video_thumbnail = $_POST['video_thumbnail'] ?? null;
    $video_channel_title = $_POST['video_channel_title'] ?? null;

    if ($video_playlist_id && $video_id_to_add && $video_title && $video_thumbnail && $video_channel_title) {
      // 1. Validate playlist ownership
      $stmt_owner = $db->prepare("SELECT user_id FROM playlists WHERE id = ? AND user_id = ?");
      $stmt_owner->bind_param('ii', $video_playlist_id, $user_id);
      $stmt_owner->execute();
      $result_owner = $stmt_owner->get_result();
      if ($result_owner->num_rows === 1) {
        // 2. Check for duplicate video
        $stmt_check = $db->prepare("SELECT id FROM playlist_videos WHERE playlist_id = ? AND video_id = ? AND user_id = ?");
        $stmt_check->bind_param('isi', $video_playlist_id, $video_id_to_add, $user_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        if ($result_check->num_rows > 0) {
          header('Location: edit-playlist.php?id=' . urlencode($video_playlist_id) . '&status=video_exists');
          exit;
        } else {
          // 3. Insert video
          $stmt_insert = $db->prepare("INSERT INTO playlist_videos (playlist_id, user_id, video_id, title, thumbnail_url, channel_title) VALUES (?, ?, ?, ?, ?, ?)");
          if ($stmt_insert) {
            $stmt_insert->bind_param('iissss', $video_playlist_id, $user_id, $video_id_to_add, $video_title, $video_thumbnail, $video_channel_title);
            if ($stmt_insert->execute()) {
              header('Location: edit-playlist.php?id=' . urlencode($video_playlist_id) . '&status=video_added');
              exit;
            } else {
              $page_messages[] = ['type' => 'error', 'text' => 'Error adding video to database: ' . htmlspecialchars($stmt_insert->error)];
            }
            $stmt_insert->close();
          } else {
            $page_messages[] = ['type' => 'error', 'text' => 'Error preparing insert statement for video: ' . htmlspecialchars($db->error)];
          }
        }
        $stmt_check->close();
      } else {
        $page_messages[] = ['type' => 'error', 'text' => 'You do not own this playlist or it does not exist.'];
      }
      $stmt_owner->close();
    } else {
      $page_messages[] = ['type' => 'error', 'text' => 'Missing data to add video.'];
    }
    // If add fails and doesn't redirect, keep search query
    $youtube_search_query_value = $_POST['youtube_search_query_hidden'] ?? '';
  } elseif ($action === 'search_youtube' && $google_client && $google_client->getAccessToken()) {
    $youtube_search_query_value = trim($_POST['youtube_search_query'] ?? '');
    if (!empty($youtube_search_query_value)) {
      try {
        $youtube_service = new \Google\Service\YouTube($google_client);
        $searchResponse = $youtube_service->search->listSearch('id,snippet', [
          'q' => $youtube_search_query_value,
          'maxResults' => 10,
          'type' => 'video'
        ]);
        foreach ($searchResponse->getItems() as $item) {
          $search_results[] = [
            'id' => $item->id->videoId,
            'title' => $item->snippet->title,
            'thumbnail' => $item->snippet->thumbnails->default->url,
            'channelTitle' => $item->snippet->channelTitle
          ];
        }
        if (empty($search_results)) {
          $page_messages[] = ['type' => 'info', 'text' => 'No YouTube results found for "' . htmlspecialchars($youtube_search_query_value) . '".'];
        }
      } catch (Google_Service_Exception $e) {
        $page_messages[] = ['type' => 'error', 'text' => 'Google Service API Error: ' . htmlspecialchars($e->getMessage())];
        if ($e->getCode() == 401) { // Unauthorized
          unset($_SESSION['youtube_access_token']);
          $page_messages[] = ['type' => 'error', 'text' => 'YouTube authentication error. Please re-authenticate.'];
        }
      } catch (Exception $e) {
        $page_messages[] = ['type' => 'error', 'text' => 'An unexpected error occurred during YouTube search: ' . htmlspecialchars($e->getMessage())];
      }
    } else {
      $page_messages[] = ['type' => 'info', 'text' => 'YouTube search query cannot be empty.'];
    }
  } elseif ($action === 'search_youtube' && (!$google_client || !$google_client->getAccessToken())) {
    $page_messages[] = ['type' => 'info', 'text' => 'Please authenticate with YouTube to perform a search.'];
    $youtube_search_query_value = trim($_POST['youtube_search_query'] ?? '');
  }
}

// --- Fetch data for display (unless already populated by POST error handling) ---
// Fetch playlist details
if (!$playlist) { // If not populated by POST error handling for details edit
  $stmt_fetch = $db->prepare("SELECT id, title, description, created_at FROM playlists WHERE id = ? AND user_id = ?");
  if ($stmt_fetch) {
    $stmt_fetch->bind_param('ii', $playlist_id, $user_id);
    $stmt_fetch->execute();
    $result = $stmt_fetch->get_result();
    if ($result->num_rows === 1) {
      $playlist = $result->fetch_assoc();
    } else {
      $page_messages[] = ['type' => 'error', 'text' => "Playlist not found or you do not have permission to view it."];
    }
    $stmt_fetch->close();
  } else {
    $page_messages[] = ['type' => 'error', 'text' => "Error preparing to fetch playlist details: " . htmlspecialchars($db->error)];
  }
}

// Fetch existing videos for this playlist
if ($playlist) { // Only fetch if playlist exists
  $items_stmt = $db->prepare("SELECT id as item_id, video_id, title, thumbnail_url, channel_title, added_at FROM playlist_videos WHERE playlist_id = ? AND user_id = ? ORDER BY added_at DESC");
  if ($items_stmt) {
    $items_stmt->bind_param('ii', $playlist_id, $user_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    while ($row = $items_result->fetch_assoc()) {
      $existing_playlist_videos[] = $row;
    }
    $items_stmt->close();
  } else {
    $page_messages[] = ['type' => 'error', 'text' => "Error fetching existing playlist items: " . htmlspecialchars($db->error)];
  }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Playlist: <?= $playlist ? htmlspecialchars($playlist['title']) : 'Playlist' ?> - YouTube Playlist App</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    /* Basic styling for messages and sections */
    .messages-container div {
      padding: 10px;
      margin-bottom: 10px;
      border-radius: 4px;
      border-left-width: 6px;
      border-left-style: solid;
    }

    .messages-container .info-message {
      background-color: #e7f3fe;
      border-left-color: #2196F3;
      color: #0d47a1;
    }

    .messages-container .error-message {
      background-color: #ffebee;
      border-left-color: #f44336;
      color: #b71c1c;
    }

    .messages-container .success-message {
      background-color: #e8f5e9;
      border-left-color: #4CAF50;
      color: #1b5e20;
    }

    .section {
      margin-bottom: 30px;
      padding: 20px;
      background-color: #f9f9f9;
      border-radius: 5px;
    }

    .section h3 {
      margin-top: 0;
      border-bottom: 1px solid #eee;
      padding-bottom: 10px;
      margin-bottom: 15px;
    }

    .video-list {
      list-style-type: none;
      padding: 0;
    }

    .video-list li {
      display: flex;
      align-items: flex-start;
      margin-bottom: 15px;
      padding: 10px;
      background: #fff;
      border: 1px solid #eee;
      border-radius: 4px;
    }

    .video-list img {
      width: 120px;
      height: 90px;
      object-fit: cover;
      margin-right: 15px;
      border-radius: 4px;
    }

    .video-list .video-info {
      flex-grow: 1;
    }

    .video-list .video-info strong {
      display: block;
      margin-bottom: 5px;
      font-size: 1.1em;
    }

    .video-list .video-info small {
      color: #666;
      display: block;
      margin-bottom: 3px;
    }

    .video-list .video-actions form {
      margin-top: 5px;
    }

    .video-list .video-actions .btn-danger {
      margin-left: auto;
    }

    /* For remove button */
    .auth-link,
    .btn-youtube-action {
      display: inline-block;
      padding: 8px 12px;
      background-color: #c4302b;
      color: white;
      text-decoration: none;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      margin-top: 5px;
      font-size: 0.9em;
    }

    .auth-link:hover,
    .btn-youtube-action:hover {
      background-color: #a02721;
    }
  </style>
</head>

<body>
  <header>
    <h1>Edit Playlist: <?= $playlist ? htmlspecialchars($playlist['title']) : 'Playlist Not Found' ?></h1>
    <nav>
      <a href="index.php">Home</a>
      <a href="view-playlists.php">My Playlists</a>
      <a href="create-playlist.php">Create Playlist</a>
      <a href="logout.php">Logout</a>
    </nav>
  </header>

  <main>
    <div class="container">
      <!-- Display Messages -->
      <div class="messages-container">
        <?php foreach ($page_messages as $msg): ?>
          <div class="<?= htmlspecialchars($msg['type']) ?>-message"><?= htmlspecialchars($msg['text']) ?></div>
        <?php endforeach; ?>
      </div>

      <?php if ($playlist): ?>
        <!-- Section 1: Edit Playlist Details -->
        <section class="section">
          <h3>Playlist Details</h3>
          <form action="edit-playlist.php?id=<?= htmlspecialchars($playlist['id']) ?>" method="POST">
            <input type="hidden" name="playlist_id" value="<?= htmlspecialchars($playlist['id']) ?>">
            <input type="hidden" name="action" value="edit_playlist_details">
            <div class="form-group">
              <label for="playlist_title">Playlist Title:</label>
              <input type="text" id="playlist_title" name="playlist_title" value="<?= htmlspecialchars($playlist['title'] ?? '') ?>" required>
            </div>
            <div class="form-group">
              <label for="playlist_description">Playlist Description:</label>
              <textarea id="playlist_description" name="playlist_description" rows="4"><?= htmlspecialchars($playlist['description'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn">Save Details</button>
            <a href="view-playlist-details.php?id=<?= htmlspecialchars($playlist['id']) ?>" class="btn btn-secondary">View Playlist</a>
          </form>
        </section>

        <!-- Section 2: Videos in this Playlist -->
        <section class="section">
          <h3>Videos in this Playlist (<?= count($existing_playlist_videos) ?>)</h3>
          <?php if (!empty($existing_playlist_videos)): ?>
            <ul class="video-list">
              <?php foreach ($existing_playlist_videos as $video): ?>
                <li>
                  <img src="<?= htmlspecialchars($video['thumbnail_url']) ?>" alt="Thumbnail">
                  <div class="video-info">
                    <strong><?= htmlspecialchars($video['title']) ?></strong>
                    <small>Channel: <?= htmlspecialchars($video['channel_title']) ?></small>
                    <small>Added: <?= date('M j, Y H:i', strtotime($video['added_at'])) ?></small>
                  </div>
                  <div class="video-actions">
                    <form action="remove_video_from_playlist.php" method="POST" style="display:inline;">
                      <input type="hidden" name="playlist_video_id" value="<?= $video['item_id'] ?>">
                      <input type="hidden" name="playlist_id" value="<?= $playlist_id ?>">
                      <button type="submit" class="btn btn-danger btn-small" onclick="return confirm('Are you sure you want to remove this video?');">Remove</button>
                    </form>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p>This playlist currently has no videos.</p>
          <?php endif; ?>
        </section>

        <!-- Section 3: Search and Add YouTube Videos -->
        <section class="section">
          <h3>Search and Add YouTube Videos</h3>
          <?php if ($google_client): ?>
            <?php if (!$google_client->getAccessToken()): ?>
              <?php
              $authUrlState = 'playlist_id=' . urlencode($playlist_id); // Pass playlist_id in state
              $authUrl = $google_client->createAuthUrl() . '&state=' . urlencode($authUrlState);
              ?>
              <p>To search and add videos from YouTube, you need to connect your YouTube account.</p>
              <a href="<?= htmlspecialchars($authUrl) ?>" class="auth-link">Connect to YouTube</a>
            <?php else: ?>
              <p style="color: green;">Authenticated with YouTube.
                <a href="edit-playlist.php?id=<?= urlencode($playlist_id) ?>&yt_logout=1" class="btn-youtube-action" style="background-color:#aaa; font-size:0.8em; padding: 5px 10px; margin-left:10px;">Logout from YouTube</a>
              </p>
              <form method="POST" action="edit-playlist.php?id=<?= htmlspecialchars($playlist_id) ?>" class="form-inline">
                <input type="hidden" name="action" value="search_youtube">
                <div class="form-group">
                  <input type="text" name="youtube_search_query" placeholder="Search YouTube..." value="<?= htmlspecialchars($youtube_search_query_value) ?>" style="width:auto; min-width:300px;">
                </div>
                <button type="submit" class="btn">Search YouTube</button>
              </form>

              <?php if (!empty($search_results)): ?>
                <h4 style="margin-top:20px;">Search Results:</h4>
                <ul class="video-list">
                  <?php foreach ($search_results as $video): ?>
                    <li>
                      <img src="<?= htmlspecialchars($video['thumbnail']) ?>" alt="Thumbnail">
                      <div class="video-info">
                        <strong><?= htmlspecialchars($video['title']) ?></strong>
                        <small>Channel: <?= htmlspecialchars($video['channelTitle']) ?></small>
                        <small>ID: <?= htmlspecialchars($video['id']) ?></small>
                        <a href="https://www.youtube.com/watch?v=<?= htmlspecialchars($video['id']) ?>" target="_blank" style="font-size:0.9em;">Watch</a>
                      </div>
                      <div class="video-actions">
                        <form action="edit-playlist.php?id=<?= htmlspecialchars($playlist_id) ?>" method="POST">
                          <input type="hidden" name="action" value="add_youtube_video">
                          <input type="hidden" name="playlist_id" value="<?= htmlspecialchars($playlist_id) ?>">
                          <input type="hidden" name="video_id" value="<?= htmlspecialchars($video['id']) ?>">
                          <input type="hidden" name="video_title" value="<?= htmlspecialchars($video['title']) ?>">
                          <input type="hidden" name="video_thumbnail" value="<?= htmlspecialchars($video['thumbnail']) ?>">
                          <input type="hidden" name="video_channel_title" value="<?= htmlspecialchars($video['channelTitle']) ?>">
                          <!-- To retain search query after adding a video if page reloads with search results -->
                          <input type="hidden" name="youtube_search_query_hidden" value="<?= htmlspecialchars($youtube_search_query_value) ?>">
                          <button type="submit" class="btn btn-primary btn-small">Add to Playlist</button>
                        </form>
                      </div>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            <?php endif; ?>
          <?php else: ?>
            <p>YouTube integration is disabled due to missing API credentials in the .env file.</p>
          <?php endif; ?>
        </section>

      <?php elseif (empty($page_messages)): // Only show if no major error like "playlist not found" 
      ?>
        <p>Playlist could not be loaded.</p>
        <p><a href="view-playlists.php" class="btn">Back to My Playlists</a></p>
      <?php endif; ?>
      <p style="margin-top: 30px;"><a href="view-playlists.php" class="btn btn-secondary">Back to All Playlists</a></p>
    </div>
  </main>

  <footer>
    <p>&copy; <?= date("Y") ?> YouTube Playlist App</p>
  </footer>
</body>

</html>