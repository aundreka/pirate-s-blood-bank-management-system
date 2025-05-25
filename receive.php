<?php 
include('includes/db.php');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Simple debug - remove in production
$debug_messages = [];
// Debugging removed for production

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['submit_request']) || (isset($_POST['blood_type_needed']) && isset($_POST['medical_conditions'])))) {
    $user_id = $_SESSION['user_id'];
    $blood_type_needed = isset($_POST['blood_type_needed']) ? trim($_POST['blood_type_needed']) : '';
    $medical_conditions = isset($_POST['medical_conditions']) ? trim($_POST['medical_conditions']) : '';
    $urgency_level = isset($_POST['urgency_level']) ? trim($_POST['urgency_level']) : '';
    $hospital_location = isset($_POST['hospital_location']) ? trim($_POST['hospital_location']) : '';
    $units_needed = isset($_POST['units_needed']) ? intval($_POST['units_needed']) : 1;
    $needed_by_date = isset($_POST['needed_by_date']) ? $_POST['needed_by_date'] : date('Y-m-d', strtotime('+7 days'));
    $doctor_contact = isset($_POST['doctor_contact']) ? trim($_POST['doctor_contact']) : '';
    $emergency_contact = isset($_POST['emergency_contact']) ? trim($_POST['emergency_contact']) : '';
    
    // Validation
    $errors = [];
    
    if (empty($blood_type_needed)) {
        $errors[] = "Blood type needed is required.";
    }
    
    if (empty($medical_conditions)) {
        $errors[] = "Medical conditions information is required.";
    }
    
    if (empty($urgency_level)) {
        $errors[] = "Urgency level is required.";
    }
    
    if (empty($hospital_location)) {
        $errors[] = "Please select a hospital location.";
    }
    
    if ($units_needed < 1 || $units_needed > 10) {
        $errors[] = "Units needed must be between 1 and 10.";
    }
    
    if (empty($needed_by_date) || strtotime($needed_by_date) < strtotime(date('Y-m-d'))) {
        $errors[] = "Needed by date must be today or in the future.";
    }
    
    if (empty($errors)) {
        try {
            // Check if connection exists
            if (!isset($conn) || !$conn) {
                throw new Exception("Database connection not available");
            }
            
            // Check for recent pending requests (within last 24 hours for same blood type to prevent spam)
            $checkRecent = "SELECT request_id, request_status, created_at FROM recipient_form WHERE user_id = ? AND blood_type_needed = ? AND request_status = 'Pending' AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) ORDER BY created_at DESC LIMIT 1";
            $checkStmt = $conn->prepare($checkRecent);
            
            if ($checkStmt) {
                $checkStmt->bind_param("is", $user_id, $blood_type_needed);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                
                if ($result->num_rows > 0) {
                    $recentRequest = $result->fetch_assoc();
                    
                    // Create a more helpful message with options
                    $timeAgo = date('M j, Y g:i A', strtotime($recentRequest['created_at']));
                    $message = "You have a pending blood request for " . $blood_type_needed . " from " . $timeAgo . ".";
                    $messageType = "pending_request";
                } else {
                    // Begin transaction
                    $conn->autocommit(false);
                    
                    // Insert new blood request
                    $insertRequest = "INSERT INTO recipient_form (user_id, blood_type_needed, medical_conditions, urgency_level, hospital_location, units_needed, needed_by_date, doctor_contact, emergency_contact) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $stmt = $conn->prepare($insertRequest);
                    
                    if (!$stmt) {
                        throw new Exception("Request prepare failed: " . $conn->error);
                    }
                    
                    $stmt->bind_param("issssisss", $user_id, $blood_type_needed, $medical_conditions, $urgency_level, $hospital_location, $units_needed, $needed_by_date, $doctor_contact, $emergency_contact);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Request insert failed: " . $stmt->error);
                    }
                    
                    // Get the new request ID
                    $request_id = $conn->insert_id;
                    
                    // The waitlist entry will be automatically created by the trigger
                    
                    // Commit transaction
                    $conn->commit();
                    $conn->autocommit(true);
                    
                    // Store request details in session for thank you page
                    $_SESSION['request_success'] = [
                        'request_id' => $request_id,
                        'blood_type_needed' => $blood_type_needed,
                        'urgency_level' => $urgency_level,
                        'hospital_location' => $hospital_location,
                        'units_needed' => $units_needed,
                        'needed_by_date' => $needed_by_date,
                        'requester_name' => $username,
                        'date_submitted' => date('Y-m-d H:i:s')
                    ];
                    
                    // Close statement
                    $stmt->close();
                    
                    // Redirect to confirmation page
                    header('Location: request-confirmation.php');
                    exit();
                }
                
                $checkStmt->close();
            } else {
                throw new Exception("Could not prepare recent request check: " . $conn->error);
            }
            
        } catch (Exception $e) {
            // Rollback transaction on error
            if (isset($conn) && $conn) {
                $conn->rollback();
                $conn->autocommit(true);
            }
            
            $message = "An error occurred while processing your blood request: " . $e->getMessage();
            $messageType = "error";
        }
    } else {
        $message = implode('<br>', $errors);
        $messageType = "error";
    }
}

