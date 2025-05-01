<?php
// Ensure session is started to access user details
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$user_fullname_sidebar = $_SESSION['user_fullname'] ?? 'Participant';
$user_role_sidebar = $_SESSION['user_role'] ?? 'user';
// Placeholder for profile picture - fetch from DB or use default
$user_avatar_sidebar = $_SESSION['user_avatar'] ?? 'assets/images/users/avatar-default.png'; // Example default
?>
<!-- ========== Left Sidebar Start ========== -->
<div class="leftside-menu">
  <!-- Brand Logo Light -->
  <a href="index.php" class="logo logo-light">
    <span class="logo-lg" style="text-align: start;">
      <!-- TODO: Replace with Musabaqa Logo -->
      <img src="assets/images/musabaqa-logo-light.png" alt="Musabaqa Logo" style="height: 40px;">
    </span>
    <span class="logo-sm">
      <!-- TODO: Replace with Musabaqa Small Logo -->
      <img src="assets/images/musabaqa-logo-sm-light.png" alt="Musabaqa Logo" style="height: 30px;" />
    </span>
  </a>

  <!-- Brand Logo Dark -->
  <a href="index.php" class="logo logo-dark">
    <span class="logo-lg" style="text-align: start;">
      <!-- TODO: Replace with Musabaqa Logo -->
      <img src="assets/images/musabaqa-logo-dark.png" alt="Musabaqa Logo" style="height: 40px;">
    </span>
    <span class="logo-sm">
      <!-- TODO: Replace with Musabaqa Small Logo -->
      <img src="assets/images/musabaqa-logo-sm-dark.png" alt="Musabaqa Logo" style="height: 30px;" />
    </span>
  </a>

  <!-- Sidebar Hover Menu Toggle Button -->
  <div class="button-sm-hover" data-bs-toggle="tooltip" data-bs-placement="right" title="Show Full Sidebar">
    <i class="ri-checkbox-blank-circle-line align-middle"></i>
  </div>

  <!-- Full Sidebar Menu Close Button -->
  <div class="button-close-fullsidebar">
    <i class="ri-close-fill align-middle"></i>
  </div>

  <!-- Sidebar -left -->
  <div class="h-100" id="leftside-menu-container" data-simplebar>
    <!-- Leftbar User -->
    <div class="leftbar-user">
      <a href="profile.php"> <!-- Link to profile page -->
        <img src="<?php echo htmlspecialchars($user_avatar_sidebar); ?>" alt="user-image" height="42" width="42" class="rounded-circle shadow-sm" />
        <span class="leftbar-user-name mt-2"><?php echo htmlspecialchars($user_fullname_sidebar); ?></span>
      </a>
    </div>

    <!--- Sidemenu -->
    <ul class="side-nav">
      <li class="side-nav-title">Navigation</li>

      <li class="side-nav-item">
        <a href="index.php" class="side-nav-link">
          <i class="ri-dashboard-line"></i> <!-- Changed icon -->
          <span> Dashboard </span>
        </a>
      </li>

      <li class="side-nav-item">
        <a href="application.php" class="side-nav-link"> <!-- Link to main application page or first step -->
          <i class="ri-file-list-3-line"></i> <!-- Changed icon -->
          <span> Application </span>
        </a>
      </li>
<!-- 
      <li class="side-nav-item">
        <a href="documents.php" class="side-nav-link">
          <i class="ri-folder-upload-line"></i> 
          <span> Documents </span>
        </a>
      </li> -->

       <li class="side-nav-item">
        <a href="schedule.php" class="side-nav-link">
          <i class="ri-calendar-2-line"></i> <!-- Changed icon -->
          <span> Schedule </span>
        </a>
      </li>
<!-- 
       <li class="side-nav-item">
        <a href="messages.php" class="side-nav-link"> 
          <i class="ri-message-2-line"></i>
          <span> Messages </span>
      
        </a>
      </li> -->

       <!-- <li class="side-nav-item">
        <a href="resources.php" class="side-nav-link">
          <i class="ri-book-open-line"></i> 
          <span> Resources </span>
        </a>
      </li> -->

      <!-- Optional Sections (Uncomment as needed) -->
      <!--
      <li class="side-nav-item">
        <a href="performance.php" class="side-nav-link">
          <i class="ri-line-chart-line"></i>
          <span> Performance </span>
        </a>
      </li>

      <li class="side-nav-item">
        <a href="travel.php" class="side-nav-link">
          <i class="ri-plane-line"></i>
          <span> Travel & Accomm. </span>
        </a>
      </li>
      -->

      <li class="side-nav-title mt-2">Account</li>

      <li class="side-nav-item">
        <a href="profile.php" class="side-nav-link">
          <i class="ri-user-settings-line"></i> <!-- Changed icon -->
          <span> My Profile </span>
        </a>
      </li>

      <li class="side-nav-item">
        <a href="logout.php" class="side-nav-link">
          <i class="ri-logout-box-line"></i> <!-- Kept icon -->
          <span> Logout </span>
        </a>
      </li>

      <!-- Admin Section (Conditional) -->
      <?php if ($user_role_sidebar === 'admin' || $user_role_sidebar === 'reviewer'): ?>
        <li class="side-nav-title mt-2">Admin Area</li>
        <li class="side-nav-item">
          <a href="admin/review-applications.php" class="side-nav-link">
            <i class="ri-file-search-line"></i>
            <span> Review Apps </span>
          </a>
        </li>
         <li class="side-nav-item">
          <a href="admin/manage-users.php" class="side-nav-link">
            <i class="ri-group-line"></i>
            <span> Manage Users </span>
          </a>
        </li>
        <!-- Add more admin links as needed -->
      <?php endif; ?>

    </ul>
    <!--- End Sidemenu -->

    <div class="clearfix"></div>
  </div>
</div>
<!-- ========== Left Sidebar End ========== -->