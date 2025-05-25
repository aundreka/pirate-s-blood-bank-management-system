<?php 
include('../includes/db.php');
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Fetch dashboard statistics
try {
    // Total users count
    $user_result = $conn->query("SELECT COUNT(*) as total_users FROM login WHERE user_type = 'user'");
    $total_users = ($user_result && $user_result->num_rows > 0) ? $user_result->fetch_assoc()['total_users'] : 0;

    // Pending donations count
    $donation_result = $conn->query("SELECT COUNT(*) as pending_donations FROM donate_blood WHERE donation_status = 'Pending'");
    $pending_donations = ($donation_result && $donation_result->num_rows > 0) ? $donation_result->fetch_assoc()['pending_donations'] : 0;

    // Pending blood requests count
    $request_result = $conn->query("SELECT COUNT(*) as pending_requests FROM recipient_form WHERE request_status = 'Pending'");
    $pending_requests = ($request_result && $request_result->num_rows > 0) ? $request_result->fetch_assoc()['pending_requests'] : 0;

    // Total blood inventory
    $inventory_result = $conn->query("SELECT SUM(available_units) as total_units FROM blood_inventory");
    $total_inventory = 0;
    if ($inventory_result && $inventory_result->num_rows > 0) {
        $inventory_data = $inventory_result->fetch_assoc();
        $total_inventory = $inventory_data['total_units'] ?? 0;
    }

    // Low stock blood types (less than 5 units)
    $low_stock_result = $conn->query("SELECT COUNT(*) as low_stock_count FROM blood_inventory WHERE available_units < 5");
    $low_stock_count = ($low_stock_result && $low_stock_result->num_rows > 0) ? $low_stock_result->fetch_assoc()['low_stock_count'] : 0;

    // Recent activities count
    $recent_logs_result = $conn->query("SELECT COUNT(*) as recent_logs FROM blood_log WHERE DATE(timestamp_update) = CURDATE()");
    $recent_logs = ($recent_logs_result && $recent_logs_result->num_rows > 0) ? $recent_logs_result->fetch_assoc()['recent_logs'] : 0;

    // Upcoming events count
    $events_result = $conn->query("SELECT COUNT(*) as upcoming_events FROM events WHERE status = 'upcoming' AND event_date >= CURDATE()");
    $upcoming_events = ($events_result && $events_result->num_rows > 0) ? $events_result->fetch_assoc()['upcoming_events'] : 0;

    // Pending appeals count
    $appeals_result = $conn->query("SELECT COUNT(*) as pending_appeals FROM admin_appeals WHERE status = 'pending'");
    $pending_appeals = ($appeals_result && $appeals_result->num_rows > 0) ? $appeals_result->fetch_assoc()['pending_appeals'] : 0;

    // Blood inventory for chart
    $blood_inventory_result = $conn->query("SELECT blood_type, available_units FROM blood_inventory ORDER BY blood_type");
    $blood_inventory = [];
    if ($blood_inventory_result && $blood_inventory_result->num_rows > 0) {
        while ($row = $blood_inventory_result->fetch_assoc()) {
            $blood_inventory[] = $row;
        }
    }

    // Recent donations for activity feed
    $recent_donations_query = "
        SELECT d.donation_id, d.blood_type, d.donation_status, d.created_at, l.username
        FROM donate_blood d 
        JOIN login l ON d.donor_id = l.user_id 
        ORDER BY d.created_at DESC 
        LIMIT 5
    ";
    $recent_donations_result = $conn->query($recent_donations_query);
    $recent_donations = [];
    if ($recent_donations_result && $recent_donations_result->num_rows > 0) {
        while ($row = $recent_donations_result->fetch_assoc()) {
            $recent_donations[] = $row;
        }
    }

} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    // Set default values in case of error
    $total_users = $pending_donations = $pending_requests = $total_inventory = 0;
    $low_stock_count = $recent_logs = $upcoming_events = $pending_appeals = 0;
    $blood_inventory = $recent_donations = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Pirate's Blood Bank</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cardo:ital,wght@0,400;0,700;1,400&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Quicksand:wght@300..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        }

        /* Content wrapper to handle sidebar offset */
        .content-wrapper {
            width: 100%;
            box-sizing: border-box;
        }

        /* Hero Section for Dashboard */
        .dashboard-hero {
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.9), rgba(176, 42, 55, 0.9));
            color: white;
            padding: 60px 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Animated Background Particles */
        .dashboard-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 20% 30%, rgba(255, 255, 255, 0.1) 2px, transparent 2px),
                radial-gradient(circle at 80% 70%, rgba(255, 255, 255, 0.08) 2px, transparent 2px),
                radial-gradient(circle at 40% 80%, rgba(255, 255, 255, 0.06) 1px, transparent 1px);
            background-size: 100px 100px, 150px 150px, 80px 80px;
            animation: floatParticles 20s infinite linear;
            z-index: 1;
        }

        @keyframes floatParticles {
            0% { transform: translateY(0) rotate(0deg); }
            100% { transform: translateY(-100px) rotate(360deg); }
        }

        /* Floating Admin Icons */
        .admin-icon {
            position: absolute;
            color: rgba(255, 255, 255, 0.3);
            font-size: 20px;
            animation: floatIcon 8s infinite ease-in-out;
            z-index: 2;
        }

        .admin-icon:nth-child(1) { left: 10%; animation-delay: 0s; }
        .admin-icon:nth-child(2) { left: 20%; animation-delay: 1s; }
        .admin-icon:nth-child(3) { right: 15%; animation-delay: 2s; }
        .admin-icon:nth-child(4) { right: 25%; animation-delay: 3s; }
        .admin-icon:nth-child(5) { left: 60%; animation-delay: 4s; }
        .admin-icon:nth-child(6) { right: 40%; animation-delay: 5s; }

        @keyframes floatIcon {
            0%, 100% { 
                transform: translateY(0) rotate(0deg) scale(1);
                opacity: 0.3;
            }
            25% { 
                transform: translateY(-30px) rotate(90deg) scale(1.2);
                opacity: 0.6;
            }
            50% { 
                transform: translateY(-60px) rotate(180deg) scale(0.8);
                opacity: 0.4;
            }
            75% { 
                transform: translateY(-30px) rotate(270deg) scale(1.1);
                opacity: 0.5;
            }
        }

        .dashboard-hero-content {
            position: relative;
            z-index: 10;
            max-width: 800px;
        }

        .dashboard-title {
            font-size: 42px;
            font-weight: 700;
            margin-bottom: 15px;
            font-family: 'Poppins', sans-serif;
            color: white;
            opacity: 0;
            animation: slideInFromTop 1s ease-out 0.5s forwards;
        }

        @keyframes slideInFromTop {
            0% {
                opacity: 0;
                transform: translateY(-50px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dashboard-subtitle {
            font-size: 18px;
            margin-bottom: 30px;
            opacity: 0;
            line-height: 1.6;
            animation: slideInFromBottom 1s ease-out 1s forwards;
        }

        @keyframes slideInFromBottom {
            0% {
                opacity: 0;
                transform: translateY(30px);
            }
            100% {
                opacity: 0.95;
                transform: translateY(0);
            }
        }

        /* Alert Styling */
        .alert {
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin: 40px;
            text-align: center;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        /* Quick Actions Section */
        .quick-actions-section {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            box-shadow: 2px 0 15px rgba(220, 220, 220, 0.1);
            margin: 0;
            padding: 60px 40px;
            width: 100%;
            box-sizing: border-box;
        }

        .section-title {
            font-size: 36px;
            font-weight: 700;
            color: #495057;
            text-align: center;
            margin-bottom: 20px;
            font-family: 'Poppins', sans-serif;
        }

        .section-subtitle {
            text-align: center;
            color: #6c757d;
            font-size: 18px;
            margin-bottom: 50px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.6;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .action-btn {
            background: white;
            padding: 40px 30px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border-top: 4px solid #dc3545;
            text-decoration: none;
            color: #495057;
            display: block;
        }

        .action-btn:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
            color: #495057;
        }

        .action-btn i {
            font-size: 48px;
            color: #dc3545;
            margin-bottom: 20px;
            display: block;
        }

        .action-btn-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #495057;
        }

        .action-btn-desc {
            color: #6c757d;
            line-height: 1.6;
            font-size: 14px;
        }

        /* Statistics Section */
        .stats-section {
            background: #f8f9fa;
            padding: 80px 40px;
            width: 100%;
            box-sizing: border-box;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 8px 25px rgba(220, 220, 220, 0.15);
            border: 1px solid #f8f9fa;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            text-align: center;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.12);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(135deg, #dc3545, #b02a37);
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

        .stat-trend {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .trend-success { background: #dcfce7; color: #166534; }
        .trend-warning { background: #fef3c7; color: #92400e; }
        .trend-danger { background: #fee2e2; color: #dc2626; }
        .trend-info { background: #dbeafe; color: #1d4ed8; }

        /* Charts Section */
        .charts-section {
            background: white;
            padding: 80px 40px;
            width: 100%;
            box-sizing: border-box;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 8px 25px rgba(220, 220, 220, 0.15);
            border: 1px solid #f8f9fa;
        }

        .chart-title {
            font-size: 24px;
            font-weight: 600;
            color: #495057;
            margin-bottom: 25px;
            font-family: 'Poppins', sans-serif;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chart-title i {
            color: #dc3545;
            font-size: 20px;
        }

        /* Activity Section */
        .activity-section {
            background: #f8f9fa;
            padding: 80px 40px;
            width: 100%;
            box-sizing: border-box;
        }

        .activity-container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 8px 25px rgba(220, 220, 220, 0.15);
        }

        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 25px;
        }

        .activity-title {
            font-size: 28px;
            font-weight: 600;
            color: #495057;
            display: flex;
            align-items: center;
            gap: 15px;
            font-family: 'Poppins', sans-serif;
        }

        .activity-title i {
            color: #dc3545;
            font-size: 24px;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px 0;
            border-bottom: 1px solid #f8f9fa;
            transition: all 0.3s ease;
        }

        .activity-item:hover {
            background: linear-gradient(90deg, rgba(220, 53, 69, 0.05), rgba(220, 53, 69, 0.02));
            transform: translateX(5px);
            padding-left: 10px;
            border-radius: 10px;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));
            color: #dc3545;
            font-size: 1.2rem;
        }

        .activity-content {
            flex: 1;
        }

        .activity-text {
            font-weight: 600;
            color: #495057;
            margin-bottom: 5px;
            font-size: 16px;
        }

        .activity-time {
            font-size: 14px;
            color: #6c757d;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 5px;
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
        .status-completed { 
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
        }
        .status-rejected { 
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 60px;
            color: #495057;
            font-style: italic;
            font-size: 16px;
        }

        .empty-state i {
            color: #dc3545;
            opacity: 0.7;
            font-size: 48px;
            margin-bottom: 15px;
            display: block;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .content-wrapper {
                margin-left: 200px;
                width: calc(100% - 200px);
            }

            .charts-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .content-wrapper {
                margin-left: 0;
                width: 100%;
            }

            .dashboard-hero {
                padding: 40px 20px;
            }

            .dashboard-title {
                font-size: 32px;
            }

            .dashboard-subtitle {
                font-size: 16px;
            }

            .quick-actions-section, .stats-section, .charts-section, .activity-section {
                padding: 40px 20px;
            }

            .section-title {
                font-size: 28px;
            }

            .section-subtitle {
                font-size: 16px;
            }

            .quick-actions {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .charts-grid {
                grid-template-columns: 1fr;
            }

            .activity-header {
                flex-direction: column;
                align-items: stretch;
                gap: 20px;
            }

            .activity-title {
                font-size: 20px;
            }

            .admin-icon {
                display: none;
            }
        }
    </style>
</head>

<?php include('../includes/adminheader.php'); ?>

<body>
<main>
    <div class="content-wrapper">
        <!-- Dashboard Hero Section -->
        <section class="dashboard-hero">
            <!-- Floating Admin Icons -->
            <div class="admin-icon"><i class="fas fa-chart-line"></i></div>
            <div class="admin-icon"><i class="fas fa-users"></i></div>
            <div class="admin-icon"><i class="fas fa-tint"></i></div>
            <div class="admin-icon"><i class="fas fa-calendar-alt"></i></div>
            <div class="admin-icon"><i class="fas fa-cog"></i></div>
            <div class="admin-icon"><i class="fas fa-shield-alt"></i></div>

            <div class="dashboard-hero-content">
                <h1 class="dashboard-title">
                    <i class="fas fa-tachometer-alt"></i>
                    Admin Dashboard
                </h1>
                <p class="dashboard-subtitle">Welcome back! Manage your blood bank operations and monitor system performance from this central hub.</p>
            </div>
        </section>

        <?php if (isset($error_message)): ?>
            <div class="alert">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Quick Actions Section -->
        <section class="quick-actions-section">
            <h2 class="section-title">Quick Actions</h2>
            <p class="section-subtitle">Access the most commonly used administrative functions with a single click</p>
            
            <div class="quick-actions">
                <a href="users.php" class="action-btn">
                    <i class="fas fa-users"></i>
                    <div class="action-btn-title">Manage Users</div>
                    <div class="action-btn-desc">View, edit, and manage user accounts and permissions in the system</div>
                </a>
                <a href="donations.php" class="action-btn">
                    <i class="fas fa-tint"></i>
                    <div class="action-btn-title">Review Donations</div>
                    <div class="action-btn-desc">Process and approve blood donation requests from registered donors</div>
                </a>
                <a href="requests.php" class="action-btn">
                    <i class="fas fa-hand-holding-medical"></i>
                    <div class="action-btn-title">Process Requests</div>
                    <div class="action-btn-desc">Handle blood requests from hospitals and medical facilities</div>
                </a>
                <a href="logs.php" class="action-btn">
                    <i class="fas fa-list-alt"></i>
                    <div class="action-btn-title">View Logs</div>
                    <div class="action-btn-desc">Monitor system activities and track all blood bank operations</div>
                </a>
                <a href="statistics.php" class="action-btn">
                    <i class="fas fa-chart-bar"></i>
                    <div class="action-btn-title">Statistics</div>
                    <div class="action-btn-desc">Analyze performance metrics and generate comprehensive reports</div>
                </a>
                <a href="events.php" class="action-btn">
                    <i class="fas fa-calendar-alt"></i>
                    <div class="action-btn-title">Events</div>
                    <div class="action-btn-desc">Organize and manage blood drive events and donation campaigns</div>
                </a>
            </div>
        </section>

        <!-- Statistics Section -->
        <section class="stats-section">
            <h2 class="section-title">System Overview</h2>
            <p class="section-subtitle">Real-time statistics and key performance indicators for your blood bank</p>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-trend trend-info">Active</div>
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($total_users); ?></div>
                    <div class="stat-label">Total Users</div>
                </div>

                <div class="stat-card">
                    <?php if ($pending_donations > 0): ?>
                        <div class="stat-trend trend-warning">Review Needed</div>
                    <?php endif; ?>
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($pending_donations); ?></div>
                    <div class="stat-label">Pending Donations</div>
                </div>

                <div class="stat-card">
                    <?php if ($pending_requests > 0): ?>
                        <div class="stat-trend trend-danger">Urgent</div>
                    <?php endif; ?>
                    <div class="stat-icon">
                        <i class="fas fa-hand-holding-medical"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($pending_requests); ?></div>
                    <div class="stat-label">Pending Requests</div>
                </div>

                <div class="stat-card">
                    <?php if ($low_stock_count > 0): ?>
                        <div class="stat-trend trend-warning"><?php echo $low_stock_count; ?> Low</div>
                    <?php else: ?>
                        <div class="stat-trend trend-success">Good Stock</div>
                    <?php endif; ?>
                    <div class="stat-icon">
                        <i class="fas fa-tint"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($total_inventory); ?></div>
                    <div class="stat-label">Blood Units Available</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($upcoming_events); ?></div>
                    <div class="stat-label">Upcoming Events</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-list-alt"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($recent_logs); ?></div>
                    <div class="stat-label">Today's Activities</div>
                </div>

                <div class="stat-card">
                    <?php if ($pending_appeals > 0): ?>
                        <div class="stat-trend trend-warning">Review Needed</div>
                    <?php endif; ?>
                    <div class="stat-icon">
                        <i class="fas fa-gavel"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($pending_appeals); ?></div>
                    <div class="stat-label">Pending Appeals</div>
                </div>
            </div>
        </section>


        <!-- Recent Activity Section -->
        <section class="activity-section">
            <div class="activity-container">
                <div class="activity-header">
                    <h3 class="activity-title">
                        <i class="fas fa-history"></i>
                        Recent Activities
                    </h3>
                </div>

                <?php if (empty($recent_donations)): ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-list"></i>
                        <p>No recent donation activities to display.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recent_donations as $donation): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-tint"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-text">
                                    <?php echo htmlspecialchars($donation['username']); ?> 
                                    submitted <?php echo htmlspecialchars($donation['blood_type']); ?> blood donation
                                </div>
                                <div class="activity-time">
                                    <i class="fas fa-clock"></i>
                                    <?php echo date('M j, Y g:i A', strtotime($donation['created_at'])); ?>
                                    <span class="status-badge status-<?php echo strtolower($donation['donation_status']); ?>">
                                        <?php echo $donation['donation_status']; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>
</main>

<script>
// Blood Inventory Chart
const inventoryCtx = document.getElementById('inventoryChart').getContext('2d');

// Prepare data for chart
const bloodTypes = [<?php foreach ($blood_inventory as $blood) echo "'" . addslashes($blood['blood_type']) . "',"; ?>];
const bloodUnits = [<?php foreach ($blood_inventory as $blood) echo intval($blood['available_units']) . ","; ?>];

const inventoryChart = new Chart(inventoryCtx, {
    type: 'doughnut',
    data: {
        labels: bloodTypes,
        datasets: [{
            data: bloodUnits,
            backgroundColor: [
                '#dc3545', '#fd7e14', '#ffc107', '#28a745',
                '#20c997', '#0dcaf0', '#6f42c1', '#e83e8c'
            ],
            borderWidth: 3,
            borderColor: '#fff',
            hoverBorderWidth: 4,
            hoverBorderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 20,
                    usePointStyle: true,
                    font: {
                        family: 'Poppins',
                        size: 12
                    }
                }
            }
        },
        elements: {
            arc: {
                borderWidth: 3
            }
        }
    }
});

// System Statistics Chart
const statsCtx = document.getElementById('statsChart').getContext('2d');
const statsChart = new Chart(statsCtx, {
    type: 'bar',
    data: {
        labels: ['Users', 'Pending Donations', 'Pending Requests', 'Events', 'Appeals'],
        datasets: [{
            label: 'Count',
            data: [
                <?php echo intval($total_users); ?>,
                <?php echo intval($pending_donations); ?>,
                <?php echo intval($pending_requests); ?>,
                <?php echo intval($upcoming_events); ?>,
                <?php echo intval($pending_appeals); ?>
            ],
            backgroundColor: [
                '#0dcaf0',
                '#ffc107',
                '#dc3545',
                '#6f42c1',
                '#fd7e14'
            ],
            borderRadius: 8,
            borderSkipped: false,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1,
                    font: {
                        family: 'Poppins'
                    }
                },
                grid: {
                    color: '#f1f5f9'
                }
            },
            x: {
                ticks: {
                    font: {
                        family: 'Poppins'
                    }
                },
                grid: {
                    display: false
                }
            }
        }
    }
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
                const increment = targetNum / 50;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= targetNum) {
                        current = targetNum;
                        clearInterval(timer);
                    }
                    stat.textContent = new Intl.NumberFormat().format(Math.floor(current));
                }, 30);
            }
        });
    };

    // Trigger stat animation when stats are visible
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                setTimeout(animateStats, 500);
                observer.unobserve(entry.target);
            }
        });
    });

    const statsSection = document.querySelector('.stats-section');
    if (statsSection) {
        observer.observe(statsSection);
    }
});
</script>

</body>
</html>