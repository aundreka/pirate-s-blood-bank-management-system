<?php 
include('../includes/db.php');
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$success_message = '';
$error_message = '';

// Filter parameters
$filter_type = $_GET['filter_type'] ?? 'all';
$filter_date = $_GET['filter_date'] ?? '';
$filter_blood_type = $_GET['filter_blood_type'] ?? '';
$search_term = $_GET['search'] ?? '';

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$records_per_page = 20;
$offset = ($page - 1) * $records_per_page;

// Build WHERE clause for filters
$where_conditions = [];
$params = [];
$param_types = '';

if ($filter_type !== 'all') {
    $where_conditions[] = "bl.operation_type = ?";
    $params[] = strtoupper($filter_type);
    $param_types .= 's';
}

if ($filter_date) {
    $where_conditions[] = "DATE(bl.timestamp_update) = ?";
    $params[] = $filter_date;
    $param_types .= 's';
}

if ($filter_blood_type) {
    $where_conditions[] = "bl.blood_type = ?";
    $params[] = $filter_blood_type;
    $param_types .= 's';
}

if ($search_term) {
    $where_conditions[] = "(l.username LIKE ? OR bl.notes LIKE ?)";
    $search_param = "%$search_term%";
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'ss';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

try {
    // Get total count for pagination
    $count_query = "
        SELECT COUNT(*) as total
        FROM blood_log bl
        LEFT JOIN login l ON bl.donor_id = l.user_id
        $where_clause
    ";
    
    if (!empty($params)) {
        $count_stmt = $conn->prepare($count_query);
        $count_stmt->bind_param($param_types, ...$params);
        $count_stmt->execute();
        $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
    } else {
        $total_records = $conn->query($count_query)->fetch_assoc()['total'];
    }
    
    $total_pages = ceil($total_records / $records_per_page);

    // Get blood logs with user information
    $logs_query = "
        SELECT 
            bl.*,
            l.username,
            db.donation_id as related_donation,
            DATE(bl.timestamp_update) as log_date,
            TIME(bl.timestamp_update) as log_time
        FROM blood_log bl
        LEFT JOIN login l ON bl.donor_id = l.user_id
        LEFT JOIN donate_blood db ON bl.donation_id = db.donation_id
        $where_clause
        ORDER BY bl.timestamp_update DESC
        LIMIT ? OFFSET ?
    ";
    
    $final_params = $params;
    $final_params[] = $records_per_page;
    $final_params[] = $offset;
    $final_param_types = $param_types . 'ii';
    
    if (!empty($params) || true) {
        $logs_stmt = $conn->prepare($logs_query);
        $logs_stmt->bind_param($final_param_types, ...$final_params);
        $logs_stmt->execute();
        $logs_result = $logs_stmt->get_result();
    }

    // Get statistics
    $stats_query = "
        SELECT 
            COUNT(*) as total_logs,
            SUM(CASE WHEN operation_type = 'DONATION' THEN units_donated ELSE 0 END) as total_donations,
            SUM(CASE WHEN operation_type = 'WITHDRAWAL' THEN units_donated ELSE 0 END) as total_withdrawals,
            SUM(CASE WHEN operation_type = 'ADJUSTMENT' THEN units_donated ELSE 0 END) as total_adjustments,
            COUNT(CASE WHEN DATE(timestamp_update) = CURDATE() THEN 1 END) as today_activities
        FROM blood_log
    ";
    $stats_result = $conn->query($stats_query);
    $stats = $stats_result->fetch_assoc();

    // Get blood types for filter
    $blood_types_query = "SELECT DISTINCT blood_type FROM blood_inventory ORDER BY blood_type";
    $blood_types_result = $conn->query($blood_types_query);

} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    $logs_result = null;
    $stats = ['total_logs' => 0, 'total_donations' => 0, 'total_withdrawals' => 0, 'total_adjustments' => 0, 'today_activities' => 0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Bank Logs - Pirate's Blood Bank Admin</title>
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
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto 60px auto;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
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
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
            background: linear-gradient(135deg, #dc3545, #b02a37);
            margin: 0 auto 15px auto;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 0.5rem;
            font-family: 'Poppins', sans-serif;
        }

        .stat-label {
            color: #6c757d;
            font-weight: 500;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Filter Section */
        .filters-section {
            background: #f8f9fa;
            padding: 30px 40px;
            border-bottom: 1px solid #e9ecef;
        }

        .filters-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .filters-title {
            font-size: 20px;
            font-weight: 600;
            color: #495057;
            margin-bottom: 20px;
            font-family: 'Poppins', sans-serif;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .filter-control {
            padding: 10px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
            background: white;
        }

        .filter-control:focus {
            outline: none;
            border-color: #dc3545;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #dc3545, #b02a37);
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

        /* Logs Table */
        .logs-section {
            padding: 0 40px 60px 40px;
            background: white;
        }

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
            font-size: 12px;
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

        /* Operation Type Badges */
        .operation-badge {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .operation-donation {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            color: white;
        }

        .operation-withdrawal {
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
        }

        .operation-adjustment {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: white;
        }

        /* Blood Type Badge */
        .blood-type-badge {
            font-weight: 700;
            font-size: 16px;
            color: #dc3545;
            background: rgba(220, 53, 69, 0.1);
            padding: 4px 8px;
            border-radius: 8px;
        }

        /* Pagination */
        .pagination-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
        }

        .pagination-info {
            color: #6c757d;
            font-size: 14px;
            margin: 0 20px;
        }

        .pagination-btn {
            padding: 8px 12px;
            border: 2px solid #e9ecef;
            background: white;
            color: #495057;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .pagination-btn:hover {
            border-color: #dc3545;
            color: #dc3545;
        }

        .pagination-btn.active {
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
            border-color: #dc3545;
        }

        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
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

        /* Responsive Design */
        @media (max-width: 768px) {
            .content-wrapper {
                margin-left: 0;
                width: 100%;
            }

            .page-header, .stats-section, .filters-section, .logs-section {
                padding: 40px 20px;
            }

            .page-title {
                font-size: 32px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                justify-content: stretch;
            }

            .filter-actions .btn {
                flex: 1;
                justify-content: center;
            }

            .data-table {
                font-size: 12px;
            }

            .data-table th,
            .data-table td {
                padding: 10px 8px;
            }

            .table-header {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }

            .pagination-container {
                flex-wrap: wrap;
            }
        }
    </style>
</head>

<?php include('../includes/adminheader.php'); ?>

<body>
<main>
        <button class="mobile-menu-toggle" id="mobileMenuToggle">☰</button>

    <!-- Sidebar Overlay -->
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
                    <i class="fas fa-list-alt"></i>
                    Blood Bank Activity Logs
                </h1>
                <p class="page-subtitle">Monitor and track all blood bank operations including donations, withdrawals, and inventory adjustments</p>
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
                        <i class="fas fa-list"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['total_logs']); ?></div>
                    <div class="stat-label">Total Activities</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['total_donations']); ?></div>
                    <div class="stat-label">Units Donated</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-minus-circle"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['total_withdrawals']); ?></div>
                    <div class="stat-label">Units Withdrawn</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-edit"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['total_adjustments']); ?></div>
                    <div class="stat-label">Adjustments Made</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['today_activities']); ?></div>
                    <div class="stat-label">Today's Activities</div>
                </div>
            </div>
        </section>

        <!-- Filters Section -->
        <section class="filters-section">
            <div class="filters-container">
                <h3 class="filters-title">
                    <i class="fas fa-filter"></i>
                    Filter & Search Logs
                </h3>
                
                <form method="GET" action="">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label class="filter-label">Operation Type</label>
                            <select name="filter_type" class="filter-control">
                                <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>All Operations</option>
                                <option value="donation" <?php echo $filter_type === 'donation' ? 'selected' : ''; ?>>Donations</option>
                                <option value="withdrawal" <?php echo $filter_type === 'withdrawal' ? 'selected' : ''; ?>>Withdrawals</option>
                                <option value="adjustment" <?php echo $filter_type === 'adjustment' ? 'selected' : ''; ?>>Adjustments</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">Blood Type</label>
                            <select name="filter_blood_type" class="filter-control">
                                <option value="">All Blood Types</option>
                                <?php if ($blood_types_result): ?>
                                    <?php while ($bt = $blood_types_result->fetch_assoc()): ?>
                                        <option value="<?php echo $bt['blood_type']; ?>" <?php echo $filter_blood_type === $bt['blood_type'] ? 'selected' : ''; ?>>
                                            <?php echo $bt['blood_type']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
     </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">Date</label>
                            <input type="date" name="filter_date" class="filter-control" value="<?php echo htmlspecialchars($filter_date); ?>">
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">Search</label>
                            <input type="text" name="search" class="filter-control" placeholder="Search by username or notes..." value="<?php echo htmlspecialchars($search_term); ?>">
                        </div>
                    </div>

                    <div class="filter-actions">
                        <a href="logs.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <!-- Logs Table Section -->
        <section class="logs-section">
            <div class="table-container">
                <div class="table-header">
                    <div class="table-title">
                        <i class="fas fa-history"></i>
                        Activity Log History
                    </div>
                    <div>
                        <span style="font-size: 14px; opacity: 0.9;">
                            Showing <?php echo number_format($total_records); ?> total records
                        </span>
                    </div>
                </div>

                <?php if ($logs_result && $logs_result->num_rows > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th><i class="fas fa-id-badge"></i> Log ID</th>
                                <th><i class="fas fa-cog"></i> Operation</th>
                                <th><i class="fas fa-tint"></i> Blood Type</th>
                                <th><i class="fas fa-sort-numeric-up"></i> Units</th>
                                <th><i class="fas fa-user"></i> User</th>
                                <th><i class="fas fa-calendar"></i> Date</th>
                                <th><i class="fas fa-clock"></i> Time</th>
                                <th><i class="fas fa-sticky-note"></i> Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($log = $logs_result->fetch_assoc()): ?>
                            <tr>
                                <td><strong>#<?php echo $log['log_id']; ?></strong></td>
                                <td>
                                    <span class="operation-badge operation-<?php echo strtolower($log['operation_type']); ?>">
                                        <i class="fas fa-<?php echo $log['operation_type'] === 'DONATION' ? 'plus' : ($log['operation_type'] === 'WITHDRAWAL' ? 'minus' : 'edit'); ?>"></i>
                                        <?php echo ucfirst(strtolower($log['operation_type'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="blood-type-badge"><?php echo htmlspecialchars($log['blood_type']); ?></span>
                                </td>
                                <td><strong><?php echo number_format($log['units_donated']); ?></strong></td>
                                <td>
                                    <?php if ($log['username']): ?>
                                        <?php echo htmlspecialchars($log['username']); ?>
                                    <?php else: ?>
                                        <span style="color: #6c757d; font-style: italic;">System</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($log['timestamp_update'])); ?></td>
                                <td><?php echo date('g:i A', strtotime($log['timestamp_update'])); ?></td>
                                <td>
                                    <?php if ($log['notes']): ?>
                                        <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($log['notes']); ?>">
                                            <?php echo htmlspecialchars($log['notes']); ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #6c757d; font-style: italic;">No notes</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination-container">
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="pagination-btn">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>

                            <div class="pagination-info">
                                Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                            </div>

                            <?php if ($page < $total_pages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="pagination-btn">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-list"></i>
                        <h3>No Activity Logs Found</h3>
                        <p>
                            <?php if (!empty($where_conditions)): ?>
                                No logs match your current filter criteria. Try adjusting your filters or clearing them to see all activities.
                            <?php else: ?>
                                No blood bank activities have been logged yet. Activities will appear here as donations, withdrawals, and adjustments are made.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</main>

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
// Counter animation for stats
document.addEventListener('DOMContentLoaded', function() {
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

    // Auto-refresh functionality (every 2 minutes)
    let autoRefreshInterval;
    
    function startAutoRefresh() {
        autoRefreshInterval = setInterval(() => {
            // Only refresh if no filters are applied to avoid losing user's work
            const urlParams = new URLSearchParams(window.location.search);
            const hasFilters = urlParams.get('filter_type') !== 'all' || 
                             urlParams.get('filter_date') || 
                             urlParams.get('filter_blood_type') || 
                             urlParams.get('search');
            
            if (!hasFilters) {
                window.location.reload();
            }
        }, 120000); // 2 minutes
    }

    // Start auto-refresh
    startAutoRefresh();

    // Add visual feedback for form submission
    const filterForm = document.querySelector('form');
    if (filterForm) {
        filterForm.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Applying...';
                submitBtn.disabled = true;
            }
        });
    }

    // Add keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + R to refresh
        if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
            e.preventDefault();
            window.location.reload();
        }
        
        // Ctrl/Cmd + F to focus search
        if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
            e.preventDefault();
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
    });

    // Add tooltips for truncated content
    const truncatedElements = document.querySelectorAll('[title]');
    truncatedElements.forEach(element => {
        element.addEventListener('mouseenter', function() {
            this.style.cursor = 'help';
        });
    });

    // Highlight current filters
    const activeFilters = document.querySelectorAll('.filter-control');
    activeFilters.forEach(filter => {
        if (filter.value && filter.value !== 'all' && filter.value !== '') {
            filter.style.borderColor = '#dc3545';
            filter.style.backgroundColor = 'rgba(220, 53, 69, 0.05)';
        }
    });
});

