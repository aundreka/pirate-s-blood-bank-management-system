<?php
include('includes/db.php');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'];
$eventId = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

// Initialize variables
$event = null;
$registrationSuccess = false;
$errorMessage = '';
$isAlreadyRegistered = false;

// Get user data
$userQuery = "SELECT username FROM login WHERE user_id = ?";
$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$userResult = $userStmt->get_result();
$userData = $userResult->fetch_assoc();
$userStmt->close();

// Get event details if event_id is provided
if ($eventId > 0) {
    try {
        $eventQuery = "SELECT event_id, title, description, event_date, start_time, end_time, location, max_slots, booked_slots 
                       FROM events 
                       WHERE event_id = ? AND status = 'upcoming' AND event_date >= CURRENT_DATE()";
        $eventStmt = $conn->prepare($eventQuery);
        $eventStmt->bind_param("i", $eventId);
        $eventStmt->execute();
        $eventResult = $eventStmt->get_result();
        
        if ($eventResult->num_rows > 0) {
            $event = $eventResult->fetch_assoc();
            $event['slots_available'] = $event['max_slots'] - $event['booked_slots'];
            
            // Now check registration after we have the event
            $registrationDetails = null;
            try {
                $checkQuery = "SELECT registration_id, registration_date, status FROM event_registrations WHERE event_id = ? AND user_id = ? AND status = 'registered'";
                $checkStmt = $conn->prepare($checkQuery);
                $checkStmt->bind_param("ii", $eventId, $userId);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                
                if ($checkResult->num_rows > 0) {
                    $registrationDetails = $checkResult->fetch_assoc();
                    $isAlreadyRegistered = true;
                    
                    // Generate ticket number for existing registration
                    $ticketNumber = 'TKT' . strtoupper(substr(md5($userId . $eventId . $registrationDetails['registration_id']), 0, 8));
                }
                $checkStmt->close();
            } catch (Exception $e) {
                error_log("Error checking registration: " . $e->getMessage());
            }
        }
        
        $eventStmt->close();
    } catch (Exception $e) {
        error_log("Error fetching event: " . $e->getMessage());
        $errorMessage = "Error loading event details.";
    }
}

        

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_event'])) {
    try {
        if ($event && $event['slots_available'] > 0 && !$isAlreadyRegistered) {
            // Begin transaction
            $conn->begin_transaction();
            
            // Insert registration
            $registerQuery = "INSERT INTO event_registrations (event_id, user_id, registration_date, status) VALUES (?, ?, NOW(), 'registered')";
            $registerStmt = $conn->prepare($registerQuery);
            $registerStmt->bind_param("ii", $eventId, $userId);
            
            if ($registerStmt->execute()) {
                // Update booked slots count
                $updateQuery = "UPDATE events SET booked_slots = booked_slots + 1 WHERE event_id = ?";
                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->bind_param("i", $eventId);
                $updateStmt->execute();
                $updateStmt->close();
                
                // Commit transaction
                $conn->commit();
                
                $registrationSuccess = true;
                $isAlreadyRegistered = true;
                
                // Update event slots available
                $event['booked_slots']++;
                $event['slots_available']--;
                
            } else {
                $conn->rollback();
                $errorMessage = "Registration failed. Please try again.";
            }
            
            $registerStmt->close();
        } else {
            $errorMessage = "Unable to register for this event.";
        }
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error during registration: " . $e->getMessage());
        $errorMessage = "Registration failed. Please try again.";
    }
}

// Check for cancellation message
$cancellationMessage = '';
$cancellationError = '';

if (isset($_SESSION['cancellation_message'])) {
    $cancellationMessage = $_SESSION['cancellation_message'];
    unset($_SESSION['cancellation_message']);
}

if (isset($_GET['cancelled']) && $_GET['cancelled'] == '1') {
    $cancellationMessage = 'Your registration has been successfully cancelled.';
}

if (isset($_GET['error'])) {
    $cancellationError = $_GET['error'];
}

