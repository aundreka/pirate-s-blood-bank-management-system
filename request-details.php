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
$request = null;

// Get request ID from URL
$request_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$request_id) {
    header('Location: appointments.php');
    exit();
}

// Get request details with user information
try {
    $requestQuery = "SELECT r.*, l.username, l.user_type, l.created_at as user_created_at
                    FROM recipient_form r 
                    JOIN login l ON r.user_id = l.user_id 
                    WHERE r.request_id = ? AND r.user_id = ?";
    $requestStmt = $conn->prepare($requestQuery);
    $requestStmt->bind_param("ii", $request_id, $_SESSION['user_id']);
    $requestStmt->execute();
    $requestResult = $requestStmt->get_result();
    
    if ($requestResult->num_rows === 0) {
        header('Location: requests.php?error=request_not_found');
        exit();
    }
    
    $request = $requestResult->fetch_assoc();
    $requestStmt->close();
    
} catch (Exception $e) {
    error_log("Error retrieving request details: " . $e->getMessage());
    $message = "Error retrieving request details. Please try again.";
    $messageType = "error";
}

// Generate request ticket
function generateRequestTicket($requestData) {
    $created_date = isset($requestData['created_at']) ? $requestData['created_at'] : date('Y-m-d H:i:s');
    return 'PBR-' . str_pad($requestData['request_id'], 4, '0', STR_PAD_LEFT) . '-' . 
           strtoupper($requestData['blood_type_needed']) . '-' . 
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
            "department" => "Blood Transfusion Services - 3rd Floor, Wing B",
            "parking" => "Free parking available",
            "requirements" => "Valid ID and medical records required.",
            "contact_person" => "Dr. Maria Santos",
            "email" => "transfusion@stmercy.ph"
        ],
        "MetroCare Medical Center - 456 West Avenue, Quezon City, Metro Manila, Philippines" => [
            "name" => "MetroCare Medical Center",
            "address" => "456 West Avenue, Quezon City, Metro Manila, Philippines",
            "phone" => "+63 2 8234-5678",
            "visiting_hours" => "Monday - Saturday: 7:00 AM - 7:00 PM<br>Sunday: 9:00 AM - 3:00 PM",
            "department" => "Hematology & Transfusion Unit - 2nd Floor",
            "parking" => "Paid parking - PHP 20/hour",
            "requirements" => "Valid ID, medical prescription, and insurance required.",
            "contact_person" => "Dr. Jose Reyes",
            "email" => "hematology@metrocare.ph"
        ],
        "Hopewell Community Hospital - 87 Mabini St, Calamba, Laguna, Philippines" => [
            "name" => "Hopewell Community Hospital",
            "address" => "87 Mabini St, Calamba, Laguna, Philippines",
            "phone" => "+63 49 502-3456",
            "visiting_hours" => "Monday - Friday: 8:00 AM - 5:00 PM<br>Saturday: 8:00 AM - 12:00 PM<br>Sunday: Emergency only",
            "department" => "Blood Bank & Transfusion - Ground Floor",
            "parking" => "Free parking available",
            "requirements" => "Government ID, medical prescription required.",
            "contact_person" => "Dr. Ana Cruz",
            "email" => "bloodbank@hopewell.ph"
        ],
        "Sunrise Health Center - 99 J.P. Rizal Ave, Makati City, Metro Manila, Philippines" => [
            "name" => "Sunrise Health Center",
            "address" => "99 J.P. Rizal Ave, Makati City, Metro Manila, Philippines",
            "phone" => "+63 2 8345-6789",
            "visiting_hours" => "Monday - Friday: 7:30 AM - 6:30 PM<br>Saturday: 8:00 AM - 3:00 PM<br>Sunday: Emergency only",
            "department" => "Transfusion Medicine - 4th Floor",
            "parking" => "Valet parking available - PHP 50",
            "requirements" => "Valid ID, physician's order required.",
            "contact_person" => "Dr. Michael Tan",
            "email" => "transfusion@sunrise.ph"
        ],
        "Nueva Vida Medical Institute - 210 Bonifacio Street, Davao City, Davao del Sur, Philippines" => [
            "name" => "Nueva Vida Medical Institute",
            "address" => "210 Bonifacio Street, Davao City, Davao del Sur, Philippines",
            "phone" => "+63 82 234-5678",
            "visiting_hours" => "Monday - Saturday: 8:00 AM - 6:00 PM<br>Sunday: 10:00 AM - 2:00 PM",
            "department" => "Blood Services & Transfusion - 1st Floor",
            "parking" => "Free parking for patients",
            "requirements" => "Valid ID, medical prescription, and insurance.",
            "contact_person" => "Dr. Carmen Lopez",
            "email" => "bloodservices@nuevavida.ph"
        ],
        "Unity Regional Hospital - 300 Lopez Jaena St, Iloilo City, Iloilo, Philippines" => [
            "name" => "Unity Regional Hospital",
            "address" => "300 Lopez Jaena St, Iloilo City, Iloilo, Philippines",
            "phone" => "+63 33 336-7890",
            "visiting_hours" => "Monday - Friday: 8:00 AM - 5:30 PM<br>Saturday: 8:00 AM - 1:00 PM<br>Sunday: Emergency only",
            "department" => "Transfusion Services - 2nd Floor",
            "parking" => "Free parking available",
            "requirements" => "Government-issued ID and doctor's order.",
            "contact_person" => "Dr. Roberto Flores",
            "email" => "transfusion@unity.ph"
        ]
    ];
    
    return isset($hospitals[$hospitalLocation]) ? $hospitals[$hospitalLocation] : [
        "name" => "Hospital Information Not Available",
        "address" => $hospitalLocation,
        "phone" => "Contact hospital directly",
        "visiting_hours" => "Please call hospital for hours",
        "department" => "Blood Transfusion Department",
        "parking" => "Contact hospital for parking info",
        "requirements" => "Valid ID and medical prescription required",
        "contact_person" => "Blood Bank Staff",
        "email" => "Contact hospital directly"
    ];
}

