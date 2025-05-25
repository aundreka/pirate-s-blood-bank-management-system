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

// Check if request success data exists
if (!isset($_SESSION['request_success'])) {
    header('Location: receive.php');
    exit();
}

$requestData = $_SESSION['request_success'];

// Generate request ticket
function generateRequestTicket($requestData) {
    $ticket = 'PBR-' . str_pad($requestData['request_id'], 4, '0', STR_PAD_LEFT) . '-' . 
              strtoupper($requestData['blood_type_needed']) . '-' . 
              strtoupper(substr($requestData['urgency_level'], 0, 1)) . '-' .
              date('Ymd', strtotime($requestData['date_submitted']));
    return $ticket;
}

$requestTicket = generateRequestTicket($requestData);

// Extract hospital name from full address
$hospitalParts = explode(' - ', $requestData['hospital_location']);
$hospitalName = $hospitalParts[0];
$hospitalAddress = isset($hospitalParts[1]) ? $hospitalParts[1] : '';

// Get waitlist information
$waitlistInfo = null;
try {
    $waitlistQuery = "SELECT w.waitlist_position, w.priority_level, 
                     (CASE br.urgency_level 
                        WHEN 'Critical' THEN 10.0
                        WHEN 'High' THEN 7.5
                        WHEN 'Medium' THEN 5.0
                        WHEN 'Low' THEN 2.5
                        ELSE 2.5
                     END + (10 - w.priority_level)) AS urgency_score,
                     (SELECT COUNT(*) FROM waitlist w2 
                      INNER JOIN blood_request br2 ON w2.request_id = br2.request_id
                      WHERE w2.blood_type_needed = ? 
                      AND w2.waitlist_position < w.waitlist_position 
                      AND w2.status = 'Active') as ahead_in_queue,
                     (SELECT available_units FROM blood_inventory 
                      WHERE blood_type = ?) as available_units
                     FROM waitlist w
                     INNER JOIN blood_request br ON w.request_id = br.request_id
                     WHERE w.request_id = ? AND w.status = 'Active'";
    
    $waitlistStmt = $conn->prepare($waitlistQuery);
    $waitlistStmt->bind_param("ssi", $requestData['blood_type_needed'], $requestData['blood_type_needed'], $requestData['request_id']);
    $waitlistStmt->execute();
    $waitlistResult = $waitlistStmt->get_result();
    
    if ($waitlistResult->num_rows > 0) {
        $waitlistInfo = $waitlistResult->fetch_assoc();
    }
    $waitlistStmt->close();
} catch (Exception $e) {
    // Handle error silently for now
}

// Calculate estimated wait time based on urgency and availability
$estimatedWaitMessage = "Processing your request...";
if ($waitlistInfo) {
    $available = intval($waitlistInfo['available_units'] ?? 0);
    $ahead = intval($waitlistInfo['ahead_in_queue'] ?? 0);
    
    if ($available >= $requestData['units_needed']) {
        if ($ahead == 0) {
            $estimatedWaitMessage = "Blood is available! Expect contact within 2-4 hours.";
        } else {
            $hours = $ahead * 2; // Estimate 2 hours per person ahead
            $estimatedWaitMessage = "Estimated wait: " . $hours . " hours (" . $ahead . " requests ahead of you)";
        }
    } else {
        $estimatedWaitMessage = "Currently low stock. We'll contact you when blood becomes available.";
    }
}

// Clear the session data after use
unset($_SESSION['request_success']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Confirmed - Pirate's Blood Bank</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cardo:ital,wght@0,400;0,700;1,400&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Quicksand:wght@300..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    
    <style>
        /* Main content layout */
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
    display: flex;
    flex-direction: column; /* Important: allows vertical stacking */
    align-items: center;
    justify-content: flex-start; /* Aligns content to top */
}

