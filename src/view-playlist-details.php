<?php
session_start();
require 'includes/db.php'; // Assumes $db is a mysqli connection
// Corrected path for vendor/autoload.php
require_once __DIR__ . '/vendor/autoload.php'; // For Google API client and Dotenv

// Load .env file variables
// Corrected path for .env file and Dotenv initialization
if (file_exists(__DIR__ . '/.env')) {
  $dotenv = Dotenv\Dotenv::createImmutable(__DIR__); // .env is in the same directory as the script
  $dotenv->load();
} else {
  // Handle error if .env is missing - critical for API keys
  die('Error: .env file not found. Please ensure it exists in the /var/www/html directory.');
}

if (!isset($_SESSION['id'])) { // Ensuring this uses 'id'
  header('Location: auth.php');
  exit;
}

$user_id = $_SESSION['id']; // Reverted to use 'id' as requested
$playlist_id = $_GET['id'] ?? null;
$playlist = null;
$existing_playlist_videos = [];
$page_messages = []; // For YouTube API related messages
$search_results = [];
$status_message_from_action = ''; // For messages from add_video_to_playlist.php etc.

if (isset($_GET['status'])) {
  switch ($_GET['status']) {
    case 'video_added':
      $status_message_from_action = '<p class="message success-message">Video successfully added to the playlist!</p>';
      break;
    case 'video_exists':
      $status_message_from_action = '<p class="message info-message">This video is already in the playlist.</p>';
      break;
    case 'error_adding_video':
      $status_message_from_action = '<p class="message error-message">Error adding video. Please try again.</p>';
      break;
    case 'removed':
      $status_message_from_action = '<p class="message success-message">Video removed from playlist.</p>';
      break;
    case 'remove_error':
      $status_message_from_action = '<p class="message error-message">Error removing video.</p>';
      break;
  }
}

if (!$playlist_id) {
  header('Location: view-playlists.php');
  exit;
}

// Fetch playlist details
$stmt = $db->prepare("SELECT id, title, description, created_at FROM playlists WHERE id = ? AND user_id = ?");
if ($stmt) {
  $stmt->bind_param('ii', $playlist_id, $user_id);
  $stmt->execute();
  $result = $stmt->get_result();
  if ($result->num_rows === 1) {
    $playlist = $result->fetch_assoc();
  } else {
    $page_messages[] = ['type' => 'error', 'text' => "Playlist not found or you do not have permission to view it."];
  }
  $stmt->close();
} else {
  $page_messages[] = ['type' => 'error', 'text' => "Error preparing statement to fetch playlist details: " . htmlspecialchars($db->error)];
}

// Fetch existing videos for this playlist
if ($playlist) {
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
    $page_messages[] = ['type' => 'error', 'text' => "Error fetching playlist items: " . htmlspecialchars($db->error)];
  }
}

// YouTube API Integration
$CLIENT_ID = $_ENV['CLIENT_ID'] ?? null;
$CLIENT_SECRET = $_ENV['CLIENT_SECRET'] ?? null;
// IMPORTANT: This redirect URI must be added to your Google Cloud Console for the client ID
$REDIRECT_URI = 'http://localhost:8181/view-playlist-details.php';

if (empty($CLIENT_ID) || empty($CLIENT_SECRET)) {
  $page_messages[] = ['type' => 'error', 'text' => 'CLIENT_ID or CLIENT_SECRET is not set. YouTube search will not work.'];
}

$client = new Google_Client();
$client->setClientId($CLIENT_ID);
$client->setClientSecret($CLIENT_SECRET);
$client->setRedirectUri($REDIRECT_URI);
$client->setScopes(['https://www.googleapis.com/auth/youtube.readonly']);
$client->setAccessType('offline');
$client->setPrompt('select_account consent');

