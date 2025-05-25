<style>
.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: 70px;
    height: 100vh;
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    box-shadow: 2px 0 15px rgba(220, 220, 220, 0.1);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 1000;
    overflow: hidden;
}

.sidebar:hover {
    width: 280px;
}

.section-header {
    padding: 0 20px 15px 20px;
    opacity: 0;
    transform: translateX(-20px);
    transition: all 0.3s ease;
    pointer-events: none;
}

.sidebar:hover .section-header {
    opacity: 1;
    transform: translateX(0);
}

.section-title {
    font-size: 12px;
    font-weight: 700;
    color: #dc3545;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 10px;
}

.section-divider {
    height: 2px;
    background: linear-gradient(90deg, #dc3545, transparent);
    width: 40px;
}

.nav-item {
    display: flex;
    align-items: center;
    padding: 12px 20px;
    color: #495057;
    text-decoration: none;
    transition: all 0.3s ease;
    position: relative;
    border-radius: 0 25px 25px 0;
    margin-right: 20px;
}

.nav-item:hover {
    background: linear-gradient(90deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));
    color: #dc3545;
    text-decoration: none;
    transform: translateX(5px);
}

.nav-item.primary {
    background: linear-gradient(135deg, #dc3545, #b02a37);
    color: white;
    font-weight: 600;
    margin-bottom: 8px;
}

.nav-item.primary:hover {
    background: linear-gradient(135deg, #c82333, #a02633);
    color: white;
    transform: translateX(5px) scale(1.02);
}

.nav-icon {
    width: 24px;
    height: 24px;
    min-width: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
}

.nav-text {
    opacity: 0;
    transform: translateX(-10px);
    transition: all 0.3s ease;
    white-space: nowrap;
    font-size: 14px;
    font-weight: 500;
}

.sidebar:hover .nav-text {
    opacity: 1;
    transform: translateX(0);
}

.nav-item::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    height: 100%;
    width: 4px;
    background: #dc3545;
    transform: scaleY(0);
    transition: transform 0.3s ease;
}

.nav-item:hover::before {
    transform: scaleY(1);
}

.nav-item.primary::before {
    display: none;
}

/* Badge for notifications */
.nav-badge {
    position: absolute;
    top: 8px;
    right: 25px;
    background: #dc3545;
    color: white;
    border-radius: 10px;
    padding: 2px 6px;
    font-size: 10px;
    font-weight: 600;
    opacity: 0;
    transform: scale(0);
    transition: all 0.3s ease;
}

.sidebar:hover .nav-badge {
    opacity: 1;
    transform: scale(1);
}
.sidebar-logo {
    padding: 25px 20px 20px;
    margin-bottom: 20px;
    text-align: left;
    display: flex;
    flex-direction: column;
    align-items: center;
    transition: all 0.3s ease;
}

.logo-icon {
    width: 30px;
    height: 30px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    margin: 0 auto;
}

.sidebar:hover .logo-icon {
        text-align: center;
    width: 140px;
    height: 140px;
    margin-bottom: 0;
}

/* Mobile Menu Toggle Button */
.mobile-menu-toggle {
    display:none;
    position: fixed;
    top: 15px;
    left: 15px;
    z-index: 1100;
    background: #dc3545;
    color: white;
    border: none;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    font-size: 20px;
    cursor: pointer;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

/* Responsive */
@media (max-width: 768px) {
    .sidebar {
        width: 280px !important;
        overflow: visible !important;
        transform: translateX(-100%);
        opacity: 1;
        transition: none; /* disable hover transition on mobile */
    }
    /* Show sidebar fully when mobile menu is open */
    .sidebar.mobile-open {
        transform: translateX(0);
    }

    /* Show nav-text and section-header without hover on mobile */
    .section-header,
    .nav-text {
        opacity: 1 !important;
        transform: translateX(0) !important;
        pointer-events: auto !important;
    }

    .mobile-menu-toggle {
        display: block;
    }
    .logo-icon {
        text-align: center;
    width: 140px;
    height: 140px;
    margin-bottom: 0;
}
}

/* Content margin adjustment */
.main-content {
    margin-left: 70px;
    transition: margin-left 0.3s ease;
}

@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
    }
}

/* Overlay for mobile */
.sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 999;
}

@media (max-width: 768px) {
    .sidebar-overlay.mobile-open {
        display: block;
    }
}
</style>

