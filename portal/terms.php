<?php
// filepath: /Applications/XAMPP/xamppfiles/htdocs/musabaqa/terms.php
require_once 'includes/config.php'; // Include basic config if needed for paths or APP_NAME

// Optional: Include auth.php if you need session/user context, but likely not needed for a simple terms page
// require_once 'includes/auth.php';

// Set security headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
// Adjust CSP if necessary based on content
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; style-src 'self' https://fonts.googleapis.com 'unsafe-inline'; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");

?>
<!DOCTYPE html>
<html class="h-full" data-theme="true" data-theme-mode="light" dir="ltr" lang="en">
 <head>
  <title>Terms & Conditions | <?php echo defined('APP_NAME') ? APP_NAME : 'Musabaqa'; ?></title>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport"/>
  <meta content="Terms and Conditions for Majlisu Ahlil Qur'an International Qur'an Competition" name="description"/>
  <link href="assets/media/app/favicon.ico" rel="shortcut icon"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link href="assets/vendors/keenicons/styles.bundle.css" rel="stylesheet"/>
  <link href="assets/css/styles.css" rel="stylesheet"/>
 </head>
 <body class="antialiased bg-gray-50 dark:bg-coal-600">
  <!-- Theme Mode (same as sign-up) -->
  <script>
   const defaultThemeMode = 'light';
   const getThemeMode = () => {
     const themeMode = localStorage.getItem('theme_mode') || defaultThemeMode;
     if (themeMode === 'system') {
       return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
     }
     return themeMode;
   }
   document.documentElement.setAttribute('data-theme-mode', getThemeMode());
   if (getThemeMode() === 'dark') {
     document.documentElement.classList.add('dark');
   }
  </script>

  <div class="container mx-auto px-4 py-10">
    <div class="card max-w-4xl mx-auto">
      <div class="card-body p-6 md:p-10">
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white mb-6">Terms & Conditions</h1>

        <div class="prose dark:prose-invert max-w-none text-gray-700 dark:text-gray-300">
          <p>Last updated: <?php echo date('F d, Y'); ?></p>

          <p>Please read these terms and conditions carefully before using Our Service.</p>

          <h2 class="text-xl font-medium mt-6 mb-3">Interpretation and Definitions</h2>
          <p>[Placeholder: Add your interpretation and definitions here. Explain key terms used throughout the document.]</p>
          <ul>
            <li><strong>Service</strong> refers to the <?php echo defined('APP_NAME') ? APP_NAME : 'Musabaqa'; ?> website accessible from <?php echo defined('APP_URL') ? APP_URL : '#'; ?></li>
            <li><strong>Terms and Conditions</strong> (also referred as "Terms") mean these Terms and Conditions that form the entire agreement between You and the Company regarding the use of the Service.</li>
            <li><strong>You</strong> means the individual accessing or using the Service, or the company, or other legal entity on behalf of which such individual is accessing or using the Service, as applicable.</li>
            <!-- Add other definitions as needed -->
          </ul>

          <h2 class="text-xl font-medium mt-6 mb-3">Acknowledgment</h2>
          <p>[Placeholder: State that these are the Terms governing the use of the Service and the agreement between the user and the organization. Mention that access is conditioned on acceptance.]</p>
          <p>By accessing or using the Service You agree to be bound by these Terms and Conditions. If You disagree with any part of these Terms and Conditions then You may not access the Service.</p>
          <p>Your access to and use of the Service is also conditioned on Your acceptance of and compliance with the Privacy Policy of the Company. [...]</p>


          <h2 class="text-xl font-medium mt-6 mb-3">User Accounts</h2>
          <p>[Placeholder: Detail requirements for creating an account, user responsibilities for account security, accuracy of information, and conditions for account termination.]</p>
          <p>When You create an account with Us, You must provide Us information that is accurate, complete, and current at all times. Failure to do so constitutes a breach of the Terms, which may result in immediate termination of Your account on Our Service.</p>
          <p>You are responsible for safeguarding the password that You use to access the Service [...]</p>

          <h2 class="text-xl font-medium mt-6 mb-3">Intellectual Property</h2>
          <p>[Placeholder: State ownership of the Service and its content, features, and functionality.]</p>

          <h2 class="text-xl font-medium mt-6 mb-3">Links to Other Websites</h2>
          <p>[Placeholder: Disclaimer regarding third-party websites or services linked from your Service.]</p>

          <h2 class="text-xl font-medium mt-6 mb-3">Termination</h2>
          <p>[Placeholder: Conditions under which you or the user may terminate the account/access.]</p>

          <h2 class="text-xl font-medium mt-6 mb-3">Limitation of Liability</h2>
          <p>[Placeholder: Disclaimer of liability, often in ALL CAPS as required by law in some jurisdictions.]</p>
          <p>TO THE MAXIMUM EXTENT PERMITTED BY APPLICABLE LAW, IN NO EVENT SHALL THE COMPANY OR ITS SUPPLIERS BE LIABLE FOR ANY SPECIAL, INCIDENTAL, INDIRECT, OR CONSEQUENTIAL DAMAGES WHATSOEVER [...]</p>

          <h2 class="text-xl font-medium mt-6 mb-3">"AS IS" and "AS AVAILABLE" Disclaimer</h2>
          <p>[Placeholder: State that the service is provided without warranties.]</p>

          <h2 class="text-xl font-medium mt-6 mb-3">Governing Law</h2>
          <p>[Placeholder: Specify the jurisdiction whose laws govern the Terms.]</p>

          <h2 class="text-xl font-medium mt-6 mb-3">Changes to These Terms and Conditions</h2>
          <p>[Placeholder: Explain how you reserve the right to modify terms and how users will be notified.]</p>

          <h2 class="text-xl font-medium mt-6 mb-3">Contact Us</h2>
          <p>If you have any questions about these Terms and Conditions, You can contact us:</p>
          <ul>
            <li>By email: [Your Contact Email]</li>
            <li>By visiting this page on our website: [Link to Contact Page, if any]</li>
          </ul>
        </div>

        <div class="mt-8 text-center">
            <a href="sign-up.php" class="btn btn-secondary">Back to Sign Up</a>
        </div>

      </div>
    </div>
  </div>

  <!-- Scripts (Optional: Add if needed for theme switching or other JS) -->
  <script src="assets/js/core.bundle.js"></script>
 </body>
</html>