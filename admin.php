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
    <link rel="stylesheet" href="css/base.css">
<link rel="stylesheet" href="css/components.css">
<link rel="stylesheet" href="css/layout.css">
<link rel="stylesheet" href="css/game-cards.css">
<link rel="stylesheet" href="css/mobile-controller.css">
<link rel="stylesheet" href="css/mobile-nav.css">
<link rel="stylesheet" href="css/responsive.css">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
</head>
<body>
    <div style="text-align: center; margin: 20px;">
    <a href="index.php" style="display: inline-block; margin-bottom: 20px; padding: 8px 16px; background-color: #000; color: #00ff00; border: 2px solid #ff00ff; text-decoration: none; font-family: 'Courier New', Courier, monospace; font-weight: bold; text-shadow: 1px 1px #ff00ff;">
            &larr; Back to Homepage
        </a>
        <h2>Admin Dashboard - Manage Users</h2>
        <div style="text-align: center; margin: 20px; color: #00ff00; text-shadow: 2px 2px #ff00ff; font-family: 'Courier New', Courier, monospace;">
            <h3>Statistics</h3>
            <p style="margin: 5px 0;">Total Games in Database: <span id="total-games" style="color: #ff00ff; text-shadow: 1px 1px #00ff00;">Loading...</span></p>
            <p style="margin: 5px 0;">Total Pageviews: <span id="total-pageviews" style="color: #ff00ff; text-shadow: 1px 1px #00ff00;">Loading...</span></p>
        </div>
        <div style="text-align: center; margin: 20px;">
            <input type="text" id="search-user" placeholder="Search by username or ID" style="padding: 5px; font-family: 'Courier New', Courier, monospace; color: #ff00ff; background-color: #000; border: 2px solid #00ff00;">
            <label style="color: #00ff00; font-family: 'Courier New', Courier, monospace; margin-left: 10px;">
                <input type="checkbox" id="filter-admin"> Show Admins
            </label>
            <label style="color: #00ff00; font-family: 'Courier New', Courier, monospace; margin-left: 10px;">
                <input type="checkbox" id="filter-moderator"> Show Moderators
            </label>
            <button onclick="loadUsers(1)" style="padding: 5px; font-family: 'Courier New', Courier, monospace; color: #00ff00; background-color: #000; border: 2px solid #ff00ff; cursor: pointer;">Search</button>
        </div>
        <table style="margin: 0 auto; border-collapse: collapse; width: 80%;">
            <thead>
                <tr>
                    <th style="border: 2px solid #ff00ff; padding: 8px;">Username</th>
                    <th style="border: 2px solid #ff00ff; padding: 8px;">Admin</th>
                    <th style="border: 2px solid #ff00ff; padding: 8px;">Moderator</th>
                    <th style="border: 2px solid #ff00ff; padding: 8px;">Banned</th>
                    <th style="border: 2px solid #ff00ff; padding: 8px;">Actions</th>
                </tr>
            </thead>
            <tbody id="user-table">
                <!-- Users will be populated here by JavaScript -->
            </tbody>
        </table>
        <div style="text-align: center; margin: 20px;">
            <button id="prev-page" disabled>Previous</button>
            <span id="page-info"></span>
            <button id="next-page" disabled>Next</button>
        </div>
        <div style="text-align: center; margin: 20px;">
            <h3 style="color: #00ff00; text-shadow: 2px 2px #ff00ff; font-family: 'Courier New', Courier, monospace;">Moderators</h3>
            <table style="margin: 0 auto; border-collapse: collapse; width: 80%;">
                <thead>
                    <tr>
                        <th style="border: 2px solid #ff00ff; padding: 8px;">Username</th>
                        <th style="border: 2px solid #ff00ff; padding: 8px;">Admin</th>
                        <th style="border: 2px solid #ff00ff; padding: 8px;">Moderator</th>
                        <th style="border: 2px solid #ff00ff; padding: 8px;">Banned</th>
                        <th style="border: 2px solid #ff00ff; padding: 8px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="moderator-table">
                    <!-- Moderators will be populated here by JavaScript -->
                </tbody>
            </table>
        </div>
        <div style="text-align: center; margin: 20px;">
            <h3 style="color: #00ff00; text-shadow: 2px 2px #ff00ff; font-family: 'Courier New', Courier, monospace;">Admins</h3>
            <table style="margin: 0 auto; border-collapse: collapse; width: 80%;">
                <thead>
                    <tr>
                        <th style="border: 2px solid #ff00ff; padding: 8px;">Username</th>
                        <th style="border: 2px solid #ff00ff; padding: 8px;">Admin</th>
                        <th style="border: 2px solid #ff00ff; padding: 8px;">Moderator</th>
                        <th style="border: 2px solid #ff00ff; padding: 8px;">Banned</th>
                        <th style="border: 2px solid #ff00ff; padding: 8px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="admin-table">
                    <!-- Admins will be populated here by JavaScript -->
                </tbody>
            </table>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        fetch(`${baseUrl}/api.php?action=incrementPageview`, { method: 'POST' })
            .then(response => response.json())
            .catch(error => console.error('Error incrementing pageview:', error));
    });

    function loadStatistics() {
        // Fetch total games
        fetch(`${baseUrl}/api.php?action=getStatistics`)
            .then(response => {
                if (!response.ok) throw new Error('Failed to fetch statistics: ' + response.status);
                return response.json();
            })
            .then(data => {
                document.getElementById('total-games').textContent = data.error ? 'Error' : data.total_games;
            })
            .catch(error => {
                console.error('Error fetching total games:', error);
                document.getElementById('total-games').textContent = 'Error';
            });

        // Fetch total pageviews
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
    const baseUrl = '/gamerating'; // Relative URL
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
                        window.location.href = 'login.php'; // Redirect to login on 401
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
                        <td style="border: 2px solid #ff00ff; padding: 8px;">${user.username}</td>
                        <td style="border: 2px solid #ff00ff; padding: 8px;">${user.is_admin ? 'Yes' : 'No'}</td>
                        <td style="border: 2px solid #ff00ff; padding: 8px;">${user.is_moderator ? 'Yes' : 'No'}</td>
                        <td style="border: 2px solid #ff00ff; padding: 8px;">${user.is_banned ? 'Yes' : 'No'}</td>
                        <td style="border: 2px solid #ff00ff; padding: 8px;">
                            <button onclick="toggleBan(${user.id}, ${user.is_banned ? 1 : 0})">${user.is_banned ? 'Unban' : 'Ban'}</button>
                            <button onclick="toggleModerator(${user.id}, ${user.is_moderator ? 1 : 0})">${user.is_moderator ? 'Remove Moderator' : 'Set Moderator'}</button>
                            <button onclick="toggleAdmin(${user.id}, ${user.is_admin ? 1 : 0})">${user.is_admin ? 'Remove Admin' : 'Set Admin'}</button>
                        </td>
                    `;
                    userTable.appendChild(row);
                });

                // Update pagination controls
                const totalPages = data.total_pages;
                document.getElementById('page-info').textContent = `Page ${data.current_page} of ${totalPages}`;
                document.getElementById('prev-page').disabled = data.current_page === 1;
                document.getElementById('next-page').disabled = data.current_page === totalPages;
            })
            .catch(error => {
                console.error('Error fetching users:', error);
                const userTable = document.getElementById('user-table');
                userTable.innerHTML = `<tr><td colspan="5" style="color: #ff00ff;">Error: ${error.message}</td></tr>`;
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
                        window.location.href = 'login.php'; // Redirect to login on 401
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
                        <td style="border: 2px solid #ff00ff; padding: 8px;">${user.username}</td>
                        <td style="border: 2px solid #ff00ff; padding: 8px;">${user.is_admin ? 'Yes' : 'No'}</td>
                        <td style="border: 2px solid #ff00ff; padding: 8px;">${user.is_moderator ? 'Yes' : 'No'}</td>
                        <td style="border: 2px solid #ff00ff; padding: 8px;">${user.is_banned ? 'Yes' : 'No'}</td>
                        <td style="border: 2px solid #ff00ff; padding: 8px;">
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
                moderatorTable.innerHTML = `<tr><td colspan="5" style="color: #ff00ff;">Error: ${error.message}</td></tr>`;
            });
    }

    function loadAdmins() {
        fetch(`${baseUrl}/api.php?action=getAdmins`, {
            headers: {
                'Authorization': `Bearer ${accessToken}`
            }
        })
            .then(response => {
                if (!response.ok) {
                    if (response.status === 401) {
                        window.location.href = 'login.php'; // Redirect to login on 401
                        throw new Error('Unauthorized: Please log in');
                    }
                    throw new Error('Failed to fetch admins: ' + response.status);
                }
                return response.json();
            })
            .then(admins => {
                const adminTable = document.getElementById('admin-table');
                adminTable.innerHTML = '';
                admins.forEach(user => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td style="border: 2px solid #ff00ff; padding: 8px;">${user.username}</td>
                        <td style="border: 2px solid #ff00ff; padding: 8px;">${user.is_admin ? 'Yes' : 'No'}</td>
                        <td style="border: 2px solid #ff00ff; padding: 8px;">${user.is_moderator ? 'Yes' : 'No'}</td>
                        <td style="border: 2px solid #ff00ff; padding: 8px;">${user.is_banned ? 'Yes' : 'No'}</td>
                        <td style="border: 2px solid #ff00ff; padding: 8px;">
                            <button onclick="toggleBan(${user.id}, ${user.is_banned ? 1 : 0})">${user.is_banned ? 'Unban' : 'Ban'}</button>
                            <button onclick="toggleModerator(${user.id}, ${user.is_moderator ? 1 : 0})">${user.is_moderator ? 'Remove Moderator' : 'Set Moderator'}</button>
                            <button onclick="toggleAdmin(${user.id}, ${user.is_admin ? 1 : 0})">${user.is_admin ? 'Remove Admin' : 'Set Admin'}</button>
                        </td>
                    `;
                    adminTable.appendChild(row);
                });
            })
            .catch(error => {
                console.error('Error fetching admins:', error);
                const adminTable = document.getElementById('admin-table');
                adminTable.innerHTML = `<tr><td colspan="5" style="color: #ff00ff;">Error: ${error.message}</td></tr>`;
            });
    }

    document.getElementById('prev-page').addEventListener('click', () => {
        if (currentPage > 1) {
            loadUsers(currentPage - 1);
        }
    });

    document.getElementById('next-page').addEventListener('click', () => {
        loadUsers(currentPage + 1);
    });

    function toggleBan(userId, isBanned) {
        fetch(`${baseUrl}/api.php?action=${isBanned ? 'unbanUser' : 'banUser'}&id=${userId}`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${accessToken}`,
                'X-CSRF-Token': getCsrfToken() || ""
            }
        })
            .then(response => {
                if (!response.ok) {
                    if (response.status === 401) {
                        window.location.href = 'login.php'; // Redirect to login on 401
                        throw new Error('Unauthorized: Please log in');
                    }
                    throw new Error('Failed to toggle ban: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    showNotification('Error: ' + data.error);
                } else {
                    showNotification(data.message);
                    loadUsers(currentPage);
                    loadModerators();
                    loadAdmins();
                }
            })
            .catch(error => showNotification('Error: ' + error.message));
    }

    function toggleModerator(userId, isModerator) {
        fetch(`${baseUrl}/api.php?action=${isModerator ? 'removeModerator' : 'setModerator'}&id=${userId}`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${accessToken}`,
                'X-CSRF-Token': getCsrfToken() || ""
            }
        })
            .then(response => {
                if (!response.ok) {
                    if (response.status === 401) {
                        window.location.href = 'login.php'; // Redirect to login on 401
                        throw new Error('Unauthorized: Please log in');
                    }
                    throw new Error('Failed to toggle moderator: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    showNotification('Error: ' + data.error);
                } else {
                    showNotification(data.message);
                    loadUsers(currentPage);
                    loadModerators();
                    loadAdmins();
                }
            })
            .catch(error => showNotification('Error: ' + error.message));
    }

    function toggleAdmin(userId, isAdmin) {
        fetch(`${baseUrl}/api.php?action=${isAdmin ? 'removeAdmin' : 'setAdmin'}&id=${userId}`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${accessToken}`,
                'X-CSRF-Token': getCsrfToken() || ""
            }
        })
            .then(response => {
                if (!response.ok) {
                    if (response.status === 401) {
                        window.location.href = 'login.php'; // Redirect to login on 401
                        throw new Error('Unauthorized: Please log in');
                    }
                    throw new Error('Failed to toggle admin: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    showNotification('Error: ' + data.error);
                } else {
                    showNotification(data.message);
                    loadUsers(currentPage);
                    loadModerators();
                    loadAdmins();
                }
            })
            .catch(error => showNotification('Error: ' + error.message));
    }

    function getCsrfToken() {
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        return metaTag ? metaTag.content : "";
    }

    // Load statistics and users when the page loads
    loadStatistics();
    loadUsers();
    loadModerators();
    loadAdmins();
    </script>
</body>
</html>