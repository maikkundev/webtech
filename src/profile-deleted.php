<?php
// This is a simple confirmation page, no session check needed
?>
<!DOCTYPE html>
<html lang="el">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="assets/css/style.css">
  <title> Profile deleted</title>
</head>

<body>
  <header class="navbar">
    <div class="logo">
      <a href="index.html"><img src="assets/images/looplogo.png" alt="Loop Logo" /></a>
    </div>
    <button class="menu-toggle" aria-label="Μενού">&#9776;</button>

    <nav class="menu">
      <div class="search-toggle">
        <span class="search-icon">🔍</span>
        <input type="text" class="search-input" placeholder="Αναζήτησε τραγούδι..." />
      </div>
      <a href="index.html">Αρχική</a>
      <a href="help.html">Βοήθεια</a>
      <a href="about.html">Σχετικά</a>
      <a href="auth.php">Σύνδεση / Εγγραφή</a>
      <button class="theme-toggle">🌓 Θέμα</button>
    </nav>
  </header>
  <h1>Το προφίλ διαγράφηκε</h1>

  <div class="message">
    <p>Το προφίλ έχει διαγραφεί όπως και όλα τα σχετικά δεδομένα</p>
    <p>Ευχαριστούμε που χρησιμοποιήσατε τις υπηρεσίες μας.</p>
  </div>

  <a href="auth.php" class="btn">Επιστροφή στη σύνδεση</a>
</body>

</html>