// Generate available appointment slots for approved requests
function generateAppointmentSlots($approvalDate, $hospitalLocation) {
    $slots = [];
    $hospital_details = getHospitalDetails($hospitalLocation);
    
    // Generate slots for next 14 days from approval
    $startDate = new DateTime($approvalDate);
    $endDate = clone $startDate;
    $endDate->add(new DateInterval('P14D'));
    
    $currentDate = clone $startDate;
    while ($currentDate <= $endDate) {
        $dayOfWeek = $currentDate->format('N'); // 1 = Monday, 7 = Sunday
        
        // Skip Sundays for most hospitals (emergency only)
        if ($dayOfWeek == 7 && strpos($hospital_details['visiting_hours'], 'Emergency only') !== false) {
            $currentDate->add(new DateInterval('P1D'));
            continue;
        }
        
        // Generate time slots based on hospital hours
        $timeSlots = [];
        if ($dayOfWeek >= 1 && $dayOfWeek <= 5) { // Monday to Friday
            $timeSlots = ['9:00 AM', '11:00 AM', '2:00 PM', '4:00 PM'];
        } elseif ($dayOfWeek == 6) { // Saturday
            $timeSlots = ['9:00 AM', '11:00 AM', '1:00 PM'];
        } else { // Sunday (if available)
            $timeSlots = ['10:00 AM', '12:00 PM'];
        }
        
        foreach ($timeSlots as $time) {
            $slots[] = [
                'date' => $currentDate->format('Y-m-d'),
                'time' => $time,
                'formatted_date' => $currentDate->format('M j, Y'),
                'day_name' => $currentDate->format('l')
            ];
        }
        
        $currentDate->add(new DateInterval('P1D'));
    }
    
    return $slots;
}

// Check if request is high urgency and needs immediate attention
function isHighUrgencyRequest($request) {
    return $request['urgency_level'] === 'High' && 
           in_array($request['request_status'], ['Pending', 'Approved']);
}

// Calculate time since submission for urgent requests
function getTimeSinceSubmission($request) {
    if (isset($request['created_at'])) {
        $submissionTime = strtotime($request['created_at']);
        $currentTime = time();
        $timeDiff = $currentTime - $submissionTime;
        
        $hours = floor($timeDiff / 3600);
        $minutes = floor(($timeDiff % 3600) / 60);
        
        if ($hours > 0) {
            return "{$hours} hours, {$minutes} minutes ago";
        } else {
            return "{$minutes} minutes ago";
        }
    }
    return "Unknown";
}