// Handle OAuth 2.0 callback
if (isset($_GET['code']) && $playlist_id) { // Ensure playlist_id is available for redirect
  try {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    if (isset($token['error'])) {
      $page_messages[] = ['type' => 'error', 'text' => 'Error fetching access token: ' . htmlspecialchars($token['error_description'] ?? $token['error'])];
    } else {
      $client->setAccessToken($token);
      $_SESSION['youtube_access_token'] = $token;

      $state_str = $_GET['state'] ?? '';
      parse_str(urldecode($state_str), $state_params);
      $original_playlist_id = $state_params['playlist_id'] ?? $playlist_id; // Fallback to current $playlist_id if state is missing

      header('Location: view-playlist-details.php?id=' . urlencode($original_playlist_id));
      exit;
    }
  } catch (Exception $e) {
    $page_messages[] = ['type' => 'error', 'text' => 'Exception during token fetch: ' . htmlspecialchars($e->getMessage())];
  }
}

// Check if we have an access token
if (isset($_SESSION['youtube_access_token'])) {
  $client->setAccessToken($_SESSION['youtube_access_token']);
  if ($client->isAccessTokenExpired()) {
    $refreshToken = $client->getRefreshToken();
    if ($refreshToken) {
      try {
        $client->fetchAccessTokenWithRefreshToken($refreshToken);
        $_SESSION['youtube_access_token'] = $client->getAccessToken();
      } catch (Exception $e) {
        $page_messages[] = ['type' => 'error', 'text' => 'Error refreshing token: ' . htmlspecialchars($e->getMessage())];
        unset($_SESSION['youtube_access_token']);
      }
    } else {
      $page_messages[] = ['type' => 'info', 'text' => 'YouTube access token expired, and no refresh token available. Please re-authenticate.'];
      unset($_SESSION['youtube_access_token']);
    }
  }
}

