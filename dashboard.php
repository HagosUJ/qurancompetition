<?php
require_once 'includes/auth.php';

// Check if user is logged in
require_login();
?>
<!DOCTYPE html>
<html class="h-full" data-theme="true" data-theme-mode="light" dir="ltr" lang="en">
 <head>
  <title>Majlisu Ahlil Qur'an - Dashboard</title>
  <!-- Include your CSS and other head content -->
  <link href="assets/css/styles.css" rel="stylesheet"/>
 </head>
 <body>
  <div class="container mx-auto p-8">
    <div class="card p-6">
      <h1 class="text-2xl font-bold mb-4">Welcome, <?php echo htmlspecialchars($_SESSION['fullname']); ?>!</h1>
      <p class="mb-4">You have successfully logged in.</p>
      
      <div class="flex items-center justify-between">
        <div>
          <p><strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['email']); ?></p>
          <p><strong>Role:</strong> <?php echo htmlspecialchars($_SESSION['role']); ?></p>
        </div>
        <a href="logout.php" class="btn btn-primary">Logout</a>
      </div>
    </div>
  </div>
 </body>
</html>