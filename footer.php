<?php
// Get current page
$currentPage = basename($_SERVER['PHP_SELF']);
$isIndexPage = ($currentPage === 'index.php' || $currentPage === '');

/**
 * Comprehensive mobile detection function that doesn't rely on screen width
 * Based on device characteristics and user agent detection
 * @return bool True if the device is mobile
 */
function isMobileDevice() {
    // STEP 1: User agent detection (primary method)
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $userAgent)) {
        return true;
    }
    
    // Alternative pattern - catches iPad and more tablets
    if (preg_match('/android|ipad|playbook|silk/i', $userAgent)) {
        return true;
    }
    
    // STEP 2: Check for specific HTTP headers sent by mobile browsers
    if (isset($_SERVER['HTTP_ACCEPT']) && 
        strpos($_SERVER['HTTP_ACCEPT'], 'application/vnd.wap.xhtml+xml') !== false) {
        return true;
    }
    
    // Common mobile browser profile headers
    $mobileHeaders = [
        'HTTP_X_WAP_PROFILE',           // Some Nokia, Siemens phones
        'HTTP_PROFILE',                 // Some other phones
        'HTTP_X_OPERAMINI_PHONE',       // Opera Mini
        'HTTP_X_OPERAMINI_FEATURES',    // Opera Mini
        'HTTP_X_HUAWEI_CULTURECODE',    // Huawei devices
        'HTTP_X_REQUESTED_WITH',        // Some Android devices
        'HTTP_X_UCBROWSER',             // UC Browser
        'HTTP_X_NOKIA_GATEWAY_ID',      // Some Nokia
        'HTTP_X_ORANGE_ID',             // Orange mobile
        'HTTP_X_VODAFONE_3GPDPCONTEXT', // Vodafone
        'HTTP_X_BLUECOAT_VIA'           // Some WAP-enabled devices
    ];
    
    foreach ($mobileHeaders as $header) {
        if (isset($_SERVER[$header])) {
            return true;
        }
    }
    
    // STEP 3: Check for wap.wml support (older mobile devices)
    if (isset($_SERVER['HTTP_ACCEPT']) && 
        (strpos($_SERVER['HTTP_ACCEPT'], 'text/vnd.wap.wml') !== false || 
         strpos($_SERVER['HTTP_ACCEPT'], 'application/vnd.wap.xhtml+xml') !== false)) {
        return true;
    }
    
    // Not detected as mobile
    return false;
}

// Get mobile status once
$isMobile = isMobileDevice();

// Set appropriate flags for display logic
$showMobileNav = $isIndexPage && $isMobile;
$showController = ($currentPage !== 'index.php' && 
                  $currentPage !== 'admin.php' && 
                  $currentPage !== 'indexg.php') && $isMobile;

// Add a global JavaScript variable for client-side detection
echo '<script>window.serverDetectedMobile = ' . ($isMobile ? 'true' : 'false') . ';</script>';
?>

<!-- Emergency CSS fix to hide mobile-only elements on desktop -->
<style>
/* Only rely on the device type class, not screen width */
body.desktop-device .mobile-only,
html.desktop-device .mobile-only {
    display: none !important;
}

body.mobile-device .desktop-only,
html.mobile-device .desktop-only {
    display: none !important;
}
</style>

<!-- Rest of your conditional HTML remains unchanged -->
<?php if ($showMobileNav): ?>
<!-- Mobile Navigation Menu - ONLY DISPLAYED ON MOBILE -->
<div class="mobile-nav-wrapper" id="mobile-nav-wrapper">
    <!-- Toggle button positioned separately from menu -->
    <button id="nav-toggle" class="nav-toggle">
        <span class="toggle-icon">▲</span>
    </button>
    
    <!-- The nav menu with fixed positioning -->
    <div class="mobile-nav-menu" id="mobile-nav-menu">
        <div class="mobile-nav-content">
            <div class="mobile-nav-buttons">
                <a href="login.php" class="mobile-nav-button login-button">
                    <span class="button-icon">👤</span>
                    <span class="button-text">Login</span>
                </a>
                <a href="register.php" class="mobile-nav-button register-button">
                    <span class="button-icon">✏️</span>
                    <span class="button-text">Register</span>
                </a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($showController): ?>
<!-- Mobile GameBoy-style Controller -->
<div id="mobile-controller" class="mobile-controller">
    <button id="controller-toggle" class="controller-toggle">
        <span class="toggle-icon">▼</span>
    </button>
    <div class="gameboy-container">
        <div class="dpad-container">
            <div class="dpad-label">D-Pad</div>
            <div class="dpad-center"></div>
            <button class="dpad-btn up" data-key="38">↑</button>
            <button class="dpad-btn right" data-key="39">→</button>
            <button class="dpad-btn down" data-key="40">↓</button>
            <button class="dpad-btn left" data-key="37">←</button>
        </div>
        <div class="action-buttons">
            <div class="buttons-label">Action</div>
            <button class="action-btn b-btn" data-key="66">B</button>
            <button class="action-btn a-btn" data-key="65">A</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Custom notification widget -->
