(function() {
    'use strict';
    
    // Initialize the global gameRating object if it doesn't exist
    window.gameRating = window.gameRating || {};
    
    // Auth state
    let isInitialized = false;
    let isAuthenticated = false;
    let isAnonymous = true;
    let currentUser = null;
    let tokenRefreshTimer = null;
    
    /**
     * Initialize the authentication client
     */
    async function init() {
        if (isInitialized) return;
        
        // Check for existing access token
        const accessToken = getCookie('access_token');
        const refreshToken = getCookie('refresh_token');
        const anonymousToken = getCookie('anonymous_token');
        
        if (accessToken) {
            try {
                // Verify token validity
                const response = await fetch(`${window.baseUrl}/api.php?action=verifyToken`, {
                    headers: {
                        'Authorization': `Bearer ${accessToken}`
                    }
                });
                const data = await response.json();
                
                if (data.success) {
                    // Token is valid
                    isAuthenticated = true;
                    isAnonymous = false;
                    currentUser = data.user;
                    
                    // Setup token refresh timer
                    setupTokenRefresh();
                    
                    // Update UI
                    updateAuthUI();
                } else {
                    // Token invalid, try to refresh
                    await refreshAuthToken();
                }
            } catch (error) {
                console.error('Error verifying token:', error);
                // Try to refresh on error
                await refreshAuthToken();
            }
        } else if (refreshToken) {
            // Try to refresh using refresh token
            await refreshAuthToken();
        } else if (!anonymousToken) {
            // Ensure anonymous token exists
            await createAnonymousSession();
        }
        
        isInitialized = true;
        
        // Update UI with current state
        updateAuthUI();
        
        // Attach event listeners
        attachLoginFormListeners();
        attachRegisterFormListeners();
        attachLogoutButtonListeners();
    }
    
    /**
     * Set up a timer to refresh the token before it expires
     */
    function setupTokenRefresh() {
        // Clear any existing timer
        if (tokenRefreshTimer) {
            clearTimeout(tokenRefreshTimer);
        }
        
        // Calculate when to refresh (5 minutes before expiry)
        const token = parseJwt(getCookie('access_token'));
        if (token && token.exp) {
            const expiry = token.exp * 1000; // Convert to milliseconds
            const now = Date.now();
            const timeToRefresh = Math.max(0, expiry - now - (5 * 60 * 1000)); // 5 minutes before expiry
            
            // Set timer to refresh token
            tokenRefreshTimer = setTimeout(refreshAuthToken, timeToRefresh);
        }
    }
    
    /**
     * Refresh the authentication token
     */
    async function refreshAuthToken() {
        try {
            const response = await fetch(`${window.baseUrl}/api.php?action=refreshToken`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin'
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Update auth state
                isAuthenticated = true;
                isAnonymous = false;
                currentUser = data.user;
                
                // Setup token refresh timer
                setupTokenRefresh();
                
                // Update UI
                updateAuthUI();
                
                return true;
            } else {
                // Refresh failed
                isAuthenticated = false;
                isAnonymous = true;
                currentUser = null;
                
                // Update UI
                updateAuthUI();
                
                return false;
            }
        } catch (error) {
            console.error('Error refreshing token:', error);
            isAuthenticated = false;
            isAnonymous = true;
            currentUser = null;
            
            // Update UI
            updateAuthUI();
            
            return false;
        }
    }
    
    /**
     * Create an anonymous session for the user
     */
    async function createAnonymousSession() {
        try {
            // Call API to create anonymous token
            const response = await fetch(`${window.baseUrl}/api.php?action=createAnonymousToken`, {
                method: 'POST'
            });
            
            const data = await response.json();
            
            if (data.success) {
                isAnonymous = true;
                return true;
            }
            
            return false;
        } catch (error) {
            console.error('Error creating anonymous session:', error);
            return false;
        }
    }
    
    /**
     * Update the UI based on authentication state
     */
    function updateAuthUI() {
        // Find auth-related elements
        const authSection = document.querySelector('#auth-section');
        const userSection = document.querySelector('#user-section');
        const usernameDisplay = document.querySelectorAll('.username-display');
        const adminElements = document.querySelectorAll('.admin-only');
        const modElements = document.querySelectorAll('.mod-only');
        const authRequiredElements = document.querySelectorAll('.auth-required');
        const nonAuthElements = document.querySelectorAll('.non-auth-only');
        
        if (!authSection && !userSection) return; // No auth UI elements on page
        
        if (isAuthenticated) {
            // User is logged in
            if (authSection) authSection.style.display = 'none';
            if (userSection) userSection.style.display = 'block';
            
            // Update username display
            if (usernameDisplay) {
                usernameDisplay.forEach(el => {
                    el.textContent = currentUser.username;
                });
            }
            
            // Show admin elements if the user is an admin
            if (adminElements) {
                adminElements.forEach(el => {
                    el.style.display = currentUser.is_admin ? 'block' : 'none';
                });
            }
            
            // Show mod elements if the user is a mod or admin
            if (modElements) {
                modElements.forEach(el => {
                    el.style.display = (currentUser.is_moderator || currentUser.is_admin) ? 'block' : 'none';
                });
            }
            
            // Show auth required elements
            if (authRequiredElements) {
                authRequiredElements.forEach(el => {
                    el.style.display = '';
                });
            }
            
            // Hide non-auth elements
            if (nonAuthElements) {
                nonAuthElements.forEach(el => {
                    el.style.display = 'none';
                });
            }
        } else {
            // User is not logged in
            if (authSection) authSection.style.display = 'block';
            if (userSection) userSection.style.display = 'none';
            
            // Hide admin/mod elements
            if (adminElements) {
                adminElements.forEach(el => {
                    el.style.display = 'none';
                });
            }
            
            if (modElements) {
                modElements.forEach(el => {
                    el.style.display = 'none';
                });
            }
            
            // Hide auth required elements
            if (authRequiredElements) {
                authRequiredElements.forEach(el => {
                    el.style.display = 'none';
                });
            }
            
            // Show non-auth elements
            if (nonAuthElements) {
                nonAuthElements.forEach(el => {
                    el.style.display = '';
                });
            }
        }
        
        // Dispatch an event so other components can react
        document.dispatchEvent(new CustomEvent('authStateChanged', {
            detail: {
                isAuthenticated,
                isAnonymous,
                user: currentUser
            }
        }));
    }
    
    /**
     * Attach event listeners to login form
     */
    function attachLoginFormListeners() {
        const loginForm = document.querySelector('#login-form');
        
        if (loginForm) {
            loginForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const username = loginForm.querySelector('#login-username').value;
                const password = loginForm.querySelector('#login-password').value;
                const remember = loginForm.querySelector('#login-remember')?.checked || false;
                const errorMessage = loginForm.querySelector('.error-message');
                
                try {
                    const response = await fetch(`${window.baseUrl}/api.php?action=login`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ username, password, remember })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        // Update auth state
                        isAuthenticated = true;
                        isAnonymous = false;
                        currentUser = data.user;
                        
                        // Setup token refresh timer
                        setupTokenRefresh();
                        
                        // Update UI
                        updateAuthUI();
                        
                        // Redirect if needed
                        const returnTo = new URLSearchParams(window.location.search).get('returnTo');
                        if (returnTo) {
                            window.location.href = returnTo;
                        } else if (window.location.pathname === '/login.php') {
                            window.location.href = '/index.php';
                        }
                    } else {
                        // Show error message
                        if (errorMessage) {
                            errorMessage.textContent = data.error || 'An error occurred during login';
                            errorMessage.style.display = 'block';
                        }
                    }
                } catch (error) {
                    console.error('Login error:', error);
                    if (errorMessage) {
                        errorMessage.textContent = 'An error occurred. Please try again.';
                        errorMessage.style.display = 'block';
                    }
                }
            });
        }
    }
    
    /**
     * Attach event listeners to register form
     */
    function attachRegisterFormListeners() {
        const registerForm = document.querySelector('#register-form');
        
        if (registerForm) {
            registerForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const username = registerForm.querySelector('#register-username').value;
                const email = registerForm.querySelector('#register-email').value;
                const password = registerForm.querySelector('#register-password').value;
                const confirmPassword = registerForm.querySelector('#register-confirm-password').value;
                const errorMessage = registerForm.querySelector('.error-message');
                
                // Basic validation
                if (password !== confirmPassword) {
                    if (errorMessage) {
                        errorMessage.textContent = 'Passwords do not match';
                        errorMessage.style.display = 'block';
                    }
                    return;
                }
                
                try {
                    const response = await fetch(`${window.baseUrl}/api.php?action=register`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ username, email, password })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        // Update auth state
                        isAuthenticated = true;
                        isAnonymous = false;
                        currentUser = data.user;
                        
                        // Setup token refresh timer
                        setupTokenRefresh();
                        
                        // Update UI
                        updateAuthUI();
                        
                        // Redirect if needed
                        const returnTo = new URLSearchParams(window.location.search).get('returnTo');
                        if (returnTo) {
                            window.location.href = returnTo;
                        } else if (window.location.pathname === '/register.php') {
                            window.location.href = '/index.php';
                        }
                    } else {
                        // Show error message
                        if (errorMessage) {
                            errorMessage.textContent = data.error || 'An error occurred during registration';
                            errorMessage.style.display = 'block';
                        }
                    }
                } catch (error) {
                    console.error('Registration error:', error);
                    if (errorMessage) {
                        errorMessage.textContent = 'An error occurred. Please try again.';
                        errorMessage.style.display = 'block';
                    }
                }
            });
        }
    }
    
    /**
     * Attach event listeners to logout buttons
     */
    function attachLogoutButtonListeners() {
        const logoutButtons = document.querySelectorAll('.logout-button');
        
        if (logoutButtons) {
            logoutButtons.forEach(button => {
                button.addEventListener('click', async (e) => {
                    e.preventDefault();
                    await logout();
                });
            });
        }
    }
    
    /**
     * Log out the current user
     */
    async function logout() {
        try {
            // First call logout API endpoint
            const response = await fetch(`${window.baseUrl}/api.php?action=logout`, {
                method: 'POST',
                credentials: 'include' // Important for sending cookies
            });
            
            // Manually clear cookies on the client side (except anonymous_token)
            document.cookie = 'access_token=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;';
            document.cookie = 'refresh_token=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;';
            document.cookie = 'access_token=; Path=/gamerating; Expires=Thu, 01 Jan 1970 00:00:01 GMT;';
            document.cookie = 'refresh_token=; Path=/gamerating; Expires=Thu, 01 Jan 1970 00:00:01 GMT;';
            
            // Clear auth state
            isAuthenticated = false;
            isAnonymous = true;
            currentUser = null;
            
            // Clear token refresh timer
            if (tokenRefreshTimer) {
                clearTimeout(tokenRefreshTimer);
                tokenRefreshTimer = null;
            }
            
            // Do NOT create a new anonymous session; reuse the existing anonymous_token
            // await createAnonymousSession();
            
            // Update UI
            updateAuthUI();
            
            // Force page reload to ensure clean state
            window.location.reload();
            
            return true;
        } catch (error) {
            console.error('Logout error:', error);
            return false;
        }
    }
    
    /**
     * Get the current auth token
     * @returns {string|null} The auth token or null if not authenticated
     */
    function getAuthToken() {
        return getCookie('access_token');
    }
    
    /**
     * Get the anonymous token
     * @returns {string|null} The anonymous token or null if not available
     */
    function getAnonymousToken() {
        return getCookie('anonymous_token');
    }
    
    /**
     * Fetch with auth token included
     * @param {string} url - URL to fetch
     * @param {object} options - Fetch options
     * @returns {Promise} Fetch promise
     */
    async function fetchWithAuth(url, options = {}) {
        const token = getAuthToken();
        
        // Clone options to avoid modifying the original
        const fetchOptions = { ...options };
        fetchOptions.headers = { ...fetchOptions.headers } || {};
        
        // Add auth header if token exists
        if (token) {
            fetchOptions.headers['Authorization'] = `Bearer ${token}`;
        }
        
        // Add credentials for cookies
        fetchOptions.credentials = 'same-origin';
        
        try {
            let response = await fetch(url, fetchOptions);
            
            // If unauthorized and we have a refresh token, try to refresh and retry
            if (response.status === 401 && getCookie('refresh_token')) {
                const refreshSuccessful = await refreshAuthToken();
                
                if (refreshSuccessful) {
                    // Get new token and retry request
                    const newToken = getAuthToken();
                    fetchOptions.headers['Authorization'] = `Bearer ${newToken}`;
                    response = await fetch(url, fetchOptions);
                }
            }
            
            return response;
        } catch (error) {
            console.error('Error in fetchWithAuth:', error);
            throw error;
        }
    }
    
    // Function to get a cookie value by name
    function getCookie(name) {
        const match = document.cookie.match(new RegExp('(^|;\\s*)(' + name + ')=([^;]*)'));
        return match ? decodeURIComponent(match[3]) : null;
    }

    // Parse JWT token
    function parseJwt(token) {
        try {
            const base64Url = token.split('.')[1];
            const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
            const jsonPayload = decodeURIComponent(atob(base64).split('').map(function(c) {
                return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
            }).join(''));
            return JSON.parse(jsonPayload);
        } catch (e) {
            return null;
        }
    }
    
    // Public API
    window.gameRating.auth = {
        init,
        isAuthenticated: () => isAuthenticated,
        isAnonymous: () => isAnonymous,
        getCurrentUser: () => currentUser,
        getAuthToken,
        getAnonymousToken,
        fetchWithAuth,
        logout,
        getCookie,        // Add this line
        parseJwt          // Optional: also expose parseJwt which might be useful
    };
    
    // Expose for backward compatibility
    window.fetchWithAuth = window.gameRating.auth.fetchWithAuth;
    window.getAuthToken = window.gameRating.auth.getAuthToken;
    window.logout = window.gameRating.auth.logout;
    window.getCookie = getCookie;       // Add this line
    window.parseJwt = parseJwt;         // Optional: also expose parseJwt
    
    // Auto-initialize
    document.addEventListener('DOMContentLoaded', init);
})();