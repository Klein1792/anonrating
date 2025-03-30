/**
 * Index Page JavaScript
 * Handles game listings, tabs, search functionality, and PornHub-style pagination
 */
(function() {
    let currentPage = {};
    let currentSearch = '';
    let currentSort = 'rating'; // Default sort
    let currentSortDirection = 'DESC';
    
    // Update the tabs configuration - simplified with no sort-specific fields
    const tabs = {
        'top-rated': {
            id: 'top-rated-games',
            type: 'all', 
            pagination: 'top-rated-pagination'
            // Removed sort and sortDirection properties
        },
        recent: { id: 'recent-games', pagination: 'recent-pagination', type: 'recent' },
        rpg: { id: 'rpg-games', pagination: 'rpg-pagination', type: 'genre', genre: 'Role-playing (RPG)' },
        shooter: { id: 'shooter-games', pagination: 'shooter-pagination', type: 'genre', genre: 'Shooter' },
        moba: { id: 'moba-games', pagination: 'moba-pagination', type: 'genre', genre: 'MOBA' },
        pc: { id: 'pc-games', pagination: 'pc-pagination', type: 'platform', platform: 'PC (Microsoft Windows)' },
        console: { id: 'console-games', pagination: 'console-pagination', type: 'platform', platform: 'Console' }
    };
    
    // Initialize when DOM is loaded
    document.addEventListener('DOMContentLoaded', () => {
        // Prevent Bootstrap tabs from auto-scrolling
        const originalScrollTo = window.scrollTo;
        window.scrollTo = function() {
            // Check if this is from Bootstrap's tab activation
            const stackTrace = new Error().stack || '';
            if (stackTrace.includes('bootstrap') && stackTrace.includes('tab')) {
                return; // Skip scrolling from Bootstrap tabs
            }
            // Otherwise allow normal scrolling
            return originalScrollTo.apply(this, arguments);
        };
        
        // Prevent arrow key navigation for tabs
        const tabButtons = document.querySelectorAll('[role="tab"]');
        tabButtons.forEach(button => {
            button.addEventListener('keydown', (e) => {
                if ([37, 38, 39, 40].includes(e.keyCode)) {
                    e.preventDefault();
                }
            });
        });
        
        // Prevent arrow key navigation on tab list container
        const gameTabs = document.getElementById('gameTabs');
        if (gameTabs) {
            gameTabs.addEventListener('keydown', (e) => {
                if ([37, 38, 39, 40].includes(e.keyCode)) {
                    e.preventDefault();
                }
            });
        }
        
        // Set up event listeners
        setupEventListeners();
        
        // Load all tabs
        loadAllTabs();
        
        // Setup tab change listeners for pagination scrolling
        setupTabChangeListeners();

        // REMOVE: We don't need this anymore
        // initTabSpecificSortControls();
    });
    
    // Set up event listeners for search, sort, and admin buttons
    function setupEventListeners() {
        // Search input
        const searchInput = document.getElementById('search-input');
        if (searchInput) {
            searchInput.addEventListener('input', debounce(e => {
                currentSearch = e.target.value;
                loadAllTabs();
            }, 300));
        }
        
        // Sort select - KEEP this single global sort control
        const sortSelect = document.getElementById('sort-select');
        if (sortSelect) {
            sortSelect.addEventListener('change', e => {
                const newSort = e.target.value;
                if (newSort === currentSort) {
                    currentSortDirection = currentSortDirection === 'DESC' ? 'ASC' : 'DESC';
                } else {
                    currentSort = newSort;
                    currentSortDirection = 'DESC';
                }
                loadAllTabs();
            });
        }
        
        // Admin buttons for fetching games
        const fetchFamousBtn = document.getElementById('fetch-famous-games-btn');
        if (fetchFamousBtn) {
            fetchFamousBtn.addEventListener('click', () => {
                const statusP = document.getElementById('fetch-status');
                if (statusP) statusP.textContent = 'Fetching 200 famous games...';
                
                callApi('action=fetchFamousGames', { method: 'POST' })
                    .then(data => {
                        if (statusP) {
                            statusP.textContent = data.error ? `Error: ${data.error}` : data.message;
                        }
                        if (!data.error) loadAllTabs();
                    })
                    .catch(error => {
                        if (statusP) statusP.textContent = `Error: ${error.message}`;
                    });
            });
        }
        
        const fetchGamesBtn = document.getElementById('fetch-games-btn');
        if (fetchGamesBtn) {
            fetchGamesBtn.addEventListener('click', () => {
                const statusP = document.getElementById('fetch-status');
                if (statusP) statusP.textContent = 'Fetching games...';
                
                callApi('action=fetchGamesNow', { method: 'POST' })
                    .then(data => {
                        if (statusP) {
                            statusP.textContent = data.error ? `Error: ${data.error}` : data.message;
                        }
                        if (!data.error) loadAllTabs();
                    })
                    .catch(error => {
                        if (statusP) statusP.textContent = `Error: ${error.message}`;
                    });
            });
        }
    }
    
    // Load all tabs with games
    function loadAllTabs() {
        Object.values(tabs).forEach(tab => loadTab(tab, currentPage[tab.id] || 1));
    }
    
    // Update the loadTab function to use global sort settings
    function loadTab(tab, page = 1) {
        // Map frontend sort values to backend sort values if needed
        const sortMap = {
            'release': 'first_release_date'
             // No need to map 'approval' since it's the same on backend
          
        };
        
        const apiSort = sortMap[currentSort] || currentSort;
        
        currentPage[tab.id] = page;
        const params = new URLSearchParams({
            action: 'games',
            type: tab.type,
            page: page,
            limit: 20,
            search: currentSearch,
            // Use mapped sort value
            sort: apiSort,
            sortDirection: currentSortDirection
        });
        
        if (tab.genre) params.append('genre', tab.genre);
        if (tab.platform) params.append('platform', tab.platform);
        
        const container = document.getElementById(tab.id);
        if (!container) return;
        
        container.innerHTML = '<div class="loading-spinner">Loading...</div>';
        
        callApi(params.toString())
            .then(data => {
                container.innerHTML = '';
                
                if (data.error) {
                    container.innerHTML = `<p style="color: #ff00ff;">Error: ${data.error}</p>`;
                    return;
                }
                
                const games = Array.isArray(data.games) ? data.games : [];
                
                // Special messaging for empty states
                if (games.length === 0) {
                    const infoMsg = document.createElement('div');
                    infoMsg.className = 'alert alert-info';
                    infoMsg.textContent = currentSearch 
                        ? `No games found matching "${currentSearch}"`
                        : 'No games found in this category';
                    container.appendChild(infoMsg);
                } else {
                    // Render all games
                    games.forEach(game => container.appendChild(renderGameCard(game)));
                }
                
                renderPornhubPagination(tab, Math.ceil((data.total || 0) / 20), page);
            })
            .catch(error => {
                console.error('Error loading tab', tab.id, ':', error);
                container.innerHTML = `<p style="color: #ff00ff;">Error: ${error.message}</p>`;
            });
    }
    
    // Use correct property names in renderGameCard
    function renderGameCard(game) {
        const div = document.createElement('div');
        div.className = 'new-game';
        
        const coverUrl = getCoverUrl(game);
        const avgRatingHtml = getAverageRatingBadge(game);
        
        // Use likes and dislikes from database (not likes_count)
        const likes = game.likes || 0;
        const dislikes = game.dislikes || 0;
        const totalVotes = likes + dislikes;
        
        // Use approval_percent from database
        let approvalText = 'No votes';
        if (totalVotes > 0) {
            const approvalPercent = Math.round(game.approval_percent); 
            approvalText = `${approvalPercent}% (${likes}/${totalVotes})`;
        }
        
        // Build the card with a single innerHTML operation
        div.innerHTML = `
            ${avgRatingHtml}
            <a href="game.php?id=${game.id}">
                <div class="new-game-image">
                    <img src="${coverUrl}" alt="${game.name || 'Unknown Game'}" onerror="this.src='${window.baseUrl}/images/default-image.jpg';">
                </div>
                <h3>${game.name || 'Unknown'}</h3>
                <p><span class="release-label">Release:</span> ${formatDate(game.first_release_date)}</p>
                <p><span class="rating-label">IGDB:</span> ${game.rating ? Math.round(game.rating) + '/100' : 'N/A'}</p>
                <p class="game-votes">
                    <span class="likes-label">Likes:</span> <span class="likes-content">${approvalText}</span>
                </p>
            </a>
        `;
        
        return div;
    }

    // Helper functions for cleaner code
    function getCoverUrl(game) {
        return game.cover?.url 
            ? (game.cover.url.startsWith('https:') ? game.cover.url : 'https:' + game.cover.url).replace('t_thumb', 't_cover_big')
            : `${window.baseUrl}/images/default-image.jpg`;
    }

    function getAverageRatingBadge(game) {
        if (game.avg_rating && game.avg_rating > 0) {
            const rating = parseFloat(game.avg_rating);
            const displayRating = rating === 10 ? '10' : rating.toFixed(1);
            const avgRatingClass = rating >= 7 ? 'high' : (rating >= 4 ? 'medium' : 'low');
            return `<div class="avg-rating ${avgRatingClass}">${displayRating} ★</div>`;
        }
        return `<div class="avg-rating none">No rating</div>`;
    }
    
    // Format date helper
    function formatDate(timestamp) {
        if (!timestamp) return 'Unknown';
        const date = new Date(timestamp * 1000);
        return date.toLocaleDateString();
    }
    
    // Render pagination in PornHub style
    function renderPornhubPagination(tab, totalPages, currentPage) {
        const nav = document.getElementById(tab.pagination);
        if (!nav) return;
        
        nav.innerHTML = '';
        if (totalPages <= 1) return;
        
        const ul = document.createElement('ul');
        ul.className = 'pagination';
        
        // Previous button
        const prevLi = document.createElement('li');
        prevLi.className = 'page-item prev-page';
        if (currentPage <= 1) prevLi.classList.add('disabled');
        const prevLink = document.createElement('a');
        prevLink.className = 'page-link';
        prevLink.innerHTML = '«';
        prevLink.href = '#';
        prevLink.setAttribute('aria-label', 'Previous');
        if (currentPage > 1) {
            prevLink.addEventListener('click', function(e) {
                e.preventDefault();
                loadTab(tab, currentPage - 1);
            });
        }
        prevLi.appendChild(prevLink);
        ul.appendChild(prevLi);
        
        // First 3 pages
        for (let i = 1; i <= Math.min(3, totalPages); i++) {
            addPageButton(ul, i, currentPage, tab);
        }
        
        // First ellipsis if needed
        if (totalPages > 3 && currentPage > 4) {
            addEllipsis(ul);
        }
        
        // Current page if not in first 3 or last page
        if (currentPage > 3 && currentPage < totalPages - 2) {
            addPageButton(ul, currentPage, currentPage, tab);
        }
        
        // Second ellipsis if needed
        if (totalPages > 6 && currentPage < totalPages - 3) {
            addEllipsis(ul);
        }
        
        // Last page if not in first 3
        if (totalPages > 3 && totalPages > currentPage + 2) {
            addPageButton(ul, totalPages, currentPage, tab);
        }
        
        // Next button
        const nextLi = document.createElement('li');
        nextLi.className = 'page-item next-page';
        if (currentPage >= totalPages) nextLi.classList.add('disabled');
        const nextLink = document.createElement('a');
        nextLink.className = 'page-link';
        nextLink.innerHTML = '»';
        nextLink.href = '#';
        nextLink.setAttribute('aria-label', 'Next');
        if (currentPage < totalPages) {
            nextLink.addEventListener('click', function(e) {
                e.preventDefault();
                loadTab(tab, currentPage + 1);
            });
        }
        nextLi.appendChild(nextLink);
        ul.appendChild(nextLi);
        nav.appendChild(ul);
    }
    
    // Helper function to add page button
    function addPageButton(ul, pageNum, currentPage, tab) {
        const li = document.createElement('li');
        li.className = 'page-item' + (pageNum === currentPage ? ' active' : '');
        
        const a = document.createElement('a');
        a.className = 'page-link';
        a.href = '#';
        a.textContent = pageNum;
        a.addEventListener('click', function(e) {
            e.preventDefault();
            if (pageNum !== currentPage) {
                loadTab(tab, pageNum);
            }
        });
        
        li.appendChild(a);
        ul.appendChild(li);
        return li;
    }
    
    // Helper function to add ellipsis
    function addEllipsis(ul) {
        const li = document.createElement('li');
        li.className = 'page-item disabled';
        
        const span = document.createElement('span');
        span.className = 'page-link';
        span.textContent = '...';
        
        li.appendChild(span);
        ul.appendChild(li);
    }
    
    // API call helper function
    function callApi(endpoint, options = {}) {
        if (!options.headers) {
            options.headers = {};
        }
        
        // Add JWT token if available
        const token = getJwtToken();
        if (token) {
            options.headers['Authorization'] = `Bearer ${token}`;
        }
        
        // Add CSRF token if available
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (csrfToken) {
            options.headers['X-CSRF-Token'] = csrfToken;
        }
        
        // Always include credentials
        options.credentials = 'include';
        
        return fetch(`${window.baseUrl}/api.php?${endpoint}`, options)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`API error: ${response.status}`);
                }
                return response.json();
            });
    }
    
    // Get JWT token from cookies
    function getJwtToken() {
        const name = 'jwt_token=';
        const decodedCookie = decodeURIComponent(document.cookie);
        const ca = decodedCookie.split(';');
        for (let i = 0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) === ' ') {
                c = c.substring(1);
            }
            if (c.indexOf(name) === 0) {
                return c.substring(name.length, c.length);
            }
        }
        return '';
    }

    // Setup tab change listeners for pagination scrolling
    function setupTabChangeListeners() {
        // For Bootstrap tabs
        const tabButtons = document.querySelectorAll('[data-bs-toggle="tab"]');
        tabButtons.forEach(tab => {
            // Use show.bs.tab instead of shown.bs.tab to prevent default scrolling
            tab.addEventListener('show.bs.tab', function(e) {
                // Store current scroll position before tab changes
                window.tabScrollPosition = window.scrollY;
            });
            
            tab.addEventListener('shown.bs.tab', function(e) {
                const targetTabId = e.target.getAttribute('data-bs-target').substring(1);
                const tabConfig = Object.values(tabs).find(t => t.id === targetTabId + '-games');
                
                // Restore scroll position immediately
                window.scrollTo(0, window.tabScrollPosition || 0);
                
                if (tabConfig) {
                    // Delay pagination scrolling slightly
                    setTimeout(() => {
                        scrollActivePaginationIntoView(tabConfig.pagination);
                    }, 100);
                }
            });
        });
    }

    // Helper function for debouncing
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
})();