// Check if registration was cancelled and reset state
if ($event && $registrationDetails) {
    // If registration exists but is cancelled, reset the registration state
    if ($registrationDetails['status'] === 'cancelled') {
        $isAlreadyRegistered = false;
        $registrationDetails = null;
        $ticketNumber = null;
        
        // Set a message to show they were previously registered but it's now cancelled
        if (!$cancellationMessage && !$cancellationError) {
            $cancellationMessage = 'Your previous registration for this event has been cancelled.';
        }
    }
}

// Generate ticket number if registration successful
$ticketNumber = null;
if ($registrationSuccess) {
    $ticketNumber = 'TKT' . strtoupper(substr(md5($userId . $eventId . time()), 0, 8));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Registration - Pirate's Blood Bank</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            color: #333;
            line-height: 1.6;
            min-height: 100vh;
        }

        .main-content {
            margin-left: 70px;
            padding: 20px;
            margin-right: auto;
            width: calc(100% - 70px);
            transition: margin-left 0.3s ease;
            }

        /* Success Ticket */
        .success-ticket {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
            animation: slideDown 0.5s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .success-ticket::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="2" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
            opacity: 0.3;
        }

        .ticket-header {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            position: relative;
            z-index: 2;
        }

        .ticket-icon {
            font-size: 48px;
            margin-right: 15px;
        }

        .ticket-title {
            font-size: 32px;
            font-weight: 700;
        }

        .ticket-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
            position: relative;
            z-index: 2;
        }

        .ticket-detail {
            text-align: center;
            padding: 15px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 10px;
        }

        .ticket-detail-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.8;
            margin-bottom: 5px;
        }

        .ticket-detail-value {
            font-size: 16px;
            font-weight: 600;
        }

        .ticket-number {
            text-align: center;
            margin-top: 20px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            position: relative;
            z-index: 2;
        }

        .ticket-number-label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 5px;
        }

        .ticket-number-value {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 2px;
        }

        /* Event Card */
        .event-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        .event-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(135deg, #dc3545, #b02a37);
        }

        .event-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .event-title {
            font-size: 32px;
            font-weight: 700;
            color: #333;
            margin-bottom: 15px;
        }

        .event-description {
            font-size: 18px;
            color: #666;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
        }

        .event-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 15px;
            transition: all 0.3s ease;
        }

        .detail-item:hover {
            background: #e9ecef;
            transform: translateY(-3px);
        }

        .detail-icon {
            font-size: 24px;
            color: #dc3545;
            width: 40px;
            text-align: center;
        }

        .detail-content h4 {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .detail-content p {
            font-size: 14px;
            color: #666;
        }

        /* Availability Status */
        .availability-status {
            text-align: center;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            font-weight: 600;
        }

        .availability-status.available {
            background: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }

        .availability-status.low {
            background: #fff3cd;
            color: #856404;
            border: 2px solid #ffeaa7;
        }

        .availability-status.full {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }

        .availability-status.registered {
            background: #cce5ff;
            color: #004085;
            border: 2px solid #99ccff;
        }

        /* Registration Form */
        .registration-form {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .form-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: white;
        }

        .form-input:focus {
            outline: none;
            border-color: #dc3545;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
        }

        .form-input:disabled {
            background: #f8f9fa;
            color: #666;
        }

        /* Buttons */
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #c82333, #a02232);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }

        .btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        /* Back Navigation */
        .back-nav {
            margin-bottom: 20px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #dc3545;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            color: #c82333;
            transform: translateX(-3px);
        }

        /* Instructions */
        .instructions {
            background: #e7f3ff;
            border: 1px solid #bee5eb;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .instructions h4 {
            color: #0c5460;
            margin-bottom: 10px;
            font-size: 18px;
        }

        .instructions ul {
            color: #0c5460;
            margin-left: 20px;
        }

        .instructions li {
            margin-bottom: 5px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
                min-width: 500px;
            }

            .event-card {
                padding: 25px;
            }

            .event-title {
                font-size: 24px;
            }

            .event-details-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .ticket-details {
                grid-template-columns: 1fr;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }

        /* Print Styles for Ticket */
        @media print {
            .main-content {
                margin: 0;
                padding: 0;
            }

            .back-nav,
            .btn-group,
            .instructions {
                display: none;
            }

            .success-ticket {
                background: #28a745 !important;
                color: white !important;
                print-color-adjust: exact;
            }
        }

        .btn-group {
    margin-bottom: 140px;
}

.ticket-title {
    color: white;
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

    <div class="main-content">
        <!-- Back Navigation -->
        <div class="back-nav">
            <a href="community.php" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Back to Community Events
            </a>
        </div>
<?php if ($isAlreadyRegistered && !$registrationSuccess): ?>
<!-- Existing Registration Ticket -->
<div class="success-ticket">
    <div class="ticket-header">
        <i class="fas fa-ticket-alt ticket-icon"></i>
        <h1 class="ticket-title">Your Event Ticket</h1>
    </div>
    
    <p style="text-align: center; font-size: 18px; margin-bottom: 30px; position: relative; z-index: 2;">
        You are registered for this event. Here are your ticket details:
    </p>
    
    <div class="ticket-details">
        <div class="ticket-detail">
            <div class="ticket-detail-label">Event</div>
            <div class="ticket-detail-value"><?= htmlspecialchars($event['title']) ?></div>
        </div>
        <div class="ticket-detail">
            <div class="ticket-detail-label">Date</div>
            <div class="ticket-detail-value"><?= date('M j, Y', strtotime($event['event_date'])) ?></div>
        </div>
        <div class="ticket-detail">
            <div class="ticket-detail-label">Time</div>
            <div class="ticket-detail-value"><?= date('g:i A', strtotime($event['start_time'])) ?> - <?= date('g:i A', strtotime($event['end_time'])) ?></div>
        </div>
        <div class="ticket-detail">
            <div class="ticket-detail-label">Location</div>
            <div class="ticket-detail-value"><?= htmlspecialchars($event['location']) ?></div>
        </div>
    </div>
    
    <div class="ticket-number">
        <div class="ticket-number-label">Your Ticket Number</div>
        <div class="ticket-number-value"><?= $ticketNumber ?></div>
    </div>
    
    <div style="text-align: center; margin-top: 20px; position: relative; z-index: 2;">
        <div style="background: rgba(255, 255, 255, 0.15); padding: 10px; border-radius: 8px; font-size: 14px;">
            <i class="fas fa-calendar-check"></i> 
            Registered on <?= date('F j, Y \a\t g:i A', strtotime($registrationDetails['registration_date'])) ?>
        </div>
    </div>
</div>

<!-- Instructions for registered users -->
<div class="instructions">
    <h4><i class="fas fa-info-circle"></i> Event Instructions</h4>
    <ul>
        <li>Please arrive 15 minutes before your scheduled time</li>
        <li>Bring a valid ID and show your ticket number: <strong><?= $ticketNumber ?></strong></li>
        <li>Eat a healthy meal and stay hydrated before coming</li>
        <li>Get a good night's sleep before the donation</li>
        <li>Avoid alcohol 24 hours before donation</li>
        <li>If you need to cancel, please contact us at least 24 hours in advance</li>
    </ul>
</div>

<div class="btn-group">
    <button onclick="window.print()" class="btn btn-secondary">
        <i class="fas fa-print"></i> Print Ticket
    </button>
    <button onclick="cancelRegistration()" class="btn" style="background: #dc3545; color: white;">
        <i class="fas fa-times"></i> Cancel Registration
    </button>
</div>

<!-- Add cancellation functionality -->
<script>
function cancelRegistration() {
    if (confirm('Are you sure you want to cancel your registration for this event? This action cannot be undone.')) {
        // Create a form to submit cancellation
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'cancel_registration.php';
        
        const eventIdInput = document.createElement('input');
        eventIdInput.type = 'hidden';
        eventIdInput.name = 'event_id';
        eventIdInput.value = '<?= $eventId ?>';
        
        const cancelInput = document.createElement('input');
        cancelInput.type = 'hidden';
        cancelInput.name = 'cancel_registration';
        cancelInput.value = '1';
        
        form.appendChild(eventIdInput);
        form.appendChild(cancelInput);
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php endif; ?>

        <?php if ($registrationSuccess): ?>
        <!-- Success Ticket -->
        <div class="success-ticket">
            <div class="ticket-header">
                <i class="fas fa-check-circle ticket-icon"></i>
                <h1 class="ticket-title">Registration Successful!</h1>
            </div>
            
            <p style="text-align: center; font-size: 18px; margin-bottom: 30px; position: relative; z-index: 2;">
                Thank you for registering! Your spot has been confirmed for this blood drive event.
            </p>
            
            <div class="ticket-details">
                <div class="ticket-detail">
                    <div class="ticket-detail-label">Event</div>
                    <div class="ticket-detail-value"><?= htmlspecialchars($event['title']) ?></div>
                </div>
                <div class="ticket-detail">
                    <div class="ticket-detail-label">Date</div>
                    <div class="ticket-detail-value"><?= date('M j, Y', strtotime($event['event_date'])) ?></div>
                </div>
                <div class="ticket-detail">
                    <div class="ticket-detail-label">Time</div>
                    <div class="ticket-detail-value"><?= date('g:i A', strtotime($event['start_time'])) ?> - <?= date('g:i A', strtotime($event['end_time'])) ?></div>
                </div>
                <div class="ticket-detail">
                    <div class="ticket-detail-label">Location</div>
                    <div class="ticket-detail-value"><?= htmlspecialchars($event['location']) ?></div>
                </div>
            </div>
            
            <div class="ticket-number">
                <div class="ticket-number-label">Your Ticket Number</div>
                <div class="ticket-number-value"><?= $ticketNumber ?></div>
            </div>
        </div>

        <!-- Instructions -->
        <div class="instructions">
            <h4><i class="fas fa-info-circle"></i> Important Instructions</h4>
            <ul>
                <li>Please arrive 15 minutes before your scheduled time</li>
                <li>Bring a valid ID and your ticket number</li>
                <li>Eat a healthy meal and stay hydrated before coming</li>
                <li>Get a good night's sleep before the donation</li>
                <li>Avoid alcohol 24 hours before donation</li>
                <li>If you need to cancel, please contact us at least 24 hours in advance</li>
            </ul>
        </div>

        <div class="btn-group">
            <button onclick="window.print()" class="btn btn-secondary">
                <i class="fas fa-print"></i> Print Ticket
            </button>
            <a href="community.php" class="btn btn-primary">
                <i class="fas fa-home"></i> Back to Community
            </a>
        </div>

        <?php elseif ($event): ?>
        <!-- Event Registration Form -->
        <div class="event-card">
            <div class="event-header">
                <h1 class="event-title"><?= htmlspecialchars($event['title']) ?></h1>
                <?php if (!empty($event['description'])): ?>
                    <p class="event-description"><?= htmlspecialchars($event['description']) ?></p>
                <?php endif; ?>
            </div>

            <div class="event-details-grid">
                <div class="detail-item">
                    <div class="detail-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="detail-content">
                        <h4>Date</h4>
                        <p><?= date('l, F j, Y', strtotime($event['event_date'])) ?></p>
                    </div>
                </div>

                <div class="detail-item">
                    <div class="detail-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="detail-content">
                        <h4>Time</h4>
                        <p><?= date('g:i A', strtotime($event['start_time'])) ?> - <?= date('g:i A', strtotime($event['end_time'])) ?></p>
                    </div>
                </div>

                <div class="detail-item">
                    <div class="detail-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="detail-content">
                        <h4>Location</h4>
                        <p><?= htmlspecialchars($event['location']) ?></p>
                    </div>
                </div>

                <div class="detail-item">
                    <div class="detail-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="detail-content">
                        <h4>Availability</h4>
                        <p><?= $event['slots_available'] ?> of <?= $event['max_slots'] ?> slots remaining</p>
                    </div>
                </div>
            </div>

            <!-- Availability Status -->
<?php if ($isAlreadyRegistered): ?>
                <div class="availability-status registered">
                    <i class="fas fa-check-circle"></i>
                    You are already registered for this event!
                </div>
            <?php elseif ($event['slots_available'] <= 0): ?>
                <div class="availability-status full">
                    <i class="fas fa-times-circle"></i>
                    This event is fully booked
                </div>
            <?php elseif ($event['slots_available'] <= 10): ?>
                <div class="availability-status low">
                    <i class="fas fa-exclamation-triangle"></i>
                    Only <?= $event['slots_available'] ?> slots remaining - Register quickly!
                </div>
            <?php else: ?>
                <div class="availability-status available">
                    <i class="fas fa-check-circle"></i>
                    Slots available - Register now!
                </div>
            <?php endif; ?>

            <?php if (!empty($errorMessage)): ?>
                <div class="alert error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= htmlspecialchars($errorMessage) ?>
                </div>
            <?php endif; ?>

<?php if (!$isAlreadyRegistered && $event['slots_available'] > 0): ?>
                <!-- Registration Form -->
                <form method="POST" class="registration-form">
                    <h3 style="margin-bottom: 25px; text-align: center; color: #333;">
                        <i class="fas fa-user-plus"></i> Complete Your Registration
                    </h3>

                    <div class="form-group">
                        <label for="username" class="form-label">Full Name</label>
                        <input type="text" id="username" name="username" class="form-input" 
                               value="<?= htmlspecialchars($userData['username']) ?>" disabled>
                    </div>

                    <div class="form-group">
                        <label for="phone" class="form-label">Phone Number (Optional)</label>
                        <input type="tel" id="phone" name="phone" class="form-input" 
                               placeholder="Enter your phone number for event updates">
                    </div>

                    <div class="form-group">
                        <label for="emergency_contact" class="form-label">Emergency Contact (Optional)</label>
                        <input type="text" id="emergency_contact" name="emergency_contact" class="form-input" 
                               placeholder="Name and phone number of emergency contact">
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" required style="margin-right: 8px;">
                            I confirm that I am eligible to donate blood and agree to the terms and conditions
                        </label>
                    </div>

                    <div class="btn-group">
                        <button type="submit" name="register_event" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> Confirm Registration
                        </button>
                        <a href="community.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            <?php else: ?>
                <div class="btn-group">
                    <a href="community.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Events
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <?php else: ?>
        <!-- No Event Found -->
        <div class="event-card">
            <div style="text-align: center; padding: 50px; color: #666;">
                <i class="fas fa-calendar-times" style="font-size: 64px; margin-bottom: 20px; opacity: 0.5;"></i>
                <h2>Event Not Found</h2>
                <p style="margin-bottom: 30px;">The event you're looking for doesn't exist or is no longer available for registration.</p>
                <a href="community.php" class="btn btn-primary">
                    <i class="fas fa-calendar-alt"></i> View Available Events
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add smooth animations
            const elements = document.querySelectorAll('.event-card, .success-ticket, .instructions');
            elements.forEach((element, index) => {
                element.style.opacity = '0';
                element.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    element.style.transition = 'all 0.6s ease';
                    element.style.opacity = '1';
                    element.style.transform = 'translateY(0)';
                }, index * 200);
            });

            // Add hover effects to detail items
            const detailItems = document.querySelectorAll('.detail-item');
            detailItems.forEach(item => {
                item.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-3px)';
                });
                
                item.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });

            // Form validation
            const form = document.querySelector('.registration-form form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const checkbox = form.querySelector('input[type="checkbox"]');
                    if (!checkbox.checked) {
                        e.preventDefault();
                        alert('Please confirm that you agree to the terms and conditions.');
                        return false;
                    }
                    
                    // Show loading state
                    const submitBtn = form.querySelector('button[type="submit"]');
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                    submitBtn.disabled = true;
                });
            }

            // Auto-hide success message after a delay
            const successTicket = document.querySelector('.success-ticket');
            if (successTicket) {
                // Scroll to top to show the ticket
                window.scrollTo({ top: 0, behavior: 'smooth' });
                
                // Optional: Add confetti effect or celebration animation
                console.log('Registration successful! 🎉');
            }

            console.log('Event registration page initialized');
        });

        // Print function optimization
        function printTicket() {
            const originalTitle = document.title;
            document.title = 'Event Registration Ticket';
            window.print();
            document.title = originalTitle;
        }

        <?php if (!empty($cancellationMessage)): ?>
