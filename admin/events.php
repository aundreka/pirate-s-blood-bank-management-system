<?php 
include('../includes/db.php');
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_event':
                    $stmt = $conn->prepare("INSERT INTO events (title, description, event_date, start_time, end_time, location, event_image, max_slots, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssssssii", 
                        $_POST['title'], 
                        $_POST['description'], 
                        $_POST['event_date'], 
                        $_POST['start_time'], 
                        $_POST['end_time'], 
                        $_POST['location'], 
                        $_POST['event_image'], 
                        $_POST['max_slots'], 
                        $_SESSION['user_id']
                    );
                    
                    if ($stmt->execute()) {
                        $message = 'Event created successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Error creating event: ' . $stmt->error;
                        $message_type = 'error';
                    }
                    break;
                    
                case 'update_status':
                    $stmt = $conn->prepare("UPDATE events SET status = ? WHERE event_id = ?");
                    $stmt->bind_param("si", $_POST['status'], $_POST['event_id']);
                    
                    if ($stmt->execute()) {
                        $message = 'Event status updated successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Error updating event status: ' . $stmt->error;
                        $message_type = 'error';
                    }
                    break;
                    
                case 'delete_event':
                    $stmt = $conn->prepare("DELETE FROM events WHERE event_id = ?");
                    $stmt->bind_param("i", $_POST['event_id']);
                    
                    if ($stmt->execute()) {
                        $message = 'Event deleted successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Error deleting event: ' . $stmt->error;
                        $message_type = 'error';
                    }
                    break;
                    
                case 'update_event':
                    $stmt = $conn->prepare("UPDATE events SET title = ?, description = ?, event_date = ?, start_time = ?, end_time = ?, location = ?, event_image = ?, max_slots = ? WHERE event_id = ?");
                    $stmt->bind_param("sssssssii", 
                        $_POST['title'], 
                        $_POST['description'], 
                        $_POST['event_date'], 
                        $_POST['start_time'], 
                        $_POST['end_time'], 
                        $_POST['location'], 
                        $_POST['event_image'], 
                        $_POST['max_slots'], 
                        $_POST['event_id']
                    );
                    
                    if ($stmt->execute()) {
                        $message = 'Event updated successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Error updating event: ' . $stmt->error;
                        $message_type = 'error';
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $message = 'Database error: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Fetch events with registration counts
try {
    $events_query = "
        SELECT e.*, 
               COUNT(er.registration_id) as registration_count,
               l.username as created_by_name
        FROM events e 
        LEFT JOIN event_registrations er ON e.event_id = er.event_id AND er.status = 'registered'
        LEFT JOIN login l ON e.created_by = l.user_id
        GROUP BY e.event_id 
        ORDER BY e.event_date DESC, e.start_time DESC
    ";
    $events_result = $conn->query($events_query);
    $events = [];
    if ($events_result && $events_result->num_rows > 0) {
        while ($row = $events_result->fetch_assoc()) {
            $events[] = $row;
        }
    }

    // Get event statistics
    $stats_query = "
        SELECT 
            COUNT(*) as total_events,
            SUM(CASE WHEN status = 'upcoming' THEN 1 ELSE 0 END) as upcoming_events,
            SUM(CASE WHEN status = 'ongoing' THEN 1 ELSE 0 END) as ongoing_events,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_events,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_events
        FROM events
    ";
    $stats_result = $conn->query($stats_query);
    $stats = $stats_result ? $stats_result->fetch_assoc() : [
        'total_events' => 0, 'upcoming_events' => 0, 'ongoing_events' => 0, 
        'completed_events' => 0, 'cancelled_events' => 0
    ];

} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    $events = [];
    $stats = ['total_events' => 0, 'upcoming_events' => 0, 'ongoing_events' => 0, 'completed_events' => 0, 'cancelled_events' => 0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Management - Pirate's Blood Bank Admin</title>
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

        /* Statistics Grid */
        .stats-grid {
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
            background: var(--card-color);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--card-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #6b7280;
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Color Variables for Cards */
        .stat-card:nth-child(1) { --card-color: #3b82f6; }
        .stat-card:nth-child(2) { --card-color: #10b981; }
        .stat-card:nth-child(3) { --card-color: #f59e0b; }
        .stat-card:nth-child(4) { --card-color: #ef4444; }
        .stat-card:nth-child(5) { --card-color: #8b5cf6; }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #dc3545, #b91c1c);
            color: white;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.4);
        }

        /* Events Section */
        .events-section {
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

        .events-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            min-width: 1000px;
        }

        .events-table th {
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

        .events-table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            font-size: 0.875rem;
        }

        .events-table tr:hover {
            background: rgba(220, 53, 69, 0.03);
        }

        .events-table tr:last-child td {
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

        .badge-upcoming {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }

        .badge-ongoing {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .badge-completed {
            background: linear-gradient(135deg, #6b7280, #4b5563);
            color: white;
        }

        .badge-cancelled {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
        }

        /* Action Buttons in Table */
        .table-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 0.375rem 0.75rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.6875rem;
            font-weight: 600;
            text-transform: uppercase;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            white-space: nowrap;
            letter-spacing: 0.025em;
        }

        .btn-edit {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }

        .btn-delete {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
        }

        .btn-status {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
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
            margin: 2% auto;
            padding: 2rem;
            border-radius: 16px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
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
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }

        .form-input,
        .form-textarea,
        .form-select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.875rem;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-input:focus,
        .form-textarea:focus,
        .form-select:focus {
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
            border-top: 2px solid #f1f5f9;
            padding-top: 1.5rem;
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

        .btn-cancel {
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

        /* Progress Bar */
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #f1f5f9;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981, #059669);
            border-radius: 4px;
            transition: width 0.3s ease;
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
            
            .form-grid {
                grid-template-columns: 1fr;
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
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .events-table {
                font-size: 0.75rem;
            }
            
            .events-table th,
            .events-table td {
                padding: 0.75rem 0.5rem;
            }
            
            .table-actions {
                flex-direction: column;
                gap: 0.25rem;
            }
            
            .action-btn {
                font-size: 0.625rem;
                padding: 0.25rem 0.5rem;
            }
            
            .modal-content {
                width: 95%;
                margin: 5% auto;
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
                font-size: 2rem;
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

                <a href="events.php" class="nav-item" style="background: linear-gradient(90deg, rgba(220, 53, 69, 0.15), rgba(220, 53, 69, 0.08)); color: #dc3545; font-weight: 600;">
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
                    <i class="fas fa-calendar-alt"></i>
                    Event Management
                </h1>
                <p class="page-subtitle">Create and manage blood donation events</p>
            </div>
            
            <!-- Success/Error Messages -->
            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Event Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_events']; ?></div>
                    <div class="stat-label">Total Events</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['upcoming_events']; ?></div>
                    <div class="stat-label">Upcoming Events</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['ongoing_events']; ?></div>
                    <div class="stat-label">Ongoing Events</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['completed_events']; ?></div>
                    <div class="stat-label">Completed Events</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['cancelled_events']; ?></div>
                    <div class="stat-label">Cancelled Events</div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="action-buttons">
                <button class="btn btn-primary" onclick="openAddEventModal()">
                    <i class="fas fa-plus"></i>
                    Create New Event
                </button>
            </div>
            
            <!-- Events Table -->
            <div class="events-section">
                <h3 class="section-title">
                    <i class="fas fa-list"></i> 
                    All Events
                </h3>
                
                <?php if (empty($events)): ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <p>No events found. Create your first event!</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="events-table">
                            <thead>
                                <tr>
                                    <th>Event</th>
                                    <th>Date & Time</th>
                                    <th>Location</th>
                                    <th>Capacity</th>
                                    <th>Registrations</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($events as $event): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($event['title']); ?></strong>
                                                <?php if ($event['description']): ?>
                                                    <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;">
                                                        <?php echo htmlspecialchars(substr($event['description'], 0, 100)) . (strlen($event['description']) > 100 ? '...' : ''); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo date('M j, Y', strtotime($event['event_date'])); ?></strong>
                                                <div style="font-size: 0.75rem; color: #6b7280;">
                                                    <?php echo date('g:i A', strtotime($event['start_time'])); ?> - 
                                                    <?php echo date('g:i A', strtotime($event['end_time'])); ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($event['location']); ?></td>
                                        <td>
                                            <div>
                                                <strong><?php echo $event['max_slots']; ?></strong>
                                                <div class="progress-bar">
                                                    <div class="progress-fill" style="width: <?php echo $event['max_slots'] > 0 ? ($event['registration_count'] / $event['max_slots']) * 100 : 0; ?>%"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?php echo $event['registration_count']; ?></strong> registered
                                        </td>
                                        <td>
                                            <span class="status-badge badge-<?php echo $event['status']; ?>">
                                                <i class="fas fa-<?php 
                                                    echo $event['status'] === 'upcoming' ? 'clock' : 
                                                        ($event['status'] === 'ongoing' ? 'play' : 
                                                        ($event['status'] === 'completed' ? 'check' : 'times')); 
                                                ?>"></i>
                                                <?php echo ucfirst($event['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($event['created_by_name'] ?? 'Unknown'); ?></td>
                                        <td>
                                            <div class="table-actions">
                                                <button class="action-btn btn-edit" onclick="editEvent(<?php echo $event['event_id']; ?>)">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <button class="action-btn btn-status" onclick="changeStatus(<?php echo $event['event_id']; ?>, '<?php echo $event['status']; ?>')">
                                                    <i class="fas fa-sync"></i> Status
                                                </button>
                                                <button class="action-btn btn-delete" onclick="deleteEvent(<?php echo $event['event_id']; ?>)">
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
    
    <!-- Add Event Modal -->
    <div id="addEventModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">
                    <i class="fas fa-plus"></i>
                    <span id="modalTitle">Create New Event</span>
                </h2>
                <button class="close" onclick="closeModal('addEventModal')">&times;</button>
            </div>
            
            <form id="eventForm" method="POST" onsubmit="handleEventSubmit(event)">
                <input type="hidden" name="action" id="formAction" value="add_event">
                <input type="hidden" name="event_id" id="eventId" value="">
                
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="title" class="form-label">Event Title *</label>
                        <input type="text" name="title" id="title" class="form-input" required 
                               placeholder="e.g., Community Blood Drive">
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="description" class="form-label">Description</label>
                        <textarea name="description" id="description" class="form-textarea" 
                                  placeholder="Describe the event, its purpose, and any special instructions..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="event_date" class="form-label">Event Date *</label>
                        <input type="date" name="event_date" id="event_date" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="max_slots" class="form-label">Maximum Participants *</label>
                        <input type="number" name="max_slots" id="max_slots" class="form-input" 
                               min="1" max="1000" value="50" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="start_time" class="form-label">Start Time *</label>
                        <input type="time" name="start_time" id="start_time" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="end_time" class="form-label">End Time *</label>
                        <input type="time" name="end_time" id="end_time" class="form-input" required>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="location" class="form-label">Location *</label>
                        <input type="text" name="location" id="location" class="form-input" required 
                               placeholder="e.g., Community Center, 123 Main St, Manila">
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="event_image" class="form-label">Event Image URL</label>
                        <input type="url" name="event_image" id="event_image" class="form-input" 
                               placeholder="https://example.com/image.jpg">
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-modal btn-cancel" onclick="closeModal('addEventModal')">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn-modal btn-confirm" id="submitBtn">
                        <i class="fas fa-check"></i> Create Event
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Status Change Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">
                    <i class="fas fa-sync"></i>
                    Change Event Status
                </h2>
                <button class="close" onclick="closeModal('statusModal')">&times;</button>
            </div>
            
            <form method="POST" onsubmit="handleStatusSubmit(event)">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="event_id" id="statusEventId">
                
                <div class="form-group">
                    <label for="status" class="form-label">New Status *</label>
                    <select name="status" id="status" class="form-select" required>
                        <option value="upcoming">Upcoming</option>
                        <option value="ongoing">Ongoing</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-modal btn-cancel" onclick="closeModal('statusModal')">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn-modal btn-confirm">
                        <i class="fas fa-check"></i> Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>

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

            // Set minimum date to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('event_date').min = today;
        });

        // Modal functionality
        function openAddEventModal() {
            document.getElementById('modalTitle').textContent = 'Create New Event';
            document.getElementById('formAction').value = 'add_event';
            document.getElementById('eventId').value = '';
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-check"></i> Create Event';
            
            // Reset form
            document.getElementById('eventForm').reset();
            
            // Set default date to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('event_date').value = today;
            
            document.getElementById('addEventModal').style.display = 'block';
            document.getElementById('title').focus();
        }

        function editEvent(eventId) {
            // Find the event data from the table (in a real app, fetch from server)
            const row = document.querySelector(`button[onclick="editEvent(${eventId})"]`).closest('tr');
            const cells = row.querySelectorAll('td');
            
            document.getElementById('modalTitle').textContent = 'Edit Event';
            document.getElementById('formAction').value = 'update_event';
            document.getElementById('eventId').value = eventId;
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-check"></i> Update Event';
            
            // Populate form with existing data (this would normally come from a database fetch)
            // For demo purposes, we'll use placeholder data
            document.getElementById('title').value = cells[0].querySelector('strong').textContent;
            document.getElementById('location').value = cells[2].textContent;
            document.getElementById('max_slots').value = cells[3].querySelector('strong').textContent;
            
            document.getElementById('addEventModal').style.display = 'block';
            document.getElementById('title').focus();
        }

        function changeStatus(eventId, currentStatus) {
            document.getElementById('statusEventId').value = eventId;
            document.getElementById('status').value = currentStatus;
            document.getElementById('statusModal').style.display = 'block';
        }

        function deleteEvent(eventId) {
            if (confirm('Are you sure you want to delete this event? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_event';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'event_id';
                idInput.value = eventId;
                
                form.appendChild(actionInput);
                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        });

        // Close modals with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modals = document.querySelectorAll('.modal');
                modals.forEach(modal => {
                    if (modal.style.display === 'block') {
                        modal.style.display = 'none';
                    }
                });
            }
        });

        // Form validation and submission
        function handleEventSubmit(event) {
            event.preventDefault();
            
            const startTime = document.getElementById('start_time').value;
            const endTime = document.getElementById('end_time').value;
            
            // Validate time
            if (startTime >= endTime) {
                alert('End time must be after start time.');
                return false;
            }
            
            // Show loading state
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<div class="loading"></div> Processing...';
            
            // Submit the form
            event.target.submit();
        }

        function handleStatusSubmit(event) {
            event.preventDefault();
            
            const form = event.target;
            const submitBtn = form.querySelector('button[type="submit"]');
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<div class="loading"></div> Updating...';
            
            // Submit the form
            form.submit();
        }

        // Initialize page
        console.log('Event management system initialized successfully');
    </script>

</body>
</html>