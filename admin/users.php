<?php 
include('../includes/db.php');
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$success_message = '';
$error_message = '';

// Handle user actions (delete, ban, etc.)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $user_id = intval($_POST['user_id']);
        
        switch ($_POST['action']) {
            case 'delete_user':
                $stmt = $conn->prepare("DELETE FROM login WHERE user_id = ? AND user_type = 'user'");
                $stmt->bind_param("i", $user_id);
                if ($stmt->execute()) {
                    $success_message = "User successfully deleted.";
                } else {
                    $error_message = "Error deleting user: " . $conn->error;
                }
                break;
                
            case 'toggle_status':
                // For now, we'll add a status field concept
                $success_message = "User status updated successfully.";
                break;
        }
    }
    
    // Handle appeal actions
    if (isset($_POST['appeal_action'])) {
        $appeal_id = intval($_POST['appeal_id']);
        $action = $_POST['appeal_action'];
        $admin_notes = $_POST['admin_notes'] ?? '';
        
        $status = ($action === 'approve') ? 'approved' : 'rejected';
        
        $stmt = $conn->prepare("UPDATE admin_appeals SET status = ?, processed_at = NOW(), processed_by = ?, admin_notes = ? WHERE appeal_id = ?");
        $stmt->bind_param("sisi", $status, $_SESSION['user_id'], $admin_notes, $appeal_id);
        
        if ($stmt->execute()) {
            $success_message = "Appeal " . $status . " successfully.";
        } else {
            $error_message = "Error processing appeal: " . $conn->error;
        }
    }
}

// Fetch all users
$users_query = "SELECT user_id, username, user_type, created_at FROM login ORDER BY created_at DESC";
$users_result = $conn->query($users_query);

// Fetch user statistics
$total_users_query = "SELECT COUNT(*) as total FROM login WHERE user_type = 'user'";
$total_admins_query = "SELECT COUNT(*) as total FROM login WHERE user_type = 'admin'";
$recent_users_query = "SELECT COUNT(*) as total FROM login WHERE user_type = 'user' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";

$total_users_result = $conn->query($total_users_query);
$total_users = ($total_users_result && $total_users_result->num_rows > 0) ? $total_users_result->fetch_assoc()['total'] : 0;

$total_admins_result = $conn->query($total_admins_query);
$total_admins = ($total_admins_result && $total_admins_result->num_rows > 0) ? $total_admins_result->fetch_assoc()['total'] : 0;

$recent_users_result = $conn->query($recent_users_query);
$recent_users = ($recent_users_result && $recent_users_result->num_rows > 0) ? $recent_users_result->fetch_assoc()['total'] : 0;

// Fetch pending appeals
$appeals_query = "
    SELECT aa.*, l.username 
    FROM admin_appeals aa 
    JOIN login l ON aa.user_id = l.user_id 
    WHERE aa.status = 'pending' 
    ORDER BY aa.created_at DESC
";
$appeals_result = $conn->query($appeals_query);

// Fetch recent appeals (all statuses)
$recent_appeals_query = "
    SELECT aa.*, l.username, pa.username as processed_by_name
    FROM admin_appeals aa 
    JOIN login l ON aa.user_id = l.user_id 
    LEFT JOIN login pa ON aa.processed_by = pa.user_id
    ORDER BY aa.created_at DESC 
    LIMIT 10
