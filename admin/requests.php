<?php 
include('../includes/db.php');
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$success_message = '';
$error_message = '';

// Handle form submissions (approve, cancel, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = intval($_POST['request_id']);
    $action = $_POST['action'];
    
    try {
        if ($action === 'approve') {
            $stmt = $conn->prepare("UPDATE recipient_form SET request_status = 'Approved', updated_at = NOW() WHERE request_id = ?");
            $stmt->bind_param("i", $request_id);
            if ($stmt->execute()) {
                $success_message = "Blood request approved successfully!";
                
                // Log the approval in blood_log
                $log_stmt = $conn->prepare("INSERT INTO blood_log (blood_type, operation_type, notes, timestamp_update) SELECT blood_type_needed, 'WITHDRAWAL', CONCAT('Request approved - ID: ', request_id), NOW() FROM recipient_form WHERE request_id = ?");
                $log_stmt->bind_param("i", $request_id);
                $log_stmt->execute();
            }
        } elseif ($action === 'cancel') {
            $stmt = $conn->prepare("UPDATE recipient_form SET request_status = 'Cancelled', updated_at = NOW() WHERE request_id = ?");
            $stmt->bind_param("i", $request_id);
            if ($stmt->execute()) {
                $success_message = "Blood request cancelled successfully!";
            }
        } elseif ($action === 'delete') {
            $stmt = $conn->prepare("DELETE FROM recipient_form WHERE request_id = ?");
            $stmt->bind_param("i", $request_id);
            if ($stmt->execute()) {
                $success_message = "Blood request deleted successfully!";
            }
        } elseif ($action === 'fulfill') {
            // Check if enough blood units are available
            $check_stmt = $conn->prepare("SELECT bi.available_units, rf.units_needed FROM blood_inventory bi JOIN recipient_form rf ON bi.blood_type = rf.blood_type_needed WHERE rf.request_id = ?");
            $check_stmt->bind_param("i", $request_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                if ($row['available_units'] >= $row['units_needed']) {
                    // Update request status and reduce inventory
                    $conn->begin_transaction();
                    
                    $update_request = $conn->prepare("UPDATE recipient_form SET request_status = 'Fulfilled', updated_at = NOW() WHERE request_id = ?");
                    $update_request->bind_param("i", $request_id);
                    $update_request->execute();
                    
                    $update_inventory = $conn->prepare("UPDATE blood_inventory bi JOIN recipient_form rf ON bi.blood_type = rf.blood_type_needed SET bi.available_units = bi.available_units - rf.units_needed WHERE rf.request_id = ?");
                    $update_inventory->bind_param("i", $request_id);
                    $update_inventory->execute();
                    
                    // Log the fulfillment
                    $log_stmt = $conn->prepare("INSERT INTO blood_log (blood_type, units_donated, operation_type, notes, timestamp_update) SELECT rf.blood_type_needed, rf.units_needed, 'WITHDRAWAL', CONCAT('Request fulfilled - ID: ', rf.request_id), NOW() FROM recipient_form rf WHERE rf.request_id = ?");
                    $log_stmt->bind_param("i", $request_id);
                    $log_stmt->execute();
                    
                    $conn->commit();
                    $success_message = "Blood request fulfilled successfully!";
                } else {
                    $error_message = "Insufficient blood units available for this request.";
                }
            }
        }
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error processing request: " . $e->getMessage();
    }
}

// Get sorting parameters
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$order = isset($_GET['order']) ? $_GET['order'] : 'DESC';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$urgency_filter = isset($_GET['urgency']) ? $_GET['urgency'] : 'all';

// Validate sorting parameters
$allowed_sorts = ['created_at', 'needed_by_date', 'urgency_level', 'blood_type_needed', 'units_needed', 'request_status'];
$allowed_orders = ['ASC', 'DESC'];

if (!in_array($sort_by, $allowed_sorts)) {
    $sort_by = 'created_at';
}
if (!in_array($order, $allowed_orders)) {
    $order = 'DESC';
}

// Build the query with filters
$where_conditions = [];
$params = [];
$param_types = '';

