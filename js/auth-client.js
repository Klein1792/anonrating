/**
 * Auth Client - JavaScript interface for auth system
 * Supports refresh tokens and persistent sessions
 */
(function() {
    // Create namespaces
    window.gameRating = window.gameRating || {};
    window.gameRating.auth = window.gameRating.auth || {};
    
    // Helper function to clear client-side auth data
    function clearAuthClientSide() {
        // Clear all possible auth cookies across multiple paths
        const paths = ['/', '/gamerating', ''];
        const cookiesToClear = ['access_token', 'refresh_token', 'PHPSESSID'];
        
        paths.forEach(path => {
            cookiesToClear.forEach(cookieName => {
                document.cookie = `${cookieName}=; Path=${path}; Expires=Thu, 01 Jan 1970 00:00:01 GMT;`;
            });
        });
        
        // Clear any storage items
        try {
            localStorage.removeItem('user_data');
            localStorage.removeItem('access_token');
            sessionStorage.removeItem('user_data');
            sessionStorage.removeItem('access_token');
        } catch (e) {
            console.warn("Error clearing storage:", e);
        }
        
        console.log("Client-side auth data cleared");
    }
    
    // User object - syncs with PHP session
    window.gameRating.auth.user = {
        id: 0,
        username: '',
        isLoggedIn: false,
        isAdmin: false,
        isModerator: false,
        tokenExpiry: null,
        
        // Initialize from server-provided data
        init: function(userData) {
            if (userData) {
                this.id = parseInt(userData.id) || 0;
                this.username = userData.username || '';
                this.isLoggedIn = !!userData.isLoggedIn;
                this.isAdmin = !!userData.isAdmin;
                this.isModerator = !!userData.isModerator;
                this.tokenExpiry = userData.tokenExpiry ? new Date(userData.tokenExpiry) : null;
                
                // Set permission level property
                if (this.isAdmin) {
                    this.permissionLevel = 'admin';
                } else if (this.isModerator) {
                    this.permissionLevel = 'moderator';
                } else if (this.isLoggedIn) {
                    this.permissionLevel = 'user';
                } else {
                    this.permissionLevel = 'guest';
                }
                
                console.log(`Auth initialized: ${this.isLoggedIn ? this.username : 'Guest'} (${this.permissionLevel})`);
                
                // Update UI elements
                this.updateUI();
                
                // Set up token expiry check if logged in
                if (this.isLoggedIn && this.tokenExpiry) {
                    this._setupTokenCheck();
                }
            }
        },
        
        // Check if user has specified permission level
        hasPermission: function(level) {
            if (!this.isLoggedIn) return false;
            
            switch(level) {
                case 'admin': return this.isAdmin;
                case 'moderator': return this.isAdmin || this.isModerator;
                case 'user': return true;
                default: return false;
            }
        },
        
        // Check if user can modify a specific item
        canModify: function(itemUserId) {
            if (!this.isLoggedIn) return false;
            if (this.isAdmin || this.isModerator) return true;
            return this.id === parseInt(itemUserId);
        },
        
        // CONSOLIDATED MASTER LOGOUT FUNCTION
        logout: function() {
            console.log("Auth client: Logout function called");
            
            // Use window.baseUrl with fallback
            const apiUrl = `${window.baseUrl || ''}/api.php?action=logout`;
            console.log("Logout API URL:", apiUrl);
            
            // Make API call to logout endpoint
            fetch(apiUrl, {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                console.log("Logout response status:", response.status);
                
                // Client-side cleanup regardless of server response
                clearAuthClientSide();
                
                // Reset auth state
                this.id = 0;
                this.username = '';
                this.isLoggedIn = false;
                this.isAdmin = false;
                this.isModerator = false;
                this.tokenExpiry = null;
                this.permissionLevel = 'guest';
                
                // Redirect to index page
                console.log("Redirecting after logout...");
                window.location.href = (window.baseUrl || '') + '/index.php';
            })
            .catch(error => {
                console.error("Logout error:", error);
                
                // Still do client-side cleanup and redirect
                clearAuthClientSide();
                
                // Reset auth state
                this.id = 0;
                this.username = '';
                this.isLoggedIn = false;
                this.isAdmin = false;
                this.isModerator = false;
                this.tokenExpiry = null;
                this.permissionLevel = 'guest';
                
                window.location.href = (window.baseUrl || '') + '/index.php';
            });
            
            return false; // Prevent default link action
        },
        
        // Update UI elements based on auth status
        updateUI: function() {
            // Update login/logout link visibility
            const loginEl = document.querySelector('.nav-login');
            const logoutEl = document.querySelector('.nav-logout');
            const userDisplayEl = document.querySelector('.user-display');
            const adminLinkEl = document.querySelector('.nav-admin');
            
            if (loginEl) loginEl.style.display = this.isLoggedIn ? 'none' : 'block';
            if (logoutEl) logoutEl.style.display = this.isLoggedIn ? 'block' : 'none';
            
            if (userDisplayEl && this.isLoggedIn) {
                userDisplayEl.textContent = this.username;
                userDisplayEl.style.display = 'block';
            } else if (userDisplayEl) {
                userDisplayEl.style.display = 'none';
            }
            
            if (adminLinkEl) {
                adminLinkEl.style.display = this.isAdmin ? 'block' : 'none';
            }
            
            // Add/remove permission classes to body
            document.body.classList.toggle('user-logged-in', this.isLoggedIn);
            document.body.classList.toggle('user-admin', this.isAdmin);
            document.body.classList.toggle('user-moderator', this.isModerator);
            
            // Dispatch event for other components
            document.dispatchEvent(new CustomEvent('auth:updated', { 
                detail: { user: this }
            }));
        },
        
        // Private: Setup token expiration check
        _setupTokenCheck: function() {
            if (!this.tokenExpiry) return;
            
            const now = new Date();
            const expiresIn = this.tokenExpiry.getTime() - now.getTime();
            
            // Check if token is still valid
            if (expiresIn <= 0) {
                console.warn('Auth token expired, attempting refresh');
                this._refreshToken();
                return;
            }
            
            // Schedule a check at halfway to expiration
            const checkTime = Math.min(expiresIn / 2, 24 * 60 * 60 * 1000); // Max 1 day
            
            setTimeout(() => {
                this._refreshToken();
            }, checkTime);
        },

        // Add a new method to handle token refresh
        _refreshToken: function() {
            fetch(`${window.baseUrl || ''}/api.php?action=check_auth`, {
                method: 'GET',
                credentials: 'include'
            })
            .then(response => response.json())
            .then(data => {
                console.log("Auth check response:", data);
                if (!data.authenticated) {
                    // If server says we're not authenticated but we think we are,
                    // this indicates the refresh token might also be invalid
                    if (this.isLoggedIn) {
                        console.warn("Session expired, redirecting to login");
                        window.location.href = `${window.baseUrl || ''}/login.php`;
                    }
                } else if (data.token_refreshed) {
                    // Server refreshed the token - reload to get new data
                    console.log("Token refreshed, reloading page");
                    window.location.reload();
                } else {
                    // Still authenticated, no refresh needed
                    // Schedule next check
                    this._setupTokenCheck();
                }
            })
            .catch(error => {
                console.error('Error checking auth status:', error);
                // Still schedule next check even on error
                setTimeout(() => this._setupTokenCheck(), 60 * 60 * 1000); // 1 hour
            });
        }
    };
    
    // Auth helper methods
    window.gameRating.auth.helpers = {
        // Get CSRF token
        getCsrfToken: function() {
            const meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.getAttribute('content') : '';
        },
        
        // Enhanced fetch with authentication
        fetchWithAuth: function(url, options = {}) {
            const defaultOptions = {
                credentials: 'include',
                headers: {
                    'X-CSRF-Token': this.getCsrfToken()
                }
            };
            
            return fetch(url, {
                ...defaultOptions,
                ...options,
                headers: {
                    ...defaultOptions.headers,
                    ...(options.headers || {})
                }
            });
        }
    };
    
    // Initialize on DOM content loaded
    document.addEventListener('DOMContentLoaded', function() {
        // Look for user data in meta tag
        try {
            const userDataMeta = document.querySelector('meta[name="user-data"]');
            if (userDataMeta) {
                const userData = JSON.parse(atob(userDataMeta.content));
                window.gameRating.auth.user.init(userData);
            } else {
                window.gameRating.auth.user.init({
                    isLoggedIn: false,
                    permissionLevel: 'guest'
                });
            }
        } catch (e) {
            console.error('Error initializing auth client:', e);
        }
        
        // For backward compatibility - set global variables
        window.isLoggedIn = function() { return window.gameRating.auth.user.isLoggedIn; };
        window.isAdmin = function() { return window.gameRating.auth.user.isAdmin; };
        window.isModerator = function() { return window.gameRating.auth.user.isModerator; };
        window.isAdminOrModerator = function() { 
            return window.gameRating.auth.user.isAdmin || window.gameRating.auth.user.isModerator; 
        };
        window.currentUserId = window.gameRating.auth.user.id;
        window.logout = function() { return window.gameRating.auth.user.logout(); };
    });
})();