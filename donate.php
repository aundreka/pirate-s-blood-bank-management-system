<?php 
include('includes/db.php');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['submit_donation']) || (isset($_POST['blood_type']) && isset($_POST['medical_history'])))) {
    error_log("=== DONATION FORM SUBMISSION STARTED ===");
    error_log("User ID: " . $_SESSION['user_id']);
    
    $donor_id = $_SESSION['user_id'];
    $blood_type = isset($_POST['blood_type']) ? trim($_POST['blood_type']) : '';
    $medical_history = isset($_POST['medical_history']) ? trim($_POST['medical_history']) : '';
    $weight = isset($_POST['weight']) ? floatval($_POST['weight']) : 0;
    $hospital_location = isset($_POST['hospital_location']) ? trim($_POST['hospital_location']) : '';
    $date_of_donation = isset($_POST['date_of_donation']) && $_POST['date_of_donation'] ? $_POST['date_of_donation'] : date('Y-m-d');
    
    error_log("Form data - Blood Type: $blood_type, Weight: $weight, Hospital: " . substr($hospital_location, 0, 30));
    
    // Validation
    $errors = [];
    
    // Validate blood type
    $validBloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    if (empty($blood_type) || !in_array($blood_type, $validBloodTypes)) {
        $errors[] = "Please select a valid blood type.";
    }
    
    // Validate medical history
    if (empty($medical_history)) {
        $errors[] = "Medical history is required.";
    } elseif (strlen($medical_history) < 20) {
        $errors[] = "Please provide more detailed medical history (minimum 20 characters).";
    }
    
    // Validate weight
    if ($weight < 50) {
        $errors[] = "Weight must be at least 50 kg to donate blood.";
    } elseif ($weight > 300) {
        $errors[] = "Please enter a valid weight.";
    }
    
    // Validate hospital location
    if (empty($hospital_location)) {
        $errors[] = "Please select a hospital location.";
    }
    
    // Validate donation date
    $today = date('Y-m-d');
    if ($date_of_donation < $today) {
        $errors[] = "Donation date cannot be in the past.";
    }
    
    // Check if user already has a pending donation
    if (empty($errors)) {
        try {
            $checkQuery = "SELECT COUNT(*) as pending_count FROM donate_blood WHERE donor_id = ? AND donation_status = 'Pending'";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param("i", $donor_id);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $row = $result->fetch_assoc();
            $checkStmt->close();
            
            if ($row['pending_count'] > 0) {
                $errors[] = "You already have a pending donation. Please wait for admin approval before submitting another donation.";
            }
        } catch (Exception $e) {
            error_log("Error checking pending donations: " . $e->getMessage());
            $errors[] = "Database error occurred. Please try again.";
        }
    }
    
    error_log("Validation errors: " . count($errors));
    
    if (empty($errors)) {
        try {
            error_log("Validation passed - creating donation record");
            
            // Begin transaction
            $conn->autocommit(false);
            
            // Insert donation record with PENDING status
            // DO NOT update blood_inventory or blood_log yet - admin approval required
            $insertDonation = "INSERT INTO donate_blood (donor_id, blood_type, medical_history, weight, hospital_location, date_of_donation, donation_status, created_at) 
                              VALUES (?, ?, ?, ?, ?, ?, 'Pending', NOW())";
            
            $stmt = $conn->prepare($insertDonation);
            
            if (!$stmt) {
                throw new Exception("Database prepare error: " . $conn->error);
            }
            
            $stmt->bind_param("issdss", $donor_id, $blood_type, $medical_history, $weight, $hospital_location, $date_of_donation);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to create donation record: " . $stmt->error);
            }
            
            // Get the inserted donation ID
            $donation_id = $conn->insert_id;
            error_log("Donation record created with ID: $donation_id");
            
            // Log the submission for audit purposes (but with 0 units since it's not approved yet)
            $logQuery = "INSERT INTO blood_log (donation_id, blood_type, units_donated, donor_id, operation_type, notes, timestamp_update) 
                        VALUES (?, ?, 0, ?, 'ADJUSTMENT', 'Donation submitted - pending admin approval', NOW())";
            $logStmt = $conn->prepare($logQuery);
            
            if ($logStmt) {
                $logStmt->bind_param("isi", $donation_id, $blood_type, $donor_id);
                $logStmt->execute();
                $logStmt->close();
                error_log("Audit log entry created");
            }
            
            // Commit transaction
            $conn->commit();
            $conn->autocommit(true);
            
            error_log("Donation submission completed successfully");
            
            // Store donation details in session for thank you page
            $_SESSION['donation_success'] = [
                'donation_id' => $donation_id,
                'blood_type' => $blood_type,
                'hospital_location' => $hospital_location,
                'donor_name' => $username ?? 'Donor',
                'date_submitted' => date('Y-m-d H:i:s'),
                'status' => 'Pending Admin Approval'
            ];
            
            $stmt->close();
            
            // Redirect to thank you page
            header('Location: thank-you.php');
            exit();
            
        } catch (Exception $e) {
            error_log("Database error during donation submission: " . $e->getMessage());
            
            // Rollback transaction on error
            if (isset($conn) && $conn) {
                $conn->rollback();
                $conn->autocommit(true);
            }
            
            $message = "An error occurred while processing your donation. Please try again.";
            $messageType = "error";
        }
    } else {
        error_log("Form validation failed: " . implode(', ', $errors));
        $message = implode('<br>', $errors);
        $messageType = "error";
    }
    
    error_log("=== DONATION FORM SUBMISSION ENDED ===");
}

