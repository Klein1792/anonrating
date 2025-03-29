// EMERGENCY FIX - Comment out any problematic code
/**
 * Mobile GameBoy-style Controller
 * EMERGENCY FIXED VERSION
 */

// Wait until DOM is fully loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log("mobile-controller.js loaded");
    
    const mobileController = document.getElementById('mobile-controller');
    if (!mobileController) return;
    
    // Only for mobile devices
    if (window.innerWidth <= 768) {
        console.log("Mobile device detected");
        
        // Set initial state to collapsed
        document.body.classList.add('controller-hidden');
        
        // Fix toggle button
        const toggleBtn = document.getElementById('controller-toggle');
        if (toggleBtn) {
            // Use direct event assignment which is harder to override
            toggleBtn.onclick = function(e) {
                e.preventDefault();
                e.stopPropagation();
                document.body.classList.toggle('controller-hidden');
                console.log('Toggle clicked via onclick, hidden:', document.body.classList.contains('controller-hidden'));
                return false;
            };
            
            console.log("Toggle button fixed with onclick");
        }
        
        // Force immediate display to ensure visibility
        mobileController.style.display = 'flex';
    }
});

/**
 * Setup the GameBoy controller functionality
 */
function setupGameBoyController() {
    const mobileController = document.getElementById('mobile-controller');
    
    // If the controller doesn't exist, don't continue
    if (!mobileController) {
        console.log('Mobile controller element not found');
        return;
    }
    
    // Only proceed on mobile devices
    if (window.innerWidth > 768) {
        return;
    }
    
    // Start with controller hidden but available
    document.body.classList.add('controller-hidden');
    
    // Set up toggle button - use capturing to ensure it works
    const toggleBtn = document.getElementById('controller-toggle');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            document.body.classList.toggle('controller-hidden');
            console.log('Controller toggle clicked, hidden:', document.body.classList.contains('controller-hidden'));
        }, true); // Use capture phase
        
        console.log('Controller toggle button set up');
    }
    
    // Set up controller buttons
    const controllerButtons = document.querySelectorAll('#mobile-controller button[data-key]');
    controllerButtons.forEach(button => {
        if (button.id === 'controller-toggle') {
            return; // Skip the toggle button
        }
        
        // Mobile touch events
        button.addEventListener('touchstart', function(e) {
            e.preventDefault(); // Prevent scrolling
            e.stopPropagation();
            const keyCode = parseInt(button.getAttribute('data-key'));
            button.classList.add('pressed');
            
            // Create and dispatch keyboard event
            simulateKeyPress(keyCode);
        }, true);
        
        button.addEventListener('touchend', function(e) {
            e.preventDefault();
            e.stopPropagation();
            button.classList.remove('pressed');
        }, true);
        
        // Mouse events for testing
        button.addEventListener('mousedown', function(e) {
            e.stopPropagation();
            const keyCode = parseInt(button.getAttribute('data-key'));
            button.classList.add('pressed');
            simulateKeyPress(keyCode);
        }, true);
        
        button.addEventListener('mouseup', function(e) {
            e.stopPropagation();
            button.classList.remove('pressed');
        }, true);
        
        button.addEventListener('mouseleave', function() {
            button.classList.remove('pressed');
        });
    });
    
    // Hide controller when inputs are focused
    const inputs = document.querySelectorAll('input, textarea, select');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            document.body.classList.add('input-focused');
        });
        
        input.addEventListener('blur', function() {
            document.body.classList.remove('input-focused');
        });
    });
    
    console.log('GameBoy controller setup complete');
}

/**
 * Simulate a keyboard key press
 * @param {number} keyCode - The key code to simulate
 */
function simulateKeyPress(keyCode) {
    const event = new KeyboardEvent('keydown', {
        bubbles: true,
        cancelable: true,
        keyCode: keyCode,
        which: keyCode
    });
    document.dispatchEvent(event);
}

/**
 * Check if the current device is a mobile device
 * @returns {boolean} True if the device is a mobile device
 */
function isMobileDevice() {
    return (
        ('ontouchstart' in window) || 
        (navigator.maxTouchPoints > 0) ||
        (navigator.msMaxTouchPoints > 0) ||
        window.innerWidth <= 768 ||
        /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)
    );
}