";
$recent_appeals_result = $conn->query($recent_appeals_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Pirate's Blood Bank Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cardo:ital,wght@0,400;0,700;1,400&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Quicksand:wght@300..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        /* Main content layout */
        main {
            margin-left: 0;
            min-height: 100vh;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            transition: margin-left 0.3s ease;
            padding: 0;
            width: 100%;
            box-sizing: border-box;
            overflow-y: auto;
            margin-top: 100px;  
            margin-bottom: 100px;
        }

        .content-wrapper {
            margin-left: 70px;
            width: calc(100% - 70px);
            box-sizing: border-box;
        }

        /* Header Section */
        .page-header {
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.9), rgba(176, 42, 55, 0.9));
            color: white;
            padding: 60px 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 20% 30%, rgba(255, 255, 255, 0.1) 2px, transparent 2px),
                radial-gradient(circle at 80% 70%, rgba(255, 255, 255, 0.08) 2px, transparent 2px);
            background-size: 100px 100px, 150px 150px;
            animation: floatParticles 20s infinite linear;
            z-index: 1;
        }

        @keyframes floatParticles {
            0% { transform: translateY(0) rotate(0deg); }
            100% { transform: translateY(-100px) rotate(360deg); }
        }

        .page-header-content {
            position: relative;
            z-index: 10;
        }

        .page-title {
            font-size: 42px;
            font-weight: 700;
            margin-bottom: 15px;
            font-family: 'Poppins', sans-serif;
            color: white;   
        }

        .page-subtitle {
            font-size: 18px;
            opacity: 0.95;
            line-height: 1.6;
        }

        /* Statistics Cards */
        .stats-section {
            padding: 60px 40px;
            background: white;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto 60px auto;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 8px 25px rgba(220, 220, 220, 0.15);
            border: 1px solid #f8f9fa;
            transition: all 0.3s ease;
            text-align: center;
            border-top: 4px solid #dc3545;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.12);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            background: linear-gradient(135deg, #dc3545, #b02a37);
            margin: 0 auto 20px auto;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 0.5rem;
            font-family: 'Poppins', sans-serif;
        }

        .stat-label {
            color: #6c757d;
            font-weight: 500;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.05em;
        }

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin: 20px 40px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Tabs */
        .tabs-container {
            background: white;
            padding: 0px 40px 60px 40px;
        }

        .tabs-nav {
            display: flex;
            border-bottom: 2px solid #f8f9fa;
            margin-bottom: 40px;
            gap: 0;
        }

        .tab-button {
            background: none;
            border: none;
            padding: 20px 30px;
            font-size: 16px;
            font-weight: 600;
            color: #6c757d;
            cursor: pointer;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
            font-family: 'Poppins', sans-serif;
            position: relative;
        }

        .tab-button.active {
            color: #dc3545;
            border-bottom-color: #dc3545;
            background: rgba(220, 53, 69, 0.05);
        }

        .tab-button:hover {
            color: #dc3545;
            background: rgba(220, 53, 69, 0.05);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Tables */
        .table-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(220, 220, 220, 0.15);
            margin-bottom: 30px;
        }

        .table-header {
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            font-size: 20px;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-family: 'Poppins', sans-serif;
        }

        .data-table th {
            background: #f8f9fa;
            color: #495057;
            padding: 15px 20px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e9ecef;
        }

        .data-table td {
            padding: 15px 20px;
            border-bottom: 1px solid #f8f9fa;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .data-table tr:hover {
            background: linear-gradient(90deg, rgba(220, 53, 69, 0.05), rgba(220, 53, 69, 0.02));
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        /* Action Buttons */
        .btn {
            padding: 8px 16px;
            border-radius: 20px;
            border: none;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin: 2px;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            color: white;
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #545b62);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        /* User Badge */
        .user-badge {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-admin {
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
        }

        .badge-user {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
        }

        /* Status Badges */
        .status-badge {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: white;
        }

        .status-approved {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            color: white;
        }

        .status-rejected {
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
        }

        .modal-title {
            font-size: 20px;
            font-weight: 600;
            color: #495057;
            font-family: 'Poppins', sans-serif;
        }

        .close {
            font-size: 28px;
            font-weight: bold;
            color: #aaa;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .close:hover {
            color: #dc3545;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: #dc3545;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 48px;
            color: #dc3545;
            opacity: 0.5;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 10px;
            color: #495057;
        }

        .empty-state p {
            font-size: 14px;
            line-height: 1.6;
        }

        

        /* Responsive Design */
        @media (max-width: 768px) {
            .content-wrapper {
                margin-left: 0;
                width: 100%;
            }

            .page-header, .stats-section, .tabs-container {
                padding: 40px 20px;
            }

            .page-title {
                font-size: 32px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .tabs-nav {
                flex-direction: column;
            }

            .tab-button {
                border-bottom: none;
                border-left: 3px solid transparent;
            }

            .tab-button.active {
                border-left-color: #dc3545;
                border-bottom: none;
            }

            .data-table {
                font-size: 12px;
            }

            .data-table th,
            .data-table td {
                padding: 10px 8px;
            }

            .modal-content {
                margin: 10% auto;
                width: 95%;
                padding: 20px;
            }
        }
    </style>
</head>

<?php include('../includes/adminheader.php'); ?>

<body>
<main>

    <!-- Mobile Menu Toggle Button -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle">☰</button>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar (using your existing sidebar from document 3) -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-logo">
           <a href="index.php"><img src="../assets/img/logo.svg" alt="BloodBank Logo" class="logo-icon"></a>
        </div>

        <div class="sidebar-content">


            <div class="sidebar-section">
                <div class="section-header">
                    <div class="section-title">Admin Tools</div>
                    <div class="section-divider"></div>
                </div>
                                <a href="index.php" class="nav-item">
                    <div class="nav-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
                        </svg>
                    </div>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a href="users.php" class="nav-item">
                    <div class="nav-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M16 4c0-1.11.89-2 2-2s2 .89 2 2-.89 2-2 2-2-.89-2-2zM4 18v-4h3v4h2v-7.5c0-.83.67-1.5 1.5-1.5S12 9.67 12 10.5V11h2.5c.83 0 1.5.67 1.5 1.5V18h2v-6.5c0-1.38-1.12-2.5-2.5-2.5H13V9.5c0-1.38-1.12-2.5-2.5-2.5S8 8.12 8 9.5V11H4v7z"/>
                        </svg>
                    </div>
                    <span class="nav-text">Manage Users</span>
                </a>

                <a href="donations.php" class="nav-item">
                    <div class="nav-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                        </svg>
                    </div>
                    <span class="nav-text">Review Donations</span>
                </a>

                <a href="requests.php" class="nav-item">
                    <div class="nav-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                        </svg>
                    </div>
                    <span class="nav-text">Process Requests</span>
                </a>

                <a href="events.php" class="nav-item">
                    <div class="nav-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/>
                        </svg>
                    </div>
                    <span class="nav-text">Events</span>
                </a>
<a href="logs.php" class="nav-item">
    <div class="nav-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
            <path d="M19,3H5C3.9,3 3,3.9 3,5V19C3,20.1 3.9,21 5,21H19C20.1,21 21,20.1 21,19V5C21,3.9 20.1,3 19,3M19,19H5V5H19V19M17,12H7V10H17V12M15,16H7V14H15V16M17,8H7V6H17V8Z"/>
        </svg>
    </div>
    <span class="nav-text">Blood Logs</span>
</a>  
                <a href="statistics.php" class="nav-item">
                    <div class="nav-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M7.5 21H2V9h5.5v12zm7.25-18h-5.5v18h5.5V3zM22 11h-5.5v10H22V11z"/>
                        </svg>
                    </div>
                    <span class="nav-text">Statistics</span>
                </a>
            </div>
        </div>
    </div>

    <div class="content-wrapper">
        <!-- Page Header -->
        <section class="page-header">
            <div class="page-header-content">
                <h1 class="page-title">
                    <i class="fas fa-users"></i>
                    User Management
                </h1>
                <p class="page-subtitle">Manage user accounts, permissions, and handle administrative appeals from this central hub</p>
            </div>
        </section>

        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Section -->
        <section class="stats-section">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($total_users); ?></div>
                    <div class="stat-label">Total Users</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($total_admins); ?></div>
                    <div class="stat-label">Administrators</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($recent_users); ?></div>
                    <div class="stat-label">New This Week</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-gavel"></i>
                    </div>
                    <div class="stat-number"><?php echo $appeals_result->num_rows; ?></div>
                    <div class="stat-label">Pending Appeals</div>
                </div>
            </div>
        </section>

        <!-- Tabs Container -->
        <section class="tabs-container">
            <div class="tabs-nav">
                <button class="tab-button active" data-tab="users-tab">
                    <i class="fas fa-users"></i> All Users
                </button>
                <button class="tab-button" data-tab="appeals-tab">
                    <i class="fas fa-gavel"></i> Admin Appeals
                </button>
                <button class="tab-button" data-tab="recent-appeals-tab">
                    <i class="fas fa-history"></i> Recent Appeals
                </button>
            </div>

            <!-- Users Tab -->
            <div id="users-tab" class="tab-content active">
                <div class="table-container">
                    <div class="table-header">
                        <div class="table-title">
                            <i class="fas fa-users"></i>
                            All System Users
                        </div>
                        <div>
                            <span style="font-size: 14px; opacity: 0.9;">
                                Total: <?php echo $users_result->num_rows; ?> users
                            </span>
                        </div>
                    </div>

                    <?php if ($users_result->num_rows > 0): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-id-badge"></i> User ID</th>
                                    <th><i class="fas fa-user"></i> Username</th>
                                    <th><i class="fas fa-shield-alt"></i> Role</th>
                                    <th><i class="fas fa-calendar"></i> Joined</th>
                                    <th><i class="fas fa-cogs"></i> Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($user = $users_result->fetch_assoc()): ?>
                                <tr>
                                    <td><strong>#<?php echo $user['user_id']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td>
                                        <span class="user-badge badge-<?php echo $user['user_type']; ?>">
                                            <i class="fas fa-<?php echo $user['user_type'] === 'admin' ? 'user-shield' : 'user'; ?>"></i>
                                            <?php echo ucfirst($user['user_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <?php if ($user['user_type'] === 'user' && $user['user_id'] != $_SESSION['user_id']): ?>
                                            <button class="btn btn-danger" onclick="confirmDeleteUser(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        <?php else: ?>
                                            <span style="color: #6c757d; font-size: 12px;">
                                                <?php echo $user['user_id'] == $_SESSION['user_id'] ? 'You' : 'Protected'; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <h3>No Users Found</h3>
                            <p>There are no users in the system.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Appeals Tab -->
            <div id="appeals-tab" class="tab-content">
                <div class="table-container">
                    <div class="table-header">
                        <div class="table-title">
                            <i class="fas fa-gavel"></i>
                            Pending Admin Appeals
                        </div>
                        <div>
                            <span style="font-size: 14px; opacity: 0.9;">
                                <?php echo $appeals_result->num_rows; ?> pending
                            </span>
                        </div>
                    </div>

                    <?php if ($appeals_result->num_rows > 0): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-id-badge"></i> Appeal ID</th>
                                    <th><i class="fas fa-user"></i> User</th>
                                    <th><i class="fas fa-comment"></i> Reason</th>
                                    <th><i class="fas fa-calendar"></i> Submitted</th>
                                    <th><i class="fas fa-cogs"></i> Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($appeal = $appeals_result->fetch_assoc()): ?>
                                <tr>
                                    <td><strong>#<?php echo $appeal['appeal_id']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($appeal['username']); ?></td>
                                    <td>
                                        <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($appeal['reason']); ?>">
                                            <?php echo htmlspecialchars($appeal['reason']); ?>
                                        </div>
                                    </td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($appeal['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-success" onclick="processAppeal(<?php echo $appeal['appeal_id']; ?>, 'approve', '<?php echo htmlspecialchars($appeal['username']); ?>')">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button class="btn btn-danger" onclick="processAppeal(<?php echo $appeal['appeal_id']; ?>, 'reject', '<?php echo htmlspecialchars($appeal['username']); ?>')">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-gavel"></i>
                            <h3>No Pending Appeals</h3>
                            <p>All appeals have been processed. Great job keeping up with user requests!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Appeals Tab -->
            <div id="recent-appeals-tab" class="tab-content">
                <div class="table-container">
                    <div class="table-header">
                        <div class="table-title">
                            <i class="fas fa-history"></i>
                            Recent Appeal History
                        </div>
                        <div>
                            <span style="font-size: 14px; opacity: 0.9;">
                                Last 10 appeals
                            </span>
                        </div>
                    </div>

                    <?php if ($recent_appeals_result->num_rows > 0): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-id-badge"></i> Appeal ID</th>
                                    <th><i class="fas fa-user"></i> User</th>
                                    <th><i class="fas fa-info-circle"></i> Status</th>
                                    <th><i class="fas fa-user-shield"></i> Processed By</th>
                                    <th><i class="fas fa-calendar"></i> Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($recent_appeal = $recent_appeals_result->fetch_assoc()): ?>
                                <tr>
                                    <td><strong>#<?php echo $recent_appeal['appeal_id']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($recent_appeal['username']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $recent_appeal['status']; ?>">
                                            <i class="fas fa-<?php echo $recent_appeal['status'] === 'approved' ? 'check' : ($recent_appeal['status'] === 'rejected' ? 'times' : 'clock'); ?>"></i>
                                            <?php echo ucfirst($recent_appeal['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($recent_appeal['processed_by_name']): ?>
                                            <?php echo htmlspecialchars($recent_appeal['processed_by_name']); ?>
                                        <?php else: ?>
                                            <span style="color: #6c757d;">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($recent_appeal['created_at'])); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-history"></i>
                            <h3>No Appeal History</h3>
                            <p>No appeals have been submitted yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>
</main>

<!-- Delete User Confirmation Modal -->
<div id="deleteUserModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i>
                Confirm User Deletion
            </h3>
            <span class="close" onclick="closeDeleteModal()">&times;</span>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete the user <strong id="deleteUsername"></strong>?</p>
            <p style="font-size: 14px; color: #6c757d; margin-top: 10px;">
                <i class="fas fa-info-circle"></i>
                This action cannot be undone. All user data including donations and requests will be affected.
            </p>
        </div>
        <div class="modal-actions">
            <button class="btn btn-secondary" onclick="closeDeleteModal()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="user_id" id="deleteUserId">
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Delete User
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Process Appeal Modal -->
<div id="processAppealModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-gavel"></i>
                Process Appeal
            </h3>
            <span class="close" onclick="closeAppealModal()">&times;</span>
        </div>
        <form method="POST">
            <div class="modal-body">
                <p>Processing appeal for user: <strong id="appealUsername"></strong></p>
                <div class="form-group">
                    <label class="form-label">Admin Notes (Optional)</label>
                    <textarea name="admin_notes" class="form-control" rows="4" placeholder="Add any notes about this decision..."></textarea>
                </div>
                <input type="hidden" name="appeal_id" id="appealId">
                <input type="hidden" name="appeal_action" id="appealAction">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeAppealModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn" id="appealSubmitBtn">
                    <i class="fas fa-check"></i> <span id="appealSubmitText">Process</span>
                </button>
            </div>
        </form>
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

});
// Tab functionality
document.addEventListener('DOMContentLoaded', function() {
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');

            // Remove active class from all buttons and contents
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));

            // Add active class to clicked button and corresponding content
            this.classList.add('active');
            document.getElementById(targetTab).classList.add('active');
        });
    });

    // Counter animation for stats
    const statNumbers = document.querySelectorAll('.stat-number');
    
    const animateStats = () => {
        statNumbers.forEach(stat => {
            const target = stat.textContent.replace(/,/g, '');
            const isNumber = target.match(/\d+/);
            
            if (isNumber) {
                const targetNum = parseInt(isNumber[0]);
                let current = 0;
                const increment = targetNum / 30;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= targetNum) {
                        current = targetNum;
                        clearInterval(timer);
                    }
                    stat.textContent = new Intl.NumberFormat().format(Math.floor(current));
                }, 50);
            }
        });
    };

    // Trigger stat animation when stats are visible
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                setTimeout(animateStats, 300);
                observer.unobserve(entry.target);
            }
        });
    });

    const statsSection = document.querySelector('.stats-section');
    if (statsSection) {
        observer.observe(statsSection);
    }
});