/* Confirmation container layout */
.confirmation-container {
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    box-shadow: 2px 0 25px rgba(220, 220, 220, 0.15);
    border-radius: 25px;
    padding: 50px;
    width: 100%;
    max-width: 1000px;
    box-sizing: border-box;
    text-align: center;
    position: relative;
    overflow: hidden;
    overflow-y: auto;
    margin-top: 50px; /* Reduce spacing */
}

        
        .confirmation-container::before {
            content: '';
            position: absolute;
            left: -50%;
            width: 200%;
            height: 100%;
            background: radial-gradient(circle, rgba(220, 53, 69, 0.05) 0%, transparent 70%);
            z-index: 0;
        }
        
        .confirmation-content {
            position: relative;
            z-index: 1;
        }
        
        .success-icon {
            font-size: 80px;
            color: #28a745;
            margin-bottom: 30px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .confirmation-title {
            font-size: 42px;
            font-weight: 700;
            color: #495057;
            margin-bottom: 15px;
            font-family: 'Poppins', sans-serif;
        }
        
        .confirmation-subtitle {
            font-size: 18px;
            color: #6c757d;
            margin-bottom: 40px;
            font-weight: 500;
        }
        
        .request-ticket {
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
            border-radius: 20px;
            padding: 30px;
            margin: 30px 0;
            position: relative;
            overflow: hidden;
        }
        
        .request-ticket::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            animation: shine 3s infinite;
        }
        
        @keyframes shine {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        
        .ticket-header {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .ticket-number {
            font-size: 36px;
            font-weight: 800;
            font-family: 'Courier New', monospace;
            background: rgba(255, 255, 255, 0.2);
            padding: 15px 25px;
            border-radius: 15px;
            margin: 20px 0;
            letter-spacing: 2px;
            border: 2px dashed rgba(255, 255, 255, 0.3);
        }
        
        .ticket-details {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-top: 25px;
            text-align: left;
        }
        
        .ticket-detail {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 10px;
            border-left: 4px solid rgba(255, 255, 255, 0.3);
        }
        
        .detail-label {
            font-size: 12px;
            text-transform: uppercase;
            opacity: 0.8;
            margin-bottom: 5px;
            letter-spacing: 0.5px;
        }
        
        .detail-value {
            font-size: 16px;
            font-weight: 600;
        }
        
        .urgency-critical { color: #ffffff; }
        .urgency-high { color: #fff3cd; }
        .urgency-medium { color: #d1ecf1; }
        .urgency-low { color: #d4edda; }
        
        .hospital-info {
            background: linear-gradient(90deg, rgba(23, 162, 184, 0.1), rgba(23, 162, 184, 0.05));
            border: 1px solid rgba(23, 162, 184, 0.2);
            border-radius: 20px;
            padding: 30px;
            margin: 30px 0;
            text-align: left;
        }
        
        .hospital-title {
            font-size: 20px;
            font-weight: 700;
            color: #0c5460;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .hospital-name {
            font-size: 18px;
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
        }
        
        .hospital-address {
            color: #6c757d;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .hospital-schedule {
            background: rgba(23, 162, 184, 0.1);
            padding: 15px;
            border-radius: 10px;
            border-left: 4px solid #17a2b8;
        }
        
        .schedule-text {
            font-weight: 600;
            color: #0c5460;
            margin-bottom: 5px;
        }
        
        .waitlist-info {
            background: linear-gradient(90deg, rgba(255, 193, 7, 0.1), rgba(255, 193, 7, 0.05));
            border: 1px solid rgba(255, 193, 7, 0.3);
            border-radius: 20px;
            padding: 30px;
            margin: 25px 0;
            text-align: left;
        }
        
        .waitlist-title {
            font-size: 20px;
            font-weight: 700;
            color: #856404;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .waitlist-stats {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-top: 15px;
        }
        
        .waitlist-stat {
            text-align: center;
            background: rgba(255, 193, 7, 0.1);
            padding: 15px;
            border-radius: 10px;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: #856404;
            display: block;
        }
        
        .stat-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .next-steps {
            background: linear-gradient(90deg, rgba(40, 167, 69, 0.1), rgba(40, 167, 69, 0.05));
            border: 1px solid rgba(40, 167, 69, 0.2);
            border-radius: 20px;
            padding: 30px;
            margin: 25px 0;
            text-align: left;
        }
        
        .next-steps-title {
            font-size: 20px;
            font-weight: 700;
            color: #155724;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .steps-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .steps-list li {
            color: #155724;
            margin-bottom: 10px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        
        .step-number {
            background: #28a745;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 12px;
            flex-shrink: 0;
            margin-top: 2px;
        }
        
        .action-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-top: 40px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 15px 30px;
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
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
            border: none;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.3);
            color: white;
            text-decoration: none;
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
            border: none;
        }
        
        .btn-secondary:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 8px 25px rgba(23, 162, 184, 0.3);
            color: white;
            text-decoration: none;
        }
        
        .btn-tertiary {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
            border: none;
        }
        
        .btn-tertiary:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 8px 25px rgba(108, 117, 125, 0.3);
            color: white;
            text-decoration: none;
        }
        
        .important-reminder {
            background: linear-gradient(90deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));
            border: 1px solid rgba(220, 53, 69, 0.2);
            border-radius: 15px;
            padding: 20px;
            margin: 25px 0;
            color: #721c24;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .important-reminder i {
            font-size: 24px;
            color: #dc3545;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            main {
                margin-left: 0;
                padding: 15px;
                width: 100vw;
            }
            
            .confirmation-container {
                padding: 30px 20px;
                border-radius: 15px;
            }
            
            .confirmation-title {
                font-size: 28px;
            }
            
            .ticket-details,
            .waitlist-stats {
                grid-template-columns: 1fr;
                gap: 15px;
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
            .success-icon {
                font-size: 60px;
            }
            
            .confirmation-title {
                font-size: 24px;
            }
            
            .ticket-number {
                font-size: 24px;
                padding: 12px 20px;
            }
        }
        
        /* Print styles */
        @media print {
            main {
                margin-left: 0;
                width: 100%;
            }
            
            .action-buttons {
                display: none;
            }
            
            .confirmation-container {
                box-shadow: none;
                border: 2px solid #dee2e6;
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
?><?php include('includes/sidebar.php'); ?>

<main>


    <div class="confirmation-container">
        <div class="confirmation-content">
            <div class="success-icon">
                <i class="fas fa-hand-holding-medical"></i>
            </div>
            
            <h1 class="confirmation-title">Request Confirmed!</h1>
            <p class="confirmation-subtitle">Your blood request has been successfully submitted and added to our priority queue</p>
            
            <div class="request-ticket">
                <div class="ticket-header">
                    <i class="fas fa-ticket-alt"></i>
                    Blood Request Ticket
                </div>
                
                <div class="ticket-number">
                    <?php echo $requestTicket; ?>
                </div>
                
                <div class="ticket-details">
                    <div class="ticket-detail">
                        <div class="detail-label">Requester</div>
                        <div class="detail-value"><?php echo htmlspecialchars($requestData['requester_name']); ?></div>
                    </div>
                    <div class="ticket-detail">
                        <div class="detail-label">Blood Type</div>
                        <div class="detail-value"><?php echo htmlspecialchars($requestData['blood_type_needed']); ?></div>
                    </div>
                    <div class="ticket-detail">
                        <div class="detail-label">Units Needed</div>
                        <div class="detail-value"><?php echo $requestData['units_needed']; ?> unit<?php echo $requestData['units_needed'] > 1 ? 's' : ''; ?></div>
                    </div>
                    <div class="ticket-detail">
                        <div class="detail-label">Urgency Level</div>
                        <div class="detail-value urgency-<?php echo strtolower($requestData['urgency_level']); ?>">
                            <?php 
                            $urgencyIcons = [
                                'Critical' => 'fas fa-ambulance',
                                'High' => 'fas fa-exclamation-triangle', 
                                'Medium' => 'fas fa-clock',
                                'Low' => 'fas fa-calendar'
                            ];
                            ?>
                            <i class="<?php echo $urgencyIcons[$requestData['urgency_level']] ?? 'fas fa-circle'; ?>"></i>
                            <?php echo htmlspecialchars($requestData['urgency_level']); ?>
                        </div>
                    </div>
                    <div class="ticket-detail">
                        <div class="detail-label">Needed By</div>
                        <div class="detail-value"><?php echo date('M j, Y', strtotime($requestData['needed_by_date'])); ?></div>
                    </div>
                    <div class="ticket-detail">
                        <div class="detail-label">Request Status</div>
                        <div class="detail-value">Pending Review</div>
                    </div>
                </div>
            </div>
            
            <?php if ($waitlistInfo): ?>
            <div class="waitlist-info">
                <div class="waitlist-title">
                    <i class="fas fa-list-ol"></i>
                    Waitlist Status
                </div>
                
                <div style="margin-bottom: 15px; font-weight: 500; color: #856404;">
                    <?php echo $estimatedWaitMessage; ?>
                </div>
                
                <div class="waitlist-stats">
                    <div class="waitlist-stat">
                        <span class="stat-number"><?php echo $waitlistInfo['waitlist_position']; ?></span>
                        <span class="stat-label">Your Position</span>
                    </div>
                    <div class="waitlist-stat">
                        <span class="stat-number"><?php echo $waitlistInfo['ahead_in_queue']; ?></span>
                        <span class="stat-label">Ahead of You</span>
                    </div>
                    <div class="waitlist-stat">
                        <span class="stat-number"><?php echo $waitlistInfo['available_units'] ?? 0; ?></span>
                        <span class="stat-label">Units Available</span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="hospital-info">
                <div class="hospital-title">
                    <i class="fas fa-hospital"></i>
                    Treatment Location
                </div>
                
                <div class="hospital-name"><?php echo htmlspecialchars($hospitalName); ?></div>
                <?php if ($hospitalAddress): ?>
                    <div class="hospital-address">
                        <i class="fas fa-map-marker-alt"></i>
                        <?php echo htmlspecialchars($hospitalAddress); ?>
                    </div>
                <?php endif; ?>
                
                <div class="hospital-schedule">
                    <div class="schedule-text">We will coordinate directly with your hospital</div>
                    <div style="font-size: 14px; color: #6c757d;">Blood will be delivered to the hospital when available</div>
                </div>
            </div>
            
            <div class="next-steps">
                <div class="next-steps-title">
                    <i class="fas fa-clipboard-list"></i>
                    What Happens Next
                </div>
                
                <ul class="steps-list">
                    <li>
                        <span class="step-number">1</span>
                        <div>
                            <strong>Medical Review:</strong> Our medical team will review your request and verify details with your hospital.
                        </div>
                    </li>
                    <li>
                        <span class="step-number">2</span>
                        <div>
                            <strong>Priority Queue:</strong> Your request is now in our priority queue based on urgency and medical need.
                        </div>
                    </li>
                    <li>
                        <span class="step-number">3</span>
                        <div>
                            <strong>Blood Matching:</strong> We'll match your blood type with available donors and inventory.
                        </div>
                    </li>
                    <li>
                        <span class="step-number">4</span>
                        <div>
                            <strong>Notification:</strong> You and your hospital will be contacted when blood is ready for transfusion.
                        </div>
                    </li>
                </ul>
            </div>
            
            <div class="important-reminder">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Important:</strong> Keep this request ticket number handy. 
                    Contact us immediately at <strong>(02) 8123-4567</strong> if your medical condition changes or if this becomes a critical emergency.
                </div>
            </div>
            
            <div class="action-buttons">
                <button onclick="window.print()" class="action-btn btn-primary">
                    <i class="fas fa-print"></i>
                    Print Confirmation
                </button>
                
                <a href="appointments.php" class="action-btn btn-secondary">
                    <i class="fas fa-list"></i>
                    View My Requests
                </a>
                
                <a href="index.php" class="action-btn btn-tertiary">
                    <i class="fas fa-home"></i>
                    Back to Home
                </a>
            </div>
        </div>
    </div>
                <br><br>

</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add some celebratory effects
    setTimeout(function() {
        createConfetti();
    }, 500);
    
    // Auto-scroll to top
    window.scrollTo(0, 0);
    
    // Highlight the ticket number for easy copying
    const ticketNumber = document.querySelector('.ticket-number');
    if (ticketNumber) {
        ticketNumber.addEventListener('click', function() {
            // Select the text
            if (window.getSelection) {
                const selection = window.getSelection();
                const range = document.createRange();
                range.selectNodeContents(this);
                selection.removeAllRanges();
                selection.addRange(range);
                
                // Try to copy to clipboard
                try {
                    document.execCommand('copy');
                    
                    // Show feedback
                    const originalText = this.textContent;
                    this.textContent = 'Copied!';
                    this.style.background = 'rgba(40, 167, 69, 0.3)';
                    
                    setTimeout(() => {
                        this.textContent = originalText;
                        this.style.background = 'rgba(255, 255, 255, 0.2)';
                    }, 1500);
                } catch (err) {
                    console.log('Copy failed:', err);
                }
            }
        });
        
        // Add cursor pointer to indicate clickability
        ticketNumber.style.cursor = 'pointer';
        ticketNumber.title = 'Click to copy request number';
    }
});

function createConfetti() {
    // Simple confetti effect
    const colors = ['#dc3545', '#28a745', '#17a2b8', '#ffc107', '#6f42c1'];
    const confettiCount = 50;
    
    for (let i = 0; i < confettiCount; i++) {
        setTimeout(() => {
            const confetti = document.createElement('div');
            confetti.style.position = 'fixed';
            confetti.style.left = Math.random() * 100 + 'vw';
            confetti.style.top = '-10px';
            confetti.style.width = '10px';
            confetti.style.height = '10px';
            confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
            confetti.style.borderRadius = '50%';
            confetti.style.pointerEvents = 'none';
            confetti.style.zIndex = '9999';
            confetti.style.animation = 'fall 3s linear forwards';
            
            document.body.appendChild(confetti);
            
            setTimeout(() => {
                confetti.remove();
            }, 3000);
        }, i * 50);
    }
}

// Add CSS animation for confetti
const style = document.createElement('style');
style.textContent = `
    @keyframes fall {
        0% {
            transform: translateY(-10px) rotate(0deg);
            opacity: 1;
        }
        100% {
            transform: translateY(100vh) rotate(360deg);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
</script>

</body>
<?php 
// Don't try to close database connection - let it close naturally
include('includes/footer.php'); 
?>
</html>