<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/admin/layouts/left-sidebar.php
?>
<!-- ========== Left Sidebar Start ========== -->
<div class="leftside-menu">
  <!-- Brand Logo Light -->
  <a href="index.php" class="logo logo-light">
    <span class="logo-lg">
      <img src="assets/images/logo.png" alt="logo" />
    </span>
    <span class="logo-sm">
      <img src="assets/images/logo-sm.png" alt="small logo" />
    </span>
  </a>

  <!-- Brand Logo Dark -->
  <a href="index.php" class="logo logo-dark">
    <span class="logo-lg">
      <img src="assets/images/logo-dark.png" alt="dark logo" />
    </span>
    <span class="logo-sm">
      <img src="assets/images/logo-sm.png" alt="small logo" />
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
      <a href="admin-profile.php">
        <img src="assets/images/users/avatar-1.jpg" alt="user-image" height="42" class="rounded-circle shadow-sm" />
        <span
          class="leftbar-user-name mt-2"><?php echo isset($_SESSION['admin_username']) ? htmlspecialchars($_SESSION['admin_username']) : 'Admin User'; ?></span>
      </a>
    </div>

    <!--- Sidemenu -->
    <ul class="side-nav">
      <li class="side-nav-title">Navigation</li>

      <li class="side-nav-item">
        <a href="index.php" class="side-nav-link">
          <i class="ri-dashboard-line"></i>
          <span> Home </span>
        </a>
      </li>

      <li class="side-nav-item">
        <a href="users.php" class="side-nav-link">
          <i class="ri-user-line"></i>
          <span> All Users </span>
        </a>
      </li>

      <li class="side-nav-item">
        <a data-bs-toggle="collapse" href="#sidebarApplications" aria-expanded="false"
          aria-controls="sidebarApplications" class="side-nav-link">
          <i class="ri-file-list-3-line"></i> 
          <span> Applications </span>
          <span class="menu-arrow"></span>
        </a>
        <div class="collapse" id="sidebarApplications">
          <ul class="side-nav-second-level">
            <li>
              <a href="manage_applications.php">All Applications</a>
            </li>
            <li>
              <a href="manage_applications.php?status=Submitted">Submitted Applications</a>
            </li>
            <li>
              <a href="manage_applications.php?status=Not Started">Pending Completion</a>
            </li>
            <li>
              <a href="manage_applications.php?status=Approved">Approved Applications</a>
            </li>
            <li>
              <a href="manage_applications.php?status=Rejected">Rejected Applications</a>
            </li>
          </ul>
        </div>
      </li>

      <li class="side-nav-title">Reports & Tools</li>

      <li class="side-nav-item">
        <a data-bs-toggle="collapse" href="#sidebarReports" aria-expanded="false" aria-controls="sidebarReports"
          class="side-nav-link">
          <i class="ri-bar-chart-box-line"></i>
          <span> Reports & Analytics </span>
          <span class="menu-arrow"></span>
        </a>
        <div class="collapse" id="sidebarReports">
          <ul class="side-nav-second-level">
            <li>
              <a href="reports-user.php">User Reports</a>
            </li>
            <li>
              <a href="reports-financial.php">Financial Reports</a>
            </li>
            <li>
              <a href="reports-activity.php">Activity Reports</a>
            </li>
            <li>
              <a href="reports-custom.php">Custom Reports</a>
            </li>
            <li>
              <a href="data-export.php">Data Export</a>
            </li>
          </ul>
        </div>
      </li>

      <li class="side-nav-item">
        <a data-bs-toggle="collapse" href="#sidebarCommunication" aria-expanded="false"
          aria-controls="sidebarCommunication" class="side-nav-link">
          <i class="ri-mail-send-line"></i>
          <span> Communications </span>
          <span class="menu-arrow"></span>
        </a>
        <div class="collapse" id="sidebarCommunication">
          <ul class="side-nav-second-level">
            <li>
              <a href="email-templates.php">Email Templates</a>
            </li>
            <li>
              <a href="send-notifications.php">Send Notifications</a>
            </li>
            <li>
              <a href="email-history.php">Email History</a>
            </li>
            <li>
              <a href="messaging.php">SMS/Messaging</a>
            </li>
          </ul>
        </div>
      </li>

      <li class="side-nav-title">Administration</li>

      <li class="side-nav-item">
        <a data-bs-toggle="collapse" href="#sidebarSystemAdmin" aria-expanded="false" aria-controls="sidebarSystemAdmin"
          class="side-nav-link">
          <i class="ri-settings-2-line"></i>
          <span> System Administration </span>
          <span class="menu-arrow"></span>
        </a>
        <div class="collapse" id="sidebarSystemAdmin">
          <ul class="side-nav-second-level">
            <li>
              <a href="admin-logs.php">Admin Logs</a>
            </li>
            <li>
              <a href="admin-accounts.php">Admin Accounts</a>
            </li>
            <li>
              <a href="system-settings.php">System Settings</a>
            </li>
            <li>
              <a href="backup-restore.php">Backup/Restore</a>
            </li>
          </ul>
        </div>
      </li>

      <li class="side-nav-item">
        <a href="admin-profile.php" class="side-nav-link">
          <i class="ri-user-settings-line"></i>
          <span> My Profile </span>
        </a>
      </li>

      <li class="side-nav-item">
        <a href="help-support.php" class="side-nav-link">
          <i class="ri-question-line"></i>
          <span> Help & Support </span>
        </a>
      </li>

      <li class="side-nav-item">
        <a href="index.php?state=logout" class="side-nav-link">
          <i class="ri-logout-box-line"></i>
          <span> Logout </span>
        </a>
      </li>
    </ul>
    <!--- End Sidemenu -->

    <div class="clearfix"></div>
  </div>
</div>
<!-- ========== Left Sidebar End ========== -->