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
$topDonors = [];
$userRank = null;
$userStats = null;

try {
    // Get top donors with their total donations (only DONATION operations)
    $leaderboardQuery = "
        SELECT 
            l.user_id,
            l.username,
            l.user_type,
            l.created_at,
            SUM(bl.units_donated) as total_units,
            COUNT(bl.log_id) as total_donations,
            MAX(bl.timestamp_update) as last_donation,
            GROUP_CONCAT(DISTINCT bl.blood_type ORDER BY bl.blood_type) as blood_types_donated
        FROM login l
        INNER JOIN blood_log bl ON l.user_id = bl.donor_id
        WHERE bl.operation_type = 'DONATION' AND l.user_type = 'user'
        GROUP BY l.user_id, l.username, l.user_type, l.created_at
        HAVING total_units > 0
        ORDER BY total_units DESC, total_donations DESC, last_donation DESC
        LIMIT 50
    ";
    
    $leaderboardStmt = $conn->prepare($leaderboardQuery);
    $leaderboardStmt->execute();
    $leaderboardResult = $leaderboardStmt->get_result();
    
    $rank = 1;
    while ($row = $leaderboardResult->fetch_assoc()) {
        $row['rank'] = $rank;
        $topDonors[] = $row;
        
        // Check if current user is in the results
        if ($row['user_id'] == $_SESSION['user_id']) {
            $userRank = $rank;
            $userStats = $row;
        }
        $rank++;
    }
    $leaderboardStmt->close();
    
    // If current user is not in top 50, get their stats separately
    if (!$userRank) {
        $userStatsQuery = "
            SELECT 
                l.user_id,
                l.username,
                l.user_type,
                l.created_at,
                COALESCE(SUM(bl.units_donated), 0) as total_units,
                COUNT(bl.log_id) as total_donations,
                MAX(bl.timestamp_update) as last_donation,
                GROUP_CONCAT(DISTINCT bl.blood_type ORDER BY bl.blood_type) as blood_types_donated
            FROM login l
            LEFT JOIN blood_log bl ON l.user_id = bl.donor_id AND bl.operation_type = 'DONATION'
            WHERE l.user_id = ?
            GROUP BY l.user_id, l.username, l.user_type, l.created_at
        ";
        
        $userStatsStmt = $conn->prepare($userStatsQuery);
        $userStatsStmt->bind_param("i", $_SESSION['user_id']);
        $userStatsStmt->execute();
        $userStatsResult = $userStatsStmt->get_result();
        
        if ($userStatsResult->num_rows > 0) {
            $userStats = $userStatsResult->fetch_assoc();
            
            // Get user's actual rank
            $rankQuery = "
                SELECT COUNT(*) + 1 as user_rank
                FROM (
                    SELECT 
                        l.user_id,
                        SUM(bl.units_donated) as total_units,
                        COUNT(bl.log_id) as total_donations,
                        MAX(bl.timestamp_update) as last_donation
                    FROM login l
                    INNER JOIN blood_log bl ON l.user_id = bl.donor_id
                    WHERE bl.operation_type = 'DONATION' AND l.user_type = 'user'
                    GROUP BY l.user_id
                    HAVING total_units > ?
                    OR (total_units = ? AND total_donations > ?)
                    OR (total_units = ? AND total_donations = ? AND last_donation > ?)
                ) as better_donors
            ";
            
            $lastDonation = $userStats['last_donation'] ?? '1970-01-01 00:00:00';
            $rankStmt = $conn->prepare($rankQuery);
            $rankStmt->bind_param("iiiiis", 
                $userStats['total_units'], 
                $userStats['total_units'], 
                $userStats['total_donations'],
                $userStats['total_units'], 
                $userStats['total_donations'], 
                $lastDonation
            );
            $rankStmt->execute();
            $rankResult = $rankStmt->get_result();
            $rankData = $rankResult->fetch_assoc();
            $userRank = $rankData['user_rank'];
            $userStats['rank'] = $userRank;
            $rankStmt->close();
        }
        $userStatsStmt->close();
    }
    
} catch (Exception $e) {
    error_log("Error retrieving leaderboard data: " . $e->getMessage());
    $message = "Error loading leaderboard. Please try again.";
    $messageType = "error";
}

