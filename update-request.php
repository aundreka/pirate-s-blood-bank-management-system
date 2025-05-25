<?php 
include('includes/db.php');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Initialize variables
$message = '';
$messageType = '';
$request = null;

// Get request ID from URL
$request_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$request_id) {
    header('Location: requests.php');
    exit();
}

// Get request details with proper error handling
try {
    $requestQuery = "SELECT * FROM recipient_form WHERE request_id = ? AND user_id = ? AND request_status = 'Pending'";
    $requestStmt = $conn->prepare($requestQuery);
    
    if (!$requestStmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    $requestStmt->bind_param("ii", $request_id, $_SESSION['user_id']);
    $requestStmt->execute();
    $requestResult = $requestStmt->get_result();
    
    if ($requestResult->num_rows === 0) {
        error_log("No pending request found for user {$_SESSION['user_id']} with ID {$request_id}");
        header('Location: requests.php?error=request_not_found');
        exit();
    }
    
    $request = $requestResult->fetch_assoc();
    $requestStmt->close();
    
} catch (Exception $e) {
    error_log("Error retrieving request: " . $e->getMessage());
    $message = "Error retrieving request details. Please try again.";
    $messageType = "error";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_request']) && $request) {
    
    // Sanitize and validate input
    $blood_type_needed = isset($_POST['blood_type_needed']) ? trim($_POST['blood_type_needed']) : '';
    $medical_conditions = isset($_POST['medical_conditions']) ? trim($_POST['medical_conditions']) : '';
    $urgency_level = isset($_POST['urgency_level']) ? trim($_POST['urgency_level']) : '';
    $hospital_location = isset($_POST['hospital_location']) ? trim($_POST['hospital_location']) : '';
    $units_needed = isset($_POST['units_needed']) ? intval($_POST['units_needed']) : 1;
    
    // Validation
    $errors = [];
    
    // Validate blood type
    $validBloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    if (empty($blood_type_needed) || !in_array($blood_type_needed, $validBloodTypes)) {
        $errors[] = "Please select a valid blood type.";
    }
    
    // Validate medical conditions
    if (empty($medical_conditions)) {
        $errors[] = "Medical conditions description is required.";
    } elseif (strlen($medical_conditions) < 10) {
        $errors[] = "Please provide more detailed medical conditions (minimum 10 characters).";
    }
    
    // Validate urgency level
    $validUrgencyLevels = ['Low', 'Medium', 'High'];
    if (empty($urgency_level) || !in_array($urgency_level, $validUrgencyLevels)) {
        $errors[] = "Please select a valid urgency level.";
    }
    
    // Validate hospital location
    if (empty($hospital_location)) {
        $errors[] = "Please select a hospital location.";
    }
    
    // Validate units needed
    if ($units_needed < 1 || $units_needed > 10) {
        $errors[] = "Units needed must be between 1 and 10.";
    }
    
    // If no errors, proceed with update
    if (empty($errors)) {
        try {
            // Begin transaction
            $conn->autocommit(false);
            
            // Update request
            $updateQuery = "UPDATE recipient_form SET 
                           blood_type_needed = ?, 
                           medical_conditions = ?, 
                           urgency_level = ?, 
                           hospital_location = ?, 
                           units_needed = ?,
                           updated_at = CURRENT_TIMESTAMP
                           WHERE request_id = ? AND user_id = ? AND request_status = 'Pending'";
            
            $updateStmt = $conn->prepare($updateQuery);
            
            if (!$updateStmt) {
                throw new Exception("Database prepare error: " . $conn->error);
            }
            
            $updateStmt->bind_param("ssssiii", 
                $blood_type_needed, 
                $medical_conditions, 
                $urgency_level, 
                $hospital_location, 
                $units_needed,
                $request_id, 
                $_SESSION['user_id']
            );
            
            if (!$updateStmt->execute()) {
                throw new Exception("Update execution error: " . $updateStmt->error);
            }
            
            $affected_rows = $updateStmt->affected_rows;
            
            if ($affected_rows > 0) {
                // Log the update for audit purposes
                $logQuery = "INSERT INTO blood_log (blood_type, units_donated, donor_id, operation_type, notes, timestamp_update) 
                            VALUES (?, 0, ?, 'ADJUSTMENT', ?, NOW())";
                $logStmt = $conn->prepare($logQuery);
                if ($logStmt) {
                    $note = "Blood request updated by user - Request ID: {$request_id}";
                    if ($blood_type_needed !== $request['blood_type_needed']) {
                        $note .= " - Blood type changed from {$request['blood_type_needed']} to {$blood_type_needed}";
                    }
                    $logStmt->bind_param("sis", $blood_type_needed, $_SESSION['user_id'], $note);
                    $logStmt->execute();
                    $logStmt->close();
                }
                
                // Commit transaction
                $conn->commit();
                $conn->autocommit(true);
                
                $message = "Your blood request has been updated successfully! Your changes are pending admin approval.";
                $messageType = "success";
                
                // Refresh request data
                $request['blood_type_needed'] = $blood_type_needed;
                $request['medical_conditions'] = $medical_conditions;
                $request['urgency_level'] = $urgency_level;
                $request['hospital_location'] = $hospital_location;
                $request['units_needed'] = $units_needed;
                
                error_log("Request updated successfully for user {$_SESSION['user_id']}, request ID {$request_id}");
                
            } else {
                throw new Exception("No rows were updated. The request may have been modified or is no longer pending.");
            }
            
            $updateStmt->close();
            
        } catch (Exception $e) {
            // Rollback transaction
            $conn->rollback();
            $conn->autocommit(true);
            
            error_log("Update error for request {$request_id}: " . $e->getMessage());
            $message = "An error occurred while updating your request. Please try again.";
            $messageType = "error";
        }
    } else {
        $message = implode('<br>', $errors);
        $messageType = "error";
    }
}

// Generate request ticket
function generateRequestTicket($requestData) {
    $created_date = isset($requestData['created_at']) ? $requestData['created_at'] : date('Y-m-d H:i:s');
    return 'PBR-' . str_pad($requestData['request_id'], 4, '0', STR_PAD_LEFT) . '-' . 
           strtoupper($requestData['blood_type_needed']) . '-' . 
           date('Ymd', strtotime($created_date));
}

// Get user information
try {
    $userQuery = "SELECT username FROM login WHERE user_id = ?";
    $userStmt = $conn->prepare($userQuery);
    $userStmt->bind_param("i", $_SESSION['user_id']);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    $userData = $userResult->fetch_assoc();
    $username = $userData ? $userData['username'] : 'Unknown User';
    $userStmt->close();
} catch (Exception $e) {
    error_log("Error getting user data: " . $e->getMessage());
    $username = 'User';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pirate's Blood Bank - Update Blood Request</title>
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
        
        .update-container {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            box-shadow: 2px 0 15px rgba(220, 220, 220, 0.1);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: none;
            margin: 0;
            box-sizing: border-box;
        }
        
        .update-header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 25px;
        }
        
        .update-title {
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
        
        .update-title i {
            color: #17a2b8;
            font-size: 28px;
        }
        
        .update-subtitle {
            color: #6c757d;
            font-size: 16px;
            font-weight: 500;
        }
        
        .request-info {
            background: linear-gradient(90deg, rgba(23, 162, 184, 0.1), rgba(23, 162, 184, 0.05));
            color: #495057;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            border-left: 4px solid #17a2b8;
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 20px;
            align-items: center;
        }
        
        .request-details {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .ticket-number {
            font-size: 18px;
            font-weight: 700;
            color: #17a2b8;
            font-family: 'Courier New', monospace;
            letter-spacing: 1px;
        }
        
        .request-meta {
            font-size: 14px;
            color: #6c757d;
        }
        
        .status-badge {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: white;
            padding: 10px 20px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
        }
        
        .update-form {
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
            color: #17a2b8;
            font-size: 16px;
        }
        
        .required {
            color: #dc3545;
        }
        
        .form-input,
        .form-select,
        .form-textarea {
            padding: 15px 20px;
            border: 2px solid rgba(23, 162, 184, 0.1);
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
            border-color: #17a2b8;
            box-shadow: 0 0 0 3px rgba(23, 162, 184, 0.1);
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
            color: #17a2b8;
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
            outline: 2px solid #17a2b8;
            outline-offset: 2px;
        }
        
        .blood-type-option input[type="radio"]:checked + .blood-type-label {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
            border-color: #17a2b8;
            transform: scale(1.05);
        }
        
        .blood-type-label {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px;
            border: 2px solid rgba(23, 162, 184, 0.2);
            border-radius: 10px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            color: #495057;
        }
        
        .blood-type-label:hover {
            border-color: #17a2b8;
            transform: translateY(-2px);
        }
        
        .urgency-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
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
            align-items: center;
            justify-content: center;
            padding: 12px;
            border: 2px solid rgba(23, 162, 184, 0.2);
            border-radius: 10px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            color: #495057;
            text-align: center;
        }
        
        .urgency-option input[type="radio"]:checked + .urgency-label {
            color: white;
            border-color: transparent;
            transform: scale(1.05);
        }
        
        .urgency-option input[type="radio"]:checked + .urgency-label.low {
            background: linear-gradient(135deg, #28a745, #1e7e34);
        }
        
        .urgency-option input[type="radio"]:checked + .urgency-label.medium {
            background: linear-gradient(135deg, #ffc107, #e0a800);
        }
        
        .urgency-option input[type="radio"]:checked + .urgency-label.high {
            background: linear-gradient(135deg, #dc3545, #b02a37);
        }
        
        .urgency-label:hover {
            border-color: #17a2b8;
            transform: translateY(-2px);
        }
        
        .action-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-top: 30px;
            flex-wrap: wrap;
                                    margin-bottom: 80px;

        }
        
        .action-btn {
            padding: 18px 40px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 16px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
            cursor: pointer;
        }
        
        .btn-update {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
        }
        
        .btn-update:hover:not(.btn-loading) {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 8px 25px rgba(23, 162, 184, 0.3);
        }
        
        .btn-cancel {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
        }
        
        .btn-cancel:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 8px 25px rgba(108, 117, 125, 0.3);
            color: white;
            text-decoration: none;
        }
        
        .message {
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
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
        
        /* Form validation styles */
        .form-input.error,
        .form-select.error,
        .form-textarea.error {
            border-color: #dc3545;
            background-color: rgba(220, 53, 69, 0.05);
        }
        
        .form-group.error .form-label {
            color: #dc3545;
        }
        
        /* Loading state for button */
        .btn-loading {
            opacity: 0.7;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .btn-loading i {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            main {
                margin-left: 0;
                padding: 15px;
                width: 100vw;
            }
            
            .update-container {
                padding: 25px;
                border-radius: 15px;
            }
            
            .update-title {
                font-size: 24px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .blood-type-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .urgency-grid {
                grid-template-columns: 1fr;
            }
            
            .request-info {
                grid-template-columns: 1fr;
                text-align: center;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .action-btn {
                width: 100%;
                max-width: 300px;
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            .update-container {
                padding: 20px;
                border-radius: 10px;
            }
            
            .blood-type-grid,
            .urgency-grid {
                grid-template-columns: 1fr;
            }
            
            .update-title {
                font-size: 20px;
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
    <div class="update-container">
        <div class="update-header">
            <h1 class="update-title">
                <i class="fas fa-edit"></i>
                Update Blood Request
            </h1>
            <p class="update-subtitle">Modify your pending blood request details</p>
        </div>
        
        <?php if ($request): ?>
            <div class="request-info">
                <div class="request-details">
                    <div class="ticket-number"><?php echo generateRequestTicket($request); ?></div>
                    <div class="request-meta">
                        Submitted: <?php echo isset($request['created_at']) ? date('M j, Y g:i A', strtotime($request['created_at'])) : 'Unknown'; ?> | 
                        Urgency: <?php echo htmlspecialchars($request['urgency_level']); ?>
                    </div>
                </div>
                <div class="status-badge">Pending</div>
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
            
            <form method="POST" class="update-form" id="updateForm" novalidate>
                <input type="hidden" name="update_request" value="1">
                
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
                                    <input type="radio" 
                                           id="blood_<?php echo str_replace(['+', '-'], ['pos', 'neg'], $type); ?>" 
                                           name="blood_type_needed" 
                                           value="<?php echo htmlspecialchars($type); ?>" 
                                           <?php echo ($request['blood_type_needed'] === $type) ? 'checked' : ''; ?> 
                                           required>
                                    <label for="blood_<?php echo str_replace(['+', '-'], ['pos', 'neg'], $type); ?>" 
                                           class="blood-type-label"><?php echo htmlspecialchars($type); ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="units_needed" class="form-label">
                            <i class="fas fa-prescription-bottle"></i>
                            Units Needed <span class="required">*</span>
                        </label>
                        <input type="number" 
                               id="units_needed" 
                               name="units_needed" 
                               class="form-input" 
                               min="1" 
                               max="10"
                               value="<?php echo htmlspecialchars($request['units_needed']); ?>" 
                               required>
                        <span class="form-help">Number of blood units required (1-10)</span>
                    </div>
                    
                    <div class="form-group">
                        <fieldset>
                            <legend class="form-label">
                                <i class="fas fa-exclamation-triangle"></i>
                                Urgency Level <span class="required">*</span>
                            </legend>
                            <div class="urgency-grid">
                                <?php 
                                $urgencyLevels = ['Low' => 'low', 'Medium' => 'medium', 'High' => 'high'];
                                foreach ($urgencyLevels as $level => $class): 
                                ?>
                                    <div class="urgency-option">
                                        <input type="radio" 
                                               id="urgency_<?php echo strtolower($level); ?>" 
                                               name="urgency_level" 
                                               value="<?php echo $level; ?>" 
                                               <?php echo ($request['urgency_level'] === $level) ? 'checked' : ''; ?> 
                                               required>
                                        <label for="urgency_<?php echo strtolower($level); ?>" 
                                               class="urgency-label <?php echo $class; ?>"><?php echo $level; ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="hospital_location" class="form-label">
                        <i class="fas fa-hospital"></i>
                        Hospital Location <span class="required">*</span>
                    </label>
                    <select id="hospital_location" name="hospital_location" class="form-select" required>
                        <option value="">Select a hospital...</option>
                        <?php 
                        $hospitals = [
                            "St. Mercy General Hospital - 123 Aurora Blvd, Quezon City, Metro Manila, Philippines" => "St. Mercy General Hospital - Quezon City",
                            "MetroCare Medical Center - 456 West Avenue, Quezon City, Metro Manila, Philippines" => "MetroCare Medical Center - Quezon City",
                            "Hopewell Community Hospital - 87 Mabini St, Calamba, Laguna, Philippines" => "Hopewell Community Hospital - Calamba, Laguna",
                            "Sunrise Health Center - 99 J.P. Rizal Ave, Makati City, Metro Manila, Philippines" => "Sunrise Health Center - Makati City",
                            "Nueva Vida Medical Institute - 210 Bonifacio Street, Davao City, Davao del Sur, Philippines" => "Nueva Vida Medical Institute - Davao City",
                            "Unity Regional Hospital - 300 Lopez Jaena St, Iloilo City, Iloilo, Philippines" => "Unity Regional Hospital - Iloilo City",
                            "Greenfields Medical Plaza - 45 Don Rufino Alonzo St, Baguio City, Benguet, Philippines" => "Greenfields Medical Plaza - Baguio City",
                            "WellnessPoint Hospital - 678 Ortigas Ave, Pasig City, Metro Manila, Philippines" => "WellnessPoint Hospital - Pasig City",
                            "Cedar Hill Medical Complex - 12 Gen. Luna St, San Fernando, Pampanga, Philippines" => "Cedar Hill Medical Complex - San Fernando, Pampanga",
                            "Bayview General Medical Center - 81 Roxas Blvd, Parañaque City, Metro Manila, Philippines" => "Bayview General Medical Center - Parañaque City"
                        ];
                        
                        foreach ($hospitals as $value => $display):
                        ?>
                            <option value="<?php echo htmlspecialchars($value); ?>" 
                                    <?php echo ($request['hospital_location'] === $value) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($display); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-help">Choose where you need to receive the blood transfusion</span>
                </div>
                
                <div class="form-group">
                    <label for="medical_conditions" class="form-label">
                        <i class="fas fa-notes-medical"></i>
                        Medical Conditions <span class="required">*</span>
                    </label>
                    <textarea id="medical_conditions" 
                              name="medical_conditions" 
                              class="form-textarea" 
                              placeholder="Please provide detailed information about your medical condition requiring blood transfusion, current treatments, medications, and any other relevant medical information..."
                              required><?php echo htmlspecialchars($request['medical_conditions']); ?></textarea>
                    <span class="form-help">Please provide detailed information about your condition and why blood transfusion is needed (minimum 10 characters)</span>
                </div>
                
                <div class="action-buttons">
                    <button type="submit" name="submit_update" class="action-btn btn-update" id="submitBtn">
                        <i class="fas fa-save"></i>
                        Update Request
                    </button>
                    <a href="appointments.php" class="action-btn btn-cancel">
                        <i class="fas fa-times"></i>
                        Cancel
                    </a>
                </div>
            </form>
        <?php else: ?>
            <div class="message error">
                <i class="fas fa-exclamation-triangle"></i>
                Request not found or cannot be updated. This may happen if the request doesn't exist, 
                belongs to another user, or is no longer in pending status.
            </div>
            <div class="action-buttons">
                <a href="appointments.php" class="action-btn btn-cancel">
                    <i class="fas fa-arrow-left"></i>
                    Back to Requests
                </a>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('updateForm');
    const submitBtn = document.getElementById('submitBtn');
    
    if (!form || !submitBtn) {
        console.error('Form or submit button not found');
        return;
    }
    
    // Form validation
    function validateForm() {
        let isValid = true;
        const errors = [];
        
        // Clear previous error states
        document.querySelectorAll('.form-input, .form-select, .form-textarea').forEach(input => {
            input.classList.remove('error');
            input.parentElement.classList.remove('error');
        });
        
        // Validate blood type
        const bloodType = document.querySelector('input[name="blood_type_needed"]:checked');
        if (!bloodType) {
            errors.push('Please select a blood type');
            isValid = false;
        }
        
        // Validate units needed
        const unitsNeeded = document.getElementById('units_needed');
        const unitsValue = parseInt(unitsNeeded.value);
        if (!unitsValue || unitsValue < 1 || unitsValue > 10) {
            errors.push('Units needed must be between 1 and 10');
            unitsNeeded.classList.add('error');
            unitsNeeded.parentElement.classList.add('error');
            isValid = false;
        }
        
        // Validate urgency level
        const urgencyLevel = document.querySelector('input[name="urgency_level"]:checked');
        if (!urgencyLevel) {
            errors.push('Please select an urgency level');
            isValid = false;
        }
        
        // Validate hospital location
        const hospital = document.getElementById('hospital_location');
        if (!hospital.value.trim()) {
            errors.push('Please select a hospital location');
            hospital.classList.add('error');
            hospital.parentElement.classList.add('error');
            isValid = false;
        }
        
        // Validate medical conditions
        const medicalConditions = document.getElementById('medical_conditions');
        if (!medicalConditions.value.trim() || medicalConditions.value.trim().length < 10) {
            errors.push('Medical conditions must be at least 10 characters long');
            medicalConditions.classList.add('error');
            medicalConditions.parentElement.classList.add('error');
            isValid = false;
        }
        
        return { isValid, errors };
    }
    
    // Real-time validation
    function setupRealTimeValidation() {
        // Units validation
        const unitsNeeded = document.getElementById('units_needed');
        unitsNeeded.addEventListener('input', function() {
            const value = parseInt(this.value);
            if (value >= 1 && value <= 10) {
                this.classList.remove('error');
                this.parentElement.classList.remove('error');
            }
        });
        
        // Medical conditions validation
        const medicalConditions = document.getElementById('medical_conditions');
        medicalConditions.addEventListener('input', function() {
            if (this.value.trim().length >= 10) {
                this.classList.remove('error');
                this.parentElement.classList.remove('error');
            }
        });
        
        // Hospital selection validation
        const hospital = document.getElementById('hospital_location');
        hospital.addEventListener('change', function() {
            if (this.value.trim()) {
                this.classList.remove('error');
                this.parentElement.classList.remove('error');
            }
        });
    }
    
    // Form submission handler
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const validation = validateForm();
        
        if (!validation.isValid) {
            // Show error message
            let existingMessage = document.querySelector('.message');
            if (existingMessage) {
                existingMessage.remove();
            }
            
            const errorMessage = document.createElement('div');
            errorMessage.className = 'message error';
            errorMessage.innerHTML = `
                <i class="fas fa-exclamation-triangle"></i>
                ${validation.errors.join('<br>')}
            `;
            
            form.parentNode.insertBefore(errorMessage, form);
            
            // Scroll to top to show error
            errorMessage.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            return false;
        }
        
        // Show loading state
        submitBtn.classList.add('btn-loading');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
        submitBtn.disabled = true;
        
        // Submit form
        this.submit();
    });
    
    // Initialize real-time validation
    setupRealTimeValidation();
    
    // Auto-remove success messages after 5 seconds
    const successMessage = document.querySelector('.message.success');
    if (successMessage) {
        setTimeout(() => {
            successMessage.style.opacity = '0';
            setTimeout(() => {
                successMessage.remove();
            }, 300);
        }, 5000);
    }
    
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
    
    console.log('Form validation initialized successfully');
});
</script>

</body>
<?php include('includes/footer.php'); ?>
</html>