<!-- Mobile Menu Toggle Button -->
<button class="mobile-menu-toggle" id="mobileMenuToggle">☰</button>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <!-- Logo Section -->
    <div class="sidebar-logo">
       <a href="index.php"> <img src="assets/img/logo.svg" alt="BloodBank Logo" class="logo-icon"></a>
    </div>

    <div class="sidebar-content">
        <!-- Jump In Section -->
        <div class="sidebar-section">
            <div class="section-header">
                <div class="section-title">Jump In</div>
                <div class="section-divider"></div>
            </div>
            <a href="donate.php" class="nav-item primary">
                <div class="nav-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                    </svg>
                </div>
                <span class="nav-text">Donate Blood</span>
            </a>
            
            <a href="receive.php" class="nav-item primary">
                <div class="nav-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                    </svg>
                </div>
                <span class="nav-text">Request Blood</span>
            </a>
        </div>
            <br><br>

        <!-- Quick Links Section -->
        <div class="sidebar-section">
            <div class="section-header">
                <div class="section-title">Quick Links</div>
                <div class="section-divider"></div>
            </div>
            
            <a href="learn.php" class="nav-item">
                <div class="nav-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                    </svg>
                </div>
                <span class="nav-text">Learn</span>
            </a>
         
            
            <a href="eligibility.php" class="nav-item">
                <div class="nav-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <span class="nav-text">Eligibility Check</span>
            </a>
            
            <a href="locations.php" class="nav-item">
                <div class="nav-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                    </svg>
                </div>
                <span class="nav-text">Find Locations</span>
            </a>
            
            <a href="community.php" class="nav-item">
                <div class="nav-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M16 4c0-1.11.89-2 2-2s2 .89 2 2-.89 2-2 2-2-.89-2-2zM4 18v-4h3v4h2v-7.5c0-.83.67-1.5 1.5-1.5S12 9.67 12 10.5V11h2.5c.83 0 1.5.67 1.5 1.5V18h2v-6.5c0-1.38-1.12-2.5-2.5-2.5H13V9.5c0-1.38-1.12-2.5-2.5-2.5S8 8.12 8 9.5V11H4v7z"/>
                    </svg>
                </div>
                <span class="nav-text">Community</span>
            </a>
            
            <a href="leaderboard.php" class="nav-item">
                <div class="nav-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M7.5 21H2V9h5.5v12zm7.25-18h-5.5v18h5.5V3zM22 11h-5.5v10H22V11z"/>
                    </svg>
                </div>
                <span class="nav-text">Leaderboard</span>
            </a>
            
            <a href="appointments.php" class="nav-item">
                <div class="nav-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/>
                    </svg>
                </div>
                <span class="nav-text">My Appointments</span>
            </a>
            
            
            <a href="settings.php" class="nav-item">
                <div class="nav-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19.14,12.94c0.04-0.3,0.06-0.61,0.06-0.94c0-0.32-0.02-0.64-0.07-0.94l2.03-1.58c0.18-0.14,0.23-0.41,0.12-0.61 l-1.92-3.32c-0.12-0.22-0.37-0.29-0.59-0.22l-2.39,0.96c-0.5-0.38-1.03-0.7-1.62-0.94L14.4,2.81c-0.04-0.24-0.24-0.41-0.48-0.41 h-3.84c-0.24,0-0.43,0.17-0.47,0.41L9.25,5.35C8.66,5.59,8.12,5.92,7.63,6.29L5.24,5.33c-0.22-0.08-0.47,0-0.59,0.22L2.74,8.87 C2.62,9.08,2.66,9.34,2.86,9.48l2.03,1.58C4.84,11.36,4.8,11.69,4.8,12s0.02,0.64,0.07,0.94l-2.03,1.58 c-0.18,0.14-0.23,0.41-0.12,0.61l1.92,3.32c0.12,0.22,0.37,0.29,0.59,0.22l2.39-0.96c0.5,0.38,1.03,0.7,1.62,0.94l0.36,2.54 c0.05,0.24,0.24,0.41,0.48,0.41h3.84c0.24,0,0.44-0.17,0.47-0.41l0.36-2.54c0.59-0.24,1.13-0.56,1.62-0.94l2.39,0.96 c0.22,0.08,0.47,0,0.59-0.22l1.92-3.32c0.12-0.22,0.07-0.47-0.12-0.61L19.14,12.94z M12,15.6c-1.98,0-3.6-1.62-3.6-3.6 s1.62-3.6,3.6-3.6s3.6,1.62,3.6,3.6S13.98,15.6,12,15.6z"/>
                    </svg>
                </div>
                <span class="nav-text">Settings</span>
            </a>
            
            <a href="help.php" class="nav-item">
                <div class="nav-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45,12.9 13,13.5 13,15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2,.9-2,2H8c0-2.21 1.79-4 4-4s4,1.79 4,4c0,.88-.36,1.68-.93,2.25z"/>
                    </svg>
                </div>
                <span class="nav-text">Help & Support</span>
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    
    // Toggle mobile sidebar
    mobileMenuToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        sidebar.classList.toggle('mobile-open');
        sidebarOverlay.classList.toggle('mobile-open');
    });
    
    // Close sidebar when clicking on overlay
    sidebarOverlay.addEventListener('click', function() {
        sidebar.classList.remove('mobile-open');
        sidebarOverlay.classList.remove('mobile-open');
    });
    
    // Close sidebar when clicking on a nav item (for mobile)
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => {
        item.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('mobile-open');
                sidebarOverlay.classList.remove('mobile-open');
            }
        });
    });
    
    // Add active state to current page
    const currentPage = window.location.pathname.split('/').pop();
    
    navItems.forEach(item => {
        const href = item.getAttribute('href');
        if (href === currentPage) {
            item.style.background = 'linear-gradient(90deg, rgba(220, 53, 69, 0.15), rgba(220, 53, 69, 0.08))';
            item.style.color = '#dc3545';
            item.style.fontWeight = '600';
        }
    });
});
</script>