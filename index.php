<?php 
include('includes/db.php');
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Function to determine status based on quantity
function getInventoryStatus($quantity) {
    if ($quantity <= 5) {
        return 'Critical';
    } elseif ($quantity <= 15) {
        return 'Low';
    } else {
        return 'Sufficient';
    }
}

// Function to get status class for styling
function getStatusClass($status) {
    switch($status) {
        case 'Critical':
            return 'status-critical';
        case 'Low':
            return 'status-low';
        case 'Sufficient':
            return 'status-sufficient';
        default:
            return '';
    }
}

// Fetch blood inventory data
$inventory = [];
$lastUpdate = null;

// Get blood inventory data
$query = "SELECT blood_type, available_units, last_updated FROM blood_inventory ORDER BY blood_type";
$result = $conn->query($query);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $inventory[] = $row;
    }
} else {
    $error = "Error fetching inventory data: " . $conn->error;
}

// Get the most recent update time
$lastUpdateQuery = "SELECT MAX(last_updated) as latest_update FROM blood_inventory";
$lastUpdateResult = $conn->query($lastUpdateQuery);

if ($lastUpdateResult) {
    $lastUpdate = $lastUpdateResult->fetch_assoc();
} else {
    $error = "Error fetching last update time: " . $conn->error;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pirate's Blood Bank - Saving Lives Together</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cardo:ital,wght@0,400;0,700;1,400&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Quicksand:wght@300..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        /* Main content layout */
        main {
            margin-left: 0;
            min-height: 100vh;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            transition: margin-left 0.3s ease;
            padding: 0;
            width: 100%;
            box-sizing: border-box;
            overflow-y: auto;
        }

        /* Content wrapper to handle sidebar offset */
        .content-wrapper {
            margin-left: 70px;
            width: calc(100% - 70px);
            box-sizing: border-box;
        }
.hero-section {
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.9), rgba(176, 42, 55, 0.9));
            color: white;
            padding: 100px 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
            width: 100%;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Animated Background Particles */
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 20% 30%, rgba(255, 255, 255, 0.1) 2px, transparent 2px),
                radial-gradient(circle at 80% 70%, rgba(255, 255, 255, 0.08) 2px, transparent 2px),
                radial-gradient(circle at 40% 80%, rgba(255, 255, 255, 0.06) 1px, transparent 1px);
            background-size: 100px 100px, 150px 150px, 80px 80px;
            animation: floatParticles 20s infinite linear;
            z-index: 1;
        }

        @keyframes floatParticles {
            0% { transform: translateY(0) rotate(0deg); }
            100% { transform: translateY(-100px) rotate(360deg); }
        }

        /* Floating Blood Drops */
        .blood-drop {
            position: absolute;
            color: rgba(255, 255, 255, 0.3);
            font-size: 20px;
            animation: floatDrop 8s infinite ease-in-out;
            z-index: 2;
        }

        .blood-drop:nth-child(1) { left: 10%; animation-delay: 0s; }
        .blood-drop:nth-child(2) { left: 20%; animation-delay: 1s; }
        .blood-drop:nth-child(3) { right: 15%; animation-delay: 2s; }
        .blood-drop:nth-child(4) { right: 25%; animation-delay: 3s; }
        .blood-drop:nth-child(5) { left: 60%; animation-delay: 4s; }
        .blood-drop:nth-child(6) { right: 40%; animation-delay: 5s; }

        @keyframes floatDrop {
            0%, 100% { 
                transform: translateY(0) rotate(0deg) scale(1);
                opacity: 0.3;
            }
            25% { 
                transform: translateY(-30px) rotate(90deg) scale(1.2);
                opacity: 0.6;
            }
            50% { 
                transform: translateY(-60px) rotate(180deg) scale(0.8);
                opacity: 0.4;
            }
            75% { 
                transform: translateY(-30px) rotate(270deg) scale(1.1);
                opacity: 0.5;
            }
        }

        /* Pulsing Heart Background */
        .hero-section::after {
            content: '❤️';
            position: absolute;
            top: 20%;
            right: 10%;
            font-size: 100px;
            opacity: 0.1;
            animation: heartbeat 3s infinite ease-in-out;
            z-index: 1;
        }

        @keyframes heartbeat {
            0%, 100% { 
                transform: scale(1);
                opacity: 0.1;
            }
            50% { 
                transform: scale(1.2);
                opacity: 0.2;
            }
        }

        .hero-content {
            position: relative;
            z-index: 10;
            max-width: 800px;
        }

        /* Animated Title */
        .hero-content h1 {
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 20px;
            font-family: 'Poppins', sans-serif;
            color: white;
            opacity: 0;
            animation: slideInFromTop 1s ease-out 0.5s forwards;
        }

        @keyframes slideInFromTop {
            0% {
                opacity: 0;
                transform: translateY(-50px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Animated Paragraph */
        .hero-content p {
            font-size: 20px;
            margin-bottom: 30px;
            opacity: 0;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.6;
            animation: slideInFromBottom 1s ease-out 1s forwards;
        }

        @keyframes slideInFromBottom {
            0% {
                opacity: 0;
                transform: translateY(30px);
            }
            100% {
                opacity: 0.95;
                transform: translateY(0);
            }
        }

        /* Animated Stats */
        .hero-stats {
            display: flex;
            justify-content: center;
            gap: 60px;
            margin-top: 40px;
            flex-wrap: wrap;
            opacity: 0;
            animation: fadeInScale 1s ease-out 1.5s forwards;
        }

        @keyframes fadeInScale {
            0% {
                opacity: 0;
                transform: scale(0.8);
            }
            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        .stat-item {
            text-align: center;
            position: relative;
            padding: 20px;
            border-radius: 15px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            animation: statItemFloat 4s infinite ease-in-out;
        }

        .stat-item:nth-child(1) { animation-delay: 0s; }
        .stat-item:nth-child(2) { animation-delay: 1s; }
        .stat-item:nth-child(3) { animation-delay: 2s; }

        @keyframes statItemFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .stat-item:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .stat-number {
            font-size: 36px;
            font-weight: 700;
            display: block;
            margin-bottom: 5px;
            background: linear-gradient(45deg, #fff, #ffcccb);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-label {
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.9;
        }

        /* Animated CTA Buttons */
        .cta-buttons {
            margin-top: 40px;
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
            opacity: 0;
            animation: slideInFromBottom 1s ease-out 2s forwards;
        }

        .btn-primary, .btn-secondary {
            padding: 15px 30px;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
        }

        .btn-primary {
            background: white;
            color: #dc3545;
            border: 2px solid white;
            animation: pulseGlow 2s infinite ease-in-out;
        }

        @keyframes pulseGlow {
            0%, 100% { 
                box-shadow: 0 0 20px rgba(255, 255, 255, 0.3);
            }
            50% { 
                box-shadow: 0 0 30px rgba(255, 255, 255, 0.6);
            }
        }

        .btn-primary:hover {
            background: transparent;
            color: white;
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 10px 25px rgba(255,255,255,0.3);
            animation: none;
        }

        .btn-secondary {
            background: transparent;
            color: white;
            border: 2px solid white;
            position: relative;
        }

        .btn-secondary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn-secondary:hover::before {
            left: 100%;
        }

        .btn-secondary:hover {
            background: white;
            color: #dc3545;
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 10px 25px rgba(255,255,255,0.2);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .hero-content h1 {
                font-size: 36px;
            }
            
            .hero-content p {
                font-size: 18px;
            }
            
            .hero-stats {
                gap: 30px;
            }
            
            .stat-number {
                font-size: 28px;
            }
            
            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .blood-drop {
                display: none;
            }
        }

        /* Loading Animation */
        .hero-section.loading {
            opacity: 0;
        }

        .hero-section.loaded {
            opacity: 1;
            transition: opacity 0.5s ease;
        }
        /* Inventory Section */
        .inventory-container {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            box-shadow: 2px 0 15px rgba(220, 220, 220, 0.1);
            margin: 0;
            padding: 60px 40px;
            width: 100%;
            box-sizing: border-box;
        }
        
        .section-title {
            font-size: 36px;
            font-weight: 700;
            color: #495057;
            text-align: center;
            margin-bottom: 20px;
            font-family: 'Poppins', sans-serif;
        }

        .section-subtitle {
            text-align: center;
            color: #6c757d;
            font-size: 18px;
            margin-bottom: 50px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.6;
        }
        
        .inventory-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 25px;
        }
        
        .inventory-title {
            font-size: 28px;
            font-weight: 600;
            color: #495057;
            display: flex;
            align-items: center;
            gap: 15px;
            font-family: 'Poppins', sans-serif;
        }
        
        .inventory-title i {
            color: #dc3545;
            font-size: 24px;
        }
        
        .last-updated {
            background: linear-gradient(90deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));
            color: #495057;
            padding: 12px 20px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 500;
            border: 1px solid rgba(220, 53, 69, 0.1);
        }
        
        .last-updated i {
            color: #dc3545;
            margin-right: 8px;
        }
        
        .inventory-table {
            width: 100%;
            border-collapse: collapse;
            font-family: 'Poppins', sans-serif;
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(220, 220, 220, 0.15);
        }
        
        .inventory-table th {
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
            padding: 20px;
            text-align: left;
            font-weight: 600;
            font-size: 16px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .inventory-table th:first-child {
            padding-left: 30px;
        }
        
        .inventory-table th:last-child {
            padding-right: 30px;
        }
        
        .inventory-table td {
            padding: 20px;
            border-bottom: 1px solid #f8f9fa;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        
        .inventory-table td:first-child {
            padding-left: 30px;
        }
        
        .inventory-table td:last-child {
            padding-right: 30px;
        }
        
        .inventory-table tr:hover {
            background: linear-gradient(90deg, rgba(220, 53, 69, 0.05), rgba(220, 53, 69, 0.02));
            transform: translateX(5px);
        }
        
        .inventory-table tr:last-child td {
            border-bottom: none;
        }
        
        .blood-type {
            font-weight: 700;
            font-size: 20px;
            color: #dc3545;
        }
        
        .quantity {
            font-weight: 600;
            font-size: 18px;
            color: #495057;
        }
        
        .status-badge {
            padding: 8px 16px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            letter-spacing: 0.5px;
        }
        
        .status-critical {
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
        }
        
        .status-low {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: white;
        }
        
        .status-sufficient {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            color: white;
        }
        
        .refresh-btn {
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .refresh-btn:hover {
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
        }

        /* Services Section */
        .services-section {
            background: #f8f9fa;
            padding: 80px 40px;
            width: 100%;
            box-sizing: border-box;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .service-card {
            background: white;
            padding: 40px 30px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border-top: 4px solid #dc3545;
        }

        .service-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }

        .service-card i {
            font-size: 48px;
            color: #dc3545;
            margin-bottom: 20px;
        }

        .service-card h3 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #495057;
        }

        .service-card p {
            color: #6c757d;
            line-height: 1.6;
        }

        /* About Section */
        .about-section {
            background: white;
            padding: 80px 40px;
            width: 100%;
            box-sizing: border-box;
        }

        .about-content {
            max-width: 1000px;
            margin: 0 auto;
            text-align: center;
        }

        .about-text {
            font-size: 18px;
            line-height: 1.8;
            color: #495057;
            margin-bottom: 50px;
        }

        .developers-section {
            background: #f8f9fa;
            padding: 50px 30px;
            border-radius: 15px;
            margin-top: 40px;
        }

        .developers-title {
            font-size: 28px;
            font-weight: 600;
            color: #495057;
            margin-bottom: 30px;
            text-align: center;
        }

        .developers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .developer-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
        }

        .developer-card i {
            font-size: 36px;
            color: #dc3545;
            margin-bottom: 15px;
        }

        .developer-name {
            font-weight: 600;
            color: #495057;
            font-size: 16px;
        }

        .project-info {
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));
            padding: 25px;
            border-radius: 10px;
            margin-top: 30px;
            border-left: 4px solid #dc3545;
        }

        .project-info p {
            margin: 0;
            font-style: italic;
            color: #495057;
            line-height: 1.6;
        }

        /* Emergency Section */
        .emergency-section {
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
            padding: 60px 40px;
            text-align: center;
            width: 100%;
            box-sizing: border-box;
        }

        .emergency-content h2 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 20px;
            color:white;
        }

        .emergency-content p {
            font-size: 18px;
            margin-bottom: 30px;
            opacity: 0.95;
        }

        .emergency-contact {
            background: rgba(255,255,255,0.1);
            padding: 20px;
            border-radius: 15px;
            display: inline-block;
            margin-top: 20px;
        }

        .emergency-phone {
            font-size: 24px;
            font-weight: 700;
            color: white;
            text-decoration: none;
        }
        
        .error-message {
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin: 40px 0;
            text-align: center;
            font-weight: 500;
        }
        
        .empty-inventory {
            text-align: center;
            padding: 60px;
            color: #495057;
            font-style: italic;
            font-size: 16px;
        }
        
        .empty-inventory i {
            color: #dc3545;
            opacity: 0.7;
        }
        
        /* Responsive adjustments */
        @media (max-width: 1024px) {
            .content-wrapper {
                margin-left: 200px;
                width: calc(100% - 200px);
            }
        }

        @media (max-width: 768px) {
            .content-wrapper {
                margin-left: 0;
                width: 100%;
            }

            .hero-section {
                padding: 60px 20px;
            }

            .hero-content h1 {
                font-size: 36px;
            }

            .hero-content p {
                font-size: 18px;
            }

            .hero-stats {
                gap: 40px;
            }

            .stat-number {
                font-size: 28px;
            }

            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .inventory-container, .services-section, .about-section, .emergency-section {
                padding: 40px 20px;
            }

            .section-title {
                font-size: 28px;
            }

            .section-subtitle {
                font-size: 16px;
            }
            
            .inventory-title {
                font-size: 20px;
            }
            
            .inventory-header {
                flex-direction: column;
                align-items: stretch;
                gap: 20px;
            }
            
            .inventory-table th,
            .inventory-table td {
                padding: 15px 10px;
            }
            
            .inventory-table th:first-child,
            .inventory-table td:first-child {
                padding-left: 15px;
            }
            
            .inventory-table th:last-child,
            .inventory-table td:last-child {
                padding-right: 15px;
            }

            .services-grid {
                grid-template-columns: 1fr;
            }

            .developers-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<?php 
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
    include('includes/adminheader.php');
} else {
    include('includes/header.php');
}
?>
<body>
<?php include('includes/sidebar.php'); ?>
<main>
    <div class="content-wrapper">
        <!-- Hero Section -->
         <section class="hero-section loaded">
        <!-- Floating Blood Drops -->
        <div class="blood-drop"><i class="fas fa-tint"></i></div>
        <div class="blood-drop"><i class="fas fa-tint"></i></div>
        <div class="blood-drop"><i class="fas fa-tint"></i></div>
        <div class="blood-drop"><i class="fas fa-tint"></i></div>
        <div class="blood-drop"><i class="fas fa-tint"></i></div>
        <div class="blood-drop"><i class="fas fa-tint"></i></div>

        <div class="hero-content">
            <h1>Saving Lives, One Drop at a Time</h1>
            <p>Welcome to Pirate's Blood Bank - where every donation creates a ripple of hope. Join our community of heroes and help us maintain a steady supply of life-saving blood.</p>
            
            <div class="hero-stats">
                <div class="stat-item">
                    <span class="stat-number">1,500+</span>
                    <span class="stat-label">Lives Saved</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number">800+</span>
                    <span class="stat-label">Active Donors</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number">24/7</span>
                    <span class="stat-label">Emergency Service</span>
                </div>
            </div>

            <div class="cta-buttons">
                <a href="donate.php" class="btn-primary">
                    <i class="fas fa-heart"></i>
                    Donate Now
                </a>
                <a href="receive.php" class="btn-secondary">
                    <i class="fas fa-hand-holding-medical"></i>
                    Request Blood
                </a>
            </div>
        </div>
    </section>

        <!-- Blood Inventory Section -->
        <section class="inventory-container">
            <h2 class="section-title">Live Blood Bank Inventory</h2>
            <p class="section-subtitle">Real-time blood availability status to help you understand current supply levels and urgent needs.</p>
            
            <div class="inventory-header">
                <h3 class="inventory-title">
                    <i class="fas fa-tint"></i>
                    Current Stock Levels
                </h3>
                <div style="display: flex; align-items: center; gap: 15px;">
                    <?php if (isset($lastUpdate['latest_update'])): ?>
                        <div class="last-updated">
                            <i class="fas fa-clock"></i>
                            Last Updated: <?php echo date('M j, Y - g:i A', strtotime($lastUpdate['latest_update'])); ?>
                        </div>
                    <?php endif; ?>
                    <button class="refresh-btn" onclick="location.reload();">
                        <i class="fas fa-sync-alt"></i>
                        Refresh
                    </button>
                </div>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php elseif (empty($inventory)): ?>
                <div class="empty-inventory">
                    <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px; display: block;"></i>
                    No blood inventory data available
                </div>
            <?php else: ?>
                <table class="inventory-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-tint"></i> Blood Type</th>
                            <th><i class="fas fa-flask"></i> Available Units</th>
                            <th><i class="fas fa-heart-pulse"></i> Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventory as $item): ?>
                            <?php 
                            $status = getInventoryStatus($item['available_units']);
                            $statusClass = getStatusClass($status);
                            ?>
                            <tr>
                                <td class="blood-type"><?php echo htmlspecialchars($item['blood_type']); ?></td>
                                <td class="quantity"><?php echo number_format($item['available_units']); ?> units</td>
                                <td>
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <?php if ($status === 'Critical'): ?>
                                            <i class="fas fa-exclamation-triangle"></i>
                                        <?php elseif ($status === 'Low'): ?>
                                            <i class="fas fa-exclamation-circle"></i>
                                        <?php else: ?>
                                            <i class="fas fa-check-circle"></i>
                                        <?php endif; ?>
                                        <?php echo $status; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <!-- Services Section -->
        <section class="services-section">
            <h2 class="section-title">Our Services</h2>
            <p class="section-subtitle">Comprehensive blood banking services designed to serve our community's needs</p>
            
            <div class="services-grid">
                <div class="service-card">
                    <i class="fas fa-heart"></i>
                    <h3>Blood Donation</h3>
                    <p>Safe and comfortable blood donation process with experienced medical staff ensuring your well-being throughout the donation.</p>
                </div>
                
                <div class="service-card">
                    <i class="fas fa-ambulance"></i>
                    <h3>Emergency Supply</h3>
                    <p>24/7 emergency blood supply service for hospitals and medical facilities with rapid response capabilities.</p>
                </div>
                
                <div class="service-card">
                    <i class="fas fa-microscope"></i>
                    <h3>Blood Testing</h3>
                    <p>Comprehensive blood screening and testing services ensuring the highest safety standards for all blood products.</p>
                </div>
                
                <div class="service-card">
                    <i class="fas fa-calendar-check"></i>
                    <h3>Donation Scheduling</h3>
                    <p>Convenient online appointment scheduling system to plan your donation at times that work best for you.</p>
                </div>
                
                <div class="service-card">
                    <i class="fas fa-users"></i>
                    <h3>Community Drives</h3>
                    <p>Regular blood drive events in communities, schools, and workplaces to increase donation accessibility.</p>
                </div>
                
                <div class="service-card">
                    <i class="fas fa-award"></i>
                    <h3>Donor Recognition</h3>
                    <p>Special recognition programs for regular donors including awards, certificates, and exclusive donor benefits.</p>
                </div>
            </div>
        </section>

        <!-- About Section -->
        <section class="about-section" id="about">
            <h2 class="section-title">About Pirate's Blood Bank</h2>
            <div class="about-content">
                <p class="about-text">
                    Pirate's Blood Bank is a comprehensive blood banking management system dedicated to connecting donors with those in need. Our mission is to ensure a reliable, safe, and accessible blood supply for our community while maintaining the highest standards of medical care and donor satisfaction. Through innovative technology and compassionate service, we strive to make blood donation a seamless and rewarding experience for everyone involved.
                </p>
                
                <div class="developers-section">
                    <h3 class="developers-title">Meet Our Development Team</h3>
                    <div class="developers-grid">
                        <div class="developer-card">
                            <i class="fas fa-user-graduate"></i>
                            <div class="developer-name">Peruda, Zenia Faye B.</div>
                        </div>
                        <div class="developer-card">
                            <i class="fas fa-user-graduate"></i>
                            <div class="developer-name">Abante, Marjinel C.</div>
                        </div>
                        <div class="developer-card">
                            <i class="fas fa-user-graduate"></i>
                            <div class="developer-name">Perez, Clarinze Aundreka</div>
                        </div>
                        <div class="developer-card">
                            <i class="fas fa-user-graduate"></i>
                            <div class="developer-name">Yahiya, Merhaya A.</div>
                        </div>
                    </div>
                    
                    <div class="project-info">
                        <p>
                            A Project Document Presented to the Faculty Members of the Department of Computer Studies (DCS) 
                            of the College of Engineering, Computer Studies and Architecture of Lyceum of the Philippines University - Cavite
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Emergency Contact Section -->
        <section class="emergency-section">
            <div class="emergency-content">
                <h2><i class="fas fa-exclamation-triangle"></i> Emergency Blood Request</h2>
                <p>In case of urgent blood requirements, contact us immediately. Our emergency response team is available 24/7 to assist with critical blood needs.</p>
                
                <div class="emergency-contact">
                    <div style="margin-bottom: 10px;">
                        <i class="fas fa-phone" style="margin-right: 10px;"></i>
                        Emergency Hotline
                    </div>
                    <a href="tel:(555) 123-BLOOD" class="emergency-phone">(555) 123-BLOOD</a>
                </div>
            </div>
        </section>
    </div>