// Calculate deadline for approved requests (14 days)
function calculateDeadline($approvalDate) {
    return date('M j, Y g:i A', strtotime($approvalDate . ' +14 days'));
}

// Get days remaining for approved request
function getDaysRemaining($request) {
    if ($request['request_status'] === 'Approved' && isset($request['updated_at'])) {
        $approvalTime = strtotime($request['updated_at']);
        $deadlineTime = $approvalTime + (14 * 24 * 60 * 60); // 14 days later
        $currentTime = time();
        
        $secondsRemaining = $deadlineTime - $currentTime;
        $daysRemaining = floor($secondsRemaining / (24 * 60 * 60));
        
        return max(0, $daysRemaining);
    }
    return null;
}

if ($request) {
    $ticket_id = generateRequestTicket($request);
    $hospital_details = getHospitalDetails($request['hospital_location']);
    $is_high_urgency = isHighUrgencyRequest($request);
    $time_since_submission = getTimeSinceSubmission($request);
    $days_remaining = getDaysRemaining($request);
    
    // Generate appointment slots if approved
    $appointment_slots = [];
    if ($request['request_status'] === 'Approved' && isset($request['updated_at'])) {
        $appointment_slots = generateAppointmentSlots($request['updated_at'], $request['hospital_location']);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pirate's Blood Bank - Request Details</title>
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
            color: #17a2b8;
            font-size: 28px;
        }
        
        .details-subtitle {
            color: #6c757d;
            font-size: 16px;
            font-weight: 500;
        }
        
        .ticket-section {
            background: linear-gradient(135deg, #17a2b8, #138496);
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
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .ticket-id:hover {
            transform: scale(1.05);
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
            color: #17a2b8;
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
        .status-fulfilled { border-left-color: #28a745; }
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
        
        .badge-fulfilled {
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
        
        .urgency-notice {
            background: linear-gradient(90deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));
            color: #721c24;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            border-left: 4px solid #dc3545;
            position: relative;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
        }
        
        .urgency-notice h4 {
            margin: 0 0 15px 0;
            font-size: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .time-critical {
            display: flex;
            gap: 20px;
            margin-top: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .time-critical strong {
            color: #dc3545;
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
            flex-wrap: wrap;
        }
        
        .countdown-item {
            text-align: center;
            background: rgba(255, 255, 255, 0.5);
            padding: 15px 20px;
            border-radius: 10px;
            min-width: 80px;
        }
        
        .countdown-number {
            font-size: 28px;
            font-weight: 700;
            display: block;
        }
        
        .countdown-label {
            font-size: 12px;
            text-transform: uppercase;
            opacity: 0.8;
            margin-top: 5px;
        }
        
        .request-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .detail-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-top: 4px solid #17a2b8;
        }
        
        .detail-card h4 {
            margin: 0 0 25px 0;
            font-size: 20px;
            font-weight: 700;
            color: #495057;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .detail-card h4 i {
            color: #17a2b8;
            font-size: 18px;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 12px 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .detail-item:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 600;
            color: #6c757d;
            min-width: 120px;
            flex-shrink: 0;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .detail-value {
            color: #495057;
            text-align: right;
            flex-grow: 1;
            word-break: break-word;
            font-weight: 500;
        }
        
        .blood-type {
            font-size: 24px;
            font-weight: 700;
            color: #dc3545;
            font-family: 'Courier New', monospace;
        }
        
        .urgency-high {
            color: #dc3545;
            font-weight: 700;
        }
        
        .urgency-medium {
            color: #ffc107;
            font-weight: 700;
        }
        
        .urgency-low {
            color: #28a745;
            font-weight: 700;
        }
        
        .hospital-info {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            grid-column: 1 / -1;
        }
        
        .hospital-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .hospital-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #0d47a1, #1565c0);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }
        
        .hospital-name {
            font-size: 24px;
            font-weight: 700;
            color: #0d47a1;
            margin: 0;
        }
        
        .hospital-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
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
            margin-top: 20px;
        }
        
        .visiting-hours h5 {
            margin: 0 0 10px 0;
            font-size: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .appointment-section {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            border-left: 4px solid #ffc107;
            grid-column: 1 / -1;
        }
        
        .appointment-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .appointment-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #ffc107, #e0a800);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }
        
        .appointment-title {
            font-size: 24px;
            font-weight: 700;
            color: #856404;
            margin: 0;
        }
        
        .appointment-slots {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .appointment-slot {
            background: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .appointment-slot:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-color: #17a2b8;
        }
        
        .slot-date {
            font-size: 16px;
            font-weight: 700;
            color: #495057;
            margin-bottom: 5px;
        }
        
        .slot-day {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 10px;
        }
        
        .slot-time {
            font-size: 18px;
            font-weight: 600;
            color: #17a2b8;
        }
        
        .medical-condition-card {
            grid-column: 1 / -1;
            border-top-color: #28a745;
        }
        
        .medical-condition-text {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            white-space: pre-wrap;
            line-height: 1.6;
            color: #495057;
            border-left: 4px solid #28a745;
        }
        
        .action-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 40px;
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
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            color: white;
        }
        
        .btn-outline {
            background: white;
            color: #6c757d;
            border: 2px solid #6c757d;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
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
            
            .request-details,
            .hospital-grid,
            .appointment-slots {
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
                border: 2px solid #17a2b8;
            }
            
            .appointment-section {
                background: #fff !important;
                border: 1px solid #ccc;
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
                <i class="fas fa-file-medical-alt"></i>
                Blood Request Details
            </h1>
            <p class="details-subtitle">Complete information about your blood request</p>
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
        
        <?php if ($request): ?>
            <!-- Request Ticket -->
            <div class="ticket-section">
                <div class="ticket-content">
                    <div class="ticket-id" title="Click to copy ticket ID"><?php echo htmlspecialchars($ticket_id); ?></div>
                    <div class="ticket-subtitle">Blood Request Confirmation Ticket</div>
                    <div class="ticket-qr">
                        <i class="fas fa-qrcode"></i>
                    </div>
                </div>
            </div>
            
            <!-- High Urgency Notice -->
            <?php if ($is_high_urgency): ?>
            <div class="urgency-notice">
                <h4>
                    <i class="fas fa-exclamation-triangle"></i>
                    HIGH URGENCY REQUEST
                </h4>
                <p>This is a high-priority blood request that requires immediate attention. Hospital staff have been notified and will prioritize this request.</p>
                <div class="time-critical">
                    <span><strong>Submitted:</strong> <?php echo $time_since_submission; ?></span>
                    <span><strong>Priority Level:</strong> URGENT</span>
                    <span><strong>Required by:</strong> <?php echo date('M j, Y', strtotime($request['needed_by_date'])); ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Status Section -->
            <div class="status-section status-<?php echo strtolower($request['request_status']); ?>">
                <div class="status-info">
                    <h3>Current Status</h3>
                    <div class="status-description">
                        <?php
                        switch(strtolower($request['request_status'])) {
                            case 'pending':
                                echo "Your blood request is currently being reviewed by hospital staff. You will be notified once it's processed.";
                                break;
                            case 'approved':
                                echo "Great news! Your blood request has been approved. Please schedule an appointment and visit the hospital with your ID and required documents within 14 days.";
                                break;
                            case 'fulfilled':
                                echo "Your blood request has been successfully fulfilled. Thank you for using our services.";
                                break;
                            case 'rejected':
                                echo "Unfortunately, your blood request could not be approved at this time. Please contact the hospital for more information.";
                                break;
                            case 'cancelled':
                                echo "This blood request has been cancelled. If you need assistance, please contact support.";
                                break;
                            default:
                                echo "Status information is being updated.";
                        }
                        ?>
                    </div>
                </div>
                <div class="status-badge badge-<?php echo strtolower($request['request_status']); ?>">
                    <?php
                    $statusIcons = [
                        'pending' => 'fas fa-clock',
                        'approved' => 'fas fa-check-circle',
                        'fulfilled' => 'fas fa-heart',
                        'rejected' => 'fas fa-times-circle',
                        'cancelled' => 'fas fa-ban'
                    ];
                    $icon = $statusIcons[strtolower($request['request_status'])] ?? 'fas fa-info-circle';
                    ?>
                    <i class="<?php echo $icon; ?>"></i>
                    <?php echo ucfirst($request['request_status']); ?>
                </div>
            </div>
            
            <!-- Deadline Notice for Approved Requests -->
            <?php if ($request['request_status'] === 'Approved'): ?>
                <div class="deadline-notice <?php echo ($days_remaining !== null && $days_remaining <= 3) ? 'deadline-urgent' : ''; ?>">
                    <h4>
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo ($days_remaining !== null && $days_remaining <= 3) ? 'URGENT: Request Deadline' : 'Important: Request Deadline'; ?>
                    </h4>
                    <p>
                        <strong>Your approved request must be fulfilled within 14 days or it will be automatically cancelled.</strong>
                        <?php if (isset($request['updated_at'])): ?>
                            Deadline: <strong><?php echo calculateDeadline($request['updated_at']); ?></strong>
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
                                        $hoursLeft = ($days_remaining * 24) + floor((strtotime($request['updated_at'] . ' +14 days') - time()) / 3600) % 24;
                                        echo max(0, $hoursLeft);
                                    } else {
                                        echo "0";
                                    }
                                ?></span>
                                <span class="countdown-label">Hours Left</span>
                            </div>
                        </div>
                        
                        <?php if ($days_remaining <= 3): ?>
                            <p style="margin-top: 15px; font-weight: 700;">
                                <i class="fas fa-bell"></i>
                                Please schedule your appointment and visit the hospital soon!
                            </p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Appointment Scheduling for Approved Requests -->
            <?php if ($request['request_status'] === 'Approved' && !empty($appointment_slots)): ?>
            <div class="appointment-section">
                <div class="appointment-header">
                    <div class="appointment-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h3 class="appointment-title">Schedule Your Appointment</h3>
                </div>
                
                <p style="margin: 0 0 20px 0; color: #856404; line-height: 1.6;">
                    Select a convenient date and time for your blood transfusion appointment. Available slots are shown for the next 14 days.
                </p>
                
                <div class="appointment-slots">
                    <?php 
                    $slotsShown = 0;
                    foreach ($appointment_slots as $slot): 
                        if ($slotsShown >= 12) break; // Show max 12 slots
                        $slotsShown++;
                    ?>
                        <div class="appointment-slot" onclick="selectAppointment('<?php echo $slot['date']; ?>', '<?php echo $slot['time']; ?>')">
                            <div class="slot-date"><?php echo $slot['formatted_date']; ?></div>
                            <div class="slot-day"><?php echo $slot['day_name']; ?></div>
                            <div class="slot-time"><?php echo $slot['time']; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <p style="margin-top: 20px; color: #856404; font-size: 14px; text-align: center;">
                    <i class="fas fa-info-circle"></i>
                    Click on a time slot to schedule your appointment. You will receive a confirmation email.
                </p>
            </div>
            <?php endif; ?>
            
            <!-- Request Details Grid -->
            <div class="request-details">
                <!-- Request Information -->
                <div class="detail-card">
                    <h4><i class="fas fa-file-medical"></i> Request Information</h4>
                    <div class="detail-item">
                        <span class="detail-label">Request ID</span>
                        <span class="detail-value">#<?php echo $request['request_id']; ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Blood Type</span>
                        <span class="detail-value blood-type"><?php echo htmlspecialchars($request['blood_type_needed']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Units Needed</span>
                        <span class="detail-value"><?php echo htmlspecialchars($request['units_needed']); ?> unit(s)</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Urgency Level</span>
                        <span class="detail-value">
                            <span class="urgency-<?php echo strtolower($request['urgency_level']); ?>">
                                <?php echo htmlspecialchars($request['urgency_level']); ?>
                            </span>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Needed By</span>
                        <span class="detail-value"><?php echo date('M j, Y', strtotime($request['needed_by_date'])); ?></span>
                    </div>
                </div>
                
                <!-- User Information -->
                <div class="detail-card">
                    <h4><i class="fas fa-user"></i> Requester Information</h4>
                    <div class="detail-item">
                        <span class="detail-label">Username</span>
                        <span class="detail-value"><?php echo htmlspecialchars($request['username']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Account Type</span>
                        <span class="detail-value"><?php echo ucfirst($request['user_type']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Member Since</span>
                        <span class="detail-value"><?php echo date('M j, Y', strtotime($request['user_created_at'])); ?></span>
                    </div>
                    <?php if (!empty($request['doctor_contact'])): ?>
                    <div class="detail-item">
                        <span class="detail-label">Doctor Contact</span>
                        <span class="detail-value"><?php echo htmlspecialchars($request['doctor_contact']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($request['emergency_contact'])): ?>
                    <div class="detail-item">
                        <span class="detail-label">Emergency Contact</span>
                        <span class="detail-value"><?php echo htmlspecialchars($request['emergency_contact']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Timeline Information -->
                <div class="detail-card">
                    <h4><i class="fas fa-clock"></i> Timeline</h4>
                    <div class="detail-item">
                        <span class="detail-label">Submitted</span>
                        <span class="detail-value"><?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?></span>
                    </div>
                    <?php if (isset($request['updated_at']) && $request['request_status'] !== 'Pending'): ?>
                    <div class="detail-item">
                        <span class="detail-label">Last Updated</span>
                        <span class="detail-value"><?php echo date('M j, Y g:i A', strtotime($request['updated_at'])); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($request['request_status'] === 'Approved' && isset($request['updated_at'])): ?>
                    <div class="detail-item">
                        <span class="detail-label">Deadline</span>
                        <span class="detail-value" style="color: #dc3545; font-weight: 700;"><?php echo calculateDeadline($request['updated_at']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="detail-item">
                        <span class="detail-label">Days Since Request</span>
                        <span class="detail-value"><?php 
                            $daysSince = floor((time() - strtotime($request['created_at'])) / (24 * 60 * 60));
                            echo $daysSince . ' day' . ($daysSince != 1 ? 's' : '');
                        ?></span>
                    </div>
                </div>
                
                <!-- Medical Condition -->
                <div class="detail-card medical-condition-card">
                    <h4><i class="fas fa-notes-medical"></i> Medical Condition</h4>
                    <div class="medical-condition-text">
                        <?php echo htmlspecialchars($request['medical_conditions']); ?>
                    </div>
                </div>
            </div>
            
            <!-- Hospital Information -->
            <div class="hospital-info">
                <div class="hospital-header">
                    <div class="hospital-icon">
                        <i class="fas fa-hospital"></i>
                    </div>
                    <h3 class="hospital-name"><?php echo htmlspecialchars($hospital_details['name']); ?></h3>
                </div>
                
                <div class="hospital-grid">
                    <div class="hospital-item">
                        <h5><i class="fas fa-map-marker-alt"></i> Address</h5>
                        <p><?php echo htmlspecialchars($hospital_details['address']); ?></p>
                    </div>
                    
                    <div class="hospital-item">
                        <h5><i class="fas fa-phone"></i> Contact Information</h5>
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
                        <h5><i class="fas fa-car"></i> Parking & Requirements</h5>
                        <p><strong>Parking:</strong> <?php echo htmlspecialchars($hospital_details['parking']); ?></p>
                        <p><strong>Requirements:</strong> <?php echo htmlspecialchars($hospital_details['requirements']); ?></p>
                    </div>
                </div>
                
                <div class="visiting-hours">
                    <h5>
                        <i class="fas fa-clock"></i> Visiting Hours
                    </h5>
                    <p><?php echo $hospital_details['visiting_hours']; ?></p>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="action-buttons">
                <?php if ($request['request_status'] === 'Pending'): ?>
                    <a href="update-request.php?id=<?php echo $request['request_id']; ?>" class="action-btn btn-primary">
                        <i class="fas fa-edit"></i>
                        Update Request
                    </a>
                <?php endif; ?>
                
                <?php if (in_array(strtolower($request['request_status']), ['pending', 'approved'])): ?>
                    <a href="tel:<?php echo str_replace([' ', '-', '(', ')'], '', $hospital_details['phone']); ?>" class="action-btn btn-primary">
                        <i class="fas fa-phone"></i>
                        Call Hospital
                    </a>
                <?php endif; ?>
                
                <?php if (strtolower($request['request_status']) === 'approved'): ?>
                    <a href="mailto:<?php echo $hospital_details['email']; ?>?subject=Blood Request Appointment - <?php echo urlencode($ticket_id); ?>&body=Hello,%0D%0A%0D%0AI would like to schedule an appointment for my approved blood request.%0D%0A%0D%0ATicket ID: <?php echo urlencode($ticket_id); ?>%0D%0ABlood Type: <?php echo urlencode($request['blood_type_needed']); ?>%0D%0AUnits Needed: <?php echo urlencode($request['units_needed']); ?>%0D%0A%0D%0APlease let me know available times.%0D%0A%0D%0AThank you." class="action-btn btn-secondary">
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
                    Back to Requests
                </a>
            </div>
            
        <?php else: ?>
            <div class="message error">
                <i class="fas fa-exclamation-triangle"></i>
                Request not found or you don't have permission to view it.
            </div>
            <div class="action-buttons">
                <a href="requests.php" class="action-btn btn-primary">
                    <i class="fas fa-arrow-left"></i>
                    Back to Requests
                </a>
            </div>
        <?php endif; ?>
        <br><br>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Real-time countdown for approved requests
    <?php if ($request && $request['request_status'] === 'Approved' && isset($request['updated_at'])): ?>
    function updateCountdown() {
        const approvalTime = new Date('<?php echo $request['updated_at']; ?>').getTime();
        const deadlineTime = approvalTime + (14 * 24 * 60 * 60 * 1000); // 14 days later
        const now = new Date().getTime();
        const timeLeft = deadlineTime - now;
        
        if (timeLeft > 0) {
            const days = Math.floor(timeLeft / (1000 * 60 * 60 * 24));
            const hours = Math.floor((timeLeft % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            
            const daysElement = document.querySelector('.countdown-item:first-child .countdown-number');
            const hoursElement = document.querySelector('.countdown-item:nth-child(2) .countdown-number');
            
            if (daysElement) daysElement.textContent = days;
            if (hoursElement) hoursElement.textContent = hours;
            
            // Add urgency styling if less than 3 days
            if (timeLeft < 3 * 24 * 60 * 60 * 1000) {
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
                    <h4><i class="fas fa-exclamation-circle"></i> Request Expired</h4>
                    <p style="color: #721c24; font-weight: 700;">
                        This request has expired and may be automatically cancelled.
                        Please contact the hospital or submit a new request.
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
        ticketId.addEventListener('click', function() {
            navigator.clipboard.writeText(this.textContent).then(() => {
                // Show temporary feedback
                const originalText = this.textContent;
                this.textContent = 'COPIED!';
                this.style.backgroundColor = 'rgba(255,255,255,0.3)';
                
                setTimeout(() => {
                    this.textContent = originalText;
                    this.style.backgroundColor = 'transparent';
                }, 1500);
            }).catch(() => {
                console.log('Could not copy ticket ID');
            });
        });
    }
    
    // Appointment selection functionality
    window.selectAppointment = function(date, time) {
        if (confirm(`Schedule appointment for ${date} at ${time}?`)) {
            // Here you would typically send an AJAX request to schedule the appointment
            // For now, we'll just show an alert
            alert(`Appointment scheduled for ${date} at ${time}. You will receive a confirmation email shortly.`);
            
            // You can add actual appointment scheduling logic here
            // Example AJAX call:
            /*
            fetch('schedule_appointment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    request_id: <?php echo $request_id; ?>,
                    appointment_date: date,
                    appointment_time: time
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Appointment scheduled successfully! Check your email for confirmation.');
                    location.reload();
                } else {
                    alert('Error scheduling appointment. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error scheduling appointment. Please try again.');
            });
            */
        }
    };
    
    // Auto-refresh for urgent requests
    <?php if ($is_high_urgency && in_array(strtolower($request['request_status']), ['pending'])): ?>
    setInterval(function() {
        // Refresh page every 3 minutes for urgent pending requests
        location.reload();
    }, 180000);
    <?php endif; ?>
    
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
    document.querySelectorAll('.detail-card, .hospital-item, .appointment-slot').forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(card);
    });
    
    console.log('Request details page initialized successfully');
    console.log('Request ID: <?php echo $request ? $request['request_id'] : 'N/A'; ?>');
    console.log('Status: <?php echo $request ? $request['request_status'] : 'N/A'; ?>');
});
</script>

</body>
<?php include('includes/footer.php'); ?>
</html>