<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/layouts/head-css.php
// Ensure session is started (should already be started in index.php via auth.php)
$language = $_SESSION['language'] ?? 'en'; // Default to English
$css_file = ($language === 'ar') ? 'app-rtl.min.css' : 'app.min.css';
?>

<!-- Theme Config Js -->
<script src="assets/js/config.js"></script>

<!-- App css -->
<link href="assets/css/<?php echo htmlspecialchars($css_file); ?>" rel="stylesheet" type="text/css" id="app-style" />

<!-- Icons css -->
<link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />