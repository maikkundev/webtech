<?php
// src/youtube-test-search.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$page_messages = []; // Initialize $page_messages array earlier

// Corrected path for vendor/autoload.php
require_once __DIR__ . '/../vendor/autoload.php';
session_start();

// Load .env file variables
// Corrected path for .env file and Dotenv initialization
if (file_exists(__DIR__ . '/.env')) {
  $dotenv = Dotenv\Dotenv::createImmutable(__DIR__); // .env is in the same directory as the script in the container
  $dotenv->load();
} else {
  // Fallback or error if .env is missing
  $page_messages[] = 'Error: .env file not found in /var/www/html/. Please ensure it exists.';
}

// -----------------------------------------------------------------------------
// CLIENT_ID and CLIENT_SECRET should now be loaded from your .env file
// -----------------------------------------------------------------------------
$CLIENT_ID = $_ENV['CLIENT_ID'] ?? null;
$CLIENT_SECRET = $_ENV['CLIENT_SECRET'] ?? null;

// This must be an authorized redirect URI for your client ID in Google Cloud Console
$REDIRECT_URI = 'http://localhost:8181/youtube-test-search.php'; // Adjust if your server/port is different
// -----------------------------------------------------------------------------

// Check if credentials are loaded
if (empty($CLIENT_ID) || empty($CLIENT_SECRET)) {
  $page_messages[] = 'Error: CLIENT_ID or CLIENT_SECRET is not set. Please check your .env file.';
  // Optionally, prevent further execution if credentials are vital
}

$client = new Google_Client();
$client->setClientId($CLIENT_ID);
$client->setClientSecret($CLIENT_SECRET);
$client->setRedirectUri($REDIRECT_URI);
$client->setScopes([
  'https://www.googleapis.com/auth/youtube.readonly'
]);
$client->setAccessType('offline'); // To get a refresh token
$client->setPrompt('select_account consent');

$search_results = [];

// Handle OAuth 2.0 callback
if (isset($_GET['code'])) {
  try {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    if (isset($token['error'])) {
      $page_messages[] = 'Error fetching access token: ' . htmlspecialchars($token['error_description'] ?? $token['error']);
    } else {
      $client->setAccessToken($token);
      $_SESSION['youtube_access_token'] = $token;
      // Redirect to self to remove code from URL and prevent re-processing
      header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
      exit;
    }
  } catch (Exception $e) {
    $page_messages[] = 'Exception during token fetch: ' . htmlspecialchars($e->getMessage());
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
        $page_messages[] = 'Error refreshing token: ' . htmlspecialchars($e->getMessage());
        unset($_SESSION['youtube_access_token']); // Clear invalid token
      }
    } else {
      $page_messages[] = 'Access token expired, and no refresh token available. Please re-authenticate.';
      unset($_SESSION['youtube_access_token']);
    }
  }
} else {
  // If no token and no auth code, user needs to authenticate
  if (!isset($_GET['code'])) { // Avoid showing link if we just tried to fetch token and failed
    $page_messages[] = 'Not authenticated with YouTube.';
  }
}

