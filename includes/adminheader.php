<?php
// Start session to access user data
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Initialize variables
$profileInitial = '?';
$username = 'Guest';
$userType = 'Guest';

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    
    // Fetch user data from database
    $query = "SELECT username, user_type FROM login WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $userData = $result->fetch_assoc();
        $username = htmlspecialchars($userData['username']);
        $userType = $userData['user_type'] == 'user' ? 'User' : 'Admin';
        
        // Get first letter of username for profile picture
        $profileInitial = strtoupper(substr($username, 0, 1));
    }
    $stmt->close();
}
$conn->close();

// Determine current section (admin or user)
$currentPath = $_SERVER['REQUEST_URI'];
$isAdminSection = strpos($currentPath, '/admin/') !== false;
?>

<style>
.header {
    position: fixed;
    top: 0;
    left: 0; /* Changed from 70px to 0 to remove left gap */
    right: 0;
    height: 70px;
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    box-shadow: 0 2px 15px rgba(220, 220, 220, 0.1);
    z-index: 999;
    transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.header-content {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    height: 100%;
    padding: 0 20px;
    max-width: 1400px;
    margin: 0 auto;
    gap: 20px;
}

.header-left {
    display: flex;
    align-items: center;
    flex-shrink: 0;
}

.header-right {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-left: auto;
}

/* Navigation Links */
.header-nav {
    display: flex;
    align-items: center;
    gap: 20px;
}

.nav-link {
    display: flex;
    align-items: center;
    padding: 8px 16px;
    color: #495057;
    text-decoration: none;
    font-weight: 500;
    font-size: 14px;
    border-radius: 20px;
    transition: all 0.3s ease;
    position: relative;
    white-space: nowrap;
    border: 2px solid transparent;
}

.nav-link:hover {
    background: linear-gradient(90deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));
    color: #dc3545;
    text-decoration: none;
    transform: translateY(-2px);
    border-color: rgba(220, 53, 69, 0.2);
}

.nav-link.active {
    background: linear-gradient(135deg, #dc3545, #b02a37);
    color: white;
    border-color: #dc3545;
}

.nav-link.active:hover {
    background: linear-gradient(135deg, #c82333, #a02633);
    color: white;
    border-color: #c82333;
}

.nav-icon {
    width: 18px;
    height: 18px;
    margin-right: 8px;
}

/* View Toggle Button - Special styling for admin/user switch */
.view-toggle {
    background: linear-gradient(135deg, #28a745, #1e7e34);
    color: white;
    border: 2px solid #28a745;
    padding: 8px 16px;
    font-weight: 600;
    position: relative;
    overflow: hidden;
}

.view-toggle:hover {
    background: linear-gradient(135deg, #218838, #1c7430);
    color: white;
    border-color: #218838;
    transform: translateY(-2px);
}

.view-toggle::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s;
}

.view-toggle:hover::before {
    left: 100%;
}

/* Emergency Alert Banner */
.emergency-alert {
    background: linear-gradient(135deg, #dc3545, #b02a37);
    color: white;
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 600;
    border-radius: 15px;
    animation: pulse 2s infinite;
    display: flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
    flex-shrink: 0;
}

@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
    70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
    100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
}

/* Blood Drive Ticker */
.blood-drive-ticker {
    background: #fff3cd;
    color: #856404;
    padding: 6px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    border: 1px solid #ffeaa7;
    display: flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
    flex-shrink: 0;
}

/* Notification Bell */
.notification-wrapper {
    position: relative;
    cursor: pointer;
}

.notification-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #ffffff, #f8f9fa);
    border: 2px solid rgba(220, 53, 69, 0.1);
    color: #495057;
    transition: all 0.3s ease;
    position: relative;
}

.notification-btn:hover {
    background: linear-gradient(135deg, #dc3545, #b02a37);
    color: white;
    transform: scale(1.1);
    border-color: #dc3545;
}

.notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #dc3545;
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 11px;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid white;
    animation: bounce 1s infinite;
}

@keyframes bounce {
    0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
    40% { transform: translateY(-5px); }
    60% { transform: translateY(-3px); }
}

/* Profile Dropdown */
.profile-wrapper {
    position: relative;
}

.profile-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    border-radius: 25px;
    background: linear-gradient(135deg, #ffffff, #f8f9fa);
    border: 2px solid rgba(220, 53, 69, 0.1);
    color: #495057;
    cursor: pointer;
    transition: all 0.3s ease;
}

.profile-btn:hover {
    background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));
    border-color: #dc3545;
    transform: translateY(-2px);
}