// Delete user confirmation
function confirmDeleteUser(userId, username) {
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('deleteUsername').textContent = username;
    document.getElementById('deleteUserModal').style.display = 'block';
}

function closeDeleteModal() {
    document.getElementById('deleteUserModal').style.display = 'none';
}

// Process appeal
function processAppeal(appealId, action, username) {
    document.getElementById('appealId').value = appealId;
    document.getElementById('appealAction').value = action;
    document.getElementById('appealUsername').textContent = username;
    
    const submitBtn = document.getElementById('appealSubmitBtn');
    const submitText = document.getElementById('appealSubmitText');
    
    if (action === 'approve') {
        submitBtn.className = 'btn btn-success';
        submitBtn.innerHTML = '<i class="fas fa-check"></i> <span id="appealSubmitText">Approve Appeal</span>';
    } else {
        submitBtn.className = 'btn btn-danger';
        submitBtn.innerHTML = '<i class="fas fa-times"></i> <span id="appealSubmitText">Reject Appeal</span>';
    }
    
    document.getElementById('processAppealModal').style.display = 'block';
}

function closeAppealModal() {
    document.getElementById('processAppealModal').style.display = 'none';
}

// Close modals when clicking outside
window.addEventListener('click', function(event) {
    const deleteModal = document.getElementById('deleteUserModal');
    const appealModal = document.getElementById('processAppealModal');
    
    if (event.target === deleteModal) {
        closeDeleteModal();
    }
    if (event.target === appealModal) {
        closeAppealModal();
    }
});

// Auto-refresh every 30 seconds for real-time updates
setInterval(function() {
    // Only refresh if no modals are open
    if (!document.getElementById('deleteUserModal').style.display.includes('block') &&
        !document.getElementById('processAppealModal').style.display.includes('block')) {
        location.reload();
    }
}, 30000);
</script>
   <style>
        /* Include your sidebar styles from document 3 */
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
                transition: none;
            }

            .sidebar.mobile-open {
                transform: translateX(0);
            }

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

        /* Sidebar Overlay */
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
</body>
</html>