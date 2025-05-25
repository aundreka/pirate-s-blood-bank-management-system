<?php 
include('includes/db.php');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Security check - ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Initialize variables
$message = '';
$messageType = '';
$donation = null;

// Get donation ID from URL
$donation_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$donation_id) {
    header('Location: appointments.php');
    exit();
}

// Get donation details with proper error handling
try {
    $donationQuery = "SELECT * FROM donate_blood WHERE donation_id = ? AND donor_id = ? AND donation_status = 'Pending'";
    $donationStmt = $conn->prepare($donationQuery);
    
    if (!$donationStmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    $donationStmt->bind_param("ii", $donation_id, $_SESSION['user_id']);
    $donationStmt->execute();
    $donationResult = $donationStmt->get_result();
    
    if ($donationResult->num_rows === 0) {
        error_log("No pending donation found for user {$_SESSION['user_id']} with ID {$donation_id}");
        header('Location: appointments.php?error=donation_not_found');
        exit();
    }
    
    $donation = $donationResult->fetch_assoc();
    $donationStmt->close();
    
} catch (Exception $e) {
    error_log("Error retrieving donation: " . $e->getMessage());
    $message = "Error retrieving donation details. Please try again.";
    $messageType = "error";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_donation']) && $donation) {
    
    // Sanitize and validate input
    $blood_type = isset($_POST['blood_type']) ? trim($_POST['blood_type']) : '';
    $medical_history = isset($_POST['medical_history']) ? trim($_POST['medical_history']) : '';
    $weight = isset($_POST['weight']) ? floatval($_POST['weight']) : 0;
    $hospital_location = isset($_POST['hospital_location']) ? trim($_POST['hospital_location']) : '';
    $date_of_donation = isset($_POST['date_of_donation']) ? $_POST['date_of_donation'] : date('Y-m-d');
    
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
    } elseif (strlen($medical_history) < 10) {
        $errors[] = "Please provide more detailed medical history (minimum 10 characters).";
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
    
    // If no errors, proceed with update
    if (empty($errors)) {
        try {
            // Begin transaction
            $conn->autocommit(false);
            
            // Update donation
            $updateQuery = "UPDATE donate_blood SET 
                           blood_type = ?, 
                           medical_history = ?, 
                           weight = ?, 
                           hospital_location = ?, 
                           date_of_donation = ?,
                           updated_at = CURRENT_TIMESTAMP
                           WHERE donation_id = ? AND donor_id = ? AND donation_status = 'Pending'";
            
            $updateStmt = $conn->prepare($updateQuery);
            
            if (!$updateStmt) {
                throw new Exception("Database prepare error: " . $conn->error);
            }
            
            $updateStmt->bind_param("ssdsiii", 
                $blood_type, 
                $medical_history, 
                $weight, 
                $hospital_location, 
                $date_of_donation, 
                $donation_id, 
                $_SESSION['user_id']
            );
            
            if (!$updateStmt->execute()) {
                throw new Exception("Update execution error: " . $updateStmt->error);
            }
            
            $affected_rows = $updateStmt->affected_rows;
            
            if ($affected_rows > 0) {
                // Update blood_log if blood type changed
                if ($blood_type !== $donation['blood_type']) {
                    $logQuery = "INSERT INTO blood_log (donation_id, blood_type, units_donated, donor_id, operation_type, notes) 
                                VALUES (?, ?, 1, ?, 'DONATION', 'Blood type updated during donation modification')";
                    $logStmt = $conn->prepare($logQuery);
                    if ($logStmt) {
                        $logStmt->bind_param("isi", $donation_id, $blood_type, $_SESSION['user_id']);
                        $logStmt->execute();
                        $logStmt->close();
                    }
                }
                
                // Commit transaction
                $conn->commit();
                $conn->autocommit(true);
                
                $message = "Your donation has been updated successfully!";
                $messageType = "success";
                
                // Refresh donation data
                $donation['blood_type'] = $blood_type;
                $donation['medical_history'] = $medical_history;
                $donation['weight'] = $weight;
                $donation['hospital_location'] = $hospital_location;
                $donation['date_of_donation'] = $date_of_donation;
                
                error_log("Donation updated successfully for user {$_SESSION['user_id']}, donation ID {$donation_id}");
                
            } else {
                throw new Exception("No rows were updated. The donation may have been modified or is no longer pending.");
            }
            
            $updateStmt->close();
            
        } catch (Exception $e) {
            // Rollback transaction
            $conn->rollback();
            $conn->autocommit(true);
            
            error_log("Update error for donation {$donation_id}: " . $e->getMessage());
            $message = "An error occurred while updating your donation. Please try again.";
            $messageType = "error";
        }
    } else {
        $message = implode('<br>', $errors);
        $messageType = "error";
    }
}

// Generate donation ticket
function generateDonationTicket($donationData) {
    $created_date = isset($donationData['created_at']) ? $donationData['created_at'] : date('Y-m-d H:i:s');
    return 'PBB-' . str_pad($donationData['donation_id'], 4, '0', STR_PAD_LEFT) . '-' . 
           strtoupper($donationData['blood_type']) . '-' . 
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
    <title>Pirate's Blood Bank - Update Donation</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cardo:ital,wght@0,400;0,700;1,400&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Quicksand:wght@300..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    
    <style>
        /* Your existing CSS styles remain the same */
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
        
        /* All other existing styles remain the same... */
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
            color: #dc3545;
            font-size: 28px;
        }
        
        .update-subtitle {
            color: #6c757d;
            font-size: 16px;
            font-weight: 500;
        }
        
        .donation-info {
            background: linear-gradient(90deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));
            color: #495057;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            border-left: 4px solid #dc3545;
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 20px;
            align-items: center;
        }
        
        .donation-details {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .ticket-number {
            font-size: 18px;
            font-weight: 700;
            color: #dc3545;
            font-family: 'Courier New', monospace;
            letter-spacing: 1px;
        }
        
        .donation-meta {
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
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
        }
        
        .btn-update:hover:not(.btn-loading) {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.3);
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
            
            .donation-info {
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
            
            .blood-type-grid {
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
                Update Donation
            </h1>
            <p class="update-subtitle">Modify your pending blood donation details</p>
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
        
        <?php if ($donation): ?>
            <div class="donation-info">
                <div class="donation-details">
                    <div class="ticket-number"><?php echo generateDonationTicket($donation); ?></div>
                    <div class="donation-meta">
                        Submitted: <?php echo isset($donation['created_at']) ? date('M j, Y g:i A', strtotime($donation['created_at'])) : 'Unknown'; ?> | 
                        For: <?php echo date('M j, Y', strtotime($donation['date_of_donation'])); ?>
                    </div>
                </div>
                <div class="status-badge">Pending</div>
            </div>
            
            <form method="POST" class="update-form" id="updateForm" novalidate>
                <input type="hidden" name="update_donation" value="1">
                
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
                                           <?php echo ($donation['blood_type'] === $type) ? 'checked' : ''; ?> 
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
                               value="<?php echo htmlspecialchars($donation['weight']); ?>" 
                               required>
                        <span class="form-help">Minimum weight requirement: 50 kg</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="date_of_donation" class="form-label">
                            <i class="fas fa-calendar-alt"></i>
                            Donation Date <span class="required">*</span>
                        </label>
                        <input type="date" 
                               id="date_of_donation" 
                               name="date_of_donation" 
                               class="form-input" 
                               min="<?php echo date('Y-m-d'); ?>"
                               value="<?php echo htmlspecialchars($donation['date_of_donation']); ?>"
                               required>
                        <span class="form-help">Select your preferred donation date</span>
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
                                    <?php echo ($donation['hospital_location'] === $value) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($display); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-help">Choose where you'd like to complete your donation</span>
                </div>
                
                <div class="form-group">
                    <label for="medical_history" class="form-label">
                        <i class="fas fa-notes-medical"></i>
                        Medical History <span class="required">*</span>
                    </label>
                    <textarea id="medical_history" 
                              name="medical_history" 
                              class="form-textarea" 
                              placeholder="Please provide detailed information about your current health status, medications, recent illnesses, surgeries, or any other relevant medical information..."
                              required><?php echo htmlspecialchars($donation['medical_history']); ?></textarea>
                    <span class="form-help">Update your current health status and any changes since your original submission (minimum 10 characters)</span>
                </div>
                
                <div class="action-buttons">
                    <button type="submit" name="submit_update" class="action-btn btn-update" id="submitBtn">
                        <i class="fas fa-save"></i>
                        Update Donation
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
                Donation not found or cannot be updated. This may happen if the donation doesn't exist, 
                belongs to another user, or is no longer in pending status.
            </div>
            <div class="action-buttons">
                <a href="appointments.php" class="action-btn btn-cancel">
                    <i class="fas fa-arrow-left"></i>
                    Back to Appointments
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
        const bloodType = document.querySelector('input[name="blood_type"]:checked');
        if (!bloodType) {
            errors.push('Please select a blood type');
            isValid = false;
        }
        
        // Validate weight
        const weight = document.getElementById('weight');
        const weightValue = parseFloat(weight.value);
        if (!weightValue || weightValue < 50 || weightValue > 300) {
            errors.push('Weight must be between 50-300 kg');
            weight.classList.add('error');
            weight.parentElement.classList.add('error');
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
        
        // Validate medical history
        const medicalHistory = document.getElementById('medical_history');
        if (!medicalHistory.value.trim() || medicalHistory.value.trim().length < 10) {
            errors.push('Medical history must be at least 10 characters long');
            medicalHistory.classList.add('error');
            medicalHistory.parentElement.classList.add('error');
            isValid = false;
        }
        
        // Validate donation date
        const donationDate = document.getElementById('date_of_donation');
        const today = new Date().toISOString().split('T')[0];
        if (!donationDate.value || donationDate.value < today) {
            errors.push('Please select a valid future date');
            donationDate.classList.add('error');
            donationDate.parentElement.classList.add('error');
            isValid = false;
        }
        
        return { isValid, errors };
    }
    
    // Real-time validation
    function setupRealTimeValidation() {
        // Weight validation
        const weight = document.getElementById('weight');
        weight.addEventListener('input', function() {
            const value = parseFloat(this.value);
            if (value >= 50 && value <= 300) {
                this.classList.remove('error');
                this.parentElement.classList.remove('error');
            }
        });
        
        // Medical history validation
        const medicalHistory = document.getElementById('medical_history');
        medicalHistory.addEventListener('input', function() {
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
        
        // Date validation
        const donationDate = document.getElementById('date_of_donation');
        donationDate.addEventListener('change', function() {
            const today = new Date().toISOString().split('T')[0];
            if (this.value >= today) {
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
    
    console.log('Form validation initialized successfully');
});
</script>

</body>
<?php include('includes/footer.php'); ?>
</html>