.profile-avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: linear-gradient(135deg, #dc3545, #b02a37);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 12px;
}

.profile-info {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}

.profile-name {
    font-weight: 600;
    font-size: 12px;
    line-height: 1;
}

.profile-type {
    font-size: 10px;
    color: #6c757d;
    line-height: 1;
}

.profile-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(220, 53, 69, 0.15);
    border: 1px solid rgba(220, 53, 69, 0.1);
    min-width: 180px;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.3s ease;
    z-index: 1001;
    margin-top: 10px;
}

.profile-wrapper.show-dropdown .profile-dropdown {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 20px;
    color: #495057;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s ease;
    border-bottom: 1px solid rgba(220, 53, 69, 0.05);
}

.dropdown-item:last-child {
    border-bottom: none;
}

.dropdown-item:hover {
    background: linear-gradient(90deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));
    color: #dc3545;
    text-decoration: none;
}

.dropdown-item.logout:hover {
    background: linear-gradient(90deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));
    color: #dc3545;
}

.dropdown-icon {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
}

/* Search Bar */
.search-wrapper {
    position: relative;
    width: 300px;
    flex-shrink: 0;
}

.search-input {
    width: 100%;
    padding: 8px 12px 8px 35px;
    border: 2px solid rgba(220, 53, 69, 0.1);
    border-radius: 20px;
    background: white;
    font-size: 13px;
    transition: all 0.3s ease;
}

.search-input:focus {
    outline: none;
    border-color: #dc3545;
    box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
}

.search-icon {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
    width: 16px;
    height: 16px;
}

