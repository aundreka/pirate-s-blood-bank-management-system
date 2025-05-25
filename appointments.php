<?php 
include('includes/db.php');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Handle sorting parameters
$donationSort = $_GET['donation_sort'] ?? 'date_desc';
$requestSort = $_GET['request_sort'] ?? 'date_desc';

// Handle cancellation requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['cancel_donation'])) {
        $donation_id = intval($_POST['donation_id']);
        
        try {
            $conn->autocommit(false);
            
            // Update donation status to cancelled
            $cancelQuery = "UPDATE donate_blood SET donation_status = 'Cancelled', updated_at = NOW() WHERE donation_id = ? AND donor_id = ? AND donation_status = 'Pending'";
            $cancelStmt = $conn->prepare($cancelQuery);
            $cancelStmt->bind_param("ii", $donation_id, $user_id);
            
            if ($cancelStmt->execute() && $cancelStmt->affected_rows > 0) {
                // Update blood_inventory (remove the reserved unit)
                $getBloodTypeQuery = "SELECT blood_type FROM donate_blood WHERE donation_id = ?";
                $getTypeStmt = $conn->prepare($getBloodTypeQuery);
                $getTypeStmt->bind_param("i", $donation_id);
                $getTypeStmt->execute();
                $typeResult = $getTypeStmt->get_result();
                
                if ($typeResult->num_rows > 0) {
                    $bloodData = $typeResult->fetch_assoc();
                    $updateInventoryQuery = "UPDATE blood_inventory SET available_units = GREATEST(0, available_units - 1), last_updated = NOW() WHERE blood_type = ?";
                    $invStmt = $conn->prepare($updateInventoryQuery);
                    $invStmt->bind_param("s", $bloodData['blood_type']);
                    $invStmt->execute();
                    $invStmt->close();
                }
                $getTypeStmt->close();
                
                $conn->commit();
                $message = "Your donation has been cancelled successfully.";
                $messageType = "success";
            } else {
                throw new Exception("Unable to cancel donation. It may have already been processed.");
            }
            
            $cancelStmt->close();
            $conn->autocommit(true);
            
        } catch (Exception $e) {
            $conn->rollback();
            $conn->autocommit(true);
            $message = "Error cancelling donation: " . $e->getMessage();
            $messageType = "error";
        }
    }
    
    if (isset($_POST['cancel_request'])) {
    $request_id = intval($_POST['request_id']);
    
    try {
        $conn->autocommit(false);
        
        // Update request status to cancelled
        $cancelQuery = "UPDATE recipient_form SET request_status = 'Cancelled', updated_at = NOW() WHERE request_id = ? AND user_id = ? AND request_status = 'Pending'";
        $cancelStmt = $conn->prepare($cancelQuery);
        $cancelStmt->bind_param("ii", $request_id, $user_id);
        
        if ($cancelStmt->execute() && $cancelStmt->affected_rows > 0) {
            $conn->commit();
            $message = "Your blood request has been cancelled successfully.";
            $messageType = "success";
        } else {
            throw new Exception("Unable to cancel request. It may have already been processed or doesn't exist.");
        }
        
        $cancelStmt->close();
        $conn->autocommit(true);
        
    } catch (Exception $e) {
        $conn->rollback();
        $conn->autocommit(true);
        $message = "Error cancelling request: " . $e->getMessage();
        $messageType = "error";
    }
}
}

// Function to get sort order for donations
function getDonationOrderBy($sort) {
    switch ($sort) {
        case 'date_asc':
            return 'created_at ASC';
        case 'date_desc':
            return 'created_at DESC';
        case 'status_pending':
            return "CASE WHEN donation_status = 'Pending' THEN 0 ELSE 1 END, created_at DESC";
        case 'status_processing':
            return "CASE WHEN donation_status = 'Processing' THEN 0 ELSE 1 END, created_at DESC";
        case 'status_completed':
            return "CASE WHEN donation_status = 'Completed' THEN 0 ELSE 1 END, created_at DESC";
        case 'status_cancelled':
            return "CASE WHEN donation_status = 'Cancelled' THEN 0 ELSE 1 END, created_at DESC";
        case 'status_asc':
            return 'donation_status ASC, created_at DESC';
        case 'status_desc':
            return 'donation_status DESC, created_at DESC';
        default:
            return 'created_at DESC';
    }
}