// Export functionality (basic CSV export)
function exportLogs() {
    const table = document.querySelector('.data-table');
    if (!table) return;
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = [];
        const cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            let cellText = cols[j].innerText.replace(/"/g, '""');
            row.push('"' + cellText + '"');
        }
        csv.push(row.join(','));
    }
    
    // Create and download CSV file
    const csvFile = new Blob([csv.join('\n')], { type: 'text/csv' });
    const downloadLink = document.createElement('a');
    downloadLink.download = 'blood_bank_logs_' + new Date().toISOString().split('T')[0] + '.csv';
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

// Print functionality
function printLogs() {
    const printWindow = window.open('', '_blank');
    const tableHtml = document.querySelector('.table-container').outerHTML;
    
    printWindow.document.write(`
        <html>
        <head>
            <title>Blood Bank Activity Logs</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .table-container { box-shadow: none; border: 1px solid #ddd; }
                .data-table { width: 100%; border-collapse: collapse; }
                .data-table th, .data-table td { 
                    padding: 8px; 
                    border: 1px solid #ddd; 
                    font-size: 12px;
                }
                .data-table th { background: #f5f5f5; }
                .operation-badge { 
                    padding: 2px 6px; 
                    border-radius: 3px; 
                    font-size: 10px;
                    color: #333;
                    background: #f0f0f0;
                }
                .blood-type-badge {
                    font-weight: bold;
                    color: #dc3545;
                }
                @media print {
                    body { margin: 0; }
                    .table-header { background: #f5f5f5 !important; color: #333 !important; }
                }
            </style>
        </head>
        <body>
            <h1>Blood Bank Activity Logs</h1>
            <p>Generated on: ${new Date().toLocaleDateString()}</p>
            ${tableHtml}
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.focus();
    
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 250);
}

// Add export and print buttons to the page
document.addEventListener('DOMContentLoaded', function() {
    const tableHeader = document.querySelector('.table-header');
    if (tableHeader) {
        const actionsDiv = document.createElement('div');
        actionsDiv.style.display = 'flex';
        actionsDiv.style.gap = '10px';
        
        const exportBtn = document.createElement('button');
        exportBtn.className = 'btn btn-secondary';
        exportBtn.innerHTML = '<i class="fas fa-download"></i> Export CSV';
        exportBtn.onclick = exportLogs;
        exportBtn.style.fontSize = '12px';
        exportBtn.style.padding = '6px 12px';
        
        const printBtn = document.createElement('button');
        printBtn.className = 'btn btn-secondary';
        printBtn.innerHTML = '<i class="fas fa-print"></i> Print';
        printBtn.onclick = printLogs;
        printBtn.style.fontSize = '12px';
        printBtn.style.padding = '6px 12px';
        
        actionsDiv.appendChild(exportBtn);
        actionsDiv.appendChild(printBtn);
        
        const rightSection = tableHeader.querySelector('div:last-child');
        rightSection.appendChild(actionsDiv);
    }
});
</script>

<style>
/* Additional styles for enhanced functionality */
.btn-group {
    display: flex;
    gap: 5px;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}

.filter-control:focus {
    box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
}

.data-table tr:hover .operation-badge {
    transform: scale(1.05);
}

.data-table tr:hover .blood-type-badge {
    background: rgba(220, 53, 69, 0.2);
}

/* Loading spinner */
.btn .fa-spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Responsive table scroll */
@media (max-width: 768px) {
    .table-container {
        overflow-x: auto;
    }
    
    .data-table {
        min-width: 800px;
    }
}

/* Print-specific styles */
@media print {
    .filters-section,
    .page-header,
    .stats-section,
    .pagination-container,
    .btn {
        display: none !important;
    }
    
    .table-container {
        box-shadow: none !important;
        border: 1px solid #333 !important;
    }
    
    .data-table th {
        background: #f5f5f5 !important;
        color: #333 !important;
    }
}
</style>
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

        .mobile-menu-toggle {
            display: none;
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
                       