</main>
   <script>
        // Add smooth scrolling and intersection observer for animations
        document.addEventListener('DOMContentLoaded', function() {
            // Counter animation for stats
            const statNumbers = document.querySelectorAll('.stat-number');
            
            const animateStats = () => {
                statNumbers.forEach(stat => {
                    const target = stat.textContent;
                    const isNumber = target.match(/\d+/);
                    
                    if (isNumber) {
                        const targetNum = parseInt(isNumber[0]);
                        let current = 0;
                        const increment = targetNum / 100;
                        const timer = setInterval(() => {
                            current += increment;
                            if (current >= targetNum) {
                                current = targetNum;
                                clearInterval(timer);
                            }
                            stat.textContent = target.replace(/\d+/, Math.floor(current));
                        }, 20);
                    }
                });
            };

            // Trigger stat animation when stats are visible
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        setTimeout(animateStats, 1500);
                        observer.unobserve(entry.target);
                    }
                });
            });

            const statsSection = document.querySelector('.hero-stats');
            if (statsSection) {
                observer.observe(statsSection);
            }

            // Add random movement to blood drops
            const bloodDrops = document.querySelectorAll('.blood-drop');
            bloodDrops.forEach((drop, index) => {
                setInterval(() => {
                    const randomX = Math.random() * 20 - 10;
                    const randomY = Math.random() * 20 - 10;
                    drop.style.transform += ` translate(${randomX}px, ${randomY}px)`;
                }, 3000 + index * 500);
            });
        });
    </script>
</body>
<?php include('includes/footer.php'); ?>
</html>