document.addEventListener('DOMContentLoaded', function() {
    showCancellationPopup('<?= htmlspecialchars($cancellationMessage, ENT_QUOTES) ?>', 'success');
});
<?php endif; ?>

<?php if (!empty($cancellationError)): ?>
document.addEventListener('DOMContentLoaded', function() {
    showCancellationPopup('<?= htmlspecialchars($cancellationError, ENT_QUOTES) ?>', 'error');
});
<?php endif; ?>

function showCancellationPopup(message, type) {
    // Create popup overlay
    const overlay = document.createElement('div');
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: fadeIn 0.3s ease;
    `;
    
    // Create popup
    const popup = document.createElement('div');
    popup.style.cssText = `
        background: white;
        padding: 40px;
        border-radius: 20px;
        text-align: center;
        max-width: 400px;
        margin: 20px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        animation: slideUp 0.3s ease;
    `;
    
    // Icon based on type
    const icon = type === 'success' ? 
        '<i class="fas fa-check-circle" style="font-size: 48px; color: #28a745; margin-bottom: 20px;"></i>' :
        '<i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #dc3545; margin-bottom: 20px;"></i>';
    
    popup.innerHTML = `
        ${icon}
        <h3 style="margin-bottom: 15px; color: #333;">${type === 'success' ? 'Registration Cancelled' : 'Cancellation Failed'}</h3>
        <p style="margin-bottom: 30px; color: #666; line-height: 1.6;">${message}</p>
        <button onclick="closeCancellationPopup()" style="
            background: ${type === 'success' ? '#28a745' : '#dc3545'};
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        ">OK</button>
    `;
    
    overlay.appendChild(popup);
    document.body.appendChild(overlay);
    
    // Add styles for animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(50px); }
            to { opacity: 1; transform: translateY(0); }
        }
    `;
    document.head.appendChild(style);
    
    // Store reference for closing
    window.cancellationOverlay = overlay;
}