// Handle YouTube search form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['youtube_search_query']) && $playlist_id) {
  if ($client->getAccessToken()) {
    try {
      $youtube = new \Google\Service\YouTube($client);
      $search_query = trim($_POST['youtube_search_query']);
      if (!empty($search_query)) {
        $searchResponse = $youtube->search->listSearch('id,snippet', [
          'q' => $search_query,
          'maxResults' => 10, // Increased to 10
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
          $page_messages[] = ['type' => 'info', 'text' => 'No YouTube results found for "' . htmlspecialchars($search_query) . '".'];
        }
      } else {
        $page_messages[] = ['type' => 'info', 'text' => 'YouTube search query cannot be empty.'];
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
    $page_messages[] = ['type' => 'info', 'text' => 'Cannot search YouTube. Not authenticated.'];
  }
}

// Handle YouTube logout
if (isset($_GET['yt_logout']) && $playlist_id) {
  unset($_SESSION['youtube_access_token']);
  // Optionally, revoke token with Google if desired, though just unsetting session is often enough for app-level logout
  // if ($client->getAccessToken()) { $client->revokeToken(); }
  header('Location: view-playlist-details.php?id=' . urlencode($playlist_id));
  exit;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $playlist ? htmlspecialchars($playlist['title']) : 'Playlist Details' ?> - YouTube Playlist App</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    /* Styles adapted from youtube-test-search.php for consistency */
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
    }

    .messages-container .error-message {
      background-color: #ffebee;
      border-left-color: #f44336;
    }

    .messages-container .success-message {
      background-color: #e8f5e9;
      border-left-color: #4CAF50;
    }

    .auth-link,
    .btn-youtube-action {
      display: inline-block;
      padding: 10px 15px;
      background-color: #c4302b;
      color: white;
      text-decoration: none;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      margin-top: 10px;
    }

    .auth-link:hover,
    .btn-youtube-action:hover {
      background-color: #a02721;
    }

    .youtube-search-form input[type="text"] {
      padding: 10px;
      width: calc(70% - 10px);
      margin-right: 10px;
      border: 1px solid #ddd;
      border-radius: 4px;
    }

    .youtube-search-form button {
      padding: 10px 15px;
      background-color: #555;
      color: white;
      border: none;
      border-radius: 4px;
      cursor: pointer;
    }

    .youtube-search-form button:hover {
      background-color: #333;
    }

    .search-results-list,
    .playlist-videos-list {
      list-style-type: none;
      padding: 0;
    }

    .search-results-list li,
    .playlist-videos-list li {
      display: flex;
      align-items: flex-start;
      margin-bottom: 15px;
      padding: 10px;
      background: #fff;
      border: 1px solid #eee;
      border-radius: 4px;
    }

    .search-results-list img,
    .playlist-videos-list img {
      width: 120px;
      height: 90px;
      object-fit: cover;
      margin-right: 15px;
      border-radius: 4px;
    }

    .search-results-list .video-info,
    .playlist-videos-list .video-info {
      flex-grow: 1;
    }

    .search-results-list .video-info strong,
    .playlist-videos-list .video-info strong {
      display: block;
      margin-bottom: 5px;
      font-size: 1.1em;
    }

    .search-results-list .video-info small,
    .playlist-videos-list .video-info small {
      color: #666;
      display: block;
      margin-bottom: 3px;
    }

    .search-results-list .video-actions form {
      margin-top: 5px;
    }

    .playlist-videos-list .video-actions {
      margin-left: auto;
      padding-left: 10px;
    }

    .btn-add-to-playlist,
    .btn-remove-from-playlist {
      padding: 6px 10px;
      font-size: 0.9em;
    }
  </style>
</head>

<body>
  <header>
    <h1><?= $playlist ? htmlspecialchars($playlist['title']) : 'Playlist Details' ?></h1>
    <nav>
      <a href="index.php">Home</a>
      <a href="view-playlists.php">My Playlists</a>
      <a href="create-playlist.php">Create Playlist</a>
      <!-- <a href="view-profile.php">Profile</a> -->
      <a href="logout.php">Logout</a>
    </nav>
  </header>

  <main>
    <div class="container">
      <!-- Display messages from add/remove video actions -->
      <?= $status_message_from_action ?>

      <!-- Display general page messages (e.g., playlist load errors, YouTube API messages) -->
      <div class="messages-container">
        <?php foreach ($page_messages as $msg): ?>
          <div class="<?= htmlspecialchars($msg['type']) ?>-message"><?= htmlspecialchars($msg['text']) ?></div>
        <?php endforeach; ?>
      </div>

      <?php if ($playlist): ?>
        <div class="playlist-meta">
          <h2><?= htmlspecialchars($playlist['title']) ?></h2>
          <p class="description"><?= nl2br(htmlspecialchars($playlist['description'] ?? 'No description provided.')) ?></p>
          <small>Created: <?= date('F j, Y', strtotime($playlist['created_at'])) ?></small>
          <div class="playlist-actions">
            <a href="edit-playlist.php?id=<?= $playlist['id'] ?>" class="btn btn-small">Edit Details</a>
            <!-- Delete playlist button was here, might be better on view-playlists.php to avoid accidental clicks when managing videos -->
          </div>
        </div>

        <hr>

        <h3>Videos in this Playlist</h3>
        <?php if (!empty($existing_playlist_videos)): ?>
          <ul class="playlist-videos-list">
            <?php foreach ($existing_playlist_videos as $video): ?>
              <li>
                <img src="<?= htmlspecialchars($video['thumbnail_url']) ?>" alt="Thumbnail for <?= htmlspecialchars($video['title']) ?>">
                <div class="video-info">
                  <strong><?= htmlspecialchars($video['title']) ?></strong>
                  <small>Channel: <?= htmlspecialchars($video['channel_title']) ?></small>
                  <small>Added: <?= date('M j, Y H:i', strtotime($video['added_at'])) ?></small>
                  <small>Video ID: <?= htmlspecialchars($video['video_id']) ?></small>
                </div>
                <div class="video-actions">
                  <form action="remove_video_from_playlist.php" method="POST" style="display:inline;">
                    <input type="hidden" name="playlist_video_id" value="<?= $video['item_id'] ?>">
                    <input type="hidden" name="playlist_id" value="<?= $playlist_id ?>">
                    <button type="submit" class="btn btn-danger btn-small btn-remove-from-playlist" onclick="return confirm('Are you sure you want to remove this video?');">Remove</button>
                  </form>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p>This playlist currently has no videos.</p>
        <?php endif; ?>

        <hr style="margin-top:30px; margin-bottom:30px;">

        <h3>Search and Add YouTube Videos</h3>
        <?php if (!$client->getAccessToken() && !empty($CLIENT_ID) && !empty($CLIENT_SECRET)): ?>
          <?php
          // Construct auth URL with state containing playlist_id
          $authUrlState = 'playlist_id=' . urlencode($playlist_id);
          $authUrl = $client->createAuthUrl() . '&state=' . urlencode($authUrlState);
          ?>
          <p>To search and add videos, you need to connect your YouTube account.</p>
          <a href="<?= htmlspecialchars($authUrl) ?>" class="auth-link">Connect to YouTube</a>
        <?php elseif ($client->getAccessToken()): ?>
          <p style="color: green;">Authenticated with YouTube.
            <a href="view-playlist-details.php?id=<?= urlencode($playlist_id) ?>&yt_logout=1" class="btn-youtube-action" style="background-color:#aaa; font-size:0.8em; padding: 5px 10px;">Logout from YouTube</a>
          </p>

          <form method="POST" action="view-playlist-details.php?id=<?= htmlspecialchars($playlist_id) ?>" class="youtube-search-form">
            <input type="text" name="youtube_search_query" placeholder="Search YouTube (e.g., 'PHP tutorials')"
              value="<?= isset($_POST['youtube_search_query']) ? htmlspecialchars($_POST['youtube_search_query']) : '' ?>">
            <button type="submit">Search YouTube</button>
          </form>

          <?php if (!empty($search_results)): ?>
            <h4>Search Results:</h4>
            <ul class="search-results-list">
              <?php foreach ($search_results as $video): ?>
                <li>
                  <img src="<?= htmlspecialchars($video['thumbnail']) ?>" alt="Thumbnail for <?= htmlspecialchars($video['title']) ?>">
                  <div class="video-info">
                    <strong><?= htmlspecialchars($video['title']) ?></strong>
                    <small>Channel: <?= htmlspecialchars($video['channelTitle']) ?></small>
                    <small>Video ID: <?= htmlspecialchars($video['id']) ?></small>
                    <a href="https://www.youtube.com/watch?v=<?= htmlspecialchars($video['id']) ?>" target="_blank" style="font-size:0.9em;">Watch on YouTube</a>
                  </div>
                  <div class="video-actions">
                    <form action="add_video_to_playlist.php" method="POST">
                      <input type="hidden" name="playlist_id" value="<?= htmlspecialchars($playlist_id) ?>">
                      <input type="hidden" name="video_id" value="<?= htmlspecialchars($video['id']) ?>">
                      <input type="hidden" name="video_title" value="<?= htmlspecialchars($video['title']) ?>">
                      <input type="hidden" name="video_thumbnail" value="<?= htmlspecialchars($video['thumbnail']) ?>">
                      <input type="hidden" name="video_channel_title" value="<?= htmlspecialchars($video['channelTitle']) ?>">
                      <button type="submit" class="btn btn-primary btn-small btn-add-to-playlist">Add to Playlist</button>
                    </form>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        <?php elseif (empty($CLIENT_ID) || empty($CLIENT_SECRET)): ?>
          <p>YouTube client ID or secret not configured. Video search is disabled.</p>
        <?php endif; ?>

      <?php elseif (empty($page_messages)): // Only show "back to playlists" if no major error messages were already shown for playlist loading 
      ?>
        <p><a href="view-playlists.php" class="btn">Back to My Playlists</a></p>
      <?php endif; ?>
      <p style="margin-top: 30px;"><a href="view-playlists.php" class="btn">Back to All Playlists</a></p>
    </div>
  </main>

  <footer>
    <p>&copy; <?= date("Y") ?> YouTube Playlist App</p>
  </footer>
</body>

</html>