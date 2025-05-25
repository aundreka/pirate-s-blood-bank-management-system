<?php 
include('../includes/db.php');
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Fetch comprehensive statistics
try {
    // User Statistics
    $total_users_result = $conn->query("SELECT COUNT(*) as count FROM login WHERE user_type = 'user'");
    $total_users = ($total_users_result && $total_users_result->num_rows > 0) ? $total_users_result->fetch_assoc()['count'] : 0;

    $new_users_today_result = $conn->query("SELECT COUNT(*) as count FROM login WHERE user_type = 'user' AND DATE(created_at) = CURDATE()");
    $new_users_today = ($new_users_today_result && $new_users_today_result->num_rows > 0) ? $new_users_today_result->fetch_assoc()['count'] : 0;

    $new_users_week_result = $conn->query("SELECT COUNT(*) as count FROM login WHERE user_type = 'user' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $new_users_week = ($new_users_week_result && $new_users_week_result->num_rows > 0) ? $new_users_week_result->fetch_assoc()['count'] : 0;

    $new_users_month_result = $conn->query("SELECT COUNT(*) as count FROM login WHERE user_type = 'user' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $new_users_month = ($new_users_month_result && $new_users_month_result->num_rows > 0) ? $new_users_month_result->fetch_assoc()['count'] : 0;

    // Donation Statistics
    $total_donations_result = $conn->query("SELECT COUNT(*) as count FROM donate_blood");
    $total_donations = ($total_donations_result && $total_donations_result->num_rows > 0) ? $total_donations_result->fetch_assoc()['count'] : 0;

    $completed_donations_result = $conn->query("SELECT COUNT(*) as count FROM donate_blood WHERE donation_status = 'Completed'");
    $completed_donations = ($completed_donations_result && $completed_donations_result->num_rows > 0) ? $completed_donations_result->fetch_assoc()['count'] : 0;

    $pending_donations_result = $conn->query("SELECT COUNT(*) as count FROM donate_blood WHERE donation_status = 'Pending'");
    $pending_donations = ($pending_donations_result && $pending_donations_result->num_rows > 0) ? $pending_donations_result->fetch_assoc()['count'] : 0;

    $approved_donations_result = $conn->query("SELECT COUNT(*) as count FROM donate_blood WHERE donation_status = 'Approved'");
    $approved_donations = ($approved_donations_result && $approved_donations_result->num_rows > 0) ? $approved_donations_result->fetch_assoc()['count'] : 0;

    $rejected_donations_result = $conn->query("SELECT COUNT(*) as count FROM donate_blood WHERE donation_status = 'Rejected'");
    $rejected_donations = ($rejected_donations_result && $rejected_donations_result->num_rows > 0) ? $rejected_donations_result->fetch_assoc()['count'] : 0;

    // Blood Request Statistics
    $total_requests_result = $conn->query("SELECT COUNT(*) as count FROM recipient_form");
    $total_requests = ($total_requests_result && $total_requests_result->num_rows > 0) ? $total_requests_result->fetch_assoc()['count'] : 0;

    $fulfilled_requests_result = $conn->query("SELECT COUNT(*) as count FROM recipient_form WHERE request_status = 'Fulfilled'");
    $fulfilled_requests = ($fulfilled_requests_result && $fulfilled_requests_result->num_rows > 0) ? $fulfilled_requests_result->fetch_assoc()['count'] : 0;

    $pending_requests_result = $conn->query("SELECT COUNT(*) as count FROM recipient_form WHERE request_status = 'Pending'");
    $pending_requests = ($pending_requests_result && $pending_requests_result->num_rows > 0) ? $pending_requests_result->fetch_assoc()['count'] : 0;

    // Blood Inventory Statistics
    $total_blood_units_result = $conn->query("SELECT SUM(available_units) as total FROM blood_inventory");
    $total_blood_units = ($total_blood_units_result && $total_blood_units_result->num_rows > 0) ? ($total_blood_units_result->fetch_assoc()['total'] ?? 0) : 0;

    $low_stock_count_result = $conn->query("SELECT COUNT(*) as count FROM blood_inventory WHERE available_units < 10");
    $low_stock_count = ($low_stock_count_result && $low_stock_count_result->num_rows > 0) ? $low_stock_count_result->fetch_assoc()['count'] : 0;

    // Event Statistics
    $total_events_result = $conn->query("SELECT COUNT(*) as count FROM events");
    $total_events = ($total_events_result && $total_events_result->num_rows > 0) ? $total_events_result->fetch_assoc()['count'] : 0;

    $upcoming_events_result = $conn->query("SELECT COUNT(*) as count FROM events WHERE status = 'upcoming'");
    $upcoming_events = ($upcoming_events_result && $upcoming_events_result->num_rows > 0) ? $upcoming_events_result->fetch_assoc()['count'] : 0;

    $event_registrations_result = $conn->query("SELECT COUNT(*) as count FROM event_registrations WHERE status = 'registered'");
    $event_registrations = ($event_registrations_result && $event_registrations_result->num_rows > 0) ? $event_registrations_result->fetch_assoc()['count'] : 0;

    // Blood Type Distribution
    $blood_distribution_result = $conn->query("SELECT blood_type, available_units FROM blood_inventory ORDER BY blood_type");
    $blood_distribution = [];
    if ($blood_distribution_result && $blood_distribution_result->num_rows > 0) {
        while ($row = $blood_distribution_result->fetch_assoc()) {
            $blood_distribution[] = $row;
        }
    }

    // Monthly User Registration Trend (Last 12 months)
    $user_trend_result = $conn->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as user_count
        FROM login 
        WHERE user_type = 'user' 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $user_trend = [];
    if ($user_trend_result && $user_trend_result->num_rows > 0) {
        while ($row = $user_trend_result->fetch_assoc()) {
            $user_trend[] = $row;
        }
    }

    // Monthly Donation Trend (Last 12 months)
    $donation_trend_result = $conn->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as donation_count
        FROM donate_blood 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $donation_trend = [];
    if ($donation_trend_result && $donation_trend_result->num_rows > 0) {
        while ($row = $donation_trend_result->fetch_assoc()) {
            $donation_trend[] = $row;
        }
    }

    // Top Donors (Users with most completed donations)
    $top_donors_result = $conn->query("
        SELECT 
            l.username,
            COUNT(d.donation_id) as donation_count,
            d.blood_type
        FROM login l
        JOIN donate_blood d ON l.user_id = d.donor_id
        WHERE d.donation_status = 'Completed'
        GROUP BY l.user_id, l.username, d.blood_type
        ORDER BY donation_count DESC
        LIMIT 10
    ");
    $top_donors = [];
    if ($top_donors_result && $top_donors_result->num_rows > 0) {
        while ($row = $top_donors_result->fetch_assoc()) {
            $top_donors[] = $row;
        }
    }

    // Blood Type Demand Analysis
    $blood_demand_result = $conn->query("
        SELECT 
            blood_type_needed as blood_type,
            COUNT(*) as request_count,
            SUM(units_needed) as total_units_needed
        FROM recipient_form
        GROUP BY blood_type_needed
        ORDER BY request_count DESC
    ");
    $blood_demand = [];
    if ($blood_demand_result && $blood_demand_result->num_rows > 0) {
        while ($row = $blood_demand_result->fetch_assoc()) {
            $blood_demand[] = $row;
        }
    }

    // Recent Activity Summary (Last 30 days)
    $activity_donations = $conn->query("SELECT COUNT(*) as count FROM donate_blood WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc()['count'] ?? 0;
    $activity_requests = $conn->query("SELECT COUNT(*) as count FROM recipient_form WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc()['count'] ?? 0;
    $activity_events = $conn->query("SELECT COUNT(*) as count FROM events WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc()['count'] ?? 0;
    $activity_registrations = $conn->query("SELECT COUNT(*) as count FROM event_registrations WHERE registration_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc()['count'] ?? 0;

} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    // Set default values in case of error
    $total_users = $new_users_today = $new_users_week = $new_users_month = 0;
    $total_donations = $completed_donations = $pending_donations = $approved_donations = $rejected_donations = 0;
    $total_requests = $fulfilled_requests = $pending_requests = 0;
    $total_blood_units = $low_stock_count = 0;
    $total_events = $upcoming_events = $event_registrations = 0;
    $blood_distribution = $user_trend = $donation_trend = $top_donors = $blood_demand = [];
    $activity_donations = $activity_requests = $activity_events = $activity_registrations = 0;
}

// Calculate engagement rates
$donation_completion_rate = $total_donations > 0 ? round(($completed_donations / $total_donations) * 100, 1) : 0;
$request_fulfillment_rate = $total_requests > 0 ? round(($fulfilled_requests / $total_requests) * 100, 1) : 0;
$user_growth_rate = $total_users > 0 ? round(($new_users_month / $total_users) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistics Dashboard - Pirate's Blood Bank Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/date-fns@2.29.3/index.min.js"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            color: #333;
        }

        /* Main Layout */
        .main-content {
            margin-left: 70px;
            min-height: 100vh;
            padding: 2rem;
            transition: margin-left 0.3s ease;
            margin-bottom: 100px;
        }

        .admin-container {
            max-width: 1600px;
            margin: 0 auto;
        }

        /* Header */
        .page-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            text-align: center;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #374151;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 0.5rem;
        }

        .page-title i {
            color: #dc3545;
        }

        .page-subtitle {
            color: #6b7280;
            font-size: 1.1rem;
            font-weight: 400;
        }

        /* Error Message */
        .error-message {
            background: linear-gradient(90deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));
            color: #dc2626;
            border: 1px solid rgba(220, 53, 69, 0.2);
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Statistics Grid */
        .stats-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.2);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #f1f5f9;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--card-color);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
            background: var(--card-color);
        }

        .stat-main {
            text-align: center;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--card-color);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: #6b7280;
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-trend {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }

        .trend-up { color: #10b981; }
        .trend-down { color: #ef4444; }
        .trend-neutral { color: #6b7280; }

        .stat-details {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #f1f5f9;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .detail-label {
            color: #6b7280;
        }

        .detail-value {
            font-weight: 600;
            color: #374151;
        }

        /* Color Variables for Cards */
        .stat-card:nth-child(1) { --card-color: #3b82f6; }
        .stat-card:nth-child(2) { --card-color: #ef4444; }
        .stat-card:nth-child(3) { --card-color: #10b981; }
        .stat-card:nth-child(4) { --card-color: #f59e0b; }
        .stat-card:nth-child(5) { --card-color: #8b5cf6; }
        .stat-card:nth-child(6) { --card-color: #06b6d4; }

        /* Charts Section */
        .charts-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .chart-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.2);
        }

        .chart-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .chart-canvas {
            position: relative;
            height: 300px;
        }

        /* Full Width Charts */
        .chart-full {
            grid-column: 1 / -1;
        }

        .chart-full .chart-canvas {
            height: 400px;
        }

        /* Tables */
        .table-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            margin-bottom: 2rem;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .data-table th {
            background: linear-gradient(135deg, #dc3545, #b91c1c);
            color: white;
            text-align: center;
            font-weight: 600;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
        }

        .data-table td {
            padding: 0.75rem;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.875rem;
        }

        .data-table tr:hover {
            background: rgba(220, 53, 69, 0.03);
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        /* Badge */
        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.625rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-high { background: #fee2e2; color: #dc2626; }
        .badge-medium { background: #fef3c7; color: #92400e; }
        .badge-low { background: #dcfce7; color: #166534; }

        /* Engagement Metrics */
        .engagement-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .engagement-item {
            text-align: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 12px;
            border-left: 4px solid var(--item-color);
        }

        .engagement-item:nth-child(1) { --item-color: #10b981; }
        .engagement-item:nth-child(2) { --item-color: #3b82f6; }
        .engagement-item:nth-child(3) { --item-color: #f59e0b; }

        .engagement-percentage {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--item-color);
            margin-bottom: 0.25rem;
        }

        .engagement-label {
            font-size: 0.75rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 1.5rem;
            }
            
            .charts-section {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }
            
            .page-header {
                padding: 2rem;
            }
            
            .page-title {
                font-size: 2rem;
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            .stat-card {
                padding: 1rem;
            }
            
            .stat-number {
                font-size: 2rem;
            }
            
            .chart-container {
                padding: 1.5rem;
            }
            
            .table-container {
                padding: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .page-title {
                font-size: 1.75rem;
            }
            
            .stat-number {
                font-size: 1.75rem;
            }
            
            .engagement-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Include Sidebar Styles */
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

        .sidebar .section-title {
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

            .sidebar-overlay.mobile-open {
                display: block;
            }
        }
    </style>
</head>

<?php include('../includes/adminheader.php'); ?>

<body>
    <!-- Mobile Menu Toggle Button -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle">☰</button>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
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
                <a href="statistics.php" class="nav-item" style="background: linear-gradient(90deg, rgba(220, 53, 69, 0.15), rgba(220, 53, 69, 0.08)); color: #dc3545; font-weight: 600;">
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

    <main class="main-content">
        <div class="admin-container">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-chart-line"></i>
                    Statistics Dashboard
                </h1>
                <p class="page-subtitle">Comprehensive analytics and insights into your blood bank operations</p>
            </div>

            <?php if (isset($error_message)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Key Performance Indicators -->
            <div class="stats-section">
                <h2 class="section-title">
                    <i class="fas fa-tachometer-alt"></i>
                    Key Performance Indicators
                </h2>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                        <div class="stat-main">
                            <div class="stat-number"><?php echo number_format($total_users); ?></div>
                            <div class="stat-label">Total Users</div>
                        </div>
                        <div class="stat-details">
                            <div class="detail-item">
                                <span class="detail-label">New Today:</span>
                                <span class="detail-value"><?php echo $new_users_today; ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">This Week:</span>
                                <span class="detail-value"><?php echo $new_users_week; ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">This Month:</span>
                                <span class="detail-value"><?php echo $new_users_month; ?></span>
                            </div>
                        </div>
                        <div class="stat-trend trend-<?php echo $user_growth_rate > 5 ? 'up' : ($user_growth_rate < 2 ? 'down' : 'neutral'); ?>">
                            <i class="fas fa-arrow-<?php echo $user_growth_rate > 5 ? 'up' : ($user_growth_rate < 2 ? 'down' : 'right'); ?>"></i>
                            <?php echo $user_growth_rate; ?>% growth rate
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="fas fa-tint"></i>
                            </div>
                        </div>
                        <div class="stat-main">
                            <div class="stat-number"><?php echo number_format($total_donations); ?></div>
                            <div class="stat-label">Total Donations</div>
                        </div>
                        <div class="stat-details">
                            <div class="detail-item">
                                <span class="detail-label">Completed:</span>
                                <span class="detail-value"><?php echo $completed_donations; ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Pending:</span>
                                <span class="detail-value"><?php echo $pending_donations; ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Approved:</span>
                                <span class="detail-value"><?php echo $approved_donations; ?></span>
                            </div>
                        </div>
                        <div class="stat-trend trend-<?php echo $donation_completion_rate > 80 ? 'up' : ($donation_completion_rate < 60 ? 'down' : 'neutral'); ?>">
                            <i class="fas fa-percentage"></i>
                            <?php echo $donation_completion_rate; ?>% completion rate
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="fas fa-hand-holding-medical"></i>
                            </div>
                        </div>
                        <div class="stat-main">
                            <div class="stat-number"><?php echo number_format($total_requests); ?></div>
                            <div class="stat-label">Blood Requests</div>
                        </div>
                        <div class="stat-details">
                            <div class="detail-item">
                                <span class="detail-label">Fulfilled:</span>
                                <span class="detail-value"><?php echo $fulfilled_requests; ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Pending:</span>
                                <span class="detail-value"><?php echo $pending_requests; ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Success Rate:</span>
                                <span class="detail-value"><?php echo $request_fulfillment_rate; ?>%</span>
                            </div>
                        </div>
                        <div class="stat-trend trend-<?php echo $request_fulfillment_rate > 80 ? 'up' : ($request_fulfillment_rate < 60 ? 'down' : 'neutral'); ?>">
                            <i class="fas fa-heart"></i>
                            <?php echo $request_fulfillment_rate; ?>% fulfillment rate
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="fas fa-warehouse"></i>
                            </div>
                        </div>
                        <div class="stat-main">
                            <div class="stat-number"><?php echo number_format($total_blood_units); ?></div>
                            <div class="stat-label">Blood Units Available</div>
                        </div>
                        <div class="stat-details">
                            <div class="detail-item">
                                <span class="detail-label">Low Stock Types:</span>
                                <span class="detail-value"><?php echo $low_stock_count; ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Status:</span>
                                <span class="detail-value badge badge-<?php echo $low_stock_count > 3 ? 'high' : ($low_stock_count > 1 ? 'medium' : 'low'); ?>">
                                    <?php echo $low_stock_count > 3 ? 'Critical' : ($low_stock_count > 1 ? 'Warning' : 'Good'); ?>
                                </span>
                            </div>
                        </div>
                        <div class="stat-trend trend-<?php echo $low_stock_count == 0 ? 'up' : ($low_stock_count > 3 ? 'down' : 'neutral'); ?>">
                            <i class="fas fa-box"></i>
                            Inventory status
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                        </div>
                        <div class="stat-main">
                            <div class="stat-number"><?php echo number_format($total_events); ?></div>
                            <div class="stat-label">Total Events</div>
                        </div>
                        <div class="stat-details">
                            <div class="detail-item">
                                <span class="detail-label">Upcoming:</span>
                                <span class="detail-value"><?php echo $upcoming_events; ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Registrations:</span>
                                <span class="detail-value"><?php echo $event_registrations; ?></span>
                            </div>
                        </div>
                        <div class="stat-trend trend-neutral">
                            <i class="fas fa-users"></i>
                            Community engagement
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                        </div>
                        <div class="stat-main">
                            <div class="stat-number"><?php echo number_format($activity_donations + $activity_requests + $activity_events); ?></div>
                            <div class="stat-label">Monthly Activity</div>
                        </div>
                        <div class="stat-details">
                            <div class="detail-item">
                                <span class="detail-label">Donations:</span>
                                <span class="detail-value"><?php echo $activity_donations; ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Requests:</span>
                                <span class="detail-value"><?php echo $activity_requests; ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Events:</span>
                                <span class="detail-value"><?php echo $activity_events; ?></span>
                            </div>
                        </div>
                        <div class="stat-trend trend-up">
                            <i class="fas fa-trending-up"></i>
                            Last 30 days
                        </div>
                    </div>
                </div>
            </div>

            <!-- Engagement Metrics -->
            <div class="stats-section">
                <h2 class="section-title">
                    <i class="fas fa-heart-pulse"></i>
                    Engagement Metrics
                </h2>
                
                <div class="engagement-grid">
                    <div class="engagement-item">
                        <div class="engagement-percentage"><?php echo $donation_completion_rate; ?>%</div>
                        <div class="engagement-label">Donation Success Rate</div>
                    </div>
                    <div class="engagement-item">
                        <div class="engagement-percentage"><?php echo $request_fulfillment_rate; ?>%</div>
                        <div class="engagement-label">Request Fulfillment Rate</div>
                    </div>
                    <div class="engagement-item">
                        <div class="engagement-percentage"><?php echo $user_growth_rate; ?>%</div>
                        <div class="engagement-label">User Growth Rate</div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="charts-section">
                <div class="chart-container">
                    <h3 class="chart-title">
                        <i class="fas fa-chart-pie"></i>
                        Blood Inventory Distribution
                    </h3>
                    <div class="chart-canvas">
                        <canvas id="bloodInventoryChart"></canvas>
                    </div>
                </div>

                <div class="chart-container">
                    <h3 class="chart-title">
                        <i class="fas fa-chart-bar"></i>
                        Blood Type Demand Analysis
                    </h3>
                    <div class="chart-canvas">
                        <canvas id="bloodDemandChart"></canvas>
                    </div>
                </div>

                <div class="chart-container chart-full">
                    <h3 class="chart-title">
                        <i class="fas fa-chart-line"></i>
                        User Registration & Donation Trends (Last 12 Months)
                    </h3>
                    <div class="chart-canvas">
                        <canvas id="trendsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Donors Table -->
            <?php if (!empty($top_donors)): ?>
            <div class="table-container">
                <h3 class="section-title">
                    <i class="fas fa-trophy"></i>
                    Top Donors
                </h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Donor Name</th>
                            <th>Blood Type</th>
                            <th>Completed Donations</th>
                            <th>Recognition</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_donors as $index => $donor): ?>
                            <tr>
                                <td>
                                    <strong>#<?php echo $index + 1; ?></strong>
                                    <?php if ($index < 3): ?>
                                        <i class="fas fa-medal" style="color: <?php echo $index == 0 ? '#ffd700' : ($index == 1 ? '#c0c0c0' : '#cd7f32'); ?>;"></i>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($donor['username']); ?></td>
                                <td><strong><?php echo htmlspecialchars($donor['blood_type']); ?></strong></td>
                                <td><?php echo $donor['donation_count']; ?> donations</td>
                                <td>
                                    <?php if ($donor['donation_count'] >= 10): ?>
                                        <span class="badge badge-high">Hero Donor</span>
                                    <?php elseif ($donor['donation_count'] >= 5): ?>
                                        <span class="badge badge-medium">Regular Donor</span>
                                    <?php else: ?>
                                        <span class="badge badge-low">New Donor</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Include sidebar JavaScript
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

            // Initialize Charts
            initializeCharts();
        });

        function initializeCharts() {
            // Blood Inventory Chart
            const inventoryCtx = document.getElementById('bloodInventoryChart');
            if (inventoryCtx) {
                const bloodTypes = [<?php foreach ($blood_distribution as $blood) echo "'" . addslashes($blood['blood_type']) . "',"; ?>];
                const bloodUnits = [<?php foreach ($blood_distribution as $blood) echo intval($blood['available_units']) . ","; ?>];

                new Chart(inventoryCtx, {
                    type: 'doughnut',
                    data: {
                        labels: bloodTypes,
                        datasets: [{
                            data: bloodUnits,
                            backgroundColor: [
                                '#ef4444', '#f97316', '#f59e0b', '#10b981',
                                '#06b6d4', '#3b82f6', '#8b5cf6', '#ec4899'
                            ],
                            borderWidth: 0,
                            hoverBorderWidth: 2,
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
                                        family: 'Inter',
                                        size: 12
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // Blood Demand Chart
            const demandCtx = document.getElementById('bloodDemandChart');
            if (demandCtx) {
                const demandTypes = [<?php foreach ($blood_demand as $demand) echo "'" . addslashes($demand['blood_type']) . "',"; ?>];
                const demandCounts = [<?php foreach ($blood_demand as $demand) echo intval($demand['request_count']) . ","; ?>];

                new Chart(demandCtx, {
                    type: 'bar',
                    data: {
                        labels: demandTypes,
                        datasets: [{
                            label: 'Requests',
                            data: demandCounts,
                            backgroundColor: '#dc3545',
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
                                        family: 'Inter'
                                    }
                                },
                                grid: {
                                    color: '#f1f5f9'
                                }
                            },
                            x: {
                                ticks: {
                                    font: {
                                        family: 'Inter'
                                    }
                                },
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            }

            // Trends Chart
            const trendsCtx = document.getElementById('trendsChart');
            if (trendsCtx) {
                const trendMonths = [<?php foreach ($user_trend as $trend) echo "'" . addslashes($trend['month']) . "',"; ?>];
                const userCounts = [<?php foreach ($user_trend as $trend) echo intval($trend['user_count']) . ","; ?>];
                
                const donationMonths = [<?php foreach ($donation_trend as $trend) echo "'" . addslashes($trend['month']) . "',"; ?>];
                const donationCounts = [<?php foreach ($donation_trend as $trend) echo intval($trend['donation_count']) . ","; ?>];

                new Chart(trendsCtx, {
                    type: 'line',
                    data: {
                        labels: trendMonths,
                        datasets: [{
                            label: 'New Users',
                            data: userCounts,
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            tension: 0.4,
                            fill: true
                        }, {
                            label: 'Donations',
                            data: donationCounts,
                            borderColor: '#dc3545',
                            backgroundColor: 'rgba(220, 53, 69, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        },
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    font: {
                                        family: 'Inter'
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1,
                                    font: {
                                        family: 'Inter'
                                    }
                                },
                                grid: {
                                    color: '#f1f5f9'
                                }
                            },
                            x: {
                                ticks: {
                                    font: {
                                        family: 'Inter'
                                    }
                                },
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            }
        }

        // Initialize page
        console.log('Statistics dashboard initialized successfully');
    </script>

</body>
</html>