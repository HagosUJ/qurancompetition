<?php
// Ensure session is started to access user details
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$user_fullname_sidebar = $_SESSION['user_fullname'] ?? 'Participant';
$user_role_sidebar = $_SESSION['user_role'] ?? 'user';
// Placeholder for profile picture - fetch from DB or use default
$user_avatar_sidebar = $_SESSION['user_avatar'] ?? 'assets/images/users/avatar-default.png';

// Default language if not set
if (!isset($_SESSION['language'])) {
    $_SESSION['language'] = 'en';
    $_SESSION['lang'] = 'en';
}
$current_lang = $_SESSION['language'] ?? 'en';

// Log sidebar language for debugging
error_log("Sidebar loaded with language: $current_lang");
?>

<!-- ========== Left Sidebar Start ========== -->
<div class="leftside-menu">
  <!-- Brand Logo Light -->
  <a href="index.php" class="logo logo-light">
    <span class="logo-lg" style="text-align: start;">
      <img src="assets/images/logo.png" alt="Musabaqa Logo" style="height: 40px;">
    </span>
    <span class="logo-sm">
      <img src="assets/images/logo.png" alt="Musabaqa Logo" style="height: 30px;" />
    </span>
  </a>

  <!-- Brand Logo Dark -->
  <a href="index.php" class="logo logo-dark">
    <span class="logo-lg" style="text-align: start;">
      <img src="assets/images/musabaqa-logo-dark.png" alt="Musabaqa Logo" style="height: 40px;">
    </span>
    <span class="logo-sm">
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
      <a href="profile.php">
        <img src="<?php echo htmlspecialchars($user_avatar_sidebar); ?>" alt="user-image" height="42" width="42" class="rounded-circle shadow-sm" />
        <span class="leftbar-user-name mt-2"><?php echo htmlspecialchars($user_fullname_sidebar); ?></span>
      </a>
    </div>

    <!--- Sidemenu -->
    <ul class="side-nav">
      <li class="side-nav-title sidebar-lang">
        <span class="lang-en <?php echo $current_lang === 'ar' ? 'hidden' : ''; ?>">Navigation</span>
        <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>">التنقل</span>
      </li>

      <li class="side-nav-item">
        <a href="index.php" class="side-nav-link">
          <i class="ri-dashboard-line"></i>
          <span class="sidebar-lang lang-en <?php echo $current_lang === 'ar' ? 'hidden' : ''; ?>">Dashboard</span>
          <span class="sidebar-lang lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>">لوحة التحكم</span>
        </a>
      </li>

      <li class="side-nav-item">
        <a href="application.php" class="side-nav-link">
          <i class="ri-file-list-3-line"></i>
          <span class="sidebar-lang lang-en <?php echo $current_lang === 'ar' ? 'hidden' : ''; ?>">Application</span>
          <span class="sidebar-lang lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>">الطلب</span>
        </a>
      </li>

      <li class="side-nav-item">
        <a href="schedule.php" class="side-nav-link">
          <i class="ri-calendar-2-line"></i>
          <span class="sidebar-lang lang-en <?php echo $current_lang === 'ar' ? 'hidden' : ''; ?>">Schedule</span>
          <span class="sidebar-lang lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>">الجدول</span>
        </a>
      </li>
      <li class="side-nav-item">
        <a href="notifications.php" class="side-nav-link">
          <i class="ri-notification-3-line"></i>
          <span class="sidebar-lang lang-en <?php echo $current_lang === 'ar' ? 'hidden' : ''; ?>">Notifications</span>
          <span class="sidebar-lang lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>">الإشعارات</span>
        </a>
      </li>

      <li class="side-nav-title sidebar-lang mt-2">
        <span class="lang-en <?php echo $current_lang === 'ar' ? 'hidden' : ''; ?>">Account</span>
        <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>">الحساب</span>
      </li>

      <li class="side-nav-item">
        <a href="profile.php" class="side-nav-link">
          <i class="ri-user-settings-line"></i>
          <span class="sidebar-lang lang-en <?php echo $current_lang === 'ar' ? 'hidden' : ''; ?>">My Profile</span>
          <span class="sidebar-lang lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>">ملفي الشخصي</span>
        </a>
      </li>

      <li class="side-nav-item">
        <a href="logout.php" class="side-nav-link">
          <i class="ri-logout-box-line"></i>
          <span class="sidebar-lang lang-en <?php echo $current_lang === 'ar' ? 'hidden' : ''; ?>">Logout</span>
          <span class="sidebar-lang lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>">تسجيل الخروج</span>
        </a>
      </li>

      <!-- Admin Section (Conditional) -->
      <?php if ($user_role_sidebar === 'admin' || $user_role_sidebar === 'reviewer'): ?>
        <li class="side-nav-title sidebar-lang mt-2">
          <span class="lang-en <?php echo $current_lang === 'ar' ? 'hidden' : ''; ?>">Admin Area</span>
          <span class="lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>">منطقة الإدارة</span>
        </li>
        <li class="side-nav-item">
          <a href="admin/review-applications.php" class="side-nav-link">
            <i class="ri-file-search-line"></i>
            <span class="sidebar-lang lang-en <?php echo $current_lang === 'ar' ? 'hidden' : ''; ?>">Review Apps</span>
            <span class="sidebar-lang lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>">مراجعة الطلبات</span>
          </a>
        </li>
        <li class="side-nav-item">
          <a href="admin/manage-users.php" class="side-nav-link">
            <i class="ri-group-line"></i>
            <span class="sidebar-lang lang-en <?php echo $current_lang === 'ar' ? 'hidden' : ''; ?>">Manage Users</span>
            <span class="sidebar-lang lang-ar <?php echo $current_lang === 'en' ? 'hidden' : ''; ?>">إدارة المستخدمين</span>
          </a>
        </li>
      <?php endif; ?>
    </ul>
    <!--- End Sidemenu -->

    <div class="clearfix"></div>
  </div>
</div>
<!-- ========== Left Sidebar End ========== -->

<!-- Sidebar Translation Styles -->
<style>
  .sidebar-lang.hidden {
    display: none !important;
  }
  [dir="rtl"] .leftside-menu {
    font-family: 'Amiri', serif;
    text-align: right;
  }
  [dir="rtl"] .side-nav-link {
    flex-direction: row-reverse;
  }
  [dir="rtl"] .side-nav-link i {
    margin-left: 10px;
    margin-right: 0;
  }
  [dir="rtl"] .leftbar-user {
    text-align: right;
  }
  [dir="rtl"] .logo-lg,
  [dir="rtl"] .logo-sm {
    text-align: right !important;
  }
</style>
<?php
// Log sidebar render completion
error_log("Sidebar rendered with language: $current_lang");
?>