if ($status_filter !== 'all') {
    $where_conditions[] = "rf.request_status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if ($urgency_filter !== 'all') {
    $where_conditions[] = "rf.urgency_level = ?";
    $params[] = $urgency_filter;
    $param_types .= 's';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Fetch blood requests with user information
$query = "
    SELECT rf.*, l.username, bi.available_units
    FROM recipient_form rf 
    JOIN login l ON rf.user_id = l.user_id 
    LEFT JOIN blood_inventory bi ON rf.blood_type_needed = bi.blood_type
    $where_clause
    ORDER BY rf.$sort_by $order
";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $requests_result = $stmt->get_result();
} else {
    $requests_result = $conn->query($query);
}

$requests = [];
if ($requests_result && $requests_result->num_rows > 0) {
    while ($row = $requests_result->fetch_assoc()) {
        $requests[] = $row;
    }
}

// Get summary statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN request_status = 'Pending' THEN 1 ELSE 0 END) as pending_requests,
        SUM(CASE WHEN request_status = 'Approved' THEN 1 ELSE 0 END) as approved_requests,
        SUM(CASE WHEN request_status = 'Fulfilled' THEN 1 ELSE 0 END) as fulfilled_requests,
        SUM(CASE WHEN request_status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_requests,
        SUM(CASE WHEN urgency_level = 'High' AND request_status = 'Pending' THEN 1 ELSE 0 END) as urgent_pending
    FROM recipient_form
";
$stats_result = $conn->query($stats_query);
$stats = $stats_result ? $stats_result->fetch_assoc() : [];

// Get blood inventory for display
$inventory_query = "SELECT blood_type, available_units FROM blood_inventory ORDER BY blood_type";
$inventory_result = $conn->query($inventory_query);
$inventory = [];
if ($inventory_result && $inventory_result->num_rows > 0) {
    while ($row = $inventory_result->fetch_assoc()) {
        $inventory[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Requests Management - Pirate's Blood Bank</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
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
        }

        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.2);
        }

        /* Header */
        .page-header {
            text-align: center;
            margin-bottom: 2.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid rgba(220, 53, 69, 0.1);
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

        /* Messages */
        .message {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideIn 0.3s ease;
            border: 1px solid transparent;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .message.success {
            background: linear-gradient(90deg, rgba(34, 197, 94, 0.1), rgba(34, 197, 94, 0.05));
            color: #166534;
            border-color: rgba(34, 197, 94, 0.2);
        }

        .message.error {
            background: linear-gradient(90deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));
            color: #dc2626;
            border-color: rgba(220, 53, 69, 0.2);
        }

        /* Summary Statistics */
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #f1f5f9;
            text-align: center;
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
            background: #dc3545;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #dc3545;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #6b7280;
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Inventory Overview */
        .inventory-overview {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(147, 197, 253, 0.1) 100%);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .inventory-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .inventory-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
        }

        .blood-type-item {
            background: white;
            padding: 1rem;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }

        .blood-type-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.12);
        }

        .blood-type-label {
            font-weight: 700;
            color: #dc3545;
            font-size: 1.125rem;
            margin-bottom: 0.25rem;
        }

        .blood-units {
            color: #6b7280;
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* Controls Section */
        .controls-section {
            background: linear-gradient(135deg, rgba(156, 163, 175, 0.1) 0%, rgba(209, 213, 219, 0.1) 100%);
            border: 1px solid rgba(156, 163, 175, 0.2);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .controls-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .controls-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .form-control {
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: #dc2626;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(220, 38, 38, 0.3);
        }

        /* Requests Section */
        .requests-section {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #f1f5f9;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Table Styling */
        .table-container {
            overflow-x: auto;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .requests-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            min-width: 1000px;
        }

        .requests-table th {
            background: linear-gradient(135deg, #dc3545, #b91c1c);
            color: white;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .requests-table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            font-size: 0.875rem;
        }

        .requests-table tr:hover {
            background: rgba(220, 53, 69, 0.03);
        }

        .requests-table tr:last-child td {
            border-bottom: none;
        }

        /* Status Badges */
        .status-badge {
            padding: 0.5rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            white-space: nowrap;
            letter-spacing: 0.025em;
        }

        .badge-pending {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }

        .badge-approved {
            background: linear-gradient(135deg, #06b6d4, #0891b2);
            color: white;
        }

        .badge-fulfilled {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .badge-cancelled {
            background: linear-gradient(135deg, #6b7280, #4b5563);
            color: white;
        }

        .urgency-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.625rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .urgency-low { background: #dcfce7; color: #166534; }
        .urgency-medium { background: #fef3c7; color: #92400e; }
        .urgency-high { background: #fee2e2; color: #dc2626; }

        .blood-type {
            background: #dc2626;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.75rem;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 0.5rem 0.75rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.375rem;
            white-space: nowrap;
            letter-spacing: 0.025em;
        }

        .btn-approve {
            background: linear-gradient(135deg, #06b6d4, #0891b2);
            color: white;
        }

        .btn-fulfill {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .btn-cancel {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }

        .btn-delete {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .action-btn:active {
            transform: translateY(0);
        }

        /* Modal Styling */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.3s ease;
            position: relative;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal-header {
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #374151;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .close {
            color: #9ca3af;
            font-size: 1.5rem;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s ease;
            background: none;
            border: none;
            padding: 0.25rem;
            border-radius: 6px;
        }

        .close:hover {
            color: #374151;
            background: #f3f4f6;
        }

        /* Form Styling */
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }

        .form-textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            resize: vertical;
            min-height: 100px;
            font-family: inherit;
            font-size: 0.875rem;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .form-textarea:focus {
            outline: none;
            border-color: #dc3545;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
        }

        /* Modal Actions */
        .modal-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
        }

        .btn-modal {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-confirm {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .btn-cancel-modal {
            background: linear-gradient(135deg, #6b7280, #4b5563);
            color: white;
        }

        .btn-modal:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .btn-modal:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #6b7280;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state p {
            font-size: 1.125rem;
            font-weight: 500;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 1.5rem;
            }
            
            .admin-container {
                padding: 2rem;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }
            
            .admin-container {
                padding: 1.5rem;
            }
            
            .page-title {
                font-size: 2rem;
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .summary-stats {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            .inventory-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 0.75rem;
            }
            
            .blood-type-item {
                padding: 0.75rem;
            }
            
            .controls-grid {
                grid-template-columns: 1fr;
            }
            
            .requests-table {
                font-size: 0.75rem;
            }
            
            .requests-table th,
            .requests-table td {
                padding: 0.75rem 0.5rem;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 0.25rem;
            }
            
            .action-btn {
                font-size: 0.6875rem;
                padding: 0.375rem 0.5rem;
            }
            
            .modal-content {
                width: 95%;
                margin: 10% auto;
                padding: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .summary-stats {
                grid-template-columns: 1fr;
            }
            
            .inventory-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .page-title {
                font-size: 1.75rem;
            }
            
            .stat-number {
                font-size: 2rem;
            }
            
            .requests-table th,
            .requests-table td {
                padding: 0.5rem 0.25rem;
                font-size: 0.6875rem;
            }
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>

<?php include('../includes/adminheader.php'); ?>

<body>
    <!-- Mobile Menu Toggle Button -->
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

    <main class="main-content">
        <div class="admin-container">
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-hand-holding-medical"></i>
                    Blood Request Management
                </h1>
                <p class="page-subtitle">Review and manage blood requests from users</p>
            </div>
            
            <!-- Success/Error Messages -->
            <?php if ($success_message): ?>
                <div class="message success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($success_message); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            <?php endif; ?>
            
            <!-- Summary Statistics -->
            <div class="summary-stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($stats['total_requests'] ?? 0); ?></div>
                    <div class="stat-label">Total Requests</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($stats['pending_requests'] ?? 0); ?></div>
                    <div class="stat-label">Pending Review</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($stats['approved_requests'] ?? 0); ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($stats['fulfilled_requests'] ?? 0); ?></div>
                    <div class="stat-label">Fulfilled</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($stats['urgent_pending'] ?? 0); ?></div>
                    <div class="stat-label">Urgent Pending</div>
                </div>
            </div>
            
            <!-- Blood Inventory Overview -->
            <div class="inventory-overview">
                <h3 class="inventory-title">
                    <i class="fas fa-warehouse"></i> 
                    Current Blood Inventory
                </h3>
                <div class="inventory-grid">
                    <?php if (!empty($inventory)): ?>
                        <?php foreach ($inventory as $blood): ?>
                            <div class="blood-type-item">
                                <div class="blood-type-label"><?php echo htmlspecialchars($blood['blood_type']); ?></div>
                                <div class="blood-units"><?php echo $blood['available_units']; ?> units</div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="blood-type-item">
                            <div class="blood-type-label">No Data</div>
                            <div class="blood-units">0 units</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Controls Section -->
            <div class="controls-section">
                <h3 class="controls-title">
                    <i class="fas fa-filter"></i> 
                    Filter & Sort Options
                </h3>
                <form method="GET" action="">
                    <div class="controls-grid">
                        <div class="form-group">
                            <label for="status">Filter by Status</label>
                            <select name="status" id="status" class="form-control">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="Approved" <?php echo $status_filter === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="Fulfilled" <?php echo $status_filter === 'Fulfilled' ? 'selected' : ''; ?>>Fulfilled</option>
                                <option value="Cancelled" <?php echo $status_filter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="urgency">Filter by Urgency</label>
                            <select name="urgency" id="urgency" class="form-control">
                                <option value="all" <?php echo $urgency_filter === 'all' ? 'selected' : ''; ?>>All Urgency Levels</option>
                                <option value="Low" <?php echo $urgency_filter === 'Low' ? 'selected' : ''; ?>>Low</option>
                                <option value="Medium" <?php echo $urgency_filter === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="High" <?php echo $urgency_filter === 'High' ? 'selected' : ''; ?>>High</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="sort">Sort by</label>
                            <select name="sort" id="sort" class="form-control">
                                <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Date Created</option>
                                <option value="needed_by_date" <?php echo $sort_by === 'needed_by_date' ? 'selected' : ''; ?>>Needed By Date</option>
                                <option value="urgency_level" <?php echo $sort_by === 'urgency_level' ? 'selected' : ''; ?>>Urgency Level</option>
                                <option value="blood_type_needed" <?php echo $sort_by === 'blood_type_needed' ? 'selected' : ''; ?>>Blood Type</option>
                                <option value="units_needed" <?php echo $sort_by === 'units_needed' ? 'selected' : ''; ?>>Units Needed</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="order">Order</label>
                            <select name="order" id="order" class="form-control">
                                <option value="DESC" <?php echo $order === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                                <option value="ASC" <?php echo $order === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i>
                                Apply Filters
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Requests Table -->
            <div class="requests-section">
                <h3 class="section-title">
                    <i class="fas fa-list"></i> 
                    Blood Requests (<?php echo count($requests); ?> total)
                </h3>
                
                <?php if (empty($requests)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No blood requests found matching your criteria.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="requests-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Requester</th>
                                    <th>Blood Type</th>
                                    <th>Units</th>
                                    <th>Available</th>
                                    <th>Urgency</th>
                                    <th>Status</th>
                                    <th>Hospital</th>
                                    <th>Needed By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $request): ?>
                                    <tr>
                                        <td>#<?php echo $request['request_id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($request['username']); ?></strong></td>
                                        <td><span class="blood-type"><?php echo htmlspecialchars($request['blood_type_needed']); ?></span></td>
                                        <td><strong><?php echo $request['units_needed']; ?></strong> unit<?php echo $request['units_needed'] > 1 ? 's' : ''; ?></td>
                                        <td>
                                            <span style="color: <?php echo ($request['available_units'] ?? 0) >= $request['units_needed'] ? '#059669' : '#dc2626'; ?>; font-weight: 600;">
                                                <?php echo $request['available_units'] ?? 0; ?> available
                                            </span>
                                        </td>
                                        <td>
                                            <span class="urgency-badge urgency-<?php echo strtolower($request['urgency_level']); ?>">
                                                <?php echo $request['urgency_level']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge badge-<?php echo strtolower($request['request_status']); ?>">
                                                <i class="fas fa-<?php 
                                                    echo $request['request_status'] === 'Pending' ? 'clock' : 
                                                        ($request['request_status'] === 'Approved' ? 'check' : 
                                                        ($request['request_status'] === 'Fulfilled' ? 'check-double' : 'times')); 
                                                ?>"></i>
                                                <?php echo $request['request_status']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars(substr($request['hospital_location'], 0, 20) . (strlen($request['hospital_location']) > 20 ? '...' : '')); ?></td>
                                        <td>
                                            <?php 
                                            $needed_by = new DateTime($request['needed_by_date']);
                                            $today = new DateTime();
                                            $is_urgent = $needed_by <= $today->add(new DateInterval('P3D'));
                                            ?>
                                            <span style="color: <?php echo $is_urgent ? '#dc2626' : '#374151'; ?>; font-weight: <?php echo $is_urgent ? '600' : '400'; ?>">
                                                <?php echo date('M j, Y', strtotime($request['needed_by_date'])); ?>
                                                <?php if ($is_urgent): ?>
                                                    <i class="fas fa-exclamation-triangle" style="color: #dc2626; margin-left: 0.25rem;"></i>
                                                <?php endif; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if ($request['request_status'] === 'Pending'): ?>
                                                    <button class="action-btn btn-approve" onclick="openModal('approve', <?php echo $request['request_id']; ?>)">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                    <button class="action-btn btn-cancel" onclick="openModal('cancel', <?php echo $request['request_id']; ?>)">
                                                        <i class="fas fa-times"></i> Cancel
                                                    </button>
                                                <?php elseif ($request['request_status'] === 'Approved'): ?>
                                                    <?php if (($request['available_units'] ?? 0) >= $request['units_needed']): ?>
                                                        <button class="action-btn btn-fulfill" onclick="openModal('fulfill', <?php echo $request['request_id']; ?>)">
                                                            <i class="fas fa-heart"></i> Fulfill
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="action-btn btn-cancel" onclick="openModal('cancel', <?php echo $request['request_id']; ?>)">
                                                        <i class="fas fa-times"></i> Cancel
                                                    </button>
                                                <?php else: ?>
                                                    <span style="color: #6b7280; font-style: italic; font-size: 0.75rem;">No actions</span>
                                                <?php endif; ?>
                                                
                                                <button class="action-btn btn-delete" onclick="confirmDelete(<?php echo $request['request_id']; ?>)">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <!-- Action Modal -->
    <div id="actionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">
                    <i id="modalIcon"></i>
                    <span id="modalTitleText"></span>
                </h2>
                <button class="close" onclick="closeModal()">&times;</button>
            </div>
            
            <form id="actionForm" onsubmit="handleFormSubmit(event)">
                <input type="hidden" name="request_id" id="modalRequestId">
                <input type="hidden" name="action" id="modalAction">
                
                <div class="form-group">
                    <label for="admin_notes" class="form-label">Admin Notes:</label>
                    <textarea name="admin_notes" id="admin_notes" class="form-textarea" 
                              placeholder="Add any notes about this decision (optional)..."></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-modal btn-cancel-modal" onclick="closeModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn-modal btn-confirm" id="confirmBtn">
                        <i class="fas fa-check"></i> Confirm
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Confirm Delete</h2>
                <button class="close" onclick="closeConfirmModal()">&times;</button>
            </div>
            
            <p>Are you sure you want to delete this blood request? This action cannot be undone.</p>
            
            <div class="modal-actions">
                <button type="button" class="btn-modal btn-cancel-modal" onclick="closeConfirmModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn-modal btn-confirm" id="confirmDeleteBtn">
                    <i class="fas fa-trash"></i> Delete Request
                </button>
            </div>
        </div>
    </div>

    <!-- Hidden form for actions -->
    <form id="hiddenActionForm" method="POST" style="display: none;">
        <input type="hidden" name="request_id" id="hiddenRequestId">
        <input type="hidden" name="action" id="hiddenAction">
    </form>

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
        });

        // Modal functionality
        const modal = document.getElementById('actionModal');
        const confirmModal = document.getElementById('confirmModal');
        let deleteRequestId = null;
        
        function openModal(action, requestId) {
            const modalTitle = document.getElementById('modalTitleText');
            const modalIcon = document.getElementById('modalIcon');
            const confirmBtn = document.getElementById('confirmBtn');
            const adminNotes = document.getElementById('admin_notes');
            
            document.getElementById('modalRequestId').value = requestId;
            document.getElementById('modalAction').value = action;
            
            // Clear previous notes
            adminNotes.value = '';
            
            // Configure modal based on action
            switch(action) {
                case 'approve':
                    modalTitle.textContent = 'Approve Request';
                    modalIcon.className = 'fas fa-check';
                    confirmBtn.innerHTML = '<i class="fas fa-check"></i> Approve Request';
                    confirmBtn.style.background = 'linear-gradient(135deg, #06b6d4, #0891b2)';
                    adminNotes.placeholder = 'Add approval notes (optional)...';
                    break;
                    
                case 'fulfill':
                    modalTitle.textContent = 'Fulfill Request';
                    modalIcon.className = 'fas fa-heart';
                    confirmBtn.innerHTML = '<i class="fas fa-heart"></i> Fulfill Request';
                    confirmBtn.style.background = 'linear-gradient(135deg, #10b981, #059669)';
                    adminNotes.placeholder = 'Add fulfillment notes (optional)...';
                    break;
                    
                case 'cancel':
                    modalTitle.textContent = 'Cancel Request';
                    modalIcon.className = 'fas fa-times';
                    confirmBtn.innerHTML = '<i class="fas fa-times"></i> Cancel Request';
                    confirmBtn.style.background = 'linear-gradient(135deg, #f59e0b, #d97706)';
                    adminNotes.placeholder = 'Please provide reason for cancellation...';
                    break;
            }
            
            modal.style.display = 'block';
            adminNotes.focus();
        }
        
        function closeModal() {
            modal.style.display = 'none';
            document.getElementById('admin_notes').value = '';
            
            // Reset confirm button
            const confirmBtn = document.getElementById('confirmBtn');
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = '<i class="fas fa-check"></i> Confirm';
        }

        function confirmDelete(requestId) {
            deleteRequestId = requestId;
            confirmModal.style.display = 'block';
        }

        function closeConfirmModal() {
            confirmModal.style.display = 'none';
            deleteRequestId = null;
        }

        document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
            if (deleteRequestId) {
                document.getElementById('hiddenRequestId').value = deleteRequestId;
                document.getElementById('hiddenAction').value = 'delete';
                document.getElementById('hiddenActionForm').submit();
            }
        });
        
        // Close modal when clicking outside of it
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeModal();
            }
            if (event.target === confirmModal) {
                closeConfirmModal();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (modal.style.display === 'block') {
                    closeModal();
                }
                if (confirmModal.style.display === 'block') {
                    closeConfirmModal();
                }
            }
        });
        
        // Form submission handler
        function handleFormSubmit(event) {
            event.preventDefault();
            
            const action = document.getElementById('modalAction').value;
            const requestId = document.getElementById('modalRequestId').value;
            const notes = document.getElementById('admin_notes').value.trim();
            const confirmBtn = document.getElementById('confirmBtn');
            
            // Require notes for cancellation
            if (action === 'cancel' && !notes) {
                alert('Please provide a reason for cancellation.');
                document.getElementById('admin_notes').focus();
                return false;
            }
            
            // Confirm the action
            const actionText = action.charAt(0).toUpperCase() + action.slice(1);
            const confirmMessage = `Are you sure you want to ${action} this request?` + 
                                 (action === 'fulfill' ? '\n\nThis will update the blood inventory.' : '');
            
            if (!confirm(confirmMessage)) {
                return false;
            }
            
            // Show loading state
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<div class="loading"></div> Processing...';
            
            // Submit the form
            setTimeout(() => {
                document.getElementById('hiddenRequestId').value = requestId;
                document.getElementById('hiddenAction').value = action;
                document.getElementById('hiddenActionForm').submit();
            }, 500);
            
            return false;
        }
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Admin blood request management system initialized successfully');
            
            // Add hover effects to table rows
            const tableRows = document.querySelectorAll('.requests-table tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.01)';
                    this.style.transition = 'transform 0.2s ease';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });

            // Highlight urgent requests
            const urgentRows = document.querySelectorAll('.urgency-high');
            urgentRows.forEach(badge => {
                const row = badge.closest('tr');
                if (row) {
                    row.style.borderLeft = '4px solid #dc2626';
                    row.style.backgroundColor = '#fef2f2';
                }
            });
        });
    </script>

    <!-- Sidebar Styles -->
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