// Handle search form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_query'])) {
  if ($client->getAccessToken()) {
    try {
      $youtube = new \Google\Service\YouTube($client); // Use FQCN
      $search_query = trim($_POST['search_query']);
      if (!empty($search_query)) {
        $searchResponse = $youtube->search->listSearch('id,snippet', [
          'q' => $search_query,
          'maxResults' => 5,
          'type' => 'video'
        ]);
        foreach ($searchResponse->getItems() as $item) {
          $search_results[] = [
            'id' => $item->id->videoId,
            'title' => $item->snippet->title,
            'thumbnail' => $item->snippet->thumbnails->default->url
          ];
        }
        if (empty($search_results)) {
          $page_messages[] = 'No results found for "' . htmlspecialchars($search_query) . '".';
        }
      } else {
        $page_messages[] = 'Search query cannot be empty.';
      }
    } catch (Google_Service_Exception $e) {
      $page_messages[] = 'Google Service API Error: ' . htmlspecialchars($e->getMessage());
      // If auth error, token might be invalid
      if ($e->getCode() == 401) {
        unset($_SESSION['youtube_access_token']);
        $page_messages[] = 'Authentication error. Please try re-authenticating.';
      }
    } catch (Exception $e) {
      $page_messages[] = 'An unexpected error occurred: ' . htmlspecialchars($e->getMessage());
    }
  } else {
    $page_messages[] = 'Cannot search. Not authenticated with YouTube.';
  }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>YouTube API Search Test</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    body {
      padding: 20px;
      font-family: sans-serif;
    }

    .container {
      max-width: 800px;
      margin: auto;
      background: #f9f9f9;
      padding: 20px;
      border-radius: 8px;
    }

    .messages div {
      padding: 10px;
      margin-bottom: 10px;
      border-radius: 4px;
    }

    .messages .info {
      background-color: #e7f3fe;
      border-left: 6px solid #2196F3;
    }

    .messages .error {
      background-color: #ffebee;
      border-left: 6px solid #f44336;
    }

    .auth-link,
    button {
      padding: 10px 15px;
      background-color: #4CAF50;
      color: white;
      text-decoration: none;
      border: none;
      border-radius: 4px;
      cursor: pointer;
    }

    .auth-link:hover,
    button:hover {
      background-color: #45a049;
    }

    .search-form input[type="text"] {
      padding: 10px;
      width: 70%;
      margin-right: 10px;
      border: 1px solid #ddd;
      border-radius: 4px;
    }

    .results ul {
      list-style-type: none;
      padding: 0;
    }

    .results li {
      display: flex;
      align-items: center;
      margin-bottom: 10px;
      padding: 10px;
      background: #fff;
      border: 1px solid #eee;
      border-radius: 4px;
    }

    .results img {
      margin-right: 10px;
      border-radius: 4px;
    }

    .logout-link {
      color: #f44336;
      margin-left: 15px;
    }
  </style>
</head>

<body>
  <div class="container">
    <h1>YouTube API Search Test</h1>

    <div class="messages">
      <?php foreach ($page_messages as $msg): ?>
        <div class="<?= (strpos(strtolower($msg), 'error') !== false || strpos(strtolower($msg), 'failed') !== false) ? 'error' : 'info' ?>"><?= $msg ?></div>
      <?php endforeach; ?>
    </div>

    <?php if (!$client->getAccessToken()): ?>
      <p><a href="<?= htmlspecialchars($client->createAuthUrl()) ?>" class="auth-link">Connect to YouTube</a></p>
    <?php else: ?>
      <p style="color: green;">Successfully authenticated with YouTube!
        <a href="?logout=1" class="logout-link">Logout from YouTube Test</a>
      </p>
      <?php
      if (isset($_GET['logout'])) {
        unset($_SESSION['youtube_access_token']);
        header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
        exit;
      }
      ?>

      <form method="POST" action="youtube-test-search.php" class="search-form">
          <label>
              <input type="text" name="search_query" placeholder="Enter search query (e.g., 'PHP tutorials')"
                value="<?= isset($_POST['search_query']) ? htmlspecialchars($_POST['search_query']) : '' ?>">
          </label>
          <button type="submit">Search</button>
      </form>

      <?php if (!empty($search_results)): ?>
        <h2>Search Results:</h2>
        <div class="results">
          <ul>
            <?php foreach ($search_results as $video): ?>
              <li>
                <img src="<?= htmlspecialchars($video['thumbnail']) ?>" alt="Thumbnail">
                <div>
                  <strong><?= htmlspecialchars($video['title']) ?></strong><br>
                  <small>ID: <?= htmlspecialchars($video['id']) ?></small><br>
                  <a href="https://www.youtube.com/watch?v=<?= htmlspecialchars($video['id']) ?>" target="_blank">Watch on YouTube</a>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</body>

</html>