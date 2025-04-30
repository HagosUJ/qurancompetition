<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/password-changed.php
require_once 'includes/auth.php';

// Check if user was redirected here properly
if (!isset($_GET['success']) || $_GET['success'] !== '1') {
    redirect('sign-in.php');
}
?>
<!DOCTYPE html>
<html class="h-full" data-theme="true" data-theme-mode="light" dir="ltr" lang="en">
<head>
  <title>Majlisu Ahlil Qur'an - Password Changed</title>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport"/>
  <meta content="Password successfully changed for Majlisu Ahlil Qur'an International" name="description"/>
  <link href="assets/media/app/favicon.ico" rel="shortcut icon"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link href="assets/css/styles.css" rel="stylesheet"/>
</head>
<body class="antialiased flex h-full text-base text-gray-700 dark:bg-coal-500">
  <!-- Theme Mode -->
  <script>
   const defaultThemeMode = 'light';
   // Rest of your theme mode script
  </script>
  
  <!-- Page -->
  <style>
   .page-bg {
     background-image: url('assets/media/images/2600x1200/bg-10.png');
   }
   .dark .page-bg {
     background-image: url('assets/media/images/2600x1200/bg-10-dark.png');
   }
  </style>
  
  <div class="flex items-center justify-center grow bg-center bg-no-repeat page-bg">
   <div class="card max-w-[500px] w-full">
    <div class="card-body p-10 text-center">
      <div class="mb-6">
        <i class="ki-duotone ki-shield-tick fs-5x text-success">
          <span class="path1"></span>
          <span class="path2"></span>
        </i>
      </div>
      
      <h2 class="text-2xl font-bold mb-4">Password Changed!</h2>
      <p class="mb-6">Your password has been changed successfully. You can now sign in with your new password.</p>
      
      <div class="text-center">
        <a href="sign-in.php" class="btn btn-primary">Sign In</a>
      </div>
    </div>
   </div>
  </div>
  
  <!-- Scripts -->
  <script src="assets/js/core.bundle.js"></script>
</body>
</html>