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

// Check if donation success data exists
if (!isset($_SESSION['donation_success'])) {
    header('Location: donate.php');
    exit();
}

$donation_data = $_SESSION['donation_success'];

// Generate donation ticket ID
function generateDonationTicket($donation_id, $blood_type, $date_submitted) {
    return 'PBB-' . str_pad($donation_id, 4, '0', STR_PAD_LEFT) . '-' . 
           strtoupper($blood_type) . '-' . 
           date('Ymd', strtotime($date_submitted));
}

$ticket_id = generateDonationTicket(
    $donation_data['donation_id'],
    $donation_data['blood_type'],
    $donation_data['date_submitted']
);

// Clear the session data after displaying
unset($_SESSION['donation_success']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pirate's Blood Bank - Thank You</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cardo:ital,wght@0,400;0,700;1,400&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Quicksand:wght@300..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    
    <style>
/* Replace the existing main styles with this fixed version */

main {
    margin-left: 70px;
    min-height: 100vh;
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    transition: margin-left 0.3s ease;
    padding: 40px 20px;
    width: calc(100vw - 70px);
    box-sizing: border-box;
    overflow-y: auto;
    /* Removed centering properties that were causing the issue */
    display: block; /* Changed from flex */
}

.thank-you-container {
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
    border-radius: 20px;
    padding: 60px 40px;
    width: 100%;
    max-width: 800px;
    text-align: center;
    position: relative;
    overflow: hidden;
    /* Center the container horizontally */
    margin: 0 auto;
    /* Add some top margin for better spacing */
    margin-top: 20px;
    margin-bottom: 40px;
}

/* Rest of your existing styles remain the same */
.thank-you-container::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 5px;
    background: linear-gradient(90deg, #dc3545, #28a745, #17a2b8);
    border-radius: 20px 20px 0 0;
}

.success-icon {
    font-size: 80px;
    color: #28a745;
    margin-bottom: 30px;
    animation: bounceIn 1s ease;
}

@keyframes bounceIn {
    0% { transform: scale(0); opacity: 0; }
    50% { transform: scale(1.1); opacity: 0.8; }
    100% { transform: scale(1); opacity: 1; }
}

.thank-you-title {
    font-size: 36px;
    font-weight: 700;
    color: #495057;
    margin-bottom: 20px;
    font-family: 'Poppins', sans-serif;
}

.thank-you-subtitle {
    font-size: 18px;
    color: #6c757d;
    margin-bottom: 40px;
    line-height: 1.6;
}

.status-notice {
    background: linear-gradient(90deg, rgba(255, 193, 7, 0.1), rgba(255, 193, 7, 0.05));
    color: #856404;
    padding: 25px;
    border-radius: 15px;
    margin-bottom: 40px;
    border-left: 4px solid #ffc107;
    text-align: left;
}

.status-notice h4 {
    margin: 0 0 15px 0;
    font-size: 20px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
}

.status-notice p {
    margin: 0;
    line-height: 1.6;
}

.donation-details {
    background: white;
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 40px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    border: 1px solid #f0f0f0;
}

.details-title {
    font-size: 20px;
    font-weight: 700;
    color: #495057;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.detail-item {
    text-align: center;
    padding: 20px;
    background: linear-gradient(135deg, rgba(220, 53, 69, 0.05), rgba(220, 53, 69, 0.02));
    border-radius: 12px;
    border: 1px solid rgba(220, 53, 69, 0.1);
}

.detail-label {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #6c757d;
    margin-bottom: 8px;
    font-weight: 600;
}

.detail-value {
    font-size: 16px;
    font-weight: 700;
    color: #495057;
    word-break: break-word;
}

.ticket-highlight {
    background: linear-gradient(135deg, #dc3545, #b02a37);
    color: white;
    font-family: 'Courier New', monospace;
    font-size: 18px;
    letter-spacing: 1px;
}

.next-steps {
    background: linear-gradient(90deg, rgba(23, 162, 184, 0.1), rgba(23, 162, 184, 0.05));
    color: #0c5460;
    padding: 25px;
    border-radius: 15px;
    margin-bottom: 40px;
    border-left: 4px solid #17a2b8;
    text-align: left;
}

.next-steps h4 {
    margin: 0 0 15px 0;
    font-size: 20px;
    font-weight: 700;
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
    margin-bottom: 10px;
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.step-number {
    background: linear-gradient(135deg, #17a2b8, #138496);
    color: white;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
    flex-shrink: 0;
    margin-top: 2px;
}

.action-buttons {
    display: flex;
    gap: 20px;
    justify-content: center;
    flex-wrap: wrap;
}

.action-btn {
    padding: 18px 30px;
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

.btn-primary {
    background: linear-gradient(135deg, #dc3545, #b02a37);
    color: white;
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
}

.btn-secondary:hover {
    transform: translateY(-3px) scale(1.02);
    box-shadow: 0 8px 25px rgba(23, 162, 184, 0.3);
    color: white;
    text-decoration: none;
}

.btn-outline {
    background: transparent;
    color: #6c757d;
    border: 2px solid #6c757d;
}

.btn-outline:hover {
    background: #6c757d;
    color: white;
    text-decoration: none;
    transform: translateY(-3px) scale(1.02);
    box-shadow: 0 8px 25px rgba(108, 117, 125, 0.3);
}

.timeline-info {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 12px;
    margin-top: 30px;
    text-align: left;
}

.timeline-title {
    font-weight: 700;
    color: #495057;
    margin-bottom: 15px;
    font-size: 16px;
}

.timeline-item {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
    font-size: 14px;
    color: #6c757d;
}

.timeline-icon {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: #dee2e6;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 8px;
}

.timeline-current {
    background: #ffc107;
    color: white;
}

/* Updated Responsive Design */
@media (max-width: 768px) {
    main {
        margin-left: 0;
        padding: 20px 15px;
        width: 100vw;
    }
    
    .thank-you-container {
        padding: 40px 25px;
        border-radius: 15px;
        margin-top: 10px;
        margin-bottom: 20px;
    }
    
    .thank-you-title {
        font-size: 28px;
    }
    
    .success-icon {
        font-size: 60px;
    }
    
    .details-grid {
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
    main {
        padding: 15px 10px;
    }
    
    .thank-you-container {
        padding: 30px 20px;
        border-radius: 10px;
        margin-top: 5px;
        margin-bottom: 15px;
    }
    
    .thank-you-title {
        font-size: 24px;
    }
    
    .success-icon {
        font-size: 50px;
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
        <div class="thank-you-container">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            
            <h1 class="thank-you-title">Thank You for Your Donation!</h1>
            <p class="thank-you-subtitle">
                Your generous contribution can help save lives. We appreciate your commitment to helping others in need.
            </p>
            
            <div class="status-notice">
                <h4>
                    <i class="fas fa-clock"></i>
                    Your Donation is Pending Approval
                </h4>
                <p>
                    <strong>Important:</strong> Your donation submission has been received and is currently under review by our medical staff. 
                    It will remain in "Pending" status until approved by our admin team. Once approved, you'll be contacted to schedule 
                    your donation appointment.
                </p>
            </div>
            
            <div class="donation-details">
                <div class="details-title">
                    <i class="fas fa-receipt"></i>
                    Donation Details
                </div>
                <div class="details-grid">
                    <div class="detail-item">
                        <div class="detail-label">Donation Ticket</div>
                        <div class="detail-value ticket-highlight"><?php echo $ticket_id; ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Blood Type</div>
                        <div class="detail-value"><?php echo htmlspecialchars($donation_data['blood_type']); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Donor Name</div>
                        <div class="detail-value"><?php echo htmlspecialchars($donation_data['donor_name']); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Submission Date</div>
                        <div class="detail-value"><?php echo date('M j, Y g:i A', strtotime($donation_data['date_submitted'])); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Hospital Location</div>
                        <div class="detail-value"><?php echo htmlspecialchars(substr($donation_data['hospital_location'], 0, 50)); ?>...</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Current Status</div>
                        <div class="detail-value" style="color: #ffc107; font-weight: 700;">
                            <i class="fas fa-clock"></i> Pending Approval
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="next-steps">
                <h4>
                    <i class="fas fa-list-check"></i>
                    What Happens Next?
                </h4>
                <ol class="steps-list">
                    <li>
                        <div class="step-number">1</div>
                        <div>Our medical staff will review your donation submission and medical history</div>
                    </li>
                    <li>
                        <div class="step-number">2</div>
                        <div>If approved, your donation status will change to "Approved" and you'll be contacted</div>
                    </li>
                    <li>
                        <div class="step-number">3</div>
                        <div>Schedule your donation appointment at your preferred hospital location</div>
                    </li>
                    <li>
                        <div class="step-number">4</div>
                        <div>Complete your donation and help save lives!</div>
                    </li>
                </ol>
            </div>
            
            <div class="timeline-info">
                <div class="timeline-title">Approval Timeline</div>
                <div class="timeline-item">
                    <div class="timeline-icon timeline-current">
                        <i class="fas fa-check"></i>
                    </div>
                    <div><strong>Now:</strong> Donation submitted and under review</div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-icon">1</div>
                    <div><strong>Within 24-48 hours:</strong> Medical staff review and approval decision</div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-icon">2</div>
                    <div><strong>If approved:</strong> Contact for appointment scheduling</div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-icon">3</div>
                    <div><strong>Donation day:</strong> Complete your life-saving donation</div>
                </div>
            </div>
            
            <div class="action-buttons">
                <a href="appointments.php" class="action-btn btn-primary">
                    <i class="fas fa-calendar-alt"></i>
                    Track My Donation
                </a>
                <a href="index.php" class="action-btn btn-secondary">
                    <i class="fas fa-tachometer-alt"></i>
                    Go to Dashboard
                </a>
                <a href="donate.php" class="action-btn btn-outline">
                    <i class="fas fa-heart"></i>
                    Donate Again Later
                </a>
            </div>
        </div>
                    <br><br>

    </main>
    
    <script>
        // Auto-redirect after 10 seconds (optional)
        let countdown = 10;
        const redirectTimer = setInterval(() => {
            countdown--;
            if (countdown <= 0) {
                clearInterval(redirectTimer);
                // Uncomment the line below if you want auto-redirect
                // window.location.href = 'dashboard.php';
            }
        }, 1000);
        
        // Add some celebratory animation
        document.addEventListener('DOMContentLoaded', function() {
            // Animate success icon
            const successIcon = document.querySelector('.success-icon');
            if (successIcon) {
                setTimeout(() => {
                    successIcon.style.transform = 'scale(1.1)';
                    setTimeout(() => {
                        successIcon.style.transform = 'scale(1)';
                    }, 200);
                }, 500);
            }
            
            // Animate detail items
            const detailItems = document.querySelectorAll('.detail-item');
            detailItems.forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    item.style.transition = 'all 0.5s ease';
                    item.style.opacity = '1';
                    item.style.transform = 'translateY(0)';
                }, 200 + (index * 100));
            });
            
            // Print functionality for donation receipt
            const ticketElement = document.querySelector('.ticket-highlight');
            if (ticketElement) {
                ticketElement.addEventListener('click', function() {
                    // Copy ticket ID to clipboard
                    navigator.clipboard.writeText(this.textContent).then(() => {
                        // Show temporary tooltip
                        const tooltip = document.createElement('div');
                        tooltip.textContent = 'Ticket ID copied!';
                        tooltip.style.cssText = `
                            position: absolute;
                            background: #28a745;
                            color: white;
                            padding: 5px 10px;
                            border-radius: 5px;
                            font-size: 12px;
                            z-index: 1000;
                            pointer-events: none;
                            transform: translateX(-50%);
                        `;
                        
                        this.parentNode.style.position = 'relative';
                        this.parentNode.appendChild(tooltip);
                        
                        setTimeout(() => {
                            if (tooltip.parentNode) {
                                tooltip.parentNode.removeChild(tooltip);
                            }
                        }, 2000);
                    }).catch(() => {
                        console.log('Could not copy ticket ID');
                    });
                });
                
                // Add hover effect to indicate clickability
                ticketElement.style.cursor = 'pointer';
                ticketElement.title = 'Click to copy ticket ID';
            }
            
            console.log('Thank you page initialized successfully');
        });
        
        // Add some confetti effect (optional)
        function createConfetti() {
            const colors = ['#dc3545', '#28a745', '#17a2b8', '#ffc107'];
            for (let i = 0; i < 50; i++) {
                const confetti = document.createElement('div');
                confetti.style.cssText = `
                    position: fixed;
                    width: 10px;
                    height: 10px;
                    background: ${colors[Math.floor(Math.random() * colors.length)]};
                    left: ${Math.random() * 100}vw;
                    top: -10px;
                    z-index: 1000;
                    pointer-events: none;
                    animation: fall ${Math.random() * 3 + 2}s linear forwards;
                `;
                document.body.appendChild(confetti);
                
                setTimeout(() => {
                    if (confetti.parentNode) {
                        confetti.parentNode.removeChild(confetti);
                    }
                }, 5000);
            }
        }
        
        // Add CSS for confetti animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fall {
                to {
                    transform: translateY(100vh) rotate(360deg);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
        
        // Trigger confetti on page load
        setTimeout(createConfetti, 500);
    </script>
    
    <?php include('includes/footer.php'); ?>
</body>
</html>