// Get user's recent requests for display
$recentRequests = [];
try {
    $recentQuery = "SELECT request_id, blood_type_needed, urgency_level, request_status, created_at, needed_by_date 
                    FROM recipient_form 
                    WHERE user_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT 3";
    $recentStmt = $conn->prepare($recentQuery);
    $recentStmt->bind_param("i", $_SESSION['user_id']);
    $recentStmt->execute();
    $recentResult = $recentStmt->get_result();
    
    while ($row = $recentResult->fetch_assoc()) {
        $recentRequests[] = $row;
    }
    $recentStmt->close();
} catch (Exception $e) {
    // Handle error silently
}

// Get user information
$userQuery = "SELECT username FROM login WHERE user_id = ?";
$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param("i", $_SESSION['user_id']);
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
    <title>Pirate's Blood Bank - Request Blood</title>
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
        
        .request-container {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            box-shadow: 2px 0 15px rgba(220, 220, 220, 0.1);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: none;
            margin: 0;
            box-sizing: border-box;
        }
        
        .request-header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 25px;
        }
        
        .request-title {
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
        
        .request-title i {
            color: #dc3545;
            font-size: 28px;
        }
        
        .request-subtitle {
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
        
        .request-form {
            display: grid;
            gap: 25px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }
        
        .form-triple {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 25px;
        }
        
        .form-label {
            font-weight: 600;
            color: #495057;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-label i {
            color: #dc3545;
            font-size: 16px;
        }
        
        .required {
            color: #dc3545;
        }
        
        .form-input,
        .form-select,
        .form-textarea {
            padding: 15px 20px;
            border: 2px solid rgba(220, 53, 69, 0.1);
            border-radius: 12px;
            font-size: 15px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
            background: white;
        }
        
        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: #dc3545;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
            transform: translateY(-2px);
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 120px;
            font-family: 'Poppins', sans-serif;
        }
        
        .form-help {
            font-size: 12px;
            color: #6c757d;
            font-style: italic;
        }
        
        /* Fieldset and Legend styling for blood type */
        fieldset {
            border: none;
            padding: 0;
            margin: 0;
        }
        
        legend.form-label {
            font-weight: 600;
            color: #495057;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            padding: 0;
        }
        
        legend.form-label i {
            color: #dc3545;
            font-size: 16px;
        }
        
        .blood-type-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-top: 10px;
        }
        
        .blood-type-option {
            position: relative;
        }
        
        .blood-type-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 1px;
            height: 1px;
            margin: 0;
            padding: 0;
            border: none;
            clip: rect(0, 0, 0, 0);
        }
        
        .blood-type-option input[type="radio"]:focus + .blood-type-label {
            outline: 2px solid #dc3545;
            outline-offset: 2px;
        }
        
        .blood-type-option input[type="radio"]:checked + .blood-type-label {
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
            border-color: #dc3545;
            transform: scale(1.05);
        }
        
        .blood-type-label {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px;
            border: 2px solid rgba(220, 53, 69, 0.2);
            border-radius: 10px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            color: #495057;
        }
        
        .blood-type-label:hover {
            border-color: #dc3545;
            transform: translateY(-2px);
        }
        
        .urgency-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-top: 10px;
        }
        
        .urgency-option {
            position: relative;
        }
        
        .urgency-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 1px;
            height: 1px;
            margin: 0;
            padding: 0;
            border: none;
            clip: rect(0, 0, 0, 0);
        }
        
        .urgency-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 15px 10px;
            border: 2px solid rgba(220, 53, 69, 0.2);
            border-radius: 10px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            color: #495057;
            min-height: 70px;
        }
        
        .urgency-label:hover {
            border-color: #dc3545;
            transform: translateY(-2px);
        }
        
        .urgency-option input[type="radio"]:checked + .urgency-label {
            color: white;
            border-color: #dc3545;
            transform: scale(1.05);
        }
        
        .urgency-option input[type="radio"]:checked + .urgency-label.critical {
            background: linear-gradient(135deg, #dc3545, #b02a37);
        }
        
        .urgency-option input[type="radio"]:checked + .urgency-label.high {
            background: linear-gradient(135deg, #fd7e14, #e55a00);
        }
        
        .urgency-option input[type="radio"]:checked + .urgency-label.low {
            background: linear-gradient(135deg, #28a745, #1e7e34);
        }
        
        .urgency-icon {
            font-size: 20px;
            margin-bottom: 5px;
        }
        
        .urgency-text {
            font-size: 12px;
            text-align: center;
            line-height: 1.2;
        }
        
        .priority-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .priority-title {
            font-weight: 600;
            color: #856404;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .priority-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .priority-list li {
            color: #856404;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .priority-list li i {
            color: #dc3545;
            font-size: 12px;
        }
        
        .submit-btn {
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
            border: none;
            padding: 18px 40px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin: 20px auto 0;
            min-width: 200px;
            margin-bottom: 120px;
        }
        
        .submit-btn:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.3);
        }
        
        .submit-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
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
        
        /* Responsive Design */
        @media (max-width: 768px) {
            main {
                margin-left: 0;
                padding: 15px;
                width: 100vw;
            }
            
            .request-container {
                padding: 25px;
                border-radius: 15px;
                
            }
            
            .request-title {
                font-size: 24px;
            }
            
            .form-row,
            .form-triple, .form-group {
                grid-template-columns: 1fr;
                gap: 20px;
                        max-width: 400px;

            }
            
            .blood-type-grid,
            .urgency-grid {
                grid-template-columns: repeat(2, 1fr);
                        max-width: 400px;

            }
        }
        
        @media (max-width: 480px) {
            main {
                padding: 10px;
            }
            
            .request-container {
                padding: 20px;
                border-radius: 10px;
            }
            
            .blood-type-grid,
            .urgency-grid {
                grid-template-columns: 1fr;
            }
            
            .request-title {
                font-size: 20px;
            }
        }
        
        /* Ensure body and html allow full scrolling */
        html, body {
            height: 100%;
            overflow-x: hidden;
        }
        
        body {
            margin: 0;
            padding: 0;
        }
        
        /* Recent Requests Section */
        .recent-requests {
            background: linear-gradient(90deg, rgba(23, 162, 184, 0.1), rgba(23, 162, 184, 0.05));
            border: 1px solid rgba(23, 162, 184, 0.2);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .recent-title {
            font-size: 18px;
            font-weight: 600;
            color: #0c5460;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .requests-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .request-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            border-left: 4px solid #17a2b8;
            transition: all 0.3s ease;
        }
        
        .request-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(23, 162, 184, 0.15);
        }
        
        .request-card.pending {
            border-left-color: #ffc107;
        }
        
        .request-card.processing {
            border-left-color: #17a2b8;
        }
        
        .request-card.fulfilled {
            border-left-color: #28a745;
        }
        
        .request-card.cancelled {
            border-left-color: #6c757d;
        }
        
        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .blood-type-badge {
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
            padding: 8px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-processing {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-fulfilled {
            background: #d4edda;
            color: #155724;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .request-details {
            font-size: 14px;
            color: #6c757d;
        }
        
        .request-urgency {
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .request-date,
        .needed-date {
            margin-bottom: 5px;
        }
        
        .view-all-link {
            text-align: center;
            margin-top: 15px;
        }
        
        .view-all-link a {
            color: #17a2b8;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 20px;
            transition: all 0.3s ease;
        }
        
        .view-all-link a:hover {
            background: rgba(23, 162, 184, 0.1);
            transform: translateY(-2px);
        }
        
        /* Pending Request Notice Styling */
        .pending-request-notice {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 1px solid #ffeaa7;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.2);
        }
        
        .notice-header {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 20px;
            font-weight: 700;
            color: #856404;
            margin-bottom: 15px;
        }
        
        .notice-header i {
            font-size: 24px;
            color: #dc3545;
        }
        
        .notice-message {
            font-size: 16px;
            color: #856404;
            margin-bottom: 25px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            border-left: 4px solid #dc3545;
        }
        
        .option-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .option-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            cursor: default;
        }
        
        .option-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            border-color: rgba(220, 53, 69, 0.2);
        }
        
        .option-card.emergency {
            border-color: #dc3545;
            background: linear-gradient(135deg, #fff5f5, #ffffff);
        }
        
        .option-card.emergency:hover {
            border-color: #dc3545;
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.2);
        }
        
        .option-card i {
            font-size: 24px;
            color: #dc3545;
            flex-shrink: 0;
        }
        
        .option-content strong {
            display: block;
            font-size: 16px;
            font-weight: 600;
            color: #495057;
            margin-bottom: 5px;
        }
        
        .option-content small {
            color: #6c757d;
            font-size: 14px;
            line-height: 1.3;
        }
        
        .view-requests-btn {
            text-align: center;
        }
        
        .view-requests-btn a {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
            padding: 15px 30px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .view-requests-btn a:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.3);
            color: white;
            text-decoration: none;
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
?><?php include('includes/sidebar.php'); ?>

<main>
    <div class="request-container">
        <div class="request-header">
            <h1 class="request-title">
                <i class="fas fa-hand-holding-medical"></i>
                Blood Request
            </h1>
            <p class="request-subtitle">Request blood for medical needs - Help is on the way</p>
        </div>
        
        <div class="welcome-message">
            <i class="fas fa-user"></i>
            Hello, <strong><?php echo htmlspecialchars($username); ?></strong>! Please provide accurate medical information for your blood request.
        </div>
        
        <?php if (!empty($recentRequests)): ?>
        <div class="recent-requests">
            <div class="recent-title">
                <i class="fas fa-history"></i>
                Your Recent Blood Requests
            </div>
            <div class="requests-grid">
                <?php foreach ($recentRequests as $request): ?>
                    <div class="request-card <?php echo strtolower($request['request_status']); ?>">
                        <div class="request-header">
                            <span class="blood-type-badge"><?php echo htmlspecialchars($request['blood_type_needed']); ?></span>
                            <span class="status-badge status-<?php echo strtolower($request['request_status']); ?>">
                                <?php echo htmlspecialchars($request['request_status']); ?>
                            </span>
                        </div>
                        <div class="request-details">
                            <div class="request-urgency">
                                <?php 
                                $urgencyIcons = [
                                    'Critical' => 'fas fa-ambulance',
                                    'High' => 'fas fa-exclamation-triangle', 
                                    'Medium' => 'fas fa-clock',
                                    'Low' => 'fas fa-calendar'
                                ];
                                ?>
                                <i class="<?php echo $urgencyIcons[$request['urgency_level']] ?? 'fas fa-circle'; ?>"></i>
                                <?php echo htmlspecialchars($request['urgency_level']); ?> Priority
                            </div>
                            <div class="request-date">
                                Requested: <?php echo date('M j, g:i A', strtotime($request['created_at'])); ?>
                            </div>
                            <div class="needed-date">
                                Needed by: <?php echo date('M j, Y', strtotime($request['needed_by_date'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="view-all-link">
                <a href="appointments.php">
                    <i class="fas fa-list"></i>
                    View All My Requests
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <?php if ($messageType == 'pending_request'): ?>
                <div class="pending-request-notice">
                    <div class="notice-header">
                        <i class="fas fa-clock"></i>
                        <strong>Pending Request Found</strong>
                    </div>
                    <div class="notice-message">
                        <?php echo $message; ?>
                    </div>
                    <div class="notice-options">
                        <div class="option-grid">
                            <div class="option-card">
                                <i class="fas fa-hourglass-half"></i>
                                <div class="option-content">
                                    <strong>Wait for processing</strong>
                                    <small>Your request is in our priority queue</small>
                                </div>
                            </div>
                            <div class="option-card emergency">
                                <i class="fas fa-phone"></i>
                                <div class="option-content">
                                    <strong>Emergency?</strong>
                                    <small>Call (+63) 966 470 1756 immediately</small>
                                </div>
                            </div>
                            <div class="option-card">
                                <i class="fas fa-plus-circle"></i>
                                <div class="option-content">
                                    <strong>Different blood type?</strong>
                                    <small>You can submit for a different blood type</small>
                                </div>
                            </div>
                            <div class="option-card">
                                <i class="fas fa-edit"></i>
                                <div class="option-content">
                                    <strong>Update needed?</strong>
                                    <small>Contact your hospital to modify request</small>
                                </div>
                            </div>
                        </div>
                        <div class="view-requests-btn">
                            <a href="my-requests.php">
                                <i class="fas fa-list"></i>
                                View Your Current Requests
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php if ($messageType == 'success'): ?>
                        <i class="fas fa-check-circle"></i>
                    <?php else: ?>
                        <i class="fas fa-exclamation-triangle"></i>
                    <?php endif; ?>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <!-- Debug info removed for production -->
        
        <div class="priority-info">
            <div class="priority-title">
                <i class="fas fa-info-circle"></i>
                Blood Request Priority Guidelines
            </div>
            <ul class="priority-list">
                <li><i class="fas fa-exclamation-triangle"></i><strong>Critical:</strong> Life-threatening emergency (immediate need)</li>
                <li><i class="fas fa-exclamation-circle"></i><strong>High:</strong> Urgent surgery or serious condition (within 24-48 hours)</li>
                <li><i class="fas fa-circle"></i><strong>Medium:</strong> Scheduled surgery or treatment (within 3-7 days)</li>
                <li><i class="fas fa-circle"></i><strong>Low:</strong> Non-urgent medical needs (within 1-2 weeks)</li>
            </ul>
        </div>
        
        <form method="POST" class="request-form" id="requestForm">
            <div class="form-group">
                <fieldset>
                    <legend class="form-label">
                        <i class="fas fa-tint"></i>
                        Blood Type Needed <span class="required">*</span>
                    </legend>
                    <div class="blood-type-grid">
                        <?php 
                        $bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                        foreach ($bloodTypes as $type): 
                        ?>
                            <div class="blood-type-option">
                                <input type="radio" id="blood_<?php echo str_replace(['+', '-'], ['pos', 'neg'], $type); ?>" name="blood_type_needed" value="<?php echo $type; ?>" required>
                                <label for="blood_<?php echo str_replace(['+', '-'], ['pos', 'neg'], $type); ?>" class="blood-type-label"><?php echo $type; ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            </div>
            
            <div class="form-group">
                <fieldset>
                    <legend class="form-label">
                        <i class="fas fa-exclamation-triangle"></i>
                        Urgency Level <span class="required">*</span>
                    </legend>
                    <div class="urgency-grid">
                        <div class="urgency-option">
                            <input type="radio" id="urgency_critical" name="urgency_level" value="Critical" required>
                            <label for="urgency_critical" class="urgency-label critical">
                                <i class="fas fa-ambulance urgency-icon"></i>
                                <span class="urgency-text">Critical<br><small>Emergency</small></span>
                            </label>
                        </div>
                        <div class="urgency-option">
                            <input type="radio" id="urgency_high" name="urgency_level" value="High" required>
                            <label for="urgency_high" class="urgency-label high">
                                <i class="fas fa-exclamation-triangle urgency-icon"></i>
                                <span class="urgency-text">High<br><small>Urgent</small></span>
                            </label>
                        </div>
                        <div class="urgency-option">
                            <input type="radio" id="urgency_medium" name="urgency_level" value="Medium" required>
                            <label for="urgency_medium" class="urgency-label medium">
                                <i class="fas fa-clock urgency-icon"></i>
                                <span class="urgency-text">Medium<br><small>Soon</small></span>
                            </label>
                        </div>
                        <div class="urgency-option">
                            <input type="radio" id="urgency_low" name="urgency_level" value="Low" required>
                            <label for="urgency_low" class="urgency-label low">
                                <i class="fas fa-calendar urgency-icon"></i>
                                <span class="urgency-text">Low<br><small>Planned</small></span>
                            </label>
                        </div>
                    </div>
                </fieldset>
            </div>
            
            <div class="form-triple">
                <div class="form-group">
                    <label for="units_needed" class="form-label">
                        <i class="fas fa-flask"></i>
                        Units Needed <span class="required">*</span>
                    </label>
                    <select id="units_needed" name="units_needed" class="form-select" required>
                        <option value="">Select units...</option>
                        <?php for ($i = 1; $i <= 10; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $i == 1 ? 'selected' : ''; ?>><?php echo $i; ?> unit<?php echo $i > 1 ? 's' : ''; ?></option>
                        <?php endfor; ?>
                    </select>
                    <span class="form-help">Maximum 10 units per request</span>
                </div>
                
                <div class="form-group">
                    <label for="needed_by_date" class="form-label">
                        <i class="fas fa-calendar-alt"></i>
                        Needed By Date <span class="required">*</span>
                    </label>
                    <input type="date" id="needed_by_date" name="needed_by_date" class="form-input" min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" required>
                    <span class="form-help">When do you need the blood?</span>
                </div>
                
                <div class="form-group">
                    <label for="hospital_location" class="form-label">
                        <i class="fas fa-hospital"></i>
                        Hospital Location <span class="required">*</span>
                    </label>
                    <select id="hospital_location" name="hospital_location" class="form-select" required>
                        <option value="">Select a hospital...</option>
                        <option value="St. Mercy General Hospital - 123 Aurora Blvd, Quezon City, Metro Manila, Philippines">St. Mercy General Hospital - Quezon City</option>
                        <option value="MetroCare Medical Center - 456 West Avenue, Quezon City, Metro Manila, Philippines">MetroCare Medical Center - Quezon City</option>
                        <option value="Hopewell Community Hospital - 87 Mabini St, Calamba, Laguna, Philippines">Hopewell Community Hospital - Calamba, Laguna</option>
                        <option value="Sunrise Health Center - 99 J.P. Rizal Ave, Makati City, Metro Manila, Philippines">Sunrise Health Center - Makati City</option>
                        <option value="Nueva Vida Medical Institute - 210 Bonifacio Street, Davao City, Davao del Sur, Philippines">Nueva Vida Medical Institute - Davao City</option>
                        <option value="Unity Regional Hospital - 300 Lopez Jaena St, Iloilo City, Iloilo, Philippines">Unity Regional Hospital - Iloilo City</option>
                        <option value="Greenfields Medical Plaza - 45 Don Rufino Alonzo St, Baguio City, Benguet, Philippines">Greenfields Medical Plaza - Baguio City</option>
                        <option value="WellnessPoint Hospital - 678 Ortigas Ave, Pasig City, Metro Manila, Philippines">WellnessPoint Hospital - Pasig City</option>
                        <option value="Cedar Hill Medical Complex - 12 Gen. Luna St, San Fernando, Pampanga, Philippines">Cedar Hill Medical Complex - San Fernando, Pampanga</option>
                        <option value="Bayview General Medical Center - 81 Roxas Blvd, Parañaque City, Metro Manila, Philippines">Bayview General Medical Center - Parañaque City</option>
                    </select>
                    <span class="form-help">Where will you receive treatment?</span>
                </div>
            </div>
            
            <div class="form-group">
                <label for="medical_conditions" class="form-label">
                    <i class="fas fa-notes-medical"></i>
                    Medical Conditions & Diagnosis <span class="required">*</span>
                </label>
                <textarea id="medical_conditions" name="medical_conditions" class="form-textarea" placeholder="Please provide detailed information about your medical condition, diagnosis, planned procedure, or reason for blood transfusion. Include any relevant medical history that may affect blood compatibility..." required></textarea>
                <span class="form-help">Be specific about your condition for proper blood matching and priority assessment</span>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="doctor_contact" class="form-label">
                        <i class="fas fa-user-md"></i>
                        Doctor Contact Information
                    </label>
                    <input type="text" id="doctor_contact" name="doctor_contact" class="form-input" placeholder="Dr. Name, Phone, Hospital Department">
                    <span class="form-help">Your attending physician's contact details (optional)</span>
                </div>
                
                <div class="form-group">
                    <label for="emergency_contact" class="form-label">
                        <i class="fas fa-phone"></i>
                        Emergency Contact
                    </label>
                    <input type="text" id="emergency_contact" name="emergency_contact" class="form-input" placeholder="Contact name and phone number">
                    <span class="form-help">Emergency contact person (optional but recommended)</span>
                </div>
            </div>
            
            <button type="submit" name="submit_request" value="1" class="submit-btn">
                <i class="fas fa-hand-holding-medical"></i>
                Submit Blood Request
            </button>
        </form>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('requestForm');
    const submitBtn = document.querySelector('.submit-btn');
    
    console.log('Request form initialized:', form);
    
    // Form validation
    form.addEventListener('submit', function(e) {
        console.log('Form submit triggered');
        
        const bloodType = document.querySelector('input[name="blood_type_needed"]:checked');
        const urgencyLevel = document.querySelector('input[name="urgency_level"]:checked');
        const unitsNeeded = document.getElementById('units_needed').value;
        const neededByDate = document.getElementById('needed_by_date').value;
        const hospitalLocation = document.getElementById('hospital_location').value;
        const medicalConditions = document.getElementById('medical_conditions').value;
        
        console.log('Form values:', {
            bloodType: bloodType ? bloodType.value : 'none selected',
            urgencyLevel: urgencyLevel ? urgencyLevel.value : 'none selected',
            unitsNeeded: unitsNeeded,
            neededByDate: neededByDate,
            hospitalLocation: hospitalLocation,
            medicalConditions: medicalConditions ? medicalConditions.substring(0, 50) + '...' : 'empty'
        });
        
        let errors = [];
        
        if (!bloodType) {
            errors.push('Please select the blood type needed.');
        }
        
        if (!urgencyLevel) {
            errors.push('Please select the urgency level.');
        }
        
        if (!unitsNeeded || parseInt(unitsNeeded) < 1 || parseInt(unitsNeeded) > 10) {
            errors.push('Please select valid number of units (1-10).');
        }
        
        if (!neededByDate) {
            errors.push('Please select when you need the blood.');
        } else {
            const selectedDate = new Date(neededByDate);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (selectedDate < today) {
                errors.push('Needed by date must be today or in the future.');
            }
        }
        
        if (!hospitalLocation) {
            errors.push('Please select a hospital location.');
        }
        
        if (!medicalConditions.trim()) {
            errors.push('Please provide your medical conditions and diagnosis.');
        }
        
        console.log('Validation errors:', errors);
        
        if (errors.length > 0) {
            e.preventDefault();
            
            // Show errors in a more user-friendly way
            const errorContainer = document.createElement('div');
            errorContainer.className = 'message error';
            errorContainer.innerHTML = '<i class="fas fa-exclamation-triangle"></i>' + errors.join('<br>');
            
            // Remove any existing error messages
            const existingError = document.querySelector('.message.error');
            if (existingError && !existingError.innerHTML.includes('Debug Information')) {
                existingError.remove();
            }
            
            // Insert error message at the top of the form
            const formContainer = document.querySelector('.request-form');
            formContainer.insertBefore(errorContainer, formContainer.firstChild);
            
            // Scroll to error message
            errorContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            // Focus on the first problematic field
            if (!bloodType) {
                const firstRadio = document.querySelector('input[name="blood_type_needed"]');
                if (firstRadio) {
                    firstRadio.focus();
                }
            } else if (!urgencyLevel) {
                const firstUrgency = document.querySelector('input[name="urgency_level"]');
                if (firstUrgency) {
                    firstUrgency.focus();
                }
            } else if (!unitsNeeded) {
                document.getElementById('units_needed').focus();
            } else if (!neededByDate) {
                document.getElementById('needed_by_date').focus();
            } else if (!hospitalLocation) {
                document.getElementById('hospital_location').focus();
            } else if (!medicalConditions.trim()) {
                document.getElementById('medical_conditions').focus();
            }
            
            return false;
        }
        
        // Remove any error messages on successful validation
        const existingError = document.querySelector('.message.error');
        if (existingError && !existingError.innerHTML.includes('Debug Information')) {
            existingError.remove();
        }
        
        console.log('Form validation passed, submitting...');
        
        // Add loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing Request...';
        
        // Let the form submit naturally
        return true;
    });
    
    // Date validation
    const dateInput = document.getElementById('needed_by_date');
    dateInput.addEventListener('change', function() {
        const selectedDate = new Date(this.value);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        if (selectedDate < today) {
            this.style.borderColor = '#dc3545';
            this.style.boxShadow = '0 0 0 3px rgba(220, 53, 69, 0.1)';
        } else {
            this.style.borderColor = 'rgba(220, 53, 69, 0.1)';
            this.style.boxShadow = 'none';
        }
    });
    
    // Medical conditions character feedback
    const medicalTextarea = document.getElementById('medical_conditions');
    medicalTextarea.addEventListener('input', function() {
        const length = this.value.length;
        if (length < 50) {
            this.style.borderColor = '#ffc107';
        } else {
            this.style.borderColor = 'rgba(220, 53, 69, 0.1)';
        }
    });
    
    // Blood type selection feedback
    const bloodTypeRadios = document.querySelectorAll('input[name="blood_type_needed"]');
    bloodTypeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            console.log('Blood type selected:', this.value);
        });
    });
    
    // Urgency level selection feedback
    const urgencyRadios = document.querySelectorAll('input[name="urgency_level"]');
    urgencyRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            console.log('Urgency level selected:', this.value);
        });
    });
    
    // Hospital selection feedback
    const hospitalSelect = document.getElementById('hospital_location');
    hospitalSelect.addEventListener('change', function() {
        console.log('Hospital selected:', this.value);
    });
});
</script>

</body>
<?php 
// Don't try to close database connection - let it close naturally
include('includes/footer.php'); 
?>
</html>