// Get user information
try {
    $userQuery = "SELECT username FROM login WHERE user_id = ?";
    $userStmt = $conn->prepare($userQuery);
    $userStmt->bind_param("i", $_SESSION['user_id']);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    $userData = $userResult->fetch_assoc();
    $username = $userData ? $userData['username'] : 'User';
    $userStmt->close();
} catch (Exception $e) {
    error_log("Error getting user data: " . $e->getMessage());
    $username = 'User';
}

// Check if user has any pending donations
$hasPendingDonation = false;
$pendingDonationInfo = null;
try {
    $pendingQuery = "SELECT donation_id, blood_type, date_of_donation, created_at FROM donate_blood WHERE donor_id = ? AND donation_status = 'Pending' ORDER BY created_at DESC LIMIT 1";
    $pendingStmt = $conn->prepare($pendingQuery);
    $pendingStmt->bind_param("i", $_SESSION['user_id']);
    $pendingStmt->execute();
    $pendingResult = $pendingStmt->get_result();
    
    if ($pendingResult->num_rows > 0) {
        $hasPendingDonation = true;
        $pendingDonationInfo = $pendingResult->fetch_assoc();
    }
    $pendingStmt->close();
} catch (Exception $e) {
    error_log("Error checking pending donations: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pirate's Blood Bank - Donate</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cardo:ital,wght@0,400;0,700;1,400&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Quicksand:wght@300..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    
    <style>
        main {
            margin-left: 70px;
            min-height: 100vh;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            transition: margin-left 0.3s ease;
            padding: 20px;
            width: calc(100vw - 70px);
            box-sizing: border-box;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch; 
        }
        
        .donation-container {
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: none;
            margin: 0;
            box-sizing: border-box;
        }
        
        .donation-header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 25px;
        }
        
        .donation-title {
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
        
        .donation-title i {
            color: #dc3545;
            font-size: 28px;
        }
        
        .donation-subtitle {
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
        
        .pending-notice {
            background: linear-gradient(90deg, rgba(255, 193, 7, 0.1), rgba(255, 193, 7, 0.05));
            color: #856404;
            padding: 20px 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            border-left: 4px solid #ffc107;
            font-weight: 500;
        }
        
        .pending-notice h4 {
            margin: 0 0 10px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
        }
        
        .pending-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(255, 193, 7, 0.3);
        }
        
        .pending-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .pending-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            opacity: 0.8;
        }
        
        .pending-value {
            font-weight: 600;
            font-size: 16px;
        }
        
        .form-disabled {
            opacity: 0.6;
            pointer-events: none;
        }
        
        .donation-form {
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
        
        .eligibility-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .eligibility-title {
            font-weight: 600;
            color: #856404;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .eligibility-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .eligibility-list li {
            color: #856404;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .eligibility-list li i {
            color: #28a745;
            font-size: 12px;
        }
        
        .approval-notice {
            background: linear-gradient(90deg, rgba(23, 162, 184, 0.1), rgba(23, 162, 184, 0.05));
            color: #0c5460;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 25px;
            border-left: 4px solid #17a2b8;
            font-weight: 500;
        }
        
        .approval-notice h4 {
            margin: 0 0 10px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
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
        
        .submit-btn:hover:not(:disabled) {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.3);
        }
        
        .submit-btn:disabled {
            opacity: 0.5;
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
        
        .blood-type-label:hover {
            border-color: #dc3545;
            transform: translateY(-2px);
        }
        
@media (max-width: 768px) {
    main {
        margin-left: 0;
        padding: 15px;
        width: 100vw;
    }
    
    .donation-container {
        padding: 20px;
        border-radius: 15px;

    }
    
    .donation-title {
        font-size: 24px;
        flex-direction: column;
        gap: 8px;
    }
    
    .form-row {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .blood-type-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
                    max-width: 400px; /* Add max-width */

    }
    
    .blood-type-label {
        padding: 14px 8px;
        font-size: 14px;
    }
    
    .pending-details {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    
    .form-input, .form-select, .form-textarea {
        padding: 14px 16px;
        font-size: 15px;
                            max-width: 400px; /* Add max-width */

    }
    
    .submit-btn {
        padding: 16px 32px;
        font-size: 15px;
        width: 100%;
        max-width: 400px;
    }
}

@media (max-width: 480px) {
    main {
        padding: 10px;
        width: 100%;
    }
    
    .donation-container {
        padding: 15px;
        border-radius: 12px;
            max-width: 200px; /* Add max-width */

    }
    
    .donation-title {
        font-size: 22px;
    }
    
    .blood-type-grid {
        grid-template-columns: 1fr;
        gap: 10px;
    }
    
    .blood-type-label {
        padding: 16px;
        font-size: 16px;
        min-height: 50px;
    }
    
    .pending-details {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .form-input, .form-select, .form-textarea {
        padding: 12px 14px;
        font-size: 14px;
    }
    
    .form-label, legend.form-label {
        font-size: 13px;
    }
    
    .submit-btn {
        padding: 14px 20px;
        font-size: 14px;
        width: 100%;
        margin: 20px 0 60px 0;
    }
    
    .welcome-message, .pending-notice, .approval-notice {
        padding: 15px 18px;
        font-size: 14px;
    }
    
    .eligibility-info {
        padding: 15px;
    }
    
    .eligibility-list li {
        font-size: 13px;
    }
}

@media (max-width: 360px) {
    .donation-container {
        padding: 12px;
    }
    
    .donation-title {
        font-size: 20px;
    }
    
    .blood-type-label {
        padding: 12px;
        font-size: 15px;
    }
    
    .form-input, .form-select, .form-textarea {
        padding: 10px 12px;
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
    <div class="donation-container">
        <div class="donation-header">
            <h1 class="donation-title">
                <i class="fas fa-heart"></i>
                Blood Donation
            </h1>
            <p class="donation-subtitle">Make a difference - Save lives through blood donation</p>
        </div>
        
        <div class="welcome-message">
            <i class="fas fa-user"></i>
            Welcome, <strong><?php echo htmlspecialchars($username); ?></strong>! Thank you for choosing to donate blood and help save lives.
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
        
        <?php if ($hasPendingDonation): ?>
            <div class="pending-notice">
                <h4>
                    <i class="fas fa-clock"></i>
                    Pending Donation - Awaiting Admin Approval
                </h4>
                <p>You have a donation request that is currently pending approval from our medical staff. You can track the status of your donation or make changes in your appointments section.</p>
                
                <div class="pending-details">
                    <div class="pending-item">
                        <div class="pending-label">Donation ID</div>
                        <div class="pending-value">#<?php echo $pendingDonationInfo['donation_id']; ?></div>
                    </div>
                    <div class="pending-item">
                        <div class="pending-label">Blood Type</div>
                        <div class="pending-value"><?php echo htmlspecialchars($pendingDonationInfo['blood_type']); ?></div>
                    </div>
                    <div class="pending-item">
                        <div class="pending-label">Preferred Date</div>
                        <div class="pending-value"><?php echo date('M j, Y', strtotime($pendingDonationInfo['date_of_donation'])); ?></div>
                    </div>
                    <div class="pending-item">
                        <div class="pending-label">Submitted</div>
                        <div class="pending-value"><?php echo date('M j, Y g:i A', strtotime($pendingDonationInfo['created_at'])); ?></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!$hasPendingDonation): ?>
            <div class="approval-notice">
                <h4>
                    <i class="fas fa-info-circle"></i>
                    Important: Admin Approval Required
                </h4>
                <p><strong>Your donation will be reviewed by our medical staff before being approved.</strong> Once submitted, your donation request will be marked as "Pending" and will not appear in our blood inventory until it has been approved and completed by our admin team. This ensures the safety and quality of all blood donations.</p>
            </div>
            
            <div class="eligibility-info">
                <div class="eligibility-title">
                    <i class="fas fa-info-circle"></i>
                    Donation Eligibility Requirements
                </div>
                <ul class="eligibility-list">
                    <li><i class="fas fa-check"></i>Must be at least 18 years old</li>
                    <li><i class="fas fa-check"></i>Minimum weight of 50 kg (110 lbs)</li>
                    <li><i class="fas fa-check"></i>Good general health condition</li>
                    <li><i class="fas fa-check"></i>No recent illness or medication</li>
                    <li><i class="fas fa-check"></i>At least 8 weeks since last donation</li>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="donation-form <?php echo $hasPendingDonation ? 'form-disabled' : ''; ?>" id="donationForm">
            <div class="form-group">
                <fieldset>
                    <legend class="form-label">
                        <i class="fas fa-tint"></i>
                        Blood Type <span class="required">*</span>
                    </legend>
                    <div class="blood-type-grid">
                        <?php 
                        $bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                        foreach ($bloodTypes as $type): 
                        ?>
                            <div class="blood-type-option">
                                <input type="radio" 
                                       id="blood_<?php echo str_replace(['+', '-'], ['pos', 'neg'], $type); ?>" 
                                       name="blood_type" 
                                       value="<?php echo htmlspecialchars($type); ?>" 
                                       <?php echo $hasPendingDonation ? 'disabled' : ''; ?> 
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
                    <label for="weight" class="form-label">
                        <i class="fas fa-weight"></i>
                        Weight (kg) <span class="required">*</span>
                    </label>
                    <input type="number" 
                           id="weight" 
                           name="weight" 
                           class="form-input" 
                           min="50" 
                           max="300"
                           step="0.1" 
                           placeholder="Enter your weight in kg" 
                           <?php echo $hasPendingDonation ? 'disabled' : ''; ?> 
                           required>
                    <span class="form-help">Minimum weight requirement: 50 kg</span>
                </div>
                
                <div class="form-group">
                    <label for="date_of_donation" class="form-label">
                        <i class="fas fa-calendar-alt"></i>
                        Preferred Donation Date
                    </label>
                    <input type="date" 
                           id="date_of_donation" 
                           name="date_of_donation" 
                           class="form-input" 
                           min="<?php echo date('Y-m-d'); ?>"
                           value="<?php echo date('Y-m-d'); ?>"
                           <?php echo $hasPendingDonation ? 'disabled' : ''; ?>>
                    <span class="form-help">Select your preferred donation date</span>
                </div>
            </div>
            
            <div class="form-group">
                <label for="hospital_location" class="form-label">
                    <i class="fas fa-hospital"></i>
                    Preferred Hospital Location <span class="required">*</span>
                </label>
                <select id="hospital_location" 
                        name="hospital_location" 
                        class="form-select" 
                        <?php echo $hasPendingDonation ? 'disabled' : ''; ?> 
                        required>
                    <option value="">Select a hospital...</option>
                    <option value="St. Mercy General Hospital - 123 Aurora Blvd, Quezon City, Metro Manila, Philippines">St. Mercy General Hospital - 123 Aurora Blvd, Quezon City</option>
                    <option value="MetroCare Medical Center - 456 West Avenue, Quezon City, Metro Manila, Philippines">MetroCare Medical Center - 456 West Avenue, Quezon City</option>
                    <option value="Hopewell Community Hospital - 87 Mabini St, Calamba, Laguna, Philippines">Hopewell Community Hospital - 87 Mabini St, Calamba, Laguna</option>
                    <option value="Sunrise Health Center - 99 J.P. Rizal Ave, Makati City, Metro Manila, Philippines">Sunrise Health Center - 99 J.P. Rizal Ave, Makati City</option>
                    <option value="Nueva Vida Medical Institute - 210 Bonifacio Street, Davao City, Davao del Sur, Philippines">Nueva Vida Medical Institute - 210 Bonifacio Street, Davao City</option>
                    <option value="Unity Regional Hospital - 300 Lopez Jaena St, Iloilo City, Iloilo, Philippines">Unity Regional Hospital - 300 Lopez Jaena St, Iloilo City</option>
                    <option value="Greenfields Medical Plaza - 45 Don Rufino Alonzo St, Baguio City, Benguet, Philippines">Greenfields Medical Plaza - 45 Don Rufino Alonzo St, Baguio City</option>
                    <option value="WellnessPoint Hospital - 678 Ortigas Ave, Pasig City, Metro Manila, Philippines">WellnessPoint Hospital - 678 Ortigas Ave, Pasig City</option>
                    <option value="Cedar Hill Medical Complex - 12 Gen. Luna St, San Fernando, Pampanga, Philippines">Cedar Hill Medical Complex - 12 Gen. Luna St, San Fernando, Pampanga</option>
                    <option value="Bayview General Medical Center - 81 Roxas Blvd, Parañaque City, Metro Manila, Philippines">Bayview General Medical Center - 81 Roxas Blvd, Parañaque City</option>
                </select>
                <span class="form-help">Choose the hospital where you'd like to complete your donation</span>
            </div>
            
            <div class="form-group">
                <label for="medical_history" class="form-label">
                    <i class="fas fa-notes-medical"></i>
                    Medical History <span class="required">*</span>
                </label>
                <textarea id="medical_history" 
                          name="medical_history" 
                          class="form-textarea" 
                          placeholder="Please provide detailed information about your current health status, any medications you're taking, recent illnesses, surgeries, or any other relevant medical information..."
                          <?php echo $hasPendingDonation ? 'disabled' : ''; ?> 
                          required></textarea>
                <span class="form-help">Please be thorough and honest about your medical history for safety reasons (minimum 20 characters)</span>
            </div>
            
            <button type="submit" 
                    name="submit_donation" 
                    value="1" 
                    class="submit-btn"
                    <?php echo $hasPendingDonation ? 'disabled' : ''; ?>>
                <i class="fas fa-heart"></i>
                <?php echo $hasPendingDonation ? 'Donation Pending' : 'Submit Donation'; ?>
            </button>
            
            <?php if ($hasPendingDonation): ?>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="appointments.php" style="color: #dc3545; text-decoration: none; font-weight: 600;">
                        <i class="fas fa-calendar-alt"></i> View My Appointments
                    </a>
                </div>
            <?php endif; ?>
        </form>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('donationForm');
    const submitBtn = document.querySelector('.submit-btn');
    const hasPendingDonation = <?php echo $hasPendingDonation ? 'true' : 'false'; ?>;
    
    console.log('Form initialized. Has pending donation:', hasPendingDonation);
    
    // If user has pending donation, disable the form completely
    if (hasPendingDonation) {
        console.log('Form disabled due to pending donation');
        return;
    }
    
    // Form validation for active forms only
    if (form && submitBtn && !hasPendingDonation) {
        form.addEventListener('submit', function(e) {
            console.log('Form submit triggered');
            
            const weight = document.getElementById('weight').value;
            const medicalHistory = document.getElementById('medical_history').value;
            const bloodType = document.querySelector('input[name="blood_type"]:checked');
            const hospitalLocation = document.getElementById('hospital_location').value;
            
            console.log('Form values:', {
                weight: weight,
                medicalHistory: medicalHistory ? medicalHistory.substring(0, 50) + '...' : 'empty',
                bloodType: bloodType ? bloodType.value : 'none selected',
                hospitalLocation: hospitalLocation ? hospitalLocation.substring(0, 30) + '...' : 'empty'
            });
            
            let errors = [];
            
            // Validate blood type
            if (!bloodType) {
                errors.push('Please select your blood type.');
            }
            
            // Validate weight
            if (!weight || parseFloat(weight) < 50) {
                errors.push('Weight must be at least 50 kg to donate blood.');
            } else if (parseFloat(weight) > 300) {
                errors.push('Please enter a valid weight (maximum 300 kg).');
            }
            
            // Validate medical history
            if (!medicalHistory.trim()) {
                errors.push('Please provide your medical history.');
            } else if (medicalHistory.trim().length < 20) {
                errors.push('Please provide more detailed medical history (minimum 20 characters).');
            }
            
            // Validate hospital location
            if (!hospitalLocation) {
                errors.push('Please select a hospital location.');
            }
            
            // Validate donation date
            const donationDate = document.getElementById('date_of_donation').value;
            const today = new Date().toISOString().split('T')[0];
            if (donationDate && donationDate < today) {
                errors.push('Donation date cannot be in the past.');
            }
            
            console.log('Validation errors:', errors);
            
            if (errors.length > 0) {
                e.preventDefault();
                
                // Show errors in a user-friendly way
                const errorContainer = document.createElement('div');
                errorContainer.className = 'message error';
                errorContainer.innerHTML = '<i class="fas fa-exclamation-triangle"></i>' + errors.join('<br>');
                
                // Remove any existing error messages
                const existingError = document.querySelector('.message.error');
                if (existingError) {
                    existingError.remove();
                }
                
                // Insert error message before the form
                const formContainer = document.querySelector('.donation-form');
                formContainer.parentNode.insertBefore(errorContainer, formContainer);
                
                // Scroll to error message
                errorContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // Focus on the first problematic field
                if (!bloodType) {
                    const firstRadio = document.querySelector('input[name="blood_type"]');
                    if (firstRadio) {
                        firstRadio.focus();
                    }
                } else if (!weight || parseFloat(weight) < 50 || parseFloat(weight) > 300) {
                    document.getElementById('weight').focus();
                } else if (!medicalHistory.trim() || medicalHistory.trim().length < 20) {
                    document.getElementById('medical_history').focus();
                } else if (!hospitalLocation) {
                    document.getElementById('hospital_location').focus();
                }
                
                return false;
            }
            
            // Remove any error messages on successful validation
            const existingError = document.querySelector('.message.error');
            if (existingError) {
                existingError.remove();
            }
            
            console.log('Form validation passed, submitting...');
            
            // Add loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            
            // Show confirmation message
            const confirmationMessage = document.createElement('div');
            confirmationMessage.className = 'message';
            confirmationMessage.style.background = 'linear-gradient(90deg, rgba(23, 162, 184, 0.1), rgba(23, 162, 184, 0.05))';
            confirmationMessage.style.color = '#0c5460';
            confirmationMessage.style.border = '1px solid rgba(23, 162, 184, 0.2)';
            confirmationMessage.innerHTML = '<i class="fas fa-info-circle"></i>Your donation is being processed. You will be redirected to a confirmation page shortly.';
            
            const formContainer = document.querySelector('.donation-form');
            formContainer.parentNode.insertBefore(confirmationMessage, formContainer);
            
            // Let the form submit naturally
            return true;
        });
        
        // Real-time validation feedback
        setupRealTimeValidation();
    }
    
    function setupRealTimeValidation() {
        // Weight validation
        const weightInput = document.getElementById('weight');
        if (weightInput) {
            weightInput.addEventListener('input', function() {
                const value = parseFloat(this.value);
                if (value >= 50 && value <= 300) {
                    this.style.borderColor = 'rgba(220, 53, 69, 0.1)';
                    this.style.boxShadow = 'none';
                } else if (value > 0) {
                    this.style.borderColor = '#dc3545';
                    this.style.boxShadow = '0 0 0 3px rgba(220, 53, 69, 0.1)';
                }
            });
        }
        
        // Medical history validation
        const medicalTextarea = document.getElementById('medical_history');
        if (medicalTextarea) {
            medicalTextarea.addEventListener('input', function() {
                const length = this.value.trim().length;
                if (length >= 20) {
                    this.style.borderColor = 'rgba(220, 53, 69, 0.1)';
                    this.style.boxShadow = 'none';
                } else if (length > 0) {
                    this.style.borderColor = '#ffc107';
                    this.style.boxShadow = '0 0 0 3px rgba(255, 193, 7, 0.1)';
                }
            });
        }
        
        // Blood type selection feedback
        const bloodTypeRadios = document.querySelectorAll('input[name="blood_type"]');
        bloodTypeRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                console.log('Blood type selected:', this.value);
            });
        });
        
        // Hospital selection feedback
        const hospitalSelect = document.getElementById('hospital_location');
        if (hospitalSelect) {
            hospitalSelect.addEventListener('change', function() {
                console.log('Hospital selected:', this.value ? this.value.substring(0, 30) + '...' : 'none');
            });
        }
    }
    
    // Auto-remove success messages after 5 seconds
    const successMessage = document.querySelector('.message.success');
    if (successMessage) {
        setTimeout(() => {
            successMessage.style.opacity = '0';
            successMessage.style.transition = 'opacity 0.3s ease';
            setTimeout(() => {
                successMessage.remove();
            }, 300);
        }, 5000);
    }
    
    console.log('Donation form script initialized successfully');
});
</script>

</body>
<?php include('includes/footer.php'); ?>
</html>