// Function to get sort order for requests
function getRequestOrderBy($sort) {
    switch ($sort) {
        case 'date_asc':
            return 'created_at ASC';
        case 'date_desc':
            return 'created_at DESC';
        case 'status_pending':
            return "CASE WHEN request_status = 'Pending' THEN 0 ELSE 1 END, created_at DESC";
        case 'status_processing':
            return "CASE WHEN request_status = 'Processing' THEN 0 ELSE 1 END, created_at DESC";
        case 'status_fulfilled':
            return "CASE WHEN request_status = 'Fulfilled' THEN 0 ELSE 1 END, created_at DESC";
        case 'status_cancelled':
            return "CASE WHEN request_status = 'Cancelled' THEN 0 ELSE 1 END, created_at DESC";
        case 'status_asc':
            return 'request_status ASC, created_at DESC';
        case 'status_desc':
            return 'request_status DESC, created_at DESC';
        default:
            return 'created_at DESC';
    }
}

// Get user's donations with sorting
$donations = [];
try {
    $donationOrderBy = getDonationOrderBy($donationSort);
    $donationsQuery = "SELECT donation_id, blood_type, medical_history, weight, hospital_location, 
                       date_of_donation, donation_status, created_at 
                       FROM donate_blood 
                       WHERE donor_id = ? 
                       ORDER BY " . $donationOrderBy;
    $donationsStmt = $conn->prepare($donationsQuery);
    $donationsStmt->bind_param("i", $user_id);
    $donationsStmt->execute();
    $donationsResult = $donationsStmt->get_result();
    
    while ($row = $donationsResult->fetch_assoc()) {
        $donations[] = $row;
    }
    $donationsStmt->close();
} catch (Exception $e) {
    $donationsError = "Error fetching donations: " . $e->getMessage();
}

// Get user's blood requests with sorting
$requests = [];
try {
    $requestOrderBy = getRequestOrderBy($requestSort);
    $requestsQuery = "SELECT request_id, blood_type_needed, medical_conditions, urgency_level, 
                      hospital_location, units_needed, needed_by_date, request_status, 
                      created_at, doctor_contact, emergency_contact
                      FROM recipient_form 
                      WHERE user_id = ? 
                      ORDER BY " . $requestOrderBy;
    $requestsStmt = $conn->prepare($requestsQuery);
    $requestsStmt->bind_param("i", $user_id);
    $requestsStmt->execute();
    $requestsResult = $requestsStmt->get_result();
    
    while ($row = $requestsResult->fetch_assoc()) {
        $requests[] = $row;
    }
    $requestsStmt->close();
} catch (Exception $e) {
    $requestsError = "Error fetching requests: " . $e->getMessage();
}

// Generate ticket numbers
function generateDonationTicket($donationData) {
    return 'PBB-' . str_pad($donationData['donation_id'], 4, '0', STR_PAD_LEFT) . '-' . 
           strtoupper($donationData['blood_type']) . '-' . 
           date('Ymd', strtotime($donationData['created_at']));
}

function generateRequestTicket($requestData) {
    return 'PBR-' . str_pad($requestData['request_id'], 4, '0', STR_PAD_LEFT) . '-' . 
           strtoupper($requestData['blood_type_needed']) . '-' . 
           strtoupper(substr($requestData['urgency_level'], 0, 1)) . '-' .
           date('Ymd', strtotime($requestData['created_at']));
}