<script>
function showNotification(message) {
    const notificationEl = document.getElementById('notification-message');
    const containerEl = document.getElementById('custom-notification');
    
    if (notificationEl && containerEl) {
        notificationEl.textContent = message;
        containerEl.style.display = 'block';
        setTimeout(() => {
            containerEl.style.display = 'none';
        }, 3000);
    }
}
</script>

<!-- MOBILE CONTROLLER SCRIPT - REVISED WITHOUT SCREEN WIDTH DEPENDENCY -->
<script>
// Global object for device detection
window.deviceDetection = {
    // Use server detection as the source of truth
    isMobile: window.serverDetectedMobile === true,
    
    // Additional checks that don't rely on screen width
    hasTouchSupport: function() {
        return ('ontouchstart' in window) || 
               (navigator.maxTouchPoints > 0) || 
               (navigator.msMaxTouchPoints > 0);
    }
};

// Apply device classes to HTML and body elements
document.addEventListener('DOMContentLoaded', function() {
    // Get mobile status from server-side detection
    const isMobile = window.deviceDetection.isMobile;
    
    // Set classes on HTML and body
    document.documentElement.classList.toggle('mobile-device', isMobile);
    document.documentElement.classList.toggle('desktop-device', !isMobile);
    document.body.classList.toggle('mobile-device', isMobile);
    document.body.classList.toggle('desktop-device', !isMobile);
    
    console.log("Device detection applied:", isMobile ? "Mobile" : "Desktop");
    
    // Get current page
    const currentPage = window.location.pathname.split('/').pop() || 'index.php';
    console.log("Current page detected:", currentPage);
    
    // Get controller and navigation elements
    const mobileController = document.getElementById('mobile-controller');
    const mobileNav = document.getElementById('mobile-nav-menu');
    const navToggleBtn = document.getElementById('nav-toggle');
    
    // Only run mobile functionality if we're on a mobile device
    if (window.deviceDetection.isMobile) {
        console.log("Setting up mobile features");
        
        // Special handling for index page
        if (currentPage === 'index.php' || currentPage === '') {
            console.log("Index page detected - hiding controller");
            
            // Ensure controller is hidden on index page
            if (mobileController) {
                mobileController.style.display = 'none';
            }
            
            // Set up nav toggle button for index page
            if (navToggleBtn) {
                // Remove existing listeners for clean setup
                const newToggle = navToggleBtn.cloneNode(true);
                navToggleBtn.parentNode.replaceChild(newToggle, navToggleBtn);
                
                // Add new listener
                newToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    document.body.classList.toggle('nav-hidden');
                    
                    // Update icon based on state
                    const toggleIcon = newToggle.querySelector('.toggle-icon');
                    if (toggleIcon) {
                        toggleIcon.textContent = '▲';
                    }
                });
            }
            
            // Exit early
            return;
        }
        
        // For non-index pages, show and set up controller
        if (mobileController) {
            console.log("Setting up controller for non-index page");
            
            // Make controller visible
            mobileController.style.display = 'flex';
            
            // Start with controller hidden but available
            document.body.classList.add('controller-hidden');
            
            // Set up controller toggle button
            const toggleBtn = document.getElementById('controller-toggle');
            if (toggleBtn) {
                toggleBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    document.body.classList.toggle('controller-hidden');
                });
                
                // Set up controller buttons
                const controllerButtons = document.querySelectorAll('#mobile-controller button[data-key]');
                controllerButtons.forEach(button => {
                    if (button.id === 'controller-toggle') return;
                    
                    // Touch events
                    button.addEventListener('touchstart', function(e) {
                        e.preventDefault();
                        const keyCode = parseInt(button.getAttribute('data-key'));
                        button.classList.add('pressed');
                        
                        // Create and dispatch keyboard event
                        const event = new KeyboardEvent('keydown', {
                            bubbles: true,
                            cancelable: true,
                            keyCode: keyCode,
                            which: keyCode
                        });
                        document.dispatchEvent(event);
                    });
                    
                    button.addEventListener('touchend', function(e) {
                        e.preventDefault();
                        button.classList.remove('pressed');
                    });
                });
            }
        }
    } else {
        // Hide mobile elements on desktop
        if (mobileController) {
            mobileController.style.display = 'none';
        }
        
        if (mobileNav) {
            mobileNav.style.display = 'none';
        }
        
        if (navToggleBtn) {
            navToggleBtn.style.display = 'none';
        }
    }
});
</script>
</body>
</html>