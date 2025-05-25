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

$message = '';
$messageType = '';
$donation = null;

// Get donation ID from URL
$donation_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$donation_id) {
    header('Location: appointments.php');
    exit();
}

// Get donation details
try {
    $donationQuery = "SELECT d.*, l.username 
                     FROM donate_blood d 
                     JOIN login l ON d.donor_id = l.user_id 
                     WHERE d.donation_id = ? AND d.donor_id = ?";
    $donationStmt = $conn->prepare($donationQuery);
    $donationStmt->bind_param("ii", $donation_id, $_SESSION['user_id']);
    $donationStmt->execute();
    $donationResult = $donationStmt->get_result();
    
    if ($donationResult->num_rows === 0) {
        header('Location: appointments.php?error=donation_not_found');
        exit();
    }
    
    $donation = $donationResult->fetch_assoc();
    $donationStmt->close();
    
} catch (Exception $e) {
    error_log("Error retrieving donation details: " . $e->getMessage());
    $message = "Error retrieving donation details. Please try again.";
    $messageType = "error";
}

// Generate donation ticket
function generateDonationTicket($donationData) {
    $created_date = isset($donationData['created_at']) ? $donationData['created_at'] : date('Y-m-d H:i:s');
    return 'PBB-' . str_pad($donationData['donation_id'], 4, '0', STR_PAD_LEFT) . '-' . 
           strtoupper($donationData['blood_type']) . '-' . 
           date('Ymd', strtotime($created_date));
}