// Helper function to get achievement badge
function getAchievementBadge($totalUnits) {
    if ($totalUnits >= 50) return ['badge' => 'Legendary Hero', 'class' => 'legendary', 'icon' => 'fas fa-crown'];
    if ($totalUnits >= 25) return ['badge' => 'Blood Champion', 'class' => 'champion', 'icon' => 'fas fa-trophy'];
    if ($totalUnits >= 15) return ['badge' => 'Life Guardian', 'class' => 'guardian', 'icon' => 'fas fa-shield-alt'];
    if ($totalUnits >= 10) return ['badge' => 'Hero Donor', 'class' => 'hero', 'icon' => 'fas fa-medal'];
    if ($totalUnits >= 5) return ['badge' => 'Noble Donor', 'class' => 'noble', 'icon' => 'fas fa-star'];
    if ($totalUnits >= 1) return ['badge' => 'Life Saver', 'class' => 'saver', 'icon' => 'fas fa-heart'];
    return ['badge' => 'New Member', 'class' => 'new', 'icon' => 'fas fa-user-plus'];
}

// Helper function to format time ago
function getTimeAgo($datetime) {
    if (!$datetime) return 'Never';
    
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    return floor($time/31536000) . ' years ago';
}

// Get some statistics
$totalDonors = count($topDonors);
$totalUnitsAll = array_sum(array_column($topDonors, 'total_units'));
$averageUnits = $totalDonors > 0 ? round($totalUnitsAll / $totalDonors, 1) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pirate's Blood Bank - Leaderboard</title>
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
        
        .leaderboard-container {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            box-shadow: 2px 0 15px rgba(220, 220, 220, 0.1);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: none;
            margin: 0;
            box-sizing: border-box;
        }
        
        .leaderboard-header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 25px;
        }
        
        .leaderboard-title {
            font-size: 36px;
            font-weight: 700;
            color: #495057;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            font-family: 'Poppins', sans-serif;
            margin-bottom: 10px;
        }
        
        .leaderboard-title i {
            color: #dc3545;
            font-size: 32px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .leaderboard-subtitle {
            color: #6c757d;
            font-size: 18px;
            font-weight: 500;
            margin-bottom: 20px;
        }
        
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="2" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
            background-size: 15px 15px;
        }
        
        .stat-content {
            position: relative;
            z-index: 1;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
            font-family: 'Courier New', monospace;
        }
        
        .stat-label {
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.9;
        }
        
        .podium-section {
            margin-bottom: 50px;
            background: linear-gradient(135deg, #fff8e1, #ffecb3);
            padding: 40px;
            border-radius: 20px;
            position: relative;
            overflow: hidden;
        }
        
        .podium-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><path d="M50 10 L60 35 L85 35 L67 52 L73 77 L50 65 L27 77 L33 52 L15 35 L40 35 Z" fill="rgba(255,193,7,0.1)"/></svg>') repeat;
            background-size: 50px 50px;
        }
        
        .podium-title {
            text-align: center;
            font-size: 28px;
            font-weight: 700;
            color: #f57c00;
            margin-bottom: 30px;
            position: relative;
            z-index: 1;
        }
        
        .podium {
            display: flex;
            justify-content: center;
            align-items: end;
            gap: 20px;
            margin: 40px 0;
            position: relative;
            z-index: 1;
        }
        
        .podium-place {
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .podium-place:hover {
            transform: translateY(-10px);
        }
        
        .podium-place.first {
            order: 2;
        }
        
        .podium-place.second {
            order: 1;
        }
        
        .podium-place.third {
            order: 3;
        }
        
        .podium-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: white;
            font-weight: 700;
            position: relative;
            overflow: hidden;
        }
        
        .first .podium-avatar {
            background: linear-gradient(135deg, #ffd700, #ffb300);
            width: 120px;
            height: 120px;
            font-size: 48px;
            box-shadow: 0 0 30px rgba(255, 215, 0, 0.5);
        }
        
        .second .podium-avatar {
            background: linear-gradient(135deg, #c0c0c0, #a8a8a8);
            box-shadow: 0 0 20px rgba(192, 192, 192, 0.5);
        }
        
        .third .podium-avatar {
            background: linear-gradient(135deg, #cd7f32, #b8860b);
            box-shadow: 0 0 20px rgba(205, 127, 50, 0.5);
        }
        
        .podium-crown {
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 24px;
            color: #ffd700;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .podium-rank {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .first .podium-rank {
            color: #ffd700;
            font-size: 22px;
        }
        
        .second .podium-rank {
            color: #c0c0c0;
        }
        
        .third .podium-rank {
            color: #cd7f32;
        }
        
        .podium-name {
            font-size: 18px;
            font-weight: 700;
            color: #495057;
            margin-bottom: 8px;
        }
        
        .podium-stats {
            font-size: 14px;
            color: #6c757d;
            line-height: 1.4;
        }
        
        .podium-units {
            font-size: 20px;
            font-weight: 700;
            color: #dc3545;
            margin-top: 8px;
        }
        
        .podium-base {
            width: 120px;
            margin: 0 auto;
            border-radius: 8px 8px 0 0;
            position: relative;
        }
        
        .first .podium-base {
            height: 80px;
            background: linear-gradient(135deg, #ffd700, #ffb300);
            width: 140px;
        }
        
        .second .podium-base {
            height: 60px;
            background: linear-gradient(135deg, #c0c0c0, #a8a8a8);
        }
        
        .third .podium-base {
            height: 40px;
            background: linear-gradient(135deg, #cd7f32, #b8860b);
        }
        
        .leaderboard-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .table-header {
            background: linear-gradient(135deg, #495057, #343a40);
            color: white;
            padding: 20px;
            font-weight: 700;
            text-align: center;
            font-size: 20px;
        }
        
        .table-row {
            display: grid;
            grid-template-columns: 80px 1fr 120px 120px 120px 150px;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #f8f9fa;
            transition: all 0.3s ease;
        }
        
        .table-row:hover {
            background: linear-gradient(90deg, rgba(23, 162, 184, 0.05), rgba(23, 162, 184, 0.02));
            transform: translateX(5px);
        }
        
        .table-row.current-user {
            background: linear-gradient(90deg, rgba(40, 167, 69, 0.1), rgba(40, 167, 69, 0.05));
            border-left: 4px solid #28a745;
            font-weight: 600;
        }
        
        .rank-number {
            font-size: 24px;
            font-weight: 700;
            color: #495057;
            text-align: center;
        }
        
        .rank-top3 {
            background: linear-gradient(135deg, #ffd700, #ffb300);
            color: white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            font-size: 18px;
        }
        
        .donor-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .donor-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #17a2b8, #138496);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 18px;
        }
        
        .donor-details h4 {
            margin: 0 0 5px 0;
            font-size: 16px;
            font-weight: 700;
            color: #495057;
        }
        
        .donor-badge {
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 12px;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .badge-legendary { background: linear-gradient(135deg, #ffd700, #ffb300); color: white; }
        .badge-champion { background: linear-gradient(135deg, #dc3545, #b02a37); color: white; }
        .badge-guardian { background: linear-gradient(135deg, #17a2b8, #138496); color: white; }
        .badge-hero { background: linear-gradient(135deg, #28a745, #1e7e34); color: white; }
        .badge-noble { background: linear-gradient(135deg, #6f42c1, #563d7c); color: white; }
        .badge-saver { background: linear-gradient(135deg, #fd7e14, #e55a0e); color: white; }
        .badge-new { background: linear-gradient(135deg, #6c757d, #5a6268); color: white; }
        
        .units-donated {
            font-size: 20px;
            font-weight: 700;
            color: #dc3545;
            text-align: center;
        }
        
        .total-donations {
            font-size: 16px;
            font-weight: 600;
            color: #495057;
            text-align: center;
        }
        
        .blood-types {
            text-align: center;
            font-size: 14px;
            font-weight: 600;
            color: #6c757d;
        }
        
        .last-donation {
            text-align: center;
            font-size: 14px;
            color: #6c757d;
        }
        
        .user-rank-section {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            border-left: 5px solid #2196f3;
        }
        
        .user-rank-title {
            font-size: 24px;
            font-weight: 700;
            color: #0d47a1;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-rank-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
        }
        
        .user-stat {
            text-align: center;
        }
        
        .user-stat-number {
            font-size: 28px;
            font-weight: 700;
            color: #0d47a1;
            margin-bottom: 5px;
        }
        
        .user-stat-label {
            font-size: 14px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
        }
        
        .action-btn:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
            text-decoration: none;
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
            
            .leaderboard-container {
                padding: 25px;
                border-radius: 15px;
            }
            
            .leaderboard-title {
                font-size: 28px;
            }
            
            .podium {
                flex-direction: column;
                align-items: center;
            }
            
            .podium-place {
                order: unset !important;
                margin-bottom: 20px;
            }
            
            .table-row {
                grid-template-columns: 60px 1fr 80px;
                gap: 10px;
            }
            
            .total-donations,
            .blood-types,
            .last-donation {
                display: none;
            }
            
            .stats-summary {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .leaderboard-title {
                font-size: 24px;
                flex-direction: column;
                gap: 10px;
            }
            
            .podium-avatar {
                width: 80px;
                height: 80px;
                font-size: 32px;
            }
            
            .first .podium-avatar {
                width: 100px;
                height: 100px;
                font-size: 40px;
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
    <div class="leaderboard-container">
        <div class="leaderboard-header">
            <h1 class="leaderboard-title">
                <i class="fas fa-trophy"></i>
                Blood Donors Leaderboard
            </h1>
            <p class="leaderboard-subtitle">Celebrating our heroes who save lives through blood donation</p>
            
            <!-- Statistics Summary -->
            <div class="stats-summary">
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $totalDonors; ?></div>
                        <div class="stat-label">Total Donors</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $totalUnitsAll; ?></div>
                        <div class="stat-label">Units Donated</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $averageUnits; ?></div>
                        <div class="stat-label">Average Units</div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="message error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($topDonors)): ?>
            <!-- Top 3 Podium -->
            <?php if (count($topDonors) >= 3): ?>
            <div class="podium-section">
                <h2 class="podium-title">
                    <i class="fas fa-crown" style="color: #ffd700;"></i>
                    Top 3 Heroes
                </h2>
                
                <div class="podium">
                    <?php for ($i = 0; $i < min(3, count($topDonors)); $i++) { 
                        $donor = $topDonors[$i];
                        $achievement = getAchievementBadge($donor['total_units']);
                        $placeClass = ['first', 'second', 'third'][$i];
                    ?>
                        <div class="podium-place <?php echo $placeClass; ?>">
                            <div class="podium-avatar">
                                <?php if ($i === 0): ?>
                                    <i class="podium-crown fas fa-crown"></i>
                                <?php endif; ?>
                                <?php echo strtoupper(substr($donor['username'], 0, 2)); ?>
                            </div>
                            <div class="podium-rank">#<?php echo $donor['rank']; ?></div>
                            <div class="podium-name"><?php echo htmlspecialchars($donor['username']); ?></div>
                            <div class="podium-stats">
                                <div class="donor-badge badge-<?php echo $achievement['class']; ?>">
                                    <i class="<?php echo $achievement['icon']; ?>"></i>
                                    <?php echo $achievement['badge']; ?>
                                </div>
                            </div>
                            <div class="podium-units"><?php echo $donor['total_units']; ?> Units</div>
                            <div class="podium-base"></div>
                        </div>
                    <?php } ?>

                </div>
            </div>
            <?php endif; ?>
            
            <!-- User's Rank Section -->
            <?php if ($userStats): ?>
            <div class="user-rank-section">
                <h3 class="user-rank-title">
                    <i class="fas fa-user"></i>
                    Your Statistics
                </h3>
                
                <div class="user-rank-stats">
                    <div class="user-stat">
                        <div class="user-stat-number">#<?php echo $userRank ?? 'N/A'; ?></div>
                        <div class="user-stat-label">Your Rank</div>
                    </div>
                    <div class="user-stat">
                        <div class="user-stat-number"><?php echo $userStats['total_units']; ?></div>
                        <div class="user-stat-label">Units Donated</div>
                    </div>
                    <div class="user-stat">
                        <div class="user-stat-number"><?php echo $userStats['total_donations']; ?></div>
                        <div class="user-stat-label">Total Donations</div>
                    </div>
                    <div class="user-stat">
                        <div class="user-stat-number"><?php 
                            $achievement = getAchievementBadge($userStats['total_units']);
                            echo $achievement['badge'];
                        ?></div>
                        <div class="user-stat-label">Achievement</div>
                    </div>
                </div>
                
                <?php if ($userStats['total_units'] == 0): ?>
                    <div style="text-align: center; margin-top: 20px; padding: 20px; background: rgba(255,255,255,0.8); border-radius: 10px;">
                        <p style="color: #0d47a1; font-weight: 600; margin: 0;">
                            <i class="fas fa-heart" style="color: #dc3545;"></i>
                            Ready to make your first donation and join our heroes? Every donation saves lives!
                        </p>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Full Leaderboard Table -->
            <div class="leaderboard-table">
                <div class="table-header">
                    <i class="fas fa-list-ol"></i>
                    Complete Leaderboard
                </div>
                
                <div class="table-header" style="display: grid; grid-template-columns: 80px 1fr 120px 120px 120px 150px; background: #f8f9fa; color: #495057; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; padding: 15px 20px;">
                    <div>Rank</div>
                    <div>Donor</div>
                    <div>Units</div>
                    <div>Donations</div>
                    <div>Blood Types</div>
                    <div>Last Donation</div>
                </div>
                
                <?php foreach ($topDonors as $index => $donor): 
                    $achievement = getAchievementBadge($donor['total_units']);
                    $isCurrentUser = ($donor['user_id'] == $_SESSION['user_id']);
                ?>
                    <div class="table-row <?php echo $isCurrentUser ? 'current-user' : ''; ?>">
                        <div class="rank-number">
                            <?php if ($donor['rank'] <= 3): ?>
                                <div class="rank-top3"><?php echo $donor['rank']; ?></div>
                            <?php else: ?>
                                #<?php echo $donor['rank']; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="donor-info">
                            <div class="donor-avatar">
                                <?php echo strtoupper(substr($donor['username'], 0, 2)); ?>
                            </div>
                            <div class="donor-details">
                                <h4>
                                    <?php echo htmlspecialchars($donor['username']); ?>
                                    <?php if ($isCurrentUser): ?>
                                        <small style="color: #28a745; font-weight: 600;">(You)</small>
                                    <?php endif; ?>
                                </h4>
                                <div class="donor-badge badge-<?php echo $achievement['class']; ?>">
                                    <i class="<?php echo $achievement['icon']; ?>"></i>
                                    <?php echo $achievement['badge']; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="units-donated"><?php echo $donor['total_units']; ?></div>
                        <div class="total-donations"><?php echo $donor['total_donations']; ?></div>
                        <div class="blood-types"><?php echo $donor['blood_types_donated'] ?: 'N/A'; ?></div>
                        <div class="last-donation"><?php echo getTimeAgo($donor['last_donation']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Achievements Guide -->
            <div class="leaderboard-table" style="margin-top: 30px;">
                <div class="table-header">
                    <i class="fas fa-award"></i>
                    Achievement Levels
                </div>
                
                <div style="padding: 30px;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                        <div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 12px;">
                            <div class="donor-badge badge-legendary" style="font-size: 14px; margin-bottom: 10px;">
                                <i class="fas fa-crown"></i> Legendary Hero
                            </div>
                            <p style="margin: 0; color: #6c757d; font-size: 14px;">50+ Units Donated</p>
                        </div>
                        
                        <div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 12px;">
                            <div class="donor-badge badge-champion" style="font-size: 14px; margin-bottom: 10px;">
                                <i class="fas fa-trophy"></i> Blood Champion
                            </div>
                            <p style="margin: 0; color: #6c757d; font-size: 14px;">25+ Units Donated</p>
                        </div>
                        
                        <div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 12px;">
                            <div class="donor-badge badge-guardian" style="font-size: 14px; margin-bottom: 10px;">
                                <i class="fas fa-shield-alt"></i> Life Guardian
                            </div>
                            <p style="margin: 0; color: #6c757d; font-size: 14px;">15+ Units Donated</p>
                        </div>
                        
                        <div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 12px;">
                            <div class="donor-badge badge-hero" style="font-size: 14px; margin-bottom: 10px;">
                                <i class="fas fa-medal"></i> Hero Donor
                            </div>
                            <p style="margin: 0; color: #6c757d; font-size: 14px;">10+ Units Donated</p>
                        </div>
                        
                        <div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 12px;">
                            <div class="donor-badge badge-noble" style="font-size: 14px; margin-bottom: 10px;">
                                <i class="fas fa-star"></i> Noble Donor
                            </div>
                            <p style="margin: 0; color: #6c757d; font-size: 14px;">5+ Units Donated</p>
                        </div>
                        
                        <div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 12px;">
                            <div class="donor-badge badge-saver" style="font-size: 14px; margin-bottom: 10px;">
                                <i class="fas fa-heart"></i> Life Saver
                            </div>
                            <p style="margin: 0; color: #6c757d; font-size: 14px;">1+ Units Donated</p>
                        </div>
                    </div>
                    
                    <div style="text-align: center; margin-top: 30px; padding: 20px; background: linear-gradient(90deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05)); border-radius: 12px; border-left: 4px solid #dc3545;">
                        <h4 style="color: #721c24; margin: 0 0 10px 0;">
                            <i class="fas fa-info-circle"></i>
                            Did You Know?
                        </h4>
                        <p style="color: #721c24; margin: 0; line-height: 1.6;">
                            One blood donation can save up to 3 lives! Join our community of heroes and help save lives in your area.
                            Every donation counts towards building a stronger, healthier community.
                        </p>
                    </div>
                </div>
            </div>
            
        <?php else: ?>
            <div class="leaderboard-table">
                <div class="table-header">
                    <i class="fas fa-info-circle"></i>
                    No Donations Yet
                </div>
                <div style="padding: 50px; text-align: center;">
                    <i class="fas fa-heart" style="font-size: 64px; color: #dc3545; opacity: 0.3; margin-bottom: 20px;"></i>
                    <h3 style="color: #495057; margin-bottom: 15px;">Be the First Hero!</h3>
                    <p style="color: #6c757d; margin-bottom: 30px; line-height: 1.6;">
                        No one has made a donation yet. Be the first to save lives and start our leaderboard!
                    </p>
                    <a href="donate.php" class="action-btn btn-primary">
                        <i class="fas fa-heart"></i>
                        Make First Donation
                    </a>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <?php if ($userStats && $userStats['total_units'] > 0): ?>
                <a href="appointments.php" class="action-btn btn-secondary">
                    <i class="fas fa-history"></i>
                    View My Donations
                </a>
            <?php endif; ?>
            
            <a href="donate.php" class="action-btn btn-primary">
                <i class="fas fa-heart"></i>
                Donate Blood
            </a>
            
            <a href="index.php" class="action-btn btn-secondary" style="background: linear-gradient(135deg, #6c757d, #5a6268);">
                <i class="fas fa-home"></i>
                Back to Dashboard
            </a>
        </div>
        
        <!-- Fun Facts Section -->
        <div style="margin-top: 40px; padding: 30px; background: linear-gradient(135deg, #e8f5e8, #c8e6c9); border-radius: 15px; border-left: 5px solid #4caf50;">
            <h3 style="color: #2e7d32; margin: 0 0 20px 0; text-align: center;">
                <i class="fas fa-lightbulb"></i>
                Blood Donation Impact
            </h3>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; text-align: center;">
                <div>
                    <div style="font-size: 32px; font-weight: 700; color: #2e7d32; margin-bottom: 8px;">
                        <?php echo $totalUnitsAll * 3; ?>
                    </div>
                    <div style="color: #4caf50; font-weight: 600; font-size: 14px;">Lives Potentially Saved</div>
                </div>
                
                <div>
                    <div style="font-size: 32px; font-weight: 700; color: #2e7d32; margin-bottom: 8px;">
                        <?php echo $totalUnitsAll * 450; ?>ml
                    </div>
                    <div style="color: #4caf50; font-weight: 600; font-size: 14px;">Total Blood Donated</div>
                </div>
                
                <div>
                    <div style="font-size: 32px; font-weight: 700; color: #2e7d32; margin-bottom: 8px;">
                        <?php echo number_format($totalUnitsAll * 450 / 1000, 1); ?>L
                    </div>
                    <div style="color: #4caf50; font-weight: 600; font-size: 14px;">Liters of Life</div>
                </div>
            </div>
            
            <p style="text-align: center; margin: 20px 0 0 0; color: #2e7d32; font-style: italic;">
                "Every hero in our leaderboard has contributed to saving lives and building a healthier community!"
            </p>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add animation to podium on load
    const podiumPlaces = document.querySelectorAll('.podium-place');
    podiumPlaces.forEach((place, index) => {
        place.style.opacity = '0';
        place.style.transform = 'translateY(50px)';
        place.style.transition = 'all 0.8s cubic-bezier(0.4, 0, 0.2, 1)';
        
        setTimeout(() => {
            place.style.opacity = '1';
            place.style.transform = 'translateY(0)';
        }, (index + 1) * 200);
    });
    
    // Add stagger animation to table rows
    const tableRows = document.querySelectorAll('.table-row');
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
            if (entry.isIntersecting) {
                setTimeout(() => {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateX(0)';
                }, index * 50);
            }
        });
    }, observerOptions);
    
    tableRows.forEach(row => {
        row.style.opacity = '0';
        row.style.transform = 'translateX(-20px)';
        row.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(row);
    });
    
    // Add click handler for podium places to show more info
    podiumPlaces.forEach(place => {
        place.addEventListener('click', function() {
            const username = this.querySelector('.podium-name').textContent;
            const units = this.querySelector('.podium-units').textContent;
            const rank = this.querySelector('.podium-rank').textContent;
            
            alert(`🏆 ${rank} - ${username}\n\n${units}\n\nClick "View Donations" to see their full history!`);
        });
    });
    
    // Add hover effects to achievement badges
    const badges = document.querySelectorAll('.donor-badge');
    badges.forEach(badge => {
        badge.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.1)';
            this.style.transition = 'transform 0.3s ease';
        });
        
        badge.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });
    
    // Auto-refresh leaderboard every 5 minutes
    setInterval(() => {
        // Only refresh if user is active (has interacted recently)
        if (document.hasFocus()) {
            location.reload();
        }
    }, 5 * 60 * 1000);
    
    // Add confetti effect for top 3 (if user wants a celebration)
    <?php if ($userStats && isset($userRank) && $userRank <= 3): ?>
    // User is in top 3, add celebration effect
    setTimeout(() => {
        const colors = ['#ffd700', '#c0c0c0', '#cd7f32'];
        for (let i = 0; i < 50; i++) {
            createConfetti(colors[Math.floor(Math.random() * colors.length)]);
        }
    }, 1000);
    
    function createConfetti(color) {
        const confetti = document.createElement('div');
        confetti.style.position = 'fixed';
        confetti.style.width = '10px';
        confetti.style.height = '10px';
        confetti.style.backgroundColor = color;
        confetti.style.left = Math.random() * window.innerWidth + 'px';
        confetti.style.top = '-10px';
        confetti.style.zIndex = '10000';
        confetti.style.borderRadius = '50%';
        confetti.style.pointerEvents = 'none';
        
        document.body.appendChild(confetti);
        
        const animation = confetti.animate([
            { transform: 'translateY(0) rotate(0deg)', opacity: 1 },
            { transform: 'translateY(' + (window.innerHeight + 100) + 'px) rotate(720deg)', opacity: 0 }
        ], {
            duration: 3000 + Math.random() * 2000,
            easing: 'cubic-bezier(0.25, 0.46, 0.45, 0.94)'
        });
        
        animation.addEventListener('finish', () => {
            confetti.remove();
        });
    }
    <?php endif; ?>
    
    console.log('Leaderboard page initialized successfully');
    console.log('Total donors:', <?php echo $totalDonors; ?>);
    console.log('User rank:', <?php echo ($userRank ? $userRank : 'null'); ?>);
});
</script>

</body>
<?php include('includes/footer.php'); ?>
</html>