function closeCancellationPopup() {
    if (window.cancellationOverlay) {
        window.cancellationOverlay.style.animation = 'fadeOut 0.3s ease';
        setTimeout(() => {
            if (window.cancellationOverlay && window.cancellationOverlay.parentNode) {
                window.cancellationOverlay.parentNode.removeChild(window.cancellationOverlay);
            }
        }, 300);
    }
}

// Add fadeOut animation
const style = document.createElement('style');
style.textContent += `
    @keyframes fadeOut {
        from { opacity: 1; }
        to { opacity: 0; }
    }
`;
document.head.appendChild(style);

// Existing cancellation function remains the same
function cancelRegistration() {
    if (confirm('Are you sure you want to cancel your registration for this event? This action cannot be undone.')) {
        // Create a form to submit cancellation
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'cancel_registration.php';
        
        const eventIdInput = document.createElement('input');
        eventIdInput.type = 'hidden';
        eventIdInput.name = 'event_id';
        eventIdInput.value = '<?= $eventId ?>';
        
        const cancelInput = document.createElement('input');
        cancelInput.type = 'hidden';
        cancelInput.name = 'cancel_registration';
        cancelInput.value = '1';
        
        form.appendChild(eventIdInput);
        form.appendChild(cancelInput);
        document.body.appendChild(form);
        form.submit();
    }
}
    </script>

    <?php include('includes/footer.php'); ?>
</body>
</html>