// Hospital information with visiting hours
function getHospitalDetails($hospitalLocation) {
    $hospitals = [
        "St. Mercy General Hospital - 123 Aurora Blvd, Quezon City, Metro Manila, Philippines" => [
            "name" => "St. Mercy General Hospital",
            "address" => "123 Aurora Blvd, Quezon City, Metro Manila, Philippines",
            "phone" => "+63 2 8123-4567",
            "visiting_hours" => "Monday - Friday: 8:00 AM - 6:00 PM<br>Saturday: 8:00 AM - 4:00 PM<br>Sunday: 10:00 AM - 2:00 PM",
            "department" => "Blood Donation Center - 2nd Floor, Wing A",
            "parking" => "Free parking available",
            "requirements" => "Valid ID required. Fasting not required.",
            "contact_person" => "Dr. Maria Santos",
            "email" => "bloodbank@stmercy.ph"
        ],
        "MetroCare Medical Center - 456 West Avenue, Quezon City, Metro Manila, Philippines" => [
            "name" => "MetroCare Medical Center",
            "address" => "456 West Avenue, Quezon City, Metro Manila, Philippines",
            "phone" => "+63 2 8234-5678",
            "visiting_hours" => "Monday - Saturday: 7:00 AM - 7:00 PM<br>Sunday: 9:00 AM - 3:00 PM",
            "department" => "Hematology Department - Ground Floor",
            "parking" => "Paid parking - PHP 20/hour",
            "requirements" => "Valid ID and donation confirmation required.",
            "contact_person" => "Dr. Jose Reyes",
            "email" => "donations@metrocare.ph"
        ],
        "Hopewell Community Hospital - 87 Mabini St, Calamba, Laguna, Philippines" => [
            "name" => "Hopewell Community Hospital",
            "address" => "87 Mabini St, Calamba, Laguna, Philippines",
            "phone" => "+63 49 502-3456",
            "visiting_hours" => "Monday - Friday: 8:00 AM - 5:00 PM<br>Saturday: 8:00 AM - 12:00 PM<br>Sunday: Closed",
            "department" => "Laboratory Services - 1st Floor",
            "parking" => "Free parking available",
            "requirements" => "Government ID and pre-donation form required.",
            "contact_person" => "Dr. Ana Cruz",
            "email" => "lab@hopewell.ph"
        ],
        "Sunrise Health Center - 99 J.P. Rizal Ave, Makati City, Metro Manila, Philippines" => [
            "name" => "Sunrise Health Center",
            "address" => "99 J.P. Rizal Ave, Makati City, Metro Manila, Philippines",
            "phone" => "+63 2 8345-6789",
            "visiting_hours" => "Monday - Friday: 7:30 AM - 6:30 PM<br>Saturday: 8:00 AM - 3:00 PM<br>Sunday: Emergency only",
            "department" => "Blood Bank Services - 3rd Floor",
            "parking" => "Valet parking available - PHP 50",
            "requirements" => "Valid ID, weight minimum 50kg.",
            "contact_person" => "Dr. Michael Tan",
            "email" => "bloodbank@sunrise.ph"
        ],
        "Nueva Vida Medical Institute - 210 Bonifacio Street, Davao City, Davao del Sur, Philippines" => [
            "name" => "Nueva Vida Medical Institute",
            "address" => "210 Bonifacio Street, Davao City, Davao del Sur, Philippines",
            "phone" => "+63 82 234-5678",
            "visiting_hours" => "Monday - Saturday: 8:00 AM - 6:00 PM<br>Sunday: 10:00 AM - 2:00 PM",
            "department" => "Transfusion Medicine - 2nd Floor, East Wing",
            "parking" => "Free parking for donors",
            "requirements" => "Valid ID and health declaration form.",
            "contact_person" => "Dr. Carmen Lopez",
            "email" => "donate@nuevavida.ph"
        ],
        "Unity Regional Hospital - 300 Lopez Jaena St, Iloilo City, Iloilo, Philippines" => [
            "name" => "Unity Regional Hospital",
            "address" => "300 Lopez Jaena St, Iloilo City, Iloilo, Philippines",
            "phone" => "+63 33 336-7890",
            "visiting_hours" => "Monday - Friday: 8:00 AM - 5:30 PM<br>Saturday: 8:00 AM - 1:00 PM<br>Sunday: Emergency only",
            "department" => "Blood Services Unit - Ground Floor",
            "parking" => "Free parking available",
            "requirements" => "Government-issued ID required.",
            "contact_person" => "Dr. Roberto Flores",
            "email" => "bloodservices@unity.ph"
        ],
        "Greenfields Medical Plaza - 45 Don Rufino Alonzo St, Baguio City, Benguet, Philippines" => [
            "name" => "Greenfields Medical Plaza",
            "address" => "45 Don Rufino Alonzo St, Baguio City, Benguet, Philippines",
            "phone" => "+63 74 442-3456",
            "visiting_hours" => "Monday - Friday: 8:30 AM - 5:00 PM<br>Saturday: 9:00 AM - 2:00 PM<br>Sunday: Closed",
            "department" => "Clinical Laboratory - 1st Floor, Suite 105",
            "parking" => "Limited free parking",
            "requirements" => "Valid ID and appointment confirmation.",
            "contact_person" => "Dr. Grace Mendoza",
            "email" => "lab@greenfields.ph"
        ],
        "WellnessPoint Hospital - 678 Ortigas Ave, Pasig City, Metro Manila, Philippines" => [
            "name" => "WellnessPoint Hospital",
            "address" => "678 Ortigas Ave, Pasig City, Metro Manila, Philippines",
            "phone" => "+63 2 8456-7890",
            "visiting_hours" => "Daily: 24 hours (Blood donation: Mon-Sat 8AM-6PM)",
            "department" => "Blood Bank - 4th Floor, Medical Tower",
            "parking" => "Multi-level parking - PHP 30/hour",
            "requirements" => "Valid ID, pre-screening questionnaire.",
            "contact_person" => "Dr. Patricia Valdez",
            "email" => "bloodbank@wellnesspoint.ph"
        ],
        "Cedar Hill Medical Complex - 12 Gen. Luna St, San Fernando, Pampanga, Philippines" => [
            "name" => "Cedar Hill Medical Complex",
            "address" => "12 Gen. Luna St, San Fernando, Pampanga, Philippines",
            "phone" => "+63 45 961-2345",
            "visiting_hours" => "Monday - Friday: 8:00 AM - 6:00 PM<br>Saturday: 8:00 AM - 4:00 PM<br>Sunday: 10:00 AM - 2:00 PM",
            "department" => "Pathology & Blood Bank - Ground Floor",
            "parking" => "Free parking for patients and donors",
            "requirements" => "Valid ID and completed health form.",
            "contact_person" => "Dr. Antonio Garcia",
            "email" => "pathology@cedarhill.ph"
        ],
        "Bayview General Medical Center - 81 Roxas Blvd, Parañaque City, Metro Manila, Philippines" => [
            "name" => "Bayview General Medical Center",
            "address" => "81 Roxas Blvd, Parañaque City, Metro Manila, Philippines",
            "phone" => "+63 2 8567-8901",
            "visiting_hours" => "Monday - Saturday: 7:00 AM - 7:00 PM<br>Sunday: 9:00 AM - 4:00 PM",
            "department" => "Blood Transfusion Services - 2nd Floor",
            "parking" => "Free 3-hour parking for donors",
            "requirements" => "Government ID and donor eligibility form.",
            "contact_person" => "Dr. Elena Rodriguez",
            "email" => "bloodservices@bayview.ph"
        ]
    ];
    
    return isset($hospitals[$hospitalLocation]) ? $hospitals[$hospitalLocation] : [
        "name" => "Hospital Information Not Available",
        "address" => $hospitalLocation,
        "phone" => "Contact hospital directly",
        "visiting_hours" => "Please call hospital for hours",
        "department" => "Blood Donation Department",
        "parking" => "Contact hospital for parking info",
        "requirements" => "Valid ID required",
        "contact_person" => "Blood Bank Staff",
        "email" => "Contact hospital directly"
    ];
}