/* Mobile Menu Button */
.mobile-menu-btn {
    display: none;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border: none;
    background: linear-gradient(135deg, #dc3545, #b02a37);
    color: white;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.mobile-menu-btn:hover {
    background: linear-gradient(135deg, #c82333, #a02633);
    transform: scale(1.05);
}

@media (max-width: 1200px) {
    .emergency-alert,
    .blood-drive-ticker {
        display: none;
    }
    
    .search-wrapper {
        width: 250px;
    }
}

/* Responsive Design */
@media (max-width: 768px) {
    .header {
        left: 0;
        padding: 0 15px;
    }
    
    .header-content {
        padding: 0 15px;
        gap: 15px;
    }
    
    .header-left {
        display: none;
    }
    
    .mobile-menu-btn {
        display: flex;
    }
    
    .search-wrapper {
        width: 180px;
    }
    
    .emergency-alert,
    .blood-drive-ticker {
        display: none;
    }
    
    .profile-info {
        display: none;
    }
}

@media (max-width: 480px) {
    .search-wrapper {
        display: none;
    }
    
    .header-right {
        gap: 15px;
    }
}

/* Adjust main content for header - removed sidebar margin */
.main-content {
    margin-top: 70px;
    margin-left: 0; /* Changed from 70px to 0 */
    transition: margin-left 0.3s ease;
}

@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
    }
}
</style>

<header class="header">
    <div class="header-content">
        <!-- Left Section -->
        <div class="header-left">
            <!-- Navigation Links -->
            <nav class="header-nav">
                <?php if ($isAdminSection): ?>
                    <!-- Admin Navigation -->
                    <a href="index.php" class="nav-link active">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
                        </svg>
                        Admin Dashboard
                    </a>
                    
                    <!-- Switch to User View -->
                    <a href="../index.php" class="nav-link view-toggle">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                        </svg>
                        Switch to User View
                    </a>
                <?php else: ?>
                    <!-- User Navigation -->
                    <a href="index.php" class="nav-link active">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
                        </svg>
                        Home
                    </a>
                    
                    <a href="about.php" class="nav-link">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>
                        </svg>
                        About
                    </a>
                    
                    <!-- Switch to Admin View (only show if user is admin) -->
                    <?php if ($userType == 'Admin'): ?>
                        <a href="admin/index.php" class="nav-link view-toggle">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/>
                            </svg>
                            Switch to Admin Panel
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </nav>
        </div>

        <!-- Right Section -->
        <div class="header-right">
            <!-- Search Bar -->
            <div class="search-wrapper">
                <svg class="search-icon" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                </svg>
                <input type="text" class="search-input" placeholder="Search donors, drives...">
            </div>
            
            
            <!-- Profile Dropdown -->
            <div class="profile-wrapper" id="profileWrapper">
                <div class="profile-btn" onclick="toggleProfileDropdown()">
                    <div class="profile-avatar"><?= $profileInitial ?></div>
                    <div class="profile-info">
                        <div class="profile-name"><?= $username ?></div>
                        <div class="profile-type"><?= $userType ?></div>
                    </div>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M7 10l5 5 5-5z"/>
                    </svg>
                </div>
                
                <div class="profile-dropdown">
                    <a href="<?= $isAdminSection ? '../profile.php' : 'profile.php' ?>" class="dropdown-item">
                        <svg class="dropdown-icon" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                        </svg>
                        My Profile
                    </a>
                    
                    <?php if ($userType == 'User'): ?>
                    <a href="<?= $isAdminSection ? '../my-donations.php' : 'my-donations.php' ?>" class="dropdown-item">
                        <svg class="dropdown-icon" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                        </svg>
                        My Donations
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($userType == 'Admin'): ?>
                    <a href="<?= $isAdminSection ? 'users.php' : 'admin/users.php' ?>" class="dropdown-item">
                        <svg class="dropdown-icon" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M16 4c0-1.11.89-2 2-2s2 .89 2 2-.89 2-2 2-2-.89-2-2zm4 18v-6h2.5l-2.54-7.63A3.98 3.98 0 0 0 16.07 6c-.8 0-1.54.37-2.01.95l-.95-2.84c-.39-1.17-1.48-2.01-2.79-2.01H6c-1.66 0-3 1.34-3 3v7c0 1.1.9 2 2 2h2v9h9z"/>
                        </svg>
                        Manage Users
                    </a>
                    <?php endif; ?>
                    
                    <a href="<?= $isAdminSection ? '../logout.php' : 'logout.php' ?>" class="dropdown-item logout">
                        <svg class="dropdown-icon" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/>
                        </svg>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
// Header functionality
document.addEventListener('DOMContentLoaded', function() {
    // Set active navigation item based on current page
    const currentPage = window.location.pathname.split('/').pop();
    const navLinks = document.querySelectorAll('.nav-link:not(.view-toggle)');
    
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        link.classList.remove('active');
        
        // Handle different path scenarios
        if (href === currentPage || 
            (currentPage === '' && href === 'index.php') ||
            (href === 'index.php' && currentPage === '')) {
            link.classList.add('active');
        }
    });
    
    // Search functionality
    const searchInput = document.querySelector('.search-input');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const query = this.value.trim();
                if (query) {
                    // Adjust search path based on current section
                    const isAdmin = window.location.pathname.includes('/admin/');
                    const searchPath = isAdmin ? '../search.php' : 'search.php';
                    window.location.href = `${searchPath}?q=${encodeURIComponent(query)}`;
                }
            }
        });
    }
});

// Profile dropdown functionality
function toggleProfileDropdown() {
    const profileWrapper = document.getElementById('profileWrapper');
    profileWrapper.classList.toggle('show-dropdown');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const profileWrapper = document.getElementById('profileWrapper');
    
    if (!profileWrapper.contains(event.target)) {
        profileWrapper.classList.remove('show-dropdown');
    }
});

// Add visual feedback for view toggle buttons
document.addEventListener('DOMContentLoaded', function() {
    const viewToggle = document.querySelector('.view-toggle');
    if (viewToggle) {
        viewToggle.addEventListener('click', function(e) {
            // Add loading state
            this.style.opacity = '0.7';
            this.innerHTML = '<svg class="nav-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12,4V2A10,10 0 0,0 2,12H4A8,8 0 0,1 12,4Z"/></svg>Switching...';
            
            // Let the browser handle the navigation
            setTimeout(() => {
                this.style.opacity = '1';
            }, 100);
        });
    }
});
</script>