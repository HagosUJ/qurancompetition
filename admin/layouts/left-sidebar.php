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
  <div
    class="button-sm-hover"
    data-bs-toggle="tooltip"
    data-bs-placement="right"
    title="Show Full Sidebar"
  >
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
        <img
          src="assets/images/users/avatar-1.jpg"
          alt="user-image"
          height="42"
          class="rounded-circle shadow-sm"
        />
        <span class="leftbar-user-name mt-2"><?php echo isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : 'Admin User'; ?></span>
      </a>
    </div>

    <!--- Sidemenu -->
    <ul class="side-nav">
      <li class="side-nav-title">Navigation</li>

      <li class="side-nav-item">
        <a href="index.php" class="side-nav-link">
          <i class="ri-dashboard-line"></i>
          <span> Dashboard </span>
        </a>
      </li>

      <li class="side-nav-item">
        <a
          data-bs-toggle="collapse"
          href="#sidebarUserManagement"
          aria-expanded="false"
          aria-controls="sidebarUserManagement"
          class="side-nav-link"
        >
          <i class="ri-user-line"></i>
          <span> User Management </span>
          <span class="menu-arrow"></span>
        </a>
        <div class="collapse" id="sidebarUserManagement">
          <ul class="side-nav-second-level">
            <li>
              <a href="users.php">All Users</a>
            </li>
            <li>
              <a href="user-add.php">Add New User</a>
            </li>
            <li>
              <a href="user-groups.php">User Groups/Roles</a>
            </li>
            <li>
              <a href="user-search.php">User Search</a>
            </li>
          </ul>
        </div>
      </li>

      <li class="side-nav-item">
        <a
          data-bs-toggle="collapse"
          href="#sidebarProfileManagement"
          aria-expanded="false"
          aria-controls="sidebarProfileManagement"
          class="side-nav-link"
        >
          <i class="ri-profile-line"></i>
          <span> Profile Management </span>
          <span class="menu-arrow"></span>
        </a>
        <div class="collapse" id="sidebarProfileManagement">
          <ul class="side-nav-second-level">
            <li>
              <a href="profiles-complete.php">Complete Profiles</a>
            </li>
            <li>
              <a href="profiles-incomplete.php">Incomplete Profiles</a>
            </li>
            <li>
              <a href="profiles-approval.php">Profile Approval</a>
            </li>
            <li>
              <a href="profiles-fields.php">Profile Fields Configuration</a>
            </li>
          </ul>
        </div>
      </li>

      <li class="side-nav-item">
        <a
          data-bs-toggle="collapse"
          href="#sidebarFinancialManagement"
          aria-expanded="false"
          aria-controls="sidebarFinancialManagement"
          class="side-nav-link"
        >
          <i class="ri-money-dollar-circle-line"></i>
          <span> Financial Management </span>
          <span class="menu-arrow"></span>
        </a>
        <div class="collapse" id="sidebarFinancialManagement">
          <ul class="side-nav-second-level">
            <li>
              <a href="payments.php">Payments Overview</a>
            </li>
            <li>
              <a href="transactions.php">Transaction History</a>
            </li>
            <li>
              <a href="payment-settings.php">Payment Settings</a>
            </li>
            <li>
              <a href="financial-reports.php">Financial Reports</a>
            </li>
          </ul>
        </div>
      </li>

      <li class="side-nav-item">
        <a
          data-bs-toggle="collapse"
          href="#sidebarRegistrationManagement"
          aria-expanded="false"
          aria-controls="sidebarRegistrationManagement"
          class="side-nav-link"
        >
          <i class="ri-user-add-line"></i>
          <span> Registration Management </span>
          <span class="menu-arrow"></span>
        </a>
        <div class="collapse" id="sidebarRegistrationManagement">
          <ul class="side-nav-second-level">
            <li>
              <a href="registrations-pending.php">Pending Registrations</a>
            </li>
            <li>
              <a href="registrations-approved.php">Approved Registrations</a>
            </li>
            <li>
              <a href="registrations-rejected.php">Rejected Registrations</a>
            </li>
            <li>
              <a href="registration-settings.php">Registration Settings</a>
            </li>
          </ul>
        </div>
      </li>

      <li class="side-nav-title">Reports & Tools</li>

      <li class="side-nav-item">
        <a
          data-bs-toggle="collapse"
          href="#sidebarReports"
          aria-expanded="false"
          aria-controls="sidebarReports"
          class="side-nav-link"
        >
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
        <a
          data-bs-toggle="collapse"
          href="#sidebarCommunication"
          aria-expanded="false"
          aria-controls="sidebarCommunication"
          class="side-nav-link"
        >
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
        <a
          data-bs-toggle="collapse"
          href="#sidebarSystemAdmin"
          aria-expanded="false"
          aria-controls="sidebarSystemAdmin"
          class="side-nav-link"
        >
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
        <a href="logout.php" class="side-nav-link">
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