// Calculate deadline for approved donations
function calculateDeadline($approvalDate) {
    return date('M j, Y g:i A', strtotime($approvalDate . ' +5 days'));
}

// Check if donation is expired (approved more than 5 days ago)
function isDonationExpired($donation) {
    if ($donation['donation_status'] === 'Approved' && isset($donation['updated_at'])) {
        $approvalTime = strtotime($donation['updated_at']);
        $currentTime = time();
        $fiveDaysInSeconds = 5 * 24 * 60 * 60; // 5 days in seconds
        
        return ($currentTime - $approvalTime) > $fiveDaysInSeconds;
    }
    return false;
}

// Get days remaining for approved donation
function getDaysRemaining($donation) {
    if ($donation['donation_status'] === 'Approved' && isset($donation['updated_at'])) {
        $approvalTime = strtotime($donation['updated_at']);
        $deadlineTime = $approvalTime + (5 * 24 * 60 * 60); // 5 days later
        $currentTime = time();
        
        $secondsRemaining = $deadlineTime - $currentTime;
        $daysRemaining = floor($secondsRemaining / (24 * 60 * 60));
        
        return max(0, $daysRemaining);
    }
    return null;
}

if ($donation) {
    $ticket_id = generateDonationTicket($donation);
    $hospital_details = getHospitalDetails($donation['hospital_location']);
    $is_expired = isDonationExpired($donation);
    $days_remaining = getDaysRemaining($donation);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pirate's Blood Bank - Donation Details</title>
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
        }
        
        .details-container {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            box-shadow: 2px 0 15px rgba(220, 220, 220, 0.1);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: none;
            margin: 0;
            box-sizing: border-box;
        }
        
        .details-header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 25px;
        }
        
        .details-title {
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
        
        .details-title i {
            color: #dc3545;
            font-size: 28px;
        }
        
        .details-subtitle {
            color: #6c757d;
            font-size: 16px;
            font-weight: 500;
        }
        
        .ticket-section {
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .ticket-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="2" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
            background-size: 20px 20px;
        }
        
        .ticket-content {
            position: relative;
            z-index: 1;
        }
        
        .ticket-id {
            font-size: 36px;
            font-weight: 700;
            font-family: 'Courier New', monospace;
            letter-spacing: 2px;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .ticket-subtitle {
            font-size: 16px;
            opacity: 0.9;
            margin-bottom: 20px;
        }
        
        .ticket-qr {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 10px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: #dc3545;
        }
        
        .status-section {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 20px;
            align-items: center;
            background: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-left: 5px solid;
        }
        
        .status-pending { border-left-color: #ffc107; }
        .status-approved { border-left-color: #17a2b8; }
        .status-completed { border-left-color: #28a745; }
        .status-rejected { border-left-color: #dc3545; }
        .status-cancelled { border-left-color: #6c757d; }
        
        .status-info h3 {
            margin: 0 0 10px 0;
            font-size: 20px;
            font-weight: 700;
            color: #495057;
        }
        
        .status-description {
            color: #6c757d;
            line-height: 1.6;
        }
        
        .status-badge {
            padding: 15px 25px;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 10px;
            white-space: nowrap;
        }
        
        .badge-pending {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: white;
        }
        
        .badge-approved {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
        }
        
        .badge-completed {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            color: white;
        }
        
        .badge-rejected {
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
        }
        
        .badge-cancelled {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
        }
        
        .deadline-notice {
            background: linear-gradient(90deg, rgba(255, 193, 7, 0.1), rgba(255, 193, 7, 0.05));
            color: #856404;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            border-left: 4px solid #ffc107;
            position: relative;
        }
        
        .deadline-urgent {
            background: linear-gradient(90deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));
            color: #721c24;
            border-left-color: #dc3545;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
        }
        
        .deadline-notice h4 {
            margin: 0 0 15px 0;
            font-size: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .countdown {
            display: flex;
            gap: 20px;
            margin-top: 15px;
        }
        
        .countdown-item {
            text-align: center;
            background: rgba(255, 255, 255, 0.5);
            padding: 10px 15px;
            border-radius: 10px;
        }
        
        .countdown-number {
            font-size: 24px;
            font-weight: 700;
            display: block;
        }
        
        .countdown-label {
            font-size: 12px;
            text-transform: uppercase;
            opacity: 0.8;
        }
        
        .donation-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .detail-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-top: 4px solid #dc3545;
        }
        
        .detail-card h4 {
            margin: 0 0 20px 0;
            font-size: 18px;
            font-weight: 700;
            color: #495057;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .detail-card h4 i {
            color: #dc3545;
        }
        
        .detail-item {
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 10px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .detail-item:last-child {
            margin-bottom: 0;
            border-bottom: none;
            padding-bottom: 0;
        }
        
        .detail-label {
            font-weight: 600;
            color: #6c757d;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            flex-shrink: 0;
            width: 40%;
        }
        
        .detail-value {
            color: #495057;
            font-weight: 500;
            text-align: right;
            flex-grow: 1;
            word-break: break-word;
        }
        
        .hospital-info {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .hospital-info h3 {
            margin: 0 0 20px 0;
            font-size: 24px;
            font-weight: 700;
            color: #0d47a1;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .hospital-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .hospital-item {
            background: rgba(255, 255, 255, 0.8);
            padding: 20px;
            border-radius: 12px;
            backdrop-filter: blur(10px);
        }
        
        .hospital-item h5 {
            margin: 0 0 10px 0;
            font-size: 16px;
            font-weight: 700;
            color: #0d47a1;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .hospital-item p {
            margin: 0;
            color: #495057;
            line-height: 1.6;
        }
        
        .visiting-hours {
            background: linear-gradient(90deg, rgba(40, 167, 69, 0.1), rgba(40, 167, 69, 0.05));
            color: #155724;
            padding: 20px;
            border-radius: 12px;
            border-left: 4px solid #28a745;
        }
        
        .action-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 30px;
                        margin-bottom: 80px;

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
        
        .btn-secondary {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
        }
        
        .btn-outline {
            background: white;
            color: #6c757d;
            border: 2px solid #6c757d;
        }
        
        .action-btn:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
            text-decoration: none;
            color: white;
        }
        
        .btn-outline:hover {
            background: #6c757d;
            color: white;
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
        
        /* Responsive Design */
        @media (max-width: 768px) {
            main {
                margin-left: 0;
                padding: 15px;
                width: 100vw;
            }
            
            .details-container {
                padding: 25px;
                border-radius: 15px;
            }
            
            .details-title {
                font-size: 24px;
            }
            
            .ticket-id {
                font-size: 24px;
            }
            
            .donation-details,
            .hospital-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .status-section {
                grid-template-columns: 1fr;
                text-align: center;
            }
            
            .countdown {
                justify-content: center;
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
            .details-container {
                padding: 20px;
                border-radius: 10px;
            }
            
            .details-title {
                font-size: 20px;
            }
            
            .ticket-id {
                font-size: 20px;
            }
            
            .countdown {
                flex-direction: column;
                gap: 10px;
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
            
            .ticket-section {
                background: #f8f9fa !important;
                color: #000 !important;
                border: 2px solid #dc3545;
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
    <div class="details-container">
        <div class="details-header">
            <h1 class="details-title">
                <i class="fas fa-receipt"></i>
                Donation Details
            </h1>
            <p class="details-subtitle">Complete information about your blood donation</p>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php if ($messageType == 'success'): ?>
                    <i class="fas fa-check-circle"></i>
                <?php else: ?>
                    <i class="fas fa-exclamation-triangle"></i>
                <?php endif; ?>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($donation): ?>
            <!-- Donation Ticket -->
            <div class="ticket-section">
                <div class="ticket-content">
                    <div class="ticket-id"><?php echo $ticket_id; ?></div>
                    <div class="ticket-subtitle">Blood Donation Confirmation Ticket</div>
                    <div class="ticket-qr">
                        <i class="fas fa-qrcode"></i>
                    </div>
                </div>
            </div>
            
            <!-- Status Section -->
            <div class="status-section status-<?php echo strtolower($donation['donation_status']); ?>">
                <div class="status-info">
                    <h3>Current Status</h3>
                    <div class="status-description">
                        <?php
                        switch($donation['donation_status']) {
                            case 'Pending':
                                echo "Your donation is under review by our medical staff. You will be notified once it's approved.";
                                break;
                            case 'Approved':
                                echo "Great news! Your donation has been approved. Please visit the hospital within 5 days to complete your donation.";
                                break;
                            case 'Completed':
                                echo "Thank you! Your donation has been completed successfully and is now helping save lives.";
                                break;
                            case 'Rejected':
                                echo "Unfortunately, your donation could not be approved at this time. Please contact us for more information.";
                                break;
                            case 'Cancelled':
                                echo "This donation has been cancelled. You can submit a new donation request anytime.";
                                break;
                        }
                        ?>
                    </div>
                </div>
                <div class="status-badge badge-<?php echo strtolower($donation['donation_status']); ?>">
                    <i class="<?php 
                        switch($donation['donation_status']) {
                            case 'Pending': echo 'fas fa-clock'; break;
                            case 'Approved': echo 'fas fa-check'; break;
                            case 'Completed': echo 'fas fa-check-double'; break;
                            case 'Rejected': echo 'fas fa-times'; break;
                            case 'Cancelled': echo 'fas fa-ban'; break;
                        }
                    ?>"></i>
                    <?php echo $donation['donation_status']; ?>
                </div>
            </div>
            
            <!-- Deadline Notice for Approved Donations -->
            <?php if ($donation['donation_status'] === 'Approved'): ?>
                <div class="deadline-notice <?php echo ($days_remaining !== null && $days_remaining <= 1) ? 'deadline-urgent' : ''; ?>">
                    <h4>
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo ($days_remaining !== null && $days_remaining <= 1) ? 'URGENT: Donation Deadline' : 'Important: Donation Deadline'; ?>
                    </h4>
                    <p>
                        <strong>Your approved donation must be completed within 5 days or it will be automatically cancelled.</strong>
                        <?php if (isset($donation['updated_at'])): ?>
                            Deadline: <strong><?php echo calculateDeadline($donation['updated_at']); ?></strong>
                        <?php endif; ?>
                    </p>
                    
                    <?php if ($days_remaining !== null): ?>
                        <div class="countdown">
                            <div class="countdown-item">
                                <span class="countdown-number"><?php echo $days_remaining; ?></span>
                                <span class="countdown-label">Days Left</span>
                            </div>
                            <div class="countdown-item">
                                <span class="countdown-number"><?php 
                                    if ($days_remaining > 0) {
                                        $hoursLeft = ($days_remaining * 24) + floor((strtotime($donation['updated_at'] . ' +5 days') - time()) / 3600) % 24;
                                        echo max(0, $hoursLeft);
                                    } else {
                                        echo "0";
                                    }
                                ?></span>
                                <span class="countdown-label">Hours Left</span>
                            </div>
                        </div>
                        
                        <?php if ($days_remaining <= 1): ?>
                            <p style="margin-top: 15px; font-weight: 700;">
                                <i class="fas fa-bell"></i>
                                Please visit the hospital immediately to complete your donation!
                            </p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Donation Details Grid -->
            <div class="donation-details">
                <div class="detail-card">
                    <h4><i class="fas fa-user"></i> Donor Information</h4>
                    <div class="detail-item">
                        <span class="detail-label">Donor Name</span>
                        <span class="detail-value"><?php echo htmlspecialchars($donation['username']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Blood Type</span>
                        <span class="detail-value" style="font-weight: 700; color: #dc3545; font-size: 18px;"><?php echo htmlspecialchars($donation['blood_type']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Weight</span>
                        <span class="detail-value"><?php echo $donation['weight']; ?> kg</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Donation ID</span>
                        <span class="detail-value">#<?php echo $donation['donation_id']; ?></span>
                    </div>
                </div>
                
                <div class="detail-card">
                    <h4><i class="fas fa-calendar-alt"></i> Timeline</h4>
                    <div class="detail-item">
                        <span class="detail-label">Submitted</span>
                        <span class="detail-value"><?php echo isset($donation['created_at']) ? date('M j, Y g:i A', strtotime($donation['created_at'])) : 'N/A'; ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Preferred Date</span>
                        <span class="detail-value"><?php echo date('M j, Y', strtotime($donation['date_of_donation'])); ?></span>
                    </div>
                    <?php if (isset($donation['updated_at']) && $donation['donation_status'] !== 'Pending'): ?>
                        <div class="detail-item">
                            <span class="detail-label">Last Updated</span>
                            <span class="detail-value"><?php echo date('M j, Y g:i A', strtotime($donation['updated_at'])); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($donation['donation_status'] === 'Approved' && isset($donation['updated_at'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Deadline</span>
                            <span class="detail-value" style="color: #dc3545; font-weight: 700;"><?php echo calculateDeadline($donation['updated_at']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Hospital Information -->
            <div class="hospital-info">
                <h3><i class="fas fa-hospital"></i> <?php echo htmlspecialchars($hospital_details['name']); ?></h3>
                
                <div class="hospital-grid">
                    <div class="hospital-item">
                        <h5><i class="fas fa-map-marker-alt"></i> Address</h5>
                        <p><?php echo htmlspecialchars($hospital_details['address']); ?></p>
                    </div>
                    
                    <div class="hospital-item">
                        <h5><i class="fas fa-phone"></i> Contact</h5>
                        <p>
                            <strong>Phone:</strong> <?php echo htmlspecialchars($hospital_details['phone']); ?><br>
                            <strong>Email:</strong> <?php echo htmlspecialchars($hospital_details['email']); ?>
                        </p>
                    </div>
                    
                    <div class="hospital-item">
                        <h5><i class="fas fa-building"></i> Department</h5>
                        <p><?php echo htmlspecialchars($hospital_details['department']); ?></p>
                        <p><strong>Contact Person:</strong> <?php echo htmlspecialchars($hospital_details['contact_person']); ?></p>
                    </div>
                    
                    <div class="hospital-item">
                        <h5><i class="fas fa-car"></i> Parking</h5>
                        <p><?php echo htmlspecialchars($hospital_details['parking']); ?></p>
                        <p><strong>Requirements:</strong> <?php echo htmlspecialchars($hospital_details['requirements']); ?></p>
                    </div>
                </div>
                
                <div class="visiting-hours" style="margin-top: 20px;">
                    <h5 style="margin: 0 0 10px 0; font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-clock"></i> Visiting Hours
                    </h5>
                    <p style="margin: 0; line-height: 1.8;"><?php echo $hospital_details['visiting_hours']; ?></p>
                </div>
            </div>
            
            <!-- Medical History (if admin) -->
            <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin'): ?>
                <div class="detail-card" style="grid-column: 1 / -1;">
                    <h4><i class="fas fa-notes-medical"></i> Medical History</h4>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; white-space: pre-wrap; line-height: 1.6;">
                        <?php echo htmlspecialchars($donation['medical_history']); ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Admin Notes (if exists and user is admin) -->
            <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin' && !empty($donation['admin_notes'])): ?>
                <div class="detail-card" style="grid-column: 1 / -1; border-top-color: #17a2b8;">
                    <h4><i class="fas fa-user-shield"></i> Admin Notes</h4>
                    <div style="background: #e3f2fd; padding: 15px; border-radius: 8px; white-space: pre-wrap; line-height: 1.6; color: #0d47a1;">
                        <?php echo htmlspecialchars($donation['admin_notes']); ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Action Buttons -->
            <div class="action-buttons">
                <?php if ($donation['donation_status'] === 'Pending'): ?>
                    <a href="update-donation.php?id=<?php echo $donation['donation_id']; ?>" class="action-btn btn-primary">
                        <i class="fas fa-edit"></i>
                        Update Details
                    </a>
                <?php endif; ?>
                
                <?php if ($donation['donation_status'] === 'Approved'): ?>
                    <a href="tel:<?php echo str_replace([' ', '-', '(', ')'], '', $hospital_details['phone']); ?>" class="action-btn btn-primary">
                        <i class="fas fa-phone"></i>
                        Call Hospital
                    </a>
                    <a href="mailto:<?php echo $hospital_details['email']; ?>?subject=Blood Donation Appointment - <?php echo $ticket_id; ?>" class="action-btn btn-secondary">
                        <i class="fas fa-envelope"></i>
                        Email Hospital
                    </a>
                <?php endif; ?>
                
                <button onclick="window.print()" class="action-btn btn-outline">
                    <i class="fas fa-print"></i>
                    Print Details
                </button>
                
                <a href="appointments.php" class="action-btn btn-outline">
                    <i class="fas fa-arrow-left"></i>
                    Back to Appointments
                </a>
            </div>
            
        <?php else: ?>
            <div class="message error">
                <i class="fas fa-exclamation-triangle"></i>
                Donation not found or you don't have permission to view it.
            </div>
            <div class="action-buttons">
                <a href="appointments.php" class="action-btn btn-primary">
                    <i class="fas fa-arrow-left"></i>
                    Back to Appointments
                </a>
            </div>
        <?php endif; ?>
            <br><br>

    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Real-time countdown for approved donations
    <?php if ($donation && $donation['donation_status'] === 'Approved' && isset($donation['updated_at'])): ?>
    function updateCountdown() {
        const approvalTime = new Date('<?php echo $donation['updated_at']; ?>').getTime();
        const deadlineTime = approvalTime + (5 * 24 * 60 * 60 * 1000); // 5 days later
        const now = new Date().getTime();
        const timeLeft = deadlineTime - now;
        
        if (timeLeft > 0) {
            const days = Math.floor(timeLeft / (1000 * 60 * 60 * 24));
            const hours = Math.floor((timeLeft % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
            
            const daysElement = document.querySelector('.countdown-item:first-child .countdown-number');
            const hoursElement = document.querySelector('.countdown-item:nth-child(2) .countdown-number');
            
            if (daysElement) daysElement.textContent = days;
            if (hoursElement) hoursElement.textContent = hours;
            
            // Add urgency styling if less than 24 hours
            if (timeLeft < 24 * 60 * 60 * 1000) {
                const deadlineNotice = document.querySelector('.deadline-notice');
                if (deadlineNotice) {
                    deadlineNotice.classList.add('deadline-urgent');
                }
            }
        } else {
            // Deadline passed
            const deadlineNotice = document.querySelector('.deadline-notice');
            if (deadlineNotice) {
                deadlineNotice.innerHTML = `
                    <h4><i class="fas fa-exclamation-circle"></i> Donation Expired</h4>
                    <p style="color: #721c24; font-weight: 700;">
                        This donation has expired and may be automatically cancelled.
                        Please contact the hospital or submit a new donation request.
                    </p>
                `;
                deadlineNotice.classList.add('deadline-urgent');
            }
        }
    }
    
    // Update countdown every minute
    updateCountdown();
    setInterval(updateCountdown, 60000);
    <?php endif; ?>
    
    // Copy ticket ID functionality
    const ticketId = document.querySelector('.ticket-id');
    if (ticketId) {
        ticketId.style.cursor = 'pointer';
        ticketId.title = 'Click to copy ticket ID';
        
        ticketId.addEventListener('click', function() {
            navigator.clipboard.writeText(this.textContent).then(() => {
                // Show temporary feedback
                const originalText = this.textContent;
                this.textContent = 'COPIED!';
                this.style.backgroundColor = 'rgba(255,255,255,0.2)';
                
                setTimeout(() => {
                    this.textContent = originalText;
                    this.style.backgroundColor = 'transparent';
                }, 1000);
            }).catch(() => {
                console.log('Could not copy ticket ID');
            });
        });
    }
    
    // Smooth scroll for internal links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Add animation to cards on scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);
    
    // Observe all cards
    document.querySelectorAll('.detail-card, .hospital-item').forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(card);
    });
    
    // Add print-specific styling
    const printButton = document.querySelector('button[onclick="window.print()"]');
    if (printButton) {
        printButton.addEventListener('click', function() {
            // Add print-specific class before printing
            document.body.classList.add('printing');
            
            setTimeout(() => {
                window.print();
                document.body.classList.remove('printing');
            }, 100);
        });
    }
    
    // Auto-refresh page if approved donation is about to expire
    <?php if ($donation && $donation['donation_status'] === 'Approved' && $days_remaining !== null && $days_remaining <= 1): ?>
    // Refresh page every 5 minutes for urgent donations
    setInterval(() => {
        window.location.reload();
    }, 5 * 60 * 1000);
    <?php endif; ?>
    
    console.log('Donation details page initialized successfully');
    console.log('Donation ID: <?php echo $donation ? $donation['donation_id'] : 'N/A'; ?>');
    console.log('Status: <?php echo $donation ? $donation['donation_status'] : 'N/A'; ?>');
});
</script>

</body>
<?php include('includes/footer.php'); ?>
</html>