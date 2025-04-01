/**
 * Authentication Utilities
 * Helper functions for authentication, tokens, and auth state management
 */

(function() {
    'use strict';
    
    // Utility to get cookie by name
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
            console.error('Error parsing JWT:', e);
            return null;
        }
    }
    
    // Fetch with auth token
    function fetchWithAuth(url, options = {}) {
        const token = getCookie('access_token') || localStorage.getItem('jwt_token');
        
        const fetchOptions = { ...options };
        fetchOptions.headers = { ...fetchOptions.headers } || {};
        
        if (token) {
            fetchOptions.headers['Authorization'] = `Bearer ${token}`;
        }
        
        fetchOptions.credentials = 'same-origin';
        
        return fetch(url, fetchOptions);
    }
    
    // Get the current auth status 
    function getAuthStatus() {
        // Try to get from gameRating.auth first
        if (window.gameRating && window.gameRating.auth) {
            const currentUser = window.gameRating.auth.getCurrentUser();
            return {
                isAuthenticated: window.gameRating.auth.isAuthenticated(),
                isAnonymous: window.gameRating.auth.isAnonymous(),
                currentUser: currentUser
            };
        }
        
        // Fallback to JWT parsing
        const token = getCookie('access_token');
        if (token) {
            try {
                const tokenData = parseJwt(token);
                if (tokenData && tokenData.user_id) {
                    return {
                        isAuthenticated: true,
                        isAnonymous: false,
                        currentUser: {
                            user_id: tokenData.user_id,
                            username: tokenData.username || 'User',
                            is_admin: tokenData.is_admin === true,
                            is_moderator: tokenData.is_moderator === true
                        }
                    };
                }
            } catch (e) {
                console.error("Error parsing auth token:", e);
            }
        }
        
        // Check for anonymous token
        const anonymousToken = getCookie('anonymous_token');
        return {
            isAuthenticated: false,
            isAnonymous: !!anonymousToken,
            currentUser: null
        };
    }
    
    function initializeAuthGlobals() {
        const authStatus = getAuthStatus();
        window.currentUser = authStatus.currentUser;
        window.isAuthenticated = authStatus.isAuthenticated;
        window.isAnonymous = authStatus.isAnonymous;
        console.log("Auth globals initialized:", {
            currentUser: window.currentUser,
            isAuthenticated: window.isAuthenticated,
            isAnonymous: window.isAnonymous
        });
    }

    // Run initialization immediately
    initializeAuthGlobals();
    
    // Export functions to global scope
    window.AuthUtils = {
        getCookie,
        parseJwt,
        fetchWithAuth,
        getAuthStatus,
        initializeAuthGlobals // Add this line to export the function
    };
})();