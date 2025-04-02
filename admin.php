<?php
include 'db_connect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$stmt = $db->prepare('SELECT is_admin FROM users WHERE id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
if (!$result || $result->num_rows === 0) {
    header('Location: login.php');
    exit;
}
$user = $result->fetch_assoc();
$stmt->close();
if (!$user['is_admin']) {
    header('Location: index.php'); // Redirect non-admins to the homepage
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Game Rater '98</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/admin.css">

    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-link">← Back to Homepage</a>
        <h2>Admin Dashboard - Manage Users</h2>
        <div class="stats-section">
            <h3>Statistics</h3>
            <p>Total Games in Database: <span id="stats-games">Loading...</span></p>
            <p>Total Pageviews: <span id="total-pageviews">Loading...</span></p>
        </div>
        <div class="card-row">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Games</h5>
                    <p class="card-text" id="card-games">Loading...</p>
                </div>
            </div>
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Reviews</h5>
                    <p class="card-text" id="total-reviews">Loading...</p>
                </div>
            </div>
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Users</h5>
                    <p class="card-text" id="total-users">Loading...</p>
                </div>
            </div>
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h5 class="card-title">Review Rate</h5>
                    <p class="card-text" id="review-rate">Loading...</p>
                </div>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <ul class="nav nav-tabs" id="adminTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button" role="tab" aria-controls="users" aria-selected="true">Users</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="moderators-tab" data-bs-toggle="tab" data-bs-target="#moderators" type="button" role="tab" aria-controls="moderators" aria-selected="false">Moderators</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="anonymous-users-tab" data-bs-toggle="tab" data-bs-target="#anonymous-users" type="button" role="tab" aria-controls="anonymous-users" aria-selected="false">Anonymous Users</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="reported-reviews-tab" data-bs-toggle="tab" data-bs-target="#reported-reviews" type="button" role="tab" aria-controls="reported-reviews" aria-selected="false">Reported Reviews</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="reported-comments-tab" data-bs-toggle="tab" data-bs-target="#reported-comments" type="button" role="tab" aria-controls="reported-comments" aria-selected="false">Reported Comments</button>
            </li>
        </ul>

        <!-- Tabs Content -->
        <div class="tab-content" id="adminTabsContent">
            <!-- Users Tab -->
            <div class="tab-pane fade show active" id="users" role="tabpanel" aria-labelledby="users-tab">
                <div class="search-bar">
                    <input type="text" id="search-user" placeholder="Search by username or ID">
                    <label>
                        <input type="checkbox" id="filter-admin"> Show Admins
                    </label>
                    <label>
                        <input type="checkbox" id="filter-moderator"> Show Moderators
                    </label>
                    <button onclick="loadUsers(1)">Search</button>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Admin</th>
                            <th>Moderator</th>
                            <th>Banned</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="user-table">
                        <!-- Users will be populated here by JavaScript -->
                    </tbody>
                </table>
                <div class="pagination">
                    <button id="prev-page" disabled>Previous</button>
                    <span id="page-info"></span>
                    <button id="next-page" disabled>Next</button>
                </div>
            </div>

            <!-- Moderators Tab -->
            <div class="tab-pane fade" id="moderators" role="tabpanel" aria-labelledby="moderators-tab">
                <table>
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Admin</th>
                            <th>Moderator</th>
                            <th>Banned</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="moderator-table">
                        <!-- Moderators will be populated here by JavaScript -->
                    </tbody>
                </table>
            </div>

            <!-- Anonymous Users Tab -->
            <div class="tab-pane fade" id="anonymous-users" role="tabpanel" aria-labelledby="anonymous-users-tab">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>IP Address</th>
                            <th>First Seen</th>
                            <th>Last Seen</th>
                            <th>Linked User</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="anonymous-table">
                        <!-- Anonymous users will be populated here by JavaScript -->
                    </tbody>
                </table>
                <div class="pagination">
                    <button id="anon-prev-page" disabled>Previous</button>
                    <span id="anon-page-info">Page 1 of 1</span>
                    <button id="anon-next-page" disabled>Next</button>
                </div>
            </div>

            <!-- Reported Reviews Tab -->
            <div class="tab-pane fade" id="reported-reviews" role="tabpanel" aria-labelledby="reported-reviews-tab">
                <table>
                    <thead>
                        <tr>
                            <th>Review</th>
                            <th>Game</th>
                            <th>Reporter</th>
                            <th>Reason</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="reports-table">
                        <!-- Reports will be populated here by JavaScript -->
                    </tbody>
                </table>
                <div class="pagination">
                    <button id="reports-prev-page" disabled>Previous</button>
                    <span id="reports-page-info">Page 1 of 1</span>
                    <button id="reports-next-page" disabled>Next</button>
                </div>
            </div>

            <!-- Reported Comments Tab Content -->
<div class="tab-pane fade" id="reported-comments" role="tabpanel" aria-labelledby="reported-comments-tab">
    <div class="admin-section">
        <h3>Reported Comments</h3>
        <div class="table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Comment</th>
                        <th>Review</th>
                        <th>Game</th>
                        <th>Reporter</th>
                        <th>Reason</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="comments-reports-table">
                    <!-- Comment reports will be populated here -->
                </tbody>
            </table>
            <div class="pagination">
                <button id="comments-reports-prev-page" disabled>Previous</button>
                <span id="comments-reports-page-info">Page 1 of 1</span>
                <button id="comments-reports-next-page" disabled>Next</button>
            </div>
        </div>
    </div>
</div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        fetch(`${baseUrl}/api.php?action=incrementPageview`, { method: 'POST' })
            .then(response => response.json())
            .catch(error => console.error('Error incrementing pageview:', error));

        // Load statistics on page load
        loadStatistics();

        // Load content for the active tab (Users) on page load
        loadUsers();

        // Add event listeners for tab switching
        const tabs = document.querySelectorAll('#adminTabs .nav-link');
        tabs.forEach(tab => {
            tab.addEventListener('shown.bs.tab', function (event) {
                const targetTab = event.target.id;
                if (targetTab === 'users-tab') {
                    loadUsers();
                } else if (targetTab === 'moderators-tab') {
                    loadModerators();
                } else if (targetTab === 'anonymous-users-tab') {
                    loadAnonymousUsers();
                } else if (targetTab === 'reported-reviews-tab') {
                    loadReportedReviews();
                } else if (targetTab === 'reported-comments-tab') {
                    loadReportedComments();
                }
            });
        });
    });

    function loadStatistics() {
        fetch(`${baseUrl}/api.php?action=getStatistics`)
            .then(response => {
                if (!response.ok) throw new Error('Failed to fetch statistics: ' + response.status);
                return response.json();
            })
            .then(data => {
                console.log('Statistics data received:', data);
                if (data.success !== undefined) {
                    if (data.success) {
                        document.getElementById('stats-games').textContent = data.total_games.toLocaleString();
                        document.getElementById('card-games').textContent = data.total_games.toLocaleString();
                        document.getElementById('total-reviews').textContent = data.total_reviews.toLocaleString();
                        document.getElementById('total-users').textContent = data.total_users.toLocaleString();
                        const reviewRate = data.total_games > 0 ? 
                            (data.total_reviews / data.total_games).toFixed(2) : 
                            '0.00';
                        document.getElementById('review-rate').textContent = `${reviewRate} reviews/game`;
                    } else {
                        const errorMsg = data.error || 'Unknown error';
                        console.error('Error in statistics data:', errorMsg);
                        document.getElementById('stats-games').textContent = 'Error: ' + errorMsg;
                        document.getElementById('card-games').textContent = 'Error';
                        document.getElementById('total-reviews').textContent = 'Error';
                        document.getElementById('total-users').textContent = 'Error';
                        document.getElementById('review-rate').textContent = 'Error';
                    }
                } else {
                    document.getElementById('stats-games').textContent = data.error ? 'Error' : data.total_games;
                    document.getElementById('card-games').textContent = data.error ? 'Error' : data.total_games;
                }
            })
            .catch(error => {
                console.error('Error fetching statistics:', error);
                document.getElementById('stats-games').textContent = 'Error: ' + error.message;
                document.getElementById('card-games').textContent = 'Error';
                document.getElementById('total-reviews').textContent = 'Error';
                document.getElementById('total-users').textContent = 'Error';
                document.getElementById('review-rate').textContent = 'Error';
            });

        fetch(`${baseUrl}/api.php?action=getPageviews`)
            .then(response => {
                if (!response.ok) throw new Error('Failed to fetch pageviews: ' + response.status);
                return response.json();
            })
            .then(data => {
                document.getElementById('total-pageviews').textContent = data.error ? 'Error' : data.total_pageviews;
            })
            .catch(error => {
                console.error('Error fetching total pageviews:', error);
                document.getElementById('total-pageviews').textContent = 'Error';
            });
    }

    let currentPage = 1;
    const usersPerPage = 10;
    const baseUrl = '/gamerating';
    const accessToken = '<?php echo isset($_SESSION['access_token']) ? $_SESSION['access_token'] : ''; ?>';

    function loadUsers(page = 1) {
        currentPage = page;
        const searchQuery = document.getElementById('search-user').value;
        const filterAdmin = document.getElementById('filter-admin').checked ? 1 : 0;
        const filterModerator = document.getElementById('filter-moderator').checked ? 1 : 0;
        const url = `${baseUrl}/api.php?action=getUsers&page=${page}&limit=${usersPerPage}${searchQuery ? `&search=${encodeURIComponent(searchQuery)}` : ''}&filter_admin=${filterAdmin}&filter_moderator=${filterModerator}`;
        fetch(url, {
            headers: {
                'Authorization': `Bearer ${accessToken}`
            }
        })
            .then(response => {
                if (!response.ok) {
                    if (response.status === 401) {
                        window.location.href = 'login.php';
                        throw new Error('Unauthorized: Please log in');
                    }
                    throw new Error('Failed to fetch users: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                const userTable = document.getElementById('user-table');
                userTable.innerHTML = '';
                data.users.forEach(user => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${user.username}</td>
                        <td>${user.is_admin ? 'Yes' : 'No'}</td>
                        <td>${user.is_moderator ? 'Yes' : 'No'}</td>
                        <td>${user.is_banned ? 'Yes' : 'No'}</td>
                        <td>
                            <button onclick="toggleBan(${user.id}, ${user.is_banned ? 1 : 0})">${user.is_banned ? 'Unban' : 'Ban'}</button>
                            <button onclick="toggleModerator(${user.id}, ${user.is_moderator ? 1 : 0})">${user.is_moderator ? 'Remove Moderator' : 'Set Moderator'}</button>
                            <button onclick="toggleAdmin(${user.id}, ${user.is_admin ? 1 : 0})">${user.is_admin ? 'Remove Admin' : 'Set Admin'}</button>
                        </td>
                    `;
                    userTable.appendChild(row);
                });

                const totalPages = data.total_pages;
                document.getElementById('page-info').textContent = `Page ${data.current_page} of ${totalPages}`;
                document.getElementById('prev-page').disabled = data.current_page === 1;
                document.getElementById('next-page').disabled = data.current_page === totalPages;
            })
            .catch(error => {
                console.error('Error fetching users:', error);
                const userTable = document.getElementById('user-table');
                userTable.innerHTML = `<tr><td colspan="5">Error: ${error.message}</td></tr>`;
                document.getElementById('page-info').textContent = 'Page 1 of 1';
                document.getElementById('prev-page').disabled = true;
                document.getElementById('next-page').disabled = true;
            });
    }

    function loadModerators() {
        fetch(`${baseUrl}/api.php?action=getModerators`, {
            headers: {
                'Authorization': `Bearer ${accessToken}`
            }
        })
            .then(response => {
                if (!response.ok) {
                    if (response.status === 401) {
                        window.location.href = 'login.php';
                        throw new Error('Unauthorized: Please log in');
                    }
                    throw new Error('Failed to fetch moderators: ' + response.status);
                }
                return response.json();
            })
            .then(moderators => {
                const moderatorTable = document.getElementById('moderator-table');
                moderatorTable.innerHTML = '';
                moderators.forEach(user => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${user.username}</td>
                        <td>${user.is_admin ? 'Yes' : 'No'}</td>
                        <td>${user.is_moderator ? 'Yes' : 'No'}</td>
                        <td>${user.is_banned ? 'Yes' : 'No'}</td>
                        <td>
                            <button onclick="toggleBan(${user.id}, ${user.is_banned ? 1 : 0})">${user.is_banned ? 'Unban' : 'Ban'}</button>
                            <button onclick="toggleModerator(${user.id}, ${user.is_moderator ? 1 : 0})">${user.is_moderator ? 'Remove Moderator' : 'Set Moderator'}</button>
                            <button onclick="toggleAdmin(${user.id}, ${user.is_admin ? 1 : 0})">${user.is_admin ? 'Remove Admin' : 'Set Admin'}</button>
                        </td>
                    `;
                    moderatorTable.appendChild(row);
                });
            })
            .catch(error => {
                console.error('Error fetching moderators:', error);
                const moderatorTable = document.getElementById('moderator-table');
                moderatorTable.innerHTML = `<tr><td colspan="5">Error: ${error.message}</td></tr>`;
            });
    }

    let anonCurrentPage = 1;
    const anonUsersPerPage = 10;

    function loadAnonymousUsers(page = 1) {
        anonCurrentPage = page;
        fetch(`${baseUrl}/api.php?action=getAnonymousUsers&page=${page}&limit=${anonUsersPerPage}`, {
            headers: {
                'Authorization': `Bearer ${accessToken}`
            }
        })
            .then(response => {
                if (!response.ok) {
                    if (response.status === 401) {
                        window.location.href = 'login.php';
                        throw new Error('Unauthorized: Please log in');
                    }
                    throw new Error('Failed to fetch anonymous users: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                const anonTable = document.getElementById('anonymous-table');
                anonTable.innerHTML = '';
                
                if (!data.anonymous_users || data.anonymous_users.length === 0) {
                    anonTable.innerHTML = `<tr><td colspan="7">No anonymous users found</td></tr>`;
                    return;
                }
                
                data.anonymous_users.forEach(user => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${user.id}</td>
                        <td>${user.ip_address || 'Unknown'}</td>
                        <td>${user.first_seen}</td>
                        <td>${user.last_seen}</td>
                        <td>${user.username || 'None'}</td>
                        <td><span class="${user.is_banned ? 'status-banned' : 'status-active'}">${user.is_banned ? 'Banned' : 'Active'}</span></td>
                        <td>
                            <button onclick="toggleAnonBan('${user.token}', ${user.is_banned})">${user.is_banned ? 'Unban' : 'Ban'}</button>
                        </td>
                    `;
                    anonTable.appendChild(row);
                });

                document.getElementById('anon-page-info').textContent = `Page ${data.current_page} of ${data.total_pages || 1}`;
                document.getElementById('anon-prev-page').disabled = data.current_page <= 1;
                document.getElementById('anon-next-page').disabled = data.current_page >= (data.total_pages || 1);
            })
            .catch(error => {
                console.error('Error fetching anonymous users:', error);
                document.getElementById('anonymous-table').innerHTML = 
                    `<tr><td colspan="7">Error: ${error.message}</td></tr>`;
                document.getElementById('anon-page-info').textContent = 'Page 1 of 1';
                document.getElementById('anon-prev-page').disabled = true;
                document.getElementById('anon-next-page').disabled = true;
            });
    }

    function toggleAnonBan(token, isBanned) {
        fetch(`${baseUrl}/api.php?action=${isBanned ? 'unbanAnonymousUser' : 'banAnonymousUser'}&token=${token}`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${accessToken}`,
                'X-CSRF-Token': getCsrfToken() || ""
            }
        })
            .then(response => {
                if (!response.ok) {
                    if (response.status === 401) {
                        window.location.href = 'login.php';
                        throw new Error('Unauthorized: Please log in');
                    }
                    throw new Error('Failed to toggle anonymous ban: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    showNotification('Error: ' + data.error);
                } else {
                    showNotification(data.message || 'Anonymous user status updated');
                    loadAnonymousUsers(anonCurrentPage);
                }
            })
            .catch(error => showNotification('Error: ' + error.message));
    }

    document.getElementById('anon-prev-page').addEventListener('click', () => {
        if (anonCurrentPage > 1) {
            loadAnonymousUsers(anonCurrentPage - 1);
        }
    });

    document.getElementById('anon-next-page').addEventListener('click', () => {
        loadAnonymousUsers(anonCurrentPage + 1);
    });

    let reportsCurrentPage = 1;
    const reportsPerPage = 10;

    function loadReportedReviews(page = 1) {
        reportsCurrentPage = page;
        fetch(`${baseUrl}/api.php?action=getReportedReviews&page=${page}&limit=${reportsPerPage}`, {
            headers: {
                'Authorization': `Bearer ${accessToken}`
            }
        })
            .then(response => {
                if (!response.ok) {
                    if (response.status === 401) {
                        window.location.href = 'login.php';
                        throw new Error('Unauthorized: Please log in');
                    }
                    throw new Error('Failed to fetch reports: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                const reportsTable = document.getElementById('reports-table');
                reportsTable.innerHTML = '';
                
                if (!data.reports || data.reports.length === 0) {
                    reportsTable.innerHTML = `<tr><td colspan="7">No reported reviews found</td></tr>`;
                    return;
                }
                
                data.reports.forEach(report => {
                    const row = document.createElement('tr');
                    const reviewContent = report.review_content.length > 50 ? 
                        report.review_content.substring(0, 50) + '...' : 
                        report.review_content;
                    row.innerHTML = `
                        <td>${reviewContent}</td>
                        <td>${report.game_name}</td>
                        <td>${report.reporter_name || 'Anonymous'}</td>
                        <td>${report.reason}</td>
                        <td>${report.created_at}</td>
                        <td><span class="status-${report.status.toLowerCase()}">${report.status.charAt(0).toUpperCase() + report.status.slice(1)}</span></td>
                        <td>
                            <button onclick="viewReportDetails(${report.id})">View Details</button>
                            <button onclick="viewReport(${report.id}, ${report.review_id}, ${report.game_id})">View on Page</button>
                            <button onclick="dismissReport(${report.id})">Dismiss</button>
                            <button onclick="actionReport(${report.id}, ${report.review_id})">Remove Review</button>
                        </td>
                    `;
                    reportsTable.appendChild(row);
                });

                document.getElementById('reports-page-info').textContent = `Page ${data.current_page} of ${data.total_pages || 1}`;
                document.getElementById('reports-prev-page').disabled = data.current_page <= 1;
                document.getElementById('reports-next-page').disabled = data.current_page >= (data.total_pages || 1);
            })
            .catch(error => {
                console.error('Error fetching reports:', error);
                document.getElementById('reports-table').innerHTML = 
                    `<tr><td colspan="7">Error: ${error.message}</td></tr>`;
                document.getElementById('reports-page-info').textContent = 'Page 1 of 1';
                document.getElementById('reports-prev-page').disabled = true;
                document.getElementById('reports-next-page').disabled = true;
            });
    }

    function getStatusLabel(status) {
        return status.charAt(0).toUpperCase() + status.slice(1);
    }

    function viewReport(reportId, reviewId, gameId) {
        fetch(`${baseUrl}/api.php?action=updateReportStatus&id=${reportId}&status=reviewing`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${accessToken}`,
                'X-CSRF-Token': getCsrfToken() || ""
            }
        }).then(response => response.json());
        
        window.open(`${baseUrl}/game.php?id=${gameId}#review-${reviewId}`, '_blank');
    }

    function dismissReport(reportId) {
        if (!confirm('Are you sure you want to dismiss this report?')) return;
        
        fetch(`${baseUrl}/api.php?action=updateReportStatus&id=${reportId}&status=rejected`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${accessToken}`,
                'X-CSRF-Token': getCsrfToken() || ""
            }
        })
            .then(response => {
                if (!response.ok) throw new Error('Failed to update report status');
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showNotification('Report dismissed successfully');
                    loadReportedReviews(reportsCurrentPage);
                } else {
                    showNotification('Error: ' + (data.error || 'Unknown error'), 'error');
                }
            })
            .catch(error => showNotification('Error: ' + error.message, 'error'));
    }

    function actionReport(reportId, reviewId) {
        if (!confirm('Are you sure you want to remove this review? This action cannot be undone.')) return;
        
        fetch(`${baseUrl}/api.php?action=adminDeleteReview&id=${reviewId}`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${accessToken}`,
                'X-CSRF-Token': getCsrfToken() || ""
            }
        })
            .then(response => {
                if (!response.ok) throw new Error('Failed to delete review');
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    return fetch(`${baseUrl}/api.php?action=updateReportStatus&id=${reportId}&status=actioned`, {
                        method: 'POST',
                        headers: {
                            'Authorization': `Bearer ${accessToken}`,
                            'X-CSRF-Token': getCsrfToken() || ""
                        }
                    });
                } else {
                    throw new Error(data.error || 'Failed to delete review');
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Review removed and report marked as actioned');
                    loadReportedReviews(reportsCurrentPage);
                } else {
                    showNotification('Error: ' + (data.error || 'Unknown error'), 'error');
                }
            })
            .catch(error => showNotification('Error: ' + error.message, 'error'));
    }

    document.getElementById('reports-prev-page').addEventListener('click', () => {
        if (reportsCurrentPage > 1) {
            loadReportedReviews(reportsCurrentPage - 1);
        }
    });

    document.getElementById('reports-next-page').addEventListener('click', () => {
        loadReportedReviews(reportsCurrentPage + 1);
    });

    function viewReportDetails(reportId) {
        fetch(`${baseUrl}/api.php?action=getReportDetails&id=${reportId}`, {
            headers: {
                'Authorization': `Bearer ${accessToken}`
            }
        })
            .then(response => {
                if (!response.ok) throw new Error('Failed to fetch report details');
                return response.json();
            })
            .then(data => {
                if (!data.report) {
                    showNotification('Error: Report not found', 'error');
                    return;
                }
                
                const report = data.report;
                const modal = document.createElement('div');
                modal.className = 'report-details-modal';
                
                modal.innerHTML = `
                    <div class="report-details-modal-content">
                        <h2>Report Details</h2>
                        <div>
                            <h3>Report Information</h3>
                            <p><strong>Status:</strong> <span class="status-${report.status.toLowerCase()}">${getStatusLabel(report.status)}</span></p>
                            <p><strong>Reporter:</strong> ${report.reporter_name || 'Anonymous'}</p>
                            <p><strong>Reason:</strong> ${report.reason}</p>
                            <p><strong>Date Reported:</strong> ${report.created_at}</p>
                        </div>
                        <div>
                            <h3>Review Content</h3>
                            <div class="content-box">
                                ${report.review_content}
                            </div>
                        </div>
                        <div>
                            <h3>Reporter's Additional Details</h3>
                            <div class="content-box">
                                ${report.details || '<em>No additional details provided</em>'}
                            </div>
                        </div>
                        <div class="actions">
                            <div class="action-buttons">
                                <button onclick="dismissReport(${report.id}); document.querySelector('.report-details-modal').remove();">
                                    Dismiss Report
                                </button>
                                <button class="remove" onclick="actionReport(${report.id}, ${report.review_id}); document.querySelector('.report-details-modal').remove();">
                                    Remove Review
                                </button>
                            </div>
                            <button class="close-button" onclick="document.querySelector('.report-details-modal').remove();">
                                Close
                            </button>
                        </div>
                    </div>
                `;
                
                document.body.appendChild(modal);
            })
            .catch(error => {
                showNotification('Error: ' + error.message, 'error');
            });
    }

    // Variables for comment reports pagination
    let commentReportsCurrentPage = 1;
    const commentReportsPerPage = 10;

    // Function to load reported comments
    function loadReportedComments(page = 1) {
        commentReportsCurrentPage = page;
        fetch(`${baseUrl}/api.php?action=getReportedComments&page=${page}&limit=${commentReportsPerPage}`, {
            headers: {
                'Authorization': `Bearer ${accessToken}`
            }
        })
            .then(response => {
                if (!response.ok) {
                    if (response.status === 401) {
                        window.location.href = 'login.php';
                        throw new Error('Unauthorized: Please log in');
                    }
                    throw new Error('Failed to fetch comment reports: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                const reportsTable = document.getElementById('comments-reports-table');
                reportsTable.innerHTML = '';
                
                if (!data.reports || data.reports.length === 0) {
                    reportsTable.innerHTML = `<tr><td colspan="8">No reported comments found</td></tr>`;
                    return;
                }
                
                data.reports.forEach(report => {
                    const row = document.createElement('tr');
                    const commentContent = report.comment_content.length > 50 ? 
                        report.comment_content.substring(0, 50) + '...' : 
                        report.comment_content;
                    row.innerHTML = `
                        <td>${commentContent}</td>
                        <td>${report.review_title}</td>
                        <td>${report.game_name}</td>
                        <td>${report.reporter_name || 'Anonymous'}</td>
                        <td>${report.reason}</td>
                        <td>${report.created_at}</td>
                        <td><span class="status-${report.status.toLowerCase()}">${report.status.charAt(0).toUpperCase() + report.status.slice(1)}</span></td>
                        <td>
                            <button onclick="viewCommentReportDetails(${report.id})">View Details</button>
                            <button onclick="viewCommentReport(${report.id}, ${report.comment_id}, ${report.review_id}, ${report.game_id})">View on Page</button>
                            <button onclick="dismissCommentReport(${report.id})">Dismiss</button>
                            <button onclick="actionCommentReport(${report.id}, ${report.comment_id})">Remove Comment</button>
                        </td>
                    `;
                    reportsTable.appendChild(row);
                });

                document.getElementById('comments-reports-page-info').textContent = `Page ${data.current_page} of ${data.total_pages || 1}`;
                document.getElementById('comments-reports-prev-page').disabled = data.current_page <= 1;
                document.getElementById('comments-reports-next-page').disabled = data.current_page >= (data.total_pages || 1);
            })
            .catch(error => {
                console.error('Error fetching comment reports:', error);
                document.getElementById('comments-reports-table').innerHTML = 
                    `<tr><td colspan="8">Error: ${error.message}</td></tr>`;
                document.getElementById('comments-reports-page-info').textContent = 'Page 1 of 1';
                document.getElementById('comments-reports-prev-page').disabled = true;
                document.getElementById('comments-reports-next-page').disabled = true;
            });
    }

    // Function to view comment report details
    function viewCommentReportDetails(reportId) {
        fetch(`${baseUrl}/api.php?action=getCommentReportDetails&id=${reportId}`, {
            headers: {
                'Authorization': `Bearer ${accessToken}`
            }
        })
            .then(response => {
                if (!response.ok) throw new Error('Failed to fetch report details');
                return response.json();
            })
            .then(data => {
                if (!data.report) {
                    showNotification('Error: Report not found', 'error');
                    return;
                }
                
                const report = data.report;
                const modal = document.createElement('div');
                modal.className = 'report-details-modal';
                
                modal.innerHTML = `
                    <div class="report-details-modal-content">
                        <h2>Comment Report Details</h2>
                        <div>
                            <h3>Report Information</h3>
                            <p><strong>Status:</strong> <span class="status-${report.status.toLowerCase()}">${getStatusLabel(report.status)}</span></p>
                            <p><strong>Reporter:</strong> ${report.reporter_name || 'Anonymous'}</p>
                            <p><strong>Reason:</strong> ${report.reason}</p>
                            <p><strong>Date Reported:</strong> ${report.created_at}</p>
                        </div>
                        <div>
                            <h3>Comment Content</h3>
                            <div class="content-box">
                                ${report.comment_content}
                            </div>
                        </div>
                        <div>
                            <h3>Reporter's Additional Details</h3>
                            <div class="content-box">
                                ${report.details || '<em>No additional details provided</em>'}
                            </div>
                        </div>
                        <div class="actions">
                            <div class="action-buttons">
                                <button onclick="dismissCommentReport(${report.id}); document.querySelector('.report-details-modal').remove();">
                                    Dismiss Report
                                </button>
                                <button class="remove" onclick="actionCommentReport(${report.id}, ${report.comment_id}); document.querySelector('.report-details-modal').remove();">
                                    Remove Comment
                                </button>
                            </div>
                            <button class="close-button" onclick="document.querySelector('.report-details-modal').remove();">
                                Close
                            </button>
                        </div>
                    </div>
                `;
                
                document.body.appendChild(modal);
            })
            .catch(error => {
                showNotification('Error: ' + error.message, 'error');
            });
    }

    // View comment report on the page
    function viewCommentReport(reportId, commentId, reviewId, gameId) {
        fetch(`${baseUrl}/api.php?action=updateCommentReportStatus&id=${reportId}&status=reviewing`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${accessToken}`,
                'X-CSRF-Token': getCsrfToken() || ""
            }
        }).then(response => response.json());
        
        window.open(`${baseUrl}/game.php?id=${gameId}#review-${reviewId}`, '_blank');
    }

    // Dismiss a comment report
    function dismissCommentReport(reportId) {
        if (!confirm('Are you sure you want to dismiss this report?')) return;
        
        fetch(`${baseUrl}/api.php?action=updateCommentReportStatus&id=${reportId}&status=rejected`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${accessToken}`,
                'X-CSRF-Token': getCsrfToken() || ""
            }
        })
            .then(response => {
                if (!response.ok) throw new Error('Failed to update report status');
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showNotification('Report dismissed successfully');
                    loadReportedComments(commentReportsCurrentPage);
                } else {
                    showNotification('Error: ' + (data.error || 'Unknown error'), 'error');
                }
            })
            .catch(error => showNotification('Error: ' + error.message, 'error'));
    }

    // Remove a reported comment
    function actionCommentReport(reportId, commentId) {
        if (!confirm('Are you sure you want to remove this comment? This action cannot be undone.')) return;
        
        fetch(`${baseUrl}/api.php?action=adminDeleteComment&id=${commentId}`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${accessToken}`,
                'X-CSRF-Token': getCsrfToken() || ""
            }
        })
            .then(response => {
                if (!response.ok) throw new Error('Failed to delete comment');
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    return fetch(`${baseUrl}/api.php?action=updateCommentReportStatus&id=${reportId}&status=actioned`, {
                        method: 'POST',
                        headers: {
                            'Authorization': `Bearer ${accessToken}`,
                            'X-CSRF-Token': getCsrfToken() || ""
                        }
                    });
                } else {
                    throw new Error(data.error || 'Failed to delete comment');
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Comment removed and report marked as actioned');
                    loadReportedComments(commentReportsCurrentPage);
                } else {
                    showNotification('Error: ' + (data.error || 'Unknown error'), 'error');
                }
            })
            .catch(error => showNotification('Error: ' + error.message, 'error'));
    }

    function getCsrfToken() {
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        return metaTag ? metaTag.content : "";
    }

    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.textContent = message;
        document.body.appendChild(notification);
        setTimeout(() => {
            notification.remove();
        }, 3000);
    }

    // Event listeners for comment reports pagination
    document.getElementById('comments-reports-prev-page').addEventListener('click', () => {
        if (commentReportsCurrentPage > 1) {
            loadReportedComments(commentReportsCurrentPage - 1);
        }
    });

    document.getElementById('comments-reports-next-page').addEventListener('click', () => {
        loadReportedComments(commentReportsCurrentPage + 1);
    });
    </script>
</body>
</html>