// Get user information
$userQuery = "SELECT username FROM login WHERE user_id = ?";
$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$userResult = $userStmt->get_result();
$userData = $userResult->fetch_assoc();
$username = $userData['username'];
$userStmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pirate's Blood Bank - My Appointments</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cardo:ital,wght@0,400;0,700;1,400&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Quicksand:wght@300..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    
    <style>
        /* Main content layout */
        main {
            margin-left: 70px;
            min-height: 100vh;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            transition: margin-left 0.3s ease;
            padding: 20px;
            width: calc(100vw - 70px);
            box-sizing: border-box;
            overflow-y: auto;
        }
        
        .appointments-container {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            box-shadow: 2px 0 15px rgba(220, 220, 220, 0.1);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: none;
            margin: 0;
            box-sizing: border-box;
        }
        
        .appointments-header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 25px;
        }
        
        .appointments-title {
            font-size: 32px;
            font-weight: 700;
            color: #495057;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            font-family: 'Poppins', sans-serif;
            margin-bottom: 10px;
        }
        
        .appointments-title i {
            color: #dc3545;
            font-size: 28px;
        }
        
        .appointments-subtitle {
            color: #6c757d;
            font-size: 16px;
            font-weight: 500;
        }
        
        .welcome-message {
            background: linear-gradient(90deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));
            color: #495057;
            padding: 15px 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            border-left: 4px solid #dc3545;
            font-weight: 500;
        }
        
        .appointments-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            position: relative;
        }
        
        .appointments-grid::before {
            content: '';
            position: absolute;
            left: 50%;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(to bottom, #dc3545, #b02a37);
            transform: translateX(-50%);
            z-index: 1;
        }
        
        .appointments-section {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 15px;
            padding: 0;
            box-shadow: 2px 0 15px rgba(220, 220, 220, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .section-label {
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
            padding: 20px;
            text-align: center;
            font-weight: 700;
            font-size: 18px;
            letter-spacing: 2px;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 0;
            box-shadow: 0 2px 10px rgba(220, 53, 69, 0.2);
        }
        
        .section-label i {
            font-size: 20px;
        }
        
        .donations-section .section-label {
            background: linear-gradient(135deg, #dc3545, #b02a37);
        }
        
        .requests-section .section-label {
            background: linear-gradient(135deg, #17a2b8, #138496);
        }
        
        .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 30px 30px 25px 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .section-title {
            font-size: 24px;
            font-weight: 700;
            color: #495057;
            font-family: 'Poppins', sans-serif;
        }
        
        .section-icon {
            font-size: 24px;
            color: #dc3545;
        }
        
        .section-count {
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-left: auto;
        }
        
        /* Sort Controls */
        .sort-controls {
            display: flex;
            align-items: center;
            gap: 15px;
            margin: 0 30px 15px 30px;
            padding: 12px;
            background: rgba(248, 249, 250, 0.8);
            border-radius: 12px;
            border: 1px solid #e9ecef;
        }
        
        .sort-label {
            font-size: 14px;
            font-weight: 600;
            color: #495057;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .sort-select {
            padding: 8px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            background: white;
            font-size: 14px;
            font-weight: 500;
            color: #495057;
            cursor: pointer;
            transition: all 0.3s ease;
            outline: none;
        }
        
        .sort-select:hover {
            border-color: #dc3545;
        }
        
        .sort-select:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
        }
        
        .appointments-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
            padding: 0 30px 30px 30px;
        }
        
        .appointment-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(220, 220, 220, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .appointment-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(220, 220, 220, 0.15);
        }
        
        .appointment-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(135deg, #dc3545, #b02a37);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        
        .ticket-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .ticket-number {
            font-size: 16px;
            font-weight: 700;
            color: #dc3545;
            font-family: 'Courier New', monospace;
            letter-spacing: 1px;
        }
        
        .blood-type-badge {
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 16px;
            display: inline-block;
        }
        
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: white;
        }
        
        .status-processing {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
        }
        
        .status-completed,
        .status-fulfilled {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            color: white;
        }
        
        .status-cancelled,
        .status-expired {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
        }
        
        .card-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .detail-label {
            font-size: 12px;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .detail-value {
            font-size: 14px;
            font-weight: 500;
            color: #495057;
        }
        
        .urgency-critical { color: #dc3545; font-weight: 700; }
        .urgency-high { color: #fd7e14; font-weight: 600; }
        .urgency-medium { color: #ffc107; font-weight: 600; }
        .urgency-low { color: #28a745; font-weight: 600; }
        
        .card-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #f0f0f0;
        }
        
        .action-btn {
            padding: 10px 20px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
            cursor: pointer;
            font-family: 'quicksand', sans-serif;
        }
        
        .btn-update {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
        }
        
        .btn-update:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(23, 162, 184, 0.3);
            color: white;
            text-decoration: none;
        }
        
        .btn-view {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
        }
        
        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.3);
            color: white;
            text-decoration: none;
        }
        
        .btn-cancel {
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
        }
        
        .btn-cancel:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
            color: white;
            text-decoration: none;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .empty-icon {
            font-size: 64px;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        
        .empty-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #495057;
        }
        
        .empty-subtitle {
            font-size: 16px;
            margin-bottom: 25px;
        }
        
        .empty-action {
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
            padding: 12px 25px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
        }
        
        .message {
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .message.success {
            background: linear-gradient(90deg, rgba(40, 167, 69, 0.1), rgba(40, 167, 69, 0.05));
            color: #155724;
            border: 1px solid rgba(40, 167, 69, 0.2);
        }
        
        .message.error {
            background: linear-gradient(90deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));
            color: #721c24;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }
        
        .hospital-info {
            grid-column: 1 / -1;
            background: rgba(220, 53, 69, 0.05);
            padding: 15px;
            border-radius: 10px;
            border-left: 4px solid #dc3545;
        }
        
        .hospital-name {
            font-weight: 600;
            color: #495057;
            margin-bottom: 5px;
        }
        
        .hospital-address {
            font-size: 13px;
            color: #6c757d;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            main {
                margin-top: 100px;
                margin-left: 0;
                padding: 15px;
                width: 100vw;
            }
            
            .appointments-container {
                padding: 25px;
                border-radius: 15px;
            }
            
            .appointments-title {
                font-size: 24px;
            }
            
            .appointments-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .appointments-grid::before {
                display: none;
            }
            
            .section-label {
                font-size: 16px;
                padding: 15px;
                letter-spacing: 1.5px;
            }
            
            .section-header {
                margin: 20px 20px 20px 20px;
            }
            
            .sort-controls {
                margin: 0 20px 15px 20px;
                padding: 3px;
            }
            
            .appointments-list {
                padding: 0 20px 20px 20px;
            }
            
            .card-details {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .card-actions {
                justify-content: center;
                gap:3px;

            }
            .action-btn {
            padding: 5px 10px;
        font-size: 12px;}
        }
        
        @media (max-width: 480px) {
            .appointments-container {
                padding: 20px;
                border-radius: 10px;
            }
            
            .appointments-title {
                font-size: 20px;
            }
            
            .appointment-card {
                padding: 20px;
            }
            
            .card-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .sort-controls {
                flex-direction: column;
                gap: 10px;
                align-items: stretch;
            }
            
            .sort-select {
                width: 100%;
            }
        }
    </style>
</head>

<body>
<?php 
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
    include('includes/adminheader.php');
} else {
    include('includes/header.php');
}
?>
<?php include('includes/sidebar.php'); ?>

<main>
    <div class="appointments-container">
        <div class="appointments-header">
            <h1 class="appointments-title">
                <i class="fas fa-calendar-check"></i>
                My Appointments
            </h1>
            <p class="appointments-subtitle">Track your blood donations and requests</p>
        </div>
        
        <div class="welcome-message">
            <i class="fas fa-user"></i>
            Welcome back, <strong><?php echo htmlspecialchars($username); ?></strong>! Here's an overview of your blood bank activities.
        </div>
        
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php if ($messageType == 'success'): ?>
                    <i class="fas fa-check-circle"></i>
                <?php else: ?>
                    <i class="fas fa-exclamation-triangle"></i>
                <?php endif; ?>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="appointments-grid">
            <!-- Left Side: Donations -->
            <div class="appointments-section donations-section">
                <div class="section-label">
                    <i class="fas fa-heart"></i>
                    <span>DONATIONS</span>
                </div>
                <div class="section-header">
                    <i class="fas fa-heart section-icon"></i>
                    <h2 class="section-title">My Donations</h2>
                    <span class="section-count"><?php echo count($donations); ?></span>
                </div>
                
                <?php if (!empty($donations)): ?>
                <div class="sort-controls">
                    <div class="sort-label">
                        <i class="fas fa-sort"></i>
                        Sort by:
                    </div>
                    <select class="sort-select" id="donationSort" onchange="changeDonationSort(this.value)">
                        <option value="date_desc" <?php echo $donationSort === 'date_desc' ? 'selected' : ''; ?>>Date (Newest First)</option>
                        <option value="date_asc" <?php echo $donationSort === 'date_asc' ? 'selected' : ''; ?>>Date (Oldest First)</option>
                        <option value="status_asc" <?php echo $donationSort === 'status_asc' ? 'selected' : ''; ?>>Status (A-Z)</option>
                        <option value="status_desc" <?php echo $donationSort === 'status_desc' ? 'selected' : ''; ?>>Status (Z-A)</option>
                        <option value="status_pending" <?php echo $donationSort === 'status_pending' ? 'selected' : ''; ?>>Pending First</option>
                        <option value="status_processing" <?php echo $donationSort === 'status_processing' ? 'selected' : ''; ?>>Processing First</option>
                        <option value="status_completed" <?php echo $donationSort === 'status_completed' ? 'selected' : ''; ?>>Completed First</option>
                        <option value="status_cancelled" <?php echo $donationSort === 'status_cancelled' ? 'selected' : ''; ?>>Cancelled First</option>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="appointments-list">
                    <?php if (empty($donations)): ?>
                        <div class="empty-state">
                            <i class="fas fa-heart empty-icon"></i>
                            <div class="empty-title">No Donations Yet</div>
                            <div class="empty-subtitle">Ready to save lives? Make your first blood donation today!</div>
                            <a href="donate.php" class="empty-action">
                                <i class="fas fa-plus"></i>
                                Donate Blood
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($donations as $donation): ?>
                            <div class="appointment-card">
                                <div class="card-header">
                                    <div class="ticket-info">
                                        <div class="ticket-number"><?php echo generateDonationTicket($donation); ?></div>
                                        <div class="blood-type-badge"><?php echo htmlspecialchars($donation['blood_type']); ?></div>
                                    </div>
                                    <div class="status-badge status-<?php echo strtolower($donation['donation_status']); ?>">
                                        <?php echo htmlspecialchars($donation['donation_status']); ?>
                                    </div>
                                </div>
                                
                                <div class="card-details">
                                    <div class="detail-item">
                                        <div class="detail-label">Weight</div>
                                        <div class="detail-value"><?php echo $donation['weight']; ?> kg</div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Donation Date</div>
                                        <div class="detail-value"><?php echo date('M j, Y', strtotime($donation['date_of_donation'])); ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Submitted</div>
                                        <div class="detail-value"><?php echo date('M j, Y g:i A', strtotime($donation['created_at'])); ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Medical History</div>
                                        <div class="detail-value"><?php echo htmlspecialchars(substr($donation['medical_history'], 0, 50)) . (strlen($donation['medical_history']) > 50 ? '...' : ''); ?></div>
                                    </div>
                                    <div class="hospital-info">
                                        <div class="hospital-name">
                                            <?php 
                                            $hospitalParts = explode(' - ', $donation['hospital_location']);
                                            echo htmlspecialchars($hospitalParts[0]);
                                            ?>
                                        </div>
                                        <?php if (isset($hospitalParts[1])): ?>
                                            <div class="hospital-address"><?php echo htmlspecialchars($hospitalParts[1]); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="card-actions">
                                    <?php if ($donation['donation_status'] === 'Pending'): ?>
                                        <a href="update-donation.php?id=<?php echo $donation['donation_id']; ?>" class="action-btn btn-update">
                                            <i class="fas fa-edit"></i>
                                            Update
                                        </a>
                                        <button class="action-btn btn-cancel" onclick="cancelDonation(<?php echo $donation['donation_id']; ?>, '<?php echo generateDonationTicket($donation); ?>')">
                                            <i class="fas fa-times"></i>
                                            Cancel
                                        </button>
                                    <?php endif; ?>
                                    <button class="action-btn btn-view" onclick="viewDetails('donation', <?php echo $donation['donation_id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                        View Details
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Right Side: Requests -->
            <div class="appointments-section requests-section">
                <div class="section-label">
                    <i class="fas fa-hand-holding-medical"></i>
                    <span>REQUESTS</span>
                </div>
                <div class="section-header">
                    <i class="fas fa-hand-holding-medical section-icon"></i>
                    <h2 class="section-title">My Requests</h2>
                    <span class="section-count"><?php echo count($requests); ?></span>
                </div>
                
                <?php if (!empty($requests)): ?>
                <div class="sort-controls">
                    <div class="sort-label">
                        <i class="fas fa-sort"></i>
                        Sort by:
                    </div>
                    <select class="sort-select" id="requestSort" onchange="changeRequestSort(this.value)">
                        <option value="date_desc" <?php echo $requestSort === 'date_desc' ? 'selected' : ''; ?>>Date (Newest First)</option>
                        <option value="date_asc" <?php echo $requestSort === 'date_asc' ? 'selected' : ''; ?>>Date (Oldest First)</option>
                        <option value="status_asc" <?php echo $requestSort === 'status_asc' ? 'selected' : ''; ?>>Status (A-Z)</option>
                        <option value="status_desc" <?php echo $requestSort === 'status_desc' ? 'selected' : ''; ?>>Status (Z-A)</option>
                        <option value="status_pending" <?php echo $requestSort === 'status_pending' ? 'selected' : ''; ?>>Pending First</option>
                        <option value="status_processing" <?php echo $requestSort === 'status_processing' ? 'selected' : ''; ?>>Processing First</option>
                        <option value="status_fulfilled" <?php echo $requestSort === 'status_fulfilled' ? 'selected' : ''; ?>>Fulfilled First</option>
                        <option value="status_cancelled" <?php echo $requestSort === 'status_cancelled' ? 'selected' : ''; ?>>Cancelled First</option>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="appointments-list">
                    <?php if (empty($requests)): ?>
                        <div class="empty-state">
                            <i class="fas fa-hand-holding-medical empty-icon"></i>
                            <div class="empty-title">No Requests Yet</div>
                            <div class="empty-subtitle">Need blood for medical treatment? Submit a request here.</div>
                            <a href="receive.php" class="empty-action">
                                <i class="fas fa-plus"></i>
                                Request Blood
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($requests as $request): ?>
                            <div class="appointment-card">
                                <div class="card-header">
                                    <div class="ticket-info">
                                        <div class="ticket-number"><?php echo generateRequestTicket($request); ?></div>
                                        <div class="blood-type-badge"><?php echo htmlspecialchars($request['blood_type_needed']); ?></div>
                                    </div>
                                    <div class="status-badge status-<?php echo strtolower($request['request_status']); ?>">
                                        <?php echo htmlspecialchars($request['request_status']); ?>
                                    </div>
                                </div>
                                
                                <div class="card-details">
                                    <div class="detail-item">
                                        <div class="detail-label">Units Needed</div>
                                        <div class="detail-value"><?php echo $request['units_needed']; ?> unit<?php echo $request['units_needed'] > 1 ? 's' : ''; ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Urgency Level</div>
                                        <div class="detail-value urgency-<?php echo strtolower($request['urgency_level']); ?>">
                                            <?php 
                                            $urgencyIcons = [
                                                'Critical' => 'fas fa-ambulance',
                                                'High' => 'fas fa-exclamation-triangle', 
                                                'Medium' => 'fas fa-clock',
                                                'Low' => 'fas fa-calendar'
                                            ];
                                            ?>
                                            <i class="<?php echo $urgencyIcons[$request['urgency_level']] ?? 'fas fa-circle'; ?>"></i>
                                            <?php echo htmlspecialchars($request['urgency_level']); ?>
                                        </div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Needed By</div>
                                        <div class="detail-value"><?php echo date('M j, Y', strtotime($request['needed_by_date'])); ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Submitted</div>
                                        <div class="detail-value"><?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?></div>
                                    </div>
                                    <div class="hospital-info">
                                        <div class="hospital-name">
                                            <?php 
                                            $hospitalParts = explode(' - ', $request['hospital_location']);
                                            echo htmlspecialchars($hospitalParts[0]);
                                            ?>
                                        </div>
                                        <?php if (isset($hospitalParts[1])): ?>
                                            <div class="hospital-address"><?php echo htmlspecialchars($hospitalParts[1]); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="card-actions">
                                    <?php if ($request['request_status'] === 'Pending'): ?>
                                        <a href="update-request.php?id=<?php echo $request['request_id']; ?>" class="action-btn btn-update">
                                            <i class="fas fa-edit"></i>
                                            Update
                                        </a>
                                        <button class="action-btn btn-cancel" onclick="cancelRequest(<?php echo $request['request_id']; ?>, '<?php echo generateRequestTicket($request); ?>')">
                                            <i class="fas fa-times"></i>
                                            Cancel
                                        </button>
                                    <?php endif; ?>
                                    <button class="action-btn btn-view" onclick="viewDetails('request', <?php echo $request['request_id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                        View Details
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
function viewDetails(type, id) {
    // For now, show an alert. You can implement a modal or redirect to a details page
    if (type === 'donation') {
        window.location.href = `donation-details.php?id=${id}`;
    } else {
        window.location.href = `request-details.php?id=${id}`;
    }
}

function cancelDonation(donationId, ticketNumber) {
    if (confirm(`Are you sure you want to cancel your donation?\n\nTicket: ${ticketNumber}\n\nThis action cannot be undone.`)) {
        // Create and submit a form
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const donationIdInput = document.createElement('input');
        donationIdInput.type = 'hidden';
        donationIdInput.name = 'donation_id';
        donationIdInput.value = donationId;
        
        const cancelInput = document.createElement('input');
        cancelInput.type = 'hidden';
        cancelInput.name = 'cancel_donation';
        cancelInput.value = '1';
        
        form.appendChild(donationIdInput);
        form.appendChild(cancelInput);
        document.body.appendChild(form);
        form.submit();
    }
}

function cancelRequest(requestId, ticketNumber) {
    if (confirm(`Are you sure you want to cancel your blood request?\n\nTicket: ${ticketNumber}\n\nThis action cannot be undone.`)) {
        // Create and submit a form
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const requestIdInput = document.createElement('input');
        requestIdInput.type = 'hidden';
        requestIdInput.name = 'request_id';
        requestIdInput.value = requestId;
        
        const cancelInput = document.createElement('input');
        cancelInput.type = 'hidden';
        cancelInput.name = 'cancel_request';
        cancelInput.value = '1';
        
        form.appendChild(requestIdInput);
        form.appendChild(cancelInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// Sort function for donations
function changeDonationSort(sortValue) {
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('donation_sort', sortValue);
    window.location.href = currentUrl.toString();
}

// Sort function for requests
function changeRequestSort(sortValue) {
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('request_sort', sortValue);
    window.location.href = currentUrl.toString();
}

document.addEventListener('DOMContentLoaded', function() {
    // Add click to copy functionality for ticket numbers
    const ticketNumbers = document.querySelectorAll('.ticket-number');
    
    ticketNumbers.forEach(ticket => {
        ticket.addEventListener('click', function() {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(this.textContent).then(() => {
                    // Show feedback
                    const originalText = this.textContent;
                    this.textContent = 'Copied!';
                    this.style.color = '#28a745';
                    
                    setTimeout(() => {
                        this.textContent = originalText;
                        this.style.color = '#dc3545';
                    }, 1500);
                });
            }
        });
        
        // Add cursor pointer and title
        ticket.style.cursor = 'pointer';
        ticket.title = 'Click to copy ticket number';
    });
    
    // Add smooth scroll animation when sort changes
    const sortSelects = document.querySelectorAll('.sort-select');
    sortSelects.forEach(select => {
        select.addEventListener('change', function() {
            // Add loading animation
            this.style.opacity = '0.6';
            this.disabled = true;
            
            // Show loading indicator
            const loadingText = document.createElement('div');
            loadingText.style.cssText = `
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: rgba(220, 53, 69, 0.9);
                color: white;
                padding: 15px 25px;
                border-radius: 10px;
                font-weight: 600;
                z-index: 9999;
            `;
            loadingText.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sorting...';
            document.body.appendChild(loadingText);
        });
    });
});
</script>

</body>
<?php include('includes/footer.php'); ?>
</html>