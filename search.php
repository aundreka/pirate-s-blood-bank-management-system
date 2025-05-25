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
$isAdmin = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';

// Initialize search variables
$searchQuery = isset($_GET['q']) ? trim($_GET['q']) : '';
$searchType = isset($_GET['type']) ? $_GET['type'] : 'all';
$bloodType = isset($_GET['blood_type']) ? $_GET['blood_type'] : '';
$location = isset($_GET['location']) ? trim($_GET['location']) : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Results arrays
$donorResults = [];
$eventResults = [];
$donationResults = [];
$totalResults = 0;

// Search logic
if (!empty($searchQuery) || !empty($bloodType) || !empty($location) || !empty($dateFrom)) {
    
    // Search for events/drives
    if ($searchType === 'all' || $searchType === 'events') {
        try {
            $eventQuery = "SELECT event_id, title, description, event_date, start_time, end_time, location, event_image, max_slots, booked_slots 
                          FROM events WHERE status = 'upcoming' AND (";
            $eventParams = [];
            $eventTypes = [];
            $eventTypes = "";   
            $conditions = [];
            
            if (!empty($searchQuery)) {
                $conditions[] = "(title LIKE ? OR description LIKE ? OR location LIKE ?)";
                $eventParams[] = "%$searchQuery%";
                $eventParams[] = "%$searchQuery%";
                $eventParams[] = "%$searchQuery%";
                $eventTypes = $eventTypes . "sss";
            }
            
            if (!empty($location)) {
                $conditions[] = "location LIKE ?";
                $eventParams[] = "%$location%";
                $eventTypes = $eventTypes . "s";
            }
            
            if (!empty($dateFrom)) {
                $conditions[] = "event_date >= ?";
                $eventParams[] = $dateFrom;
                $eventTypes = $eventTypes . "s";
            }
            
            if (!empty($dateTo)) {
                $conditions[] = "event_date <= ?";
                $eventParams[] = $dateTo;
                $eventTypes = $eventTypes . "s";
            }
            
            if (empty($conditions)) {
                $conditions[] = "1=1";
            }
            
            $eventQuery .= implode(" AND ", $conditions) . ") ORDER BY event_date ASC LIMIT 20";
            
            $eventStmt = $conn->prepare($eventQuery);
            if (!empty($eventParams)) {
                $eventStmt->bind_param($eventTypes, ...$eventParams);
            }
            $eventStmt->execute();
            $eventResult = $eventStmt->get_result();
            
            while ($row = $eventResult->fetch_assoc()) {
                $row['slots_available'] = $row['max_slots'] - $row['booked_slots'];
                $eventResults[] = $row;
            }
            $eventStmt->close();
        } catch (Exception $e) {
            error_log("Error searching events: " . $e->getMessage());
        }
    }
    
    // Search for donors (admin only)
if (($searchType === 'all' || $searchType === 'donors')) {
    try {
        $donorQuery = "SELECT l.user_id, l.username, l.created_at, 
                      COUNT(db.donation_id) as total_donations,
                      MAX(db.date_of_donation) as last_donation
                      FROM login l 
                      LEFT JOIN donate_blood db ON l.user_id = db.donor_id 
                      WHERE l.user_type = 'user'";
        $donorParams = [];
        $donorTypes = "";
        
        // Only add search condition if there's actually a search query
        if (!empty($searchQuery)) {
            $donorQuery .= " AND l.username LIKE ?";
            $donorParams[] = "%$searchQuery%";
            $donorTypes = "s";
        }
        
        $donorQuery .= " GROUP BY l.user_id ORDER BY l.username ASC LIMIT 20";
        
        $donorStmt = $conn->prepare($donorQuery);
        if (!empty($donorParams)) {
            $donorStmt->bind_param($donorTypes, ...$donorParams);
        }
        $donorStmt->execute();
        $donorResult = $donorStmt->get_result();
        
        while ($row = $donorResult->fetch_assoc()) {
            $donorResults[] = $row;
        }
        $donorStmt->close();
    } catch (Exception $e) {
        error_log("Error searching donors: " . $e->getMessage());
        // Add this for debugging
        echo "<!-- DEBUG ERROR: " . $e->getMessage() . " -->";
    }
}
    
    // Search for donations (admin only)
    if ($isAdmin && ($searchType === 'all' || $searchType === 'donations')) {
        try {
            $donationQuery = "SELECT db.donation_id, db.donor_id, l.username, db.blood_type, 
                             db.date_of_donation, db.donation_status, db.hospital_location, db.created_at
                             FROM donate_blood db 
                             JOIN login l ON db.donor_id = l.user_id 
                             WHERE 1=1";
            $donationParams = [];
            $donationTypes = "";
            
            if (!empty($searchQuery)) {
                $donationQuery .= " AND (l.username LIKE ? OR db.hospital_location LIKE ?)";
                $donationParams[] = "%$searchQuery%";
                $donationParams[] = "%$searchQuery%";
$donationTypes = $donationTypes . "ss";
            }
            
            if (!empty($bloodType)) {
                $donationQuery .= " AND db.blood_type = ?";
                $donationParams[] = $bloodType;
$donationTypes = $donationTypes . "s";
            }
            
            if (!empty($location)) {
                $donationQuery .= " AND db.hospital_location LIKE ?";
                $donationParams[] = "%$location%";
$donationTypes = $donationTypes . "s";
            }
            
            if (!empty($dateFrom)) {
                $donationQuery .= " AND db.date_of_donation >= ?";
                $donationParams[] = $dateFrom;
$donationTypes = $donationTypes . "s";
            }
            
            if (!empty($dateTo)) {
                $donationQuery .= " AND db.date_of_donation <= ?";
                $donationParams[] = $dateTo;
$donationTypes = $donationTypes . "s";
            }
            
            $donationQuery .= " ORDER BY db.date_of_donation DESC LIMIT 20";
            
            $donationStmt = $conn->prepare($donationQuery);
            if (!empty($donationParams)) {
                $donationStmt->bind_param($donationTypes, ...$donationParams);
            }
            $donationStmt->execute();
            $donationResult = $donationStmt->get_result();
            
            while ($row = $donationResult->fetch_assoc()) {
                $donationResults[] = $row;
            }
            $donationStmt->close();
        } catch (Exception $e) {
            error_log("Error searching donations: " . $e->getMessage());
        }
    }
}

$totalResults = count($eventResults) + count($donorResults) + count($donationResults);

// Get blood types for filter
$bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search - Pirate's Blood Bank</title>
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
            transition: margin-left 0.3s ease;
        }

        /* Search Header */
        .search-header {
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
            text-align: center;
        }

        .search-header h1 {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 15px;
            color: white;
            font-family: 'Garet', sans-serif;
        }

        .search-header p {
            font-size: 18px;
            opacity: 0.9;
        }

        /* Search Form */
        .search-form {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .search-row {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 20px;
            margin-bottom: 20px;
            align-items: end;
        }

        .search-input-group {
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: #dc3545;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
        }

        .search-btn {
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .search-btn:hover {
            background: linear-gradient(135deg, #c82333, #a02232);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.3);
        }

        /* Advanced Filters */
        .advanced-filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-label {
            font-size: 14px;
            font-weight: 600;
            color: #333;
        }

        .filter-select, .filter-input {
            padding: 10px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .filter-select:focus, .filter-input:focus {
            outline: none;
            border-color: #dc3545;
        }

        /* Search Tabs */
        .search-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
        }

        .search-tab {
            padding: 12px 20px;
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 25px;
            font-weight: 500;
            color: #666;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .search-tab.active {
            background: #dc3545;
            color: white;
            border-color: #dc3545;
        }

        .search-tab:hover {
            border-color: #dc3545;
            color: #dc3545;
        }

        .search-tab.active:hover {
            color: white;
        }

        /* Results */
        .results-summary {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .results-grid {
            display: grid;
            gap: 25px;
        }

        /* Event Results */
        .event-result {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border-left: 4px solid #dc3545;
            margin-bottom: 20px;
        }

        .event-result:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }

        .event-result-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .event-result-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .event-result-date {
            background: #dc3545;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .event-result-description {
            color: #666;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .event-result-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .event-result-detail {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            font-size: 14px;
        }

        .event-result-icon {
            color: #dc3545;
            width: 16px;
        }

        .event-result-actions {
            display: flex;
            gap: 10px;
        }

        .event-action-btn {
            background: #dc3545;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 15px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .event-action-btn:hover {
            background: #c82333;
            transform: translateY(-1px);
            text-decoration: none;
            color: white;
        }

        /* Donor Results */
        .donor-result {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border-left: 4px solid #28a745;
        }

        .donor-result:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }

        .donor-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .donor-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #28a745, #20c997);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: 700;
        }

        .donor-details h3 {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .donor-stats {
            display: flex;
            gap: 20px;
            color: #666;
            font-size: 14px;
        }

        .donor-stat {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Donation Results */
        .donation-result {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border-left: 4px solid #17a2b8;
        }

        .donation-result:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }

        .donation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .blood-type-badge {
            background: #17a2b8;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .donation-status {
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-completed { background: #cce5ff; color: #004085; }
        .status-rejected { background: #f8d7da; color: #721c24; }

        /* No Results */
        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .no-results i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .no-results h3 {
            font-size: 24px;
            margin-bottom: 15px;
            color: #333;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }

            .search-row {
                grid-template-columns: 1fr;
            }

            .advanced-filters {
                grid-template-columns: 1fr;
            }

            .search-tabs {
                flex-wrap: wrap;
            }

            .event-result-header {
                flex-direction: column;
                gap: 10px;
            }

            .event-result-details {
                grid-template-columns: 1fr;
            }

            .donor-info {
                flex-direction: column;
                text-align: center;
            }

            .donor-stats {
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <?php 
    if ($isAdmin) {
        include('includes/adminheader.php');
    } else {
        include('includes/header.php');
    }
    ?>
    <?php include('includes/sidebar.php'); ?>

    <div class="main-content">
        <!-- Search Header -->
        <div class="search-header">
            <h1><i class="fas fa-search"></i> Search</h1>
            <p>Find blood drives, donors, and donation records</p>
        </div>

        <!-- Search Form -->
        <div class="search-form">
            <form method="GET" action="search.php">
                <div class="search-row">
                    <div class="search-input-group">
                        <input type="text" name="q" class="search-input" 
                               placeholder="Search for events, donors, or donations..." 
                               value="<?= htmlspecialchars($searchQuery) ?>">
                    </div>
                    <div class="search-input-group">
                        <select name="type" class="filter-select">
                            <option value="all" <?= $searchType === 'all' ? 'selected' : '' ?>>All Results</option>
                            <option value="events" <?= $searchType === 'events' ? 'selected' : '' ?>>Events Only</option>
                            <?php if ($isAdmin): ?>
                                <option value="donors" <?= $searchType === 'donors' ? 'selected' : '' ?>>Donors Only</option>
                                <option value="donations" <?= $searchType === 'donations' ? 'selected' : '' ?>>Donations Only</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i>
                        Search
                    </button>
                </div>

                <!-- Advanced Filters -->
                <div class="advanced-filters">

                    <div class="filter-group">
                        <label class="filter-label">Location</label>
                        <input type="text" name="location" class="filter-input" 
                               placeholder="Enter location..." value="<?= htmlspecialchars($location) ?>">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Date From</label>
                        <input type="date" name="date_from" class="filter-input" value="<?= htmlspecialchars($dateFrom) ?>">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Date To</label>
                        <input type="date" name="date_to" class="filter-input" value="<?= htmlspecialchars($dateTo) ?>">
                    </div>
                </div>
            </form>
        </div>

        <!-- Results Summary -->
        <?php if (!empty($searchQuery) || !empty($bloodType) || !empty($location) || !empty($dateFrom)): ?>
            <div class="results-summary">
                <h2>
                    <i class="fas fa-list"></i> 
                    Search Results 
                    <span style="color: #dc3545;">(<?= $totalResults ?> found)</span>
                </h2>
                <?php if (!empty($searchQuery)): ?>
                    <p>Results for: <strong>"<?= htmlspecialchars($searchQuery) ?>"</strong></p>
                <?php endif; ?>
            </div>

            <div class="results-grid">
                <!-- Event Results -->
                <?php if (!empty($eventResults)): ?>
                    <section>
                        <h3 style="margin-bottom: 20px; color: #333;">
                            <i class="fas fa-calendar-alt" style="color: #dc3545;"></i> 
                            Blood Drives (<?= count($eventResults) ?>)
                        </h3>
                        <?php foreach ($eventResults as $event): ?>
                            <div class="event-result">
                                <div class="event-result-header">
                                    <div>
                                        <h4 class="event-result-title"><?= htmlspecialchars($event['title']) ?></h4>
                                        <p class="event-result-description"><?= htmlspecialchars($event['description']) ?></p>
                                    </div>
                                    <div class="event-result-date">
                                        <?= date('M j, Y', strtotime($event['event_date'])) ?>
                                    </div>
                                </div>
                                
                                <div class="event-result-details">
                                    <div class="event-result-detail">
                                        <i class="fas fa-clock event-result-icon"></i>
                                        <span><?= date('g:i A', strtotime($event['start_time'])) ?> - <?= date('g:i A', strtotime($event['end_time'])) ?></span>
                                    </div>
                                    <div class="event-result-detail">
                                        <i class="fas fa-map-marker-alt event-result-icon"></i>
                                        <span><?= htmlspecialchars($event['location']) ?></span>
                                    </div>
                                    <div class="event-result-detail">
                                        <i class="fas fa-users event-result-icon"></i>
                                        <span><?= $event['slots_available'] ?> slots available</span>
                                    </div>
                                </div>
                                
                                <div class="event-result-actions">
                                    <a href="register.php?event_id=<?= $event['event_id'] ?>" class="event-action-btn">
                                        <i class="fas fa-info-circle"></i> View Details
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </section>
                <?php endif; ?>

                <!-- Donor Results (Admin Only) -->
                <?php if (!empty($donorResults)): ?>
                    <section>
                        <h3 style="margin-bottom: 20px; color: #333;">
                            <i class="fas fa-users" style="color: #28a745;"></i> 
                            Donors (<?= count($donorResults) ?>)
                        </h3>
                        <?php foreach ($donorResults as $donor): ?>
                            <div class="donor-result">
                                <div class="donor-info">
                                    <div class="donor-avatar">
                                        <?= strtoupper(substr($donor['username'], 0, 1)) ?>
                                    </div>
                                    <div class="donor-details">
                                        <h3><?= htmlspecialchars($donor['username']) ?></h3>
                                        <div class="donor-stats">
                                            <div class="donor-stat">
                                                <i class="fas fa-tint"></i>
                                                <span><?= $donor['total_donations'] ?> donations</span>
                                            </div>
                                            <div class="donor-stat">
                                                <i class="fas fa-calendar"></i>
                                                <span>Joined <?= date('M Y', strtotime($donor['created_at'])) ?></span>
                                            </div>
                                            <?php if ($donor['last_donation']): ?>
                                                <div class="donor-stat">
                                                    <i class="fas fa-clock"></i>
                                                    <span>Last: <?= date('M j, Y', strtotime($donor['last_donation'])) ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </section>
                <?php endif; ?>

                <!-- Donation Results (Admin Only) -->
                <?php if ($isAdmin && !empty($donationResults)): ?>
                    <section>
                        <h3 style="margin-bottom: 20px; color: #333;">
                            <i class="fas fa-heartbeat" style="color: #17a2b8;"></i> 
                            Donations (<?= count($donationResults) ?>)
                        </h3>
                        <?php foreach ($donationResults as $donation): ?>
                            <div class="donation-result">
                                <div class="donation-header">
                                    <div>
                                        <h4><?= htmlspecialchars($donation['username']) ?></h4>
                                        <p style="color: #666; font-size: 14px;">
                                            <i class="fas fa-map-marker-alt"></i> 
                                            <?= htmlspecialchars($donation['hospital_location']) ?>
                                        </p>
                                    </div>
                                    <div style="display: flex; gap: 10px; align-items: center;">
                                        <div class="blood-type-badge"><?= htmlspecialchars($donation['blood_type']) ?></div>
                                        <div class="donation-status status-<?= strtolower($donation['donation_status']) ?>">
                                            <?= htmlspecialchars($donation['donation_status']) ?>
                                        </div>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 20px; color: #666; font-size: 14px;">
                                    <div>
                                        <i class="fas fa-calendar"></i> 
                                        <?= date('M j, Y', strtotime($donation['date_of_donation'])) ?>
                                    </div>
                                    <div>
                                        <i class="fas fa-clock"></i> 
                                        <?= date('g:i A', strtotime($donation['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </section>
                <?php endif; ?>

                <!-- No Results -->
                <?php if ($totalResults === 0): ?>
                    <div class="no-results">
                        <i class="fas fa-search"></i>
                        <h3>No results found</h3>
                        <p>Try adjusting your search terms or filters to find what you're looking for.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Search Tips -->
            <div class="search-form" style="text-align: center;">
                <h3 style="margin-bottom: 20px; color: #333;">
                    <i class="fas fa-lightbulb" style="color: #ffc107;"></i> 
                    Search Tips
                </h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 30px;">
                    <div style="padding: 20px; background: #f8f9fa; border-radius: 10px;">
                        <i class="fas fa-calendar-alt" style="color: #dc3545; font-size: 24px; margin-bottom: 10px;"></i>
                        <h4>Find Blood Drives</h4>
                        <p style="color: #666; font-size: 14px;">Search by event name, location, or date to find upcoming blood drives near you.</p>
                    </div>
                    <?php if ($isAdmin): ?>
                    <div style="padding: 20px; background: #f8f9fa; border-radius: 10px;">
                        <i class="fas fa-users" style="color: #28a745; font-size: 24px; margin-bottom: 10px;"></i>
                        <h4>Search Donors</h4>
                        <p style="color: #666; font-size: 14px;">Find registered donors by username or view their donation history.</p>
                    </div>
                    <div style="padding: 20px; background: #f8f9fa; border-radius: 10px;">
                        <i class="fas fa-heartbeat" style="color: #17a2b8; font-size: 24px; margin-bottom: 10px;"></i>
                        <h4>Track Donations</h4>
                        <p style="color: #666; font-size: 14px;">Search donations by blood type, location, date range, or donor name.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add animation to results
            const results = document.querySelectorAll('.event-result, .donor-result, .donation-result');
            results.forEach((result, index) => {
                result.style.opacity = '0';
                result.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    result.style.transition = 'all 0.6s ease';
                    result.style.opacity = '1';
                    result.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Auto-focus search input
            const searchInput = document.querySelector('.search-input');
            if (searchInput && !searchInput.value) {
                searchInput.focus();
            }

            // Form submission loading state
            const searchForm = document.querySelector('.search-form form');
            const searchBtn = document.querySelector('.search-btn');
            
            if (searchForm && searchBtn) {
                searchForm.addEventListener('submit', function() {
                    searchBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Searching...';
                    searchBtn.disabled = true;
                });
            }

            // Highlight search terms in results
            const searchQuery = '<?= htmlspecialchars($searchQuery) ?>';
            if (searchQuery.length > 0) {
                highlightSearchTerms(searchQuery);
            }

            console.log('Search page initialized');
        });

        function highlightSearchTerms(query) {
            const results = document.querySelectorAll('.event-result, .donor-result, .donation-result');
            const regex = new RegExp(`(${query})`, 'gi');
            
            results.forEach(result => {
                const textNodes = getTextNodes(result);
                textNodes.forEach(node => {
                    if (node.textContent.toLowerCase().includes(query.toLowerCase())) {
                        const parent = node.parentNode;
                        const html = node.textContent.replace(regex, '<mark style="background: #fff3cd; padding: 2px 4px; border-radius: 3px;">$1</mark>');
                        const wrapper = document.createElement('span');
                        wrapper.innerHTML = html;
                        parent.replaceChild(wrapper, node);
                    }
                });
            });
        }

        function getTextNodes(element) {
            const textNodes = [];
            const walker = document.createTreeWalker(
                element,
                NodeFilter.SHOW_TEXT,
                null,
                false
            );
            
            let node;
            while (node = walker.nextNode()) {
                if (node.textContent.trim()) {
                    textNodes.push(node);
                }
            }
            
            return textNodes;
        }

        // Clear filters function
        function clearFilters() {
            document.querySelector('input[name="q"]').value = '';
            document.querySelector('select[name="type"]').value = 'all';
            document.querySelector('select[name="blood_type"]').value = '';
            document.querySelector('input[name="location"]').value = '';
            document.querySelector('input[name="date_from"]').value = '';
            document.querySelector('input[name="date_to"]').value = '';
        }

        // Add clear filters button functionality
        const clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.className = 'search-btn';
        clearBtn.style.background = '#6c757d';
        clearBtn.innerHTML = '<i class="fas fa-times"></i> Clear';
        clearBtn.onclick = clearFilters;
        
        const searchRow = document.querySelector('.search-row');
        if (searchRow) {
            searchRow.appendChild(clearBtn);
            searchRow.style.gridTemplateColumns = '2fr 1fr auto auto';
        }
    </script>

    <?php include('includes/footer.php'); ?>
</body>
</html>