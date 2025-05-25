<?php
include('includes/db.php');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Initialize variables
$profileInitial = '?';
$username = 'Guest';
$userType = 'Guest';
$isLoggedIn = false;
$userId = null;

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    $isLoggedIn = true;
    $userId = $_SESSION['user_id'];
    
    // Fetch user data from database
    $query = "SELECT username, user_type FROM login WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $userData = $result->fetch_assoc();
        $username = htmlspecialchars($userData['username']);
        $userType = $userData['user_type'] == 'user' ? 'User' : 'Admin';
        $profileInitial = strtoupper(substr($username, 0, 1));
    }
    $stmt->close();
}

// Get community statistics
$communityStats = [
    'total_members' => 0,
    'total_donations' => 0,
    'this_month_donations' => 0,
    'total_events' => 0
];

// Total community members
try {
    $result = $conn->query("SELECT COUNT(*) as count FROM login WHERE user_type = 'user'");
    if ($result && $row = $result->fetch_assoc()) {
        $communityStats['total_members'] = $row['count'];
    }
} catch (Exception $e) {
    error_log("Error getting total members: " . $e->getMessage());
}

// Total donations
try {
    $result = $conn->query("SELECT COUNT(*) as count FROM donate_blood");
    if ($result && $row = $result->fetch_assoc()) {
        $communityStats['total_donations'] = $row['count'];
    }
} catch (Exception $e) {
    error_log("Error getting total donations: " . $e->getMessage());
}

// This month donations
try {
    $result = $conn->query("SELECT COUNT(*) as count FROM donate_blood WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    if ($result && $row = $result->fetch_assoc()) {
        $communityStats['this_month_donations'] = $row['count'];
    }
} catch (Exception $e) {
    error_log("Error getting this month donations: " . $e->getMessage());
}

// Total events
try {
    $result = $conn->query("SHOW TABLES LIKE 'events'");
    if ($result && $result->num_rows > 0) {
        $result = $conn->query("SELECT COUNT(*) as count FROM events");
        if ($result && $row = $result->fetch_assoc()) {
            $communityStats['total_events'] = $row['count'];
        }
    }
} catch (Exception $e) {
    error_log("Error getting total events: " . $e->getMessage());
}

// Get recent success stories
$recentSuccessStories = [];
try {
    $result = $conn->query("SHOW TABLES LIKE 'success_stories'");
    if ($result && $result->num_rows > 0) {
        $query = "SELECT title, content, blood_units_used, story_date FROM success_stories WHERE status = 'active' ORDER BY story_date DESC LIMIT 3";
        $result = $conn->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $recentSuccessStories[] = [
                    'title' => $row['title'],
                    'content' => $row['content'],
                    'date' => $row['story_date'],
                    'impact' => $row['blood_units_used'] . ' units of blood used'
                ];
            }
        }
    } else {
        // Sample success stories
        $recentSuccessStories = [
            [
                'title' => 'Emergency Surgery Success',
                'content' => 'Thanks to blood donations, Maria was able to receive life-saving surgery after a car accident. She is now fully recovered and back with her family.',
                'date' => date('Y-m-d', strtotime('-5 days')),
                'impact' => '4 units of blood used'
            ],
            [
                'title' => 'Cancer Patient Recovery',
                'content' => 'John, a 45-year-old cancer patient, received multiple blood transfusions during his treatment. He is now in remission and grateful to all donors.',
                'date' => date('Y-m-d', strtotime('-12 days')),
                'impact' => '8 units of blood used'
            ]
        ];
    }
} catch (Exception $e) {
    error_log("Error getting success stories: " . $e->getMessage());
}

// Get upcoming events
$upcomingEvents = [];
try {
    $result = $conn->query("SHOW TABLES LIKE 'events'");
    if ($result && $result->num_rows > 0) {
        $query = "SELECT event_id, title, description, event_date, start_time, end_time, location, event_image, max_slots, booked_slots 
                FROM events 
                WHERE event_date >= CURRENT_DATE() AND status = 'upcoming' 
                ORDER BY event_date ASC, start_time ASC 
                LIMIT 6";
        $result = $conn->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $upcomingEvents[] = [
                    'id' => $row['event_id'],
                    'title' => $row['title'],
                    'description' => $row['description'],
                    'date' => $row['event_date'],
                    'start_time' => $row['start_time'],
                    'end_time' => $row['end_time'],
                    'time' => date('g:i A', strtotime($row['start_time'])) . ' - ' . date('g:i A', strtotime($row['end_time'])),
                    'location' => $row['location'],
                    'event_image' => $row['event_image'],

                    'max_slots' => $row['max_slots'],
                    'booked_slots' => $row['booked_slots'],
                    'slots_available' => $row['max_slots'] - $row['booked_slots']
                ];
            }
        }
    } else {
        // Sample events
        $upcomingEvents = [
            [
                'id' => 1,
                'title' => 'Holiday Blood Drive',
                'description' => 'Join us for our special holiday blood drive. Help save lives during the holiday season.',
                'date' => date('Y-m-d', strtotime('+7 days')),
                'time' => '9:00 AM - 3:00 PM',
                'location' => 'Community Center, Main Street',
                        'event_image' => 'assets/img/events/1.jpg',
                'slots_available' => 25
            ]
        ];
    }
} catch (Exception $e) {
    error_log("Error getting upcoming events: " . $e->getMessage());
}

$userRegisteredEvents = [];
if ($isLoggedIn && !empty($upcomingEvents)) {
    try {
        $eventIds = array_column($upcomingEvents, 'id');
        if (!empty($eventIds)) {
            $placeholders = str_repeat('?,', count($eventIds) - 1) . '?';
            $registeredQuery = "SELECT event_id, registration_date FROM event_registrations 
                              WHERE user_id = ? AND event_id IN ($placeholders) AND status = 'registered'";
            $registeredStmt = $conn->prepare($registeredQuery);
            $registeredStmt->bind_param(str_repeat('i', count($eventIds) + 1), $userId, ...$eventIds);
            $registeredStmt->execute();
            $registeredResult = $registeredStmt->get_result();
            
            while ($row = $registeredResult->fetch_assoc()) {
                $userRegisteredEvents[$row['event_id']] = $row['registration_date'];
            }
            $registeredStmt->close();
        }
    } catch (Exception $e) {
        error_log("Error getting user registrations: " . $e->getMessage());
    }
}

// Get blood type needs (sample data - you can create a table for this)
$bloodNeeds = [
    ['type' => 'O-', 'status' => 'urgent', 'label' => 'CRITICAL'],
    ['type' => 'A+', 'status' => 'low', 'label' => 'LOW'],
    ['type' => 'B-', 'status' => 'low', 'label' => 'LOW'],
    ['type' => 'AB+', 'status' => 'normal', 'label' => 'ADEQUATE']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community - Pirate's Blood Bank</title>
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
            background: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }

        .main-content {
            margin-left: 70px;
            padding: 20px;
            max-width: 100%;
            transition: margin-left 0.3s ease;
            min-height: 100vh;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        }

        /* Community Hero Section */
        .community-hero {
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
            padding: 60px 40px;
            border-radius: 20px;
            text-align: center;
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
        }

        .community-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="2" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
            opacity: 0.3;
        }

        .community-hero h1 {
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 20px;
            position: relative;
            z-index: 2;
            color: white;
        }

        .community-hero p {
            font-size: 20px;
            margin-bottom: 30px;
            opacity: 0.9;
            position: relative;
            z-index: 2;
        }

        .community-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
            margin-top: 40px;
            position: relative;
            z-index: 2;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 36px;
            font-weight: 700;
            display: block;
            margin-bottom: 10px;
        }

        .stat-label {
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.9;
        }

        /* Navigation Tabs */
        .nav-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #e9ecef;
            padding: 0;
            
        }
        .nav-tabs button{
            font-family: 'quicksand', sans-serif !important;

        }

        .nav-tab {
            background: none;
            border: none;
            padding: 15px 25px;
            font-size: 16px;
            font-weight: 500;
            color: #666;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-tab.active {
            color: #dc3545;
            border-bottom-color: #dc3545;
            background: rgba(220, 53, 69, 0.05);
        }

        .nav-tab:hover {
            color: #dc3545;
            background: rgba(220, 53, 69, 0.05);
        }

        /* Content Sections */
        .content-section {
            display: none;
        }

        .content-section.active {
            display: block;
            animation: fadeInUp 0.5s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Card Styles */
        .card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            margin-bottom: 30px;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f3f4;
        }

        .card-title {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-icon {
            color: #dc3545;
            font-size: 24px;
        }

        /* Events Grid */
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .event-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: 1px solid #f1f3f4;
            position: relative;
            overflow: hidden;
        }

        .event-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #dc3545, #b02a37);
        }

        .event-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
            border-color: #dc3545;
        }

        .event-date-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: #dc3545;
            color: white;
            padding: 8px 12px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
            min-width: 60px;
        }

        .event-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
            margin-right: 80px;
        }

        .event-description {
            color: #666;
            margin-bottom: 15px;
            line-height: 1.5;
            font-size: 14px;
        }

        .event-details {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 20px;
        }

        .event-detail-item {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #666;
            font-size: 14px;
        }

        .event-detail-icon {
            color: #dc3545;
            width: 16px;
            text-align: center;
        }

        .event-slots {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .slots-info {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 500;
        }

        .slots-available {
            color: #28a745;
        }

        .slots-low {
            color: #ffc107;
        }

        .slots-full {
            color: #dc3545;
        }

        .register-btn {
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            width: 100%;
            text-align: center;
        }

        .register-btn:hover {
            background: linear-gradient(135deg, #c82333, #a02232);
            transform: translateY(-2px);
            text-decoration: none;
            color: white;
            box-shadow: 0 8px 20px rgba(220, 53, 69, 0.3);
        }

        .register-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

 /* Enhanced Success Stories Styles */
.stories-hero {
    background: linear-gradient(135deg, #ff6b6b, #ee5a24);
    color: white;
    padding: 60px 40px;
    border-radius: 20px;
    text-align: center;
    margin-bottom: 40px;
    position: relative;
    overflow: hidden;
}

.stories-hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><path d="M20,50 Q50,20 80,50 Q50,80 20,50" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
    opacity: 0.3;
}

.stories-hero-content {
    position: relative;
    z-index: 2;
}

.stories-hero h1 {
    font-size: 42px;
    font-weight: 700;
    margin-bottom: 20px;
}

.stories-hero p {
    font-size: 18px;
    opacity: 0.9;
    max-width: 600px;
    margin: 0 auto;
}

/* Featured Story */
.featured-story {
    background: white;
    border-radius: 20px;
    padding: 40px;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    margin-bottom: 40px;
    position: relative;
    border-left: 6px solid #ff6b6b;
}

.featured-story-badge {
    background: #ff6b6b;
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 20px;
}

.featured-story-title {
    font-size: 28px;
    font-weight: 700;
    color: #333;
    margin-bottom: 20px;
}

.featured-story-text {
    font-size: 18px;
    line-height: 1.8;
    color: #555;
    margin-bottom: 25px;
}

.featured-story-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 20px;
    border-top: 2px solid #f1f3f4;
}

.story-date {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #666;
    font-weight: 500;
}

.story-impact-badge {
    background: #ff6b6b;
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 6px;
}

/* Story Statistics */
.story-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 30px;
    margin-bottom: 50px;
}

.story-stat {
    background: white;
    padding: 30px;
    border-radius: 15px;
    text-align: center;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.story-stat:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
}

.story-stat-number {
    font-size: 36px;
    font-weight: 700;
    color: #ff6b6b;
    margin-bottom: 10px;
}

.story-stat-label {
    font-size: 14px;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 500;
}

/* Stories Section */
.stories-section {
    margin-bottom: 40px;
}

.section-title {
    font-size: 28px;
    font-weight: 700;
    color: #333;
    margin-bottom: 30px;
    text-align: center;
}

.stories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 30px;
}

.story-card {
    background: white;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    border-left: 4px solid #ff6b6b;
    height: fit-content;
}

.story-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
}

.story-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.story-title {
    font-size: 20px;
    font-weight: 600;
    color: #333;
    margin: 0;
    flex: 1;
    margin-right: 15px;
}

.story-date-small {
    font-size: 12px;
    color: #999;
    background: #f8f9fa;
    padding: 4px 8px;
    border-radius: 8px;
    white-space: nowrap;
}

.story-content {
    color: #666;
    line-height: 1.6;
    margin-bottom: 20px;
}

.story-footer {
    padding-top: 15px;
    border-top: 1px solid #f1f3f4;
}

.story-impact {
    background: #ff6b6b;
    color: white;
    padding: 6px 12px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 500;
    display: inline-block;
}

/* No Stories State */
.no-stories {
    text-align: center;
    padding: 80px 40px;
    color: #666;
}

.no-stories h3 {
    font-size: 24px;
    margin-bottom: 15px;
    color: #333;
}

/* Responsive Design for Stories */
@media (max-width: 768px) {
    .stories-hero {
        padding: 40px 20px;
    }
    
    .stories-hero h1 {
        font-size: 32px;
    }
    
    .featured-story {
        padding: 25px;
    }
    
    .featured-story-title {
        font-size: 24px;
    }
    
    .featured-story-meta {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }
    
    .story-stats {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .stories-grid {
        grid-template-columns: 1fr;
    }
    
    .story-card-header {
        flex-direction: column;
        gap: 10px;
    }
    
    .story-title {
        margin-right: 0;
    }
}

        /* Community Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .info-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }

        /* Blood Needs */
        .blood-needs {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 20px;
        }

        .blood-type {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .blood-type:hover {
            transform: translateX(5px);
        }

        .blood-type.urgent {
            background: #dc3545;
            color: white;
        }

        .blood-type.low {
            background: #ffc107;
            color: #212529;
        }

        .blood-type.normal {
            background: #28a745;
            color: white;
        }

        .blood-type-label {
            font-size: 20px;
            font-weight: 700;
        }

        .blood-type-status {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Impact Metrics */
        .impact-metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
        }

        .impact-item {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .impact-item:hover {
            background: #e9ecef;
            transform: translateY(-3px);
        }

        .impact-icon {
            font-size: 32px;
            color: #dc3545;
            margin-bottom: 10px;
        }

        .impact-number {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            display: block;
        }

        .impact-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Guidelines */
        .guidelines-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
        }

        .guideline-item {
            display: flex;
            gap: 15px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .guideline-item:hover {
            background: #e9ecef;
            transform: translateY(-3px);
        }

        .guideline-icon {
            color: #dc3545;
            font-size: 24px;
            width: 30px;
            text-align: center;
            flex-shrink: 0;
        }

        .guideline-content h4 {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .guideline-content p {
            font-size: 14px;
            color: #666;
            line-height: 1.5;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .action-card {
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
            text-align: center;
            padding: 30px;
            border-radius: 15px;
            transition: all 0.3s ease;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(220, 53, 69, 0.3);
        }

        .action-icon {
            font-size: 40px;
            margin-bottom: 15px;
            opacity: 0.9;
        }

        .action-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .action-description {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 20px;
        }

        .action-btn {
            background: white;
            color: #dc3545;
            padding: 12px 20px;
            border: none;
            border-radius: 20px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .action-btn:hover {
            background: #f8f9fa;
            transform: scale(1.05);
            text-decoration: none;
            color: #dc3545;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            
            .community-hero {
                padding: 40px 20px;
            }
            
            .community-hero h1 {
                font-size: 36px;
            }
            
            .nav-tabs {
                flex-wrap: wrap;
                gap: 5px;
            }
            
            .nav-tab {
                padding: 12px 15px;
                font-size: 14px;
            }
            
            .events-grid {
                grid-template-columns: 1fr;
            }
            
            .stories-grid {
                grid-template-columns: 1fr;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .guidelines-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Registered Event Styles */
.registered-event {
    border: 2px solid #28a745;
    position: relative;
}

.registered-event:hover {
    border: 2px solid #28a745;

}

.registered-event::before {
    background: linear-gradient(135deg, #28a745, #20c997);
}

.registered-badge {
    position: absolute;
    top: 15px;
    left: 15px;
    background: #28a745;
    color: white;
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 11px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 5px;
    z-index: 3;
}

.register-btn.registered {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
}

.register-btn.registered:hover {
    background: linear-gradient(135deg, #218838, #1ea085);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(40, 167, 69, 0.3);
}


@media (max-width: 768px) {
    .registered-event .event-title {
        margin-left: 0;
        margin-top: 25px; /* Add top margin instead on mobile */
    }
}

/* Event Image Styles */
.event-image {
    position: relative;
    width: 100%;
    height: 200px;
    border-radius: 15px 15px 0 0;
    overflow: hidden;
margin-bottom: 14px;
}

.event-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.event-card:hover .event-image img {
    transform: scale(1.05);
}

.event-date-badge {
    position: absolute;
    top: 20px;
    background: rgba(220, 53, 69, 0.9);
    backdrop-filter: blur(10px);
    color: white;
    padding: 8px 12px;
    font-size: 12px;
    font-weight: 600;
    text-align: center;
    min-width: 60px;
    box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
}

.event-content {
    padding: 0 5px;
}

.event-title {
    margin-right: 0; /* Remove the previous margin for date badge */
}

.registered-badge {
    position: absolute;
    top: 15px;
    left: 15px;
    background: rgba(40, 167, 69, 0.9);
    backdrop-filter: blur(10px);
    color: white;
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 11px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 5px;
    z-index: 3;
    box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
}

/* Update event card to accommodate image */
.event-card {
    padding: 25px;
    padding-top: 0;
}

.event-card::before {
    display: none; /* Remove the top border since we have images now */
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .event-image {
        height: 150px;
        margin: -25px -15px 15px -15px;
    }
    
    .event-card {
        padding: 15px;
        padding-top: 0;
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

    <div class="main-content">
        <!-- Community Hero Section -->
        <section class="community-hero">
            <h1>Welcome to Our Life-Saving Community</h1>
            <p>Together, we're making a difference one donation at a time. Join our amazing community of heroes who believe in the power of giving.</p>
            
            <div class="community-stats">
                <div class="stat-card">
                    <span class="stat-number"><?= number_format($communityStats['total_members']) ?></span>
                    <span class="stat-label">Community Members</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?= number_format($communityStats['total_donations']) ?></span>
                    <span class="stat-label">Total Donations</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?= $communityStats['this_month_donations'] ?></span>
                    <span class="stat-label">This Month</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?= $communityStats['total_events'] ?></span>
                    <span class="stat-label">Total Events</span>
                </div>
            </div>
        </section>



        <!-- Overview Section -->
        <div id="overview" class="content-section active">
            <!-- Quick Actions -->
            <div class="quick-actions">
                <div class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-tint"></i>
                    </div>
                    <h3 class="action-title">Donate Blood</h3>
                    <p class="action-description">Schedule your next blood donation and save lives</p>
                    <?php if ($isLoggedIn): ?>
                        <a href="donate.php" class="action-btn">Schedule Now</a>
                    <?php else: ?>
                        <a href="login.php" class="action-btn">Login to Donate</a>
                    <?php endif; ?>
                </div>
                
                <div class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-calendar-plus"></i>
                    </div>
                    <h3 class="action-title">Join Events</h3>
                    <p class="action-description">Register for upcoming blood drives and events</p>
                </div>
                
                <div class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-share-alt"></i>
                    </div>
                    <h3 class="action-title">Spread Awareness</h3>
                    <p class="action-description">Help us reach more potential donors</p>
<a href="#" class="action-btn" onclick="shareContent('Check out this article on blood innovation!', 'https://example.com/future-medicine')">Share</a>
                </div>
            </div>

            <!-- Recent Events Preview -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-calendar-alt card-icon"></i>
                        Upcoming Events
                    </h2>
                </div>
                
                <div class="events-grid">
<?php foreach ($upcomingEvents as $event): 
    $isRegistered = isset($userRegisteredEvents[$event['id']]);
?>
<div class="event-card <?= $isRegistered ? 'registered-event' : '' ?>">
    <?php if ($isRegistered): ?>
        <div class="registered-badge">
            <i class="fas fa-check-circle"></i>
            REGISTERED
        </div>
    <?php endif; ?>
    
    <!-- Event Image -->
    <div class="event-image">
        <?php 
        $defaultImage = 'assets/img/events/d.jpg';
        $eventImage = !empty($event['event_image']) ? $event['event_image'] : $defaultImage;
        
         echo "<!-- Debug: event_image = '" . ($event['event_image'] ?? 'NULL') . "' -->";
    echo "<!-- Debug: using image = '" . $eventImage . "' -->";
        ?>
        <img src="<?= htmlspecialchars($eventImage) ?>" alt="<?= htmlspecialchars($event['title']) ?>" 
             onerror="this.src='<?= $defaultImage ?>'" loading="lazy">
        <div class="event-date-badge">
            <?= date('M j', strtotime($event['date'])) ?>
        </div>
    </div>
    
    <div class="event-content">
        <h3 class="event-title"><?= htmlspecialchars($event['title']) ?></h3>
        <p class="event-description"><?= htmlspecialchars($event['description']) ?></p>
        
        <div class="event-details">
            <div class="event-detail-item">
                <i class="fas fa-clock event-detail-icon"></i>
                <span><?= htmlspecialchars($event['time']) ?></span>
            </div>
            <div class="event-detail-item">
                <i class="fas fa-map-marker-alt event-detail-icon"></i>
                <span><?= htmlspecialchars($event['location']) ?></span>
            </div>
            <div class="event-detail-item">
                <i class="fas fa-calendar event-detail-icon"></i>
                <span><?= date('l, F j, Y', strtotime($event['date'])) ?></span>
            </div>
            <?php if ($isRegistered): ?>
            <div class="event-detail-item">
                <i class="fas fa-calendar-check event-detail-icon"></i>
                <span>Registered on <?= date('M j, Y', strtotime($userRegisteredEvents[$event['id']])) ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <?php 
        $slotsClass = 'slots-available';
        $slotsText = $event['slots_available'] . ' slots available';
        if ($event['slots_available'] <= 0) {
            $slotsClass = 'slots-full';
            $slotsText = 'Event Full';
        } elseif ($event['slots_available'] <= 10) {
            $slotsClass = 'slots-low';
            $slotsText = $event['slots_available'] . ' slots remaining';
        }
        ?>
        
        <div class="event-slots">
            <div class="slots-info <?= $slotsClass ?>">
                <i class="fas fa-users"></i>
                <span><?= $slotsText ?></span>
            </div>
            <div style="font-size: 12px; color: #999;">
                <?= $event['booked_slots'] ?? 0 ?>/<?= $event['max_slots'] ?? 0 ?> registered
            </div>
        </div>
        
        <?php if ($isLoggedIn): ?>
            <?php if ($isRegistered): ?>
                <a href="register.php?event_id=<?= $event['id'] ?>" class="register-btn registered">
                    <i class="fas fa-ticket-alt"></i> View My Ticket
                </a>
            <?php elseif ($event['slots_available'] > 0): ?>
                <a href="register.php?event_id=<?= $event['id'] ?>" class="register-btn">Register for Event</a>
            <?php else: ?>
                <button class="register-btn" disabled>Event Full</button>
            <?php endif; ?>
        <?php else: ?>
            <a href="login.php" class="register-btn">Login to Register</a>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

  

        <!-- Community Info Section -->
        <div id="community" class="content-section">
            <div class="info-grid">
                <!-- Community Impact -->
                <div class="info-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-chart-line card-icon"></i>
                            Community Impact
                        </h2>
                    </div>
                    
                    <div class="impact-metrics">
                        <div class="impact-item">
                            <div class="impact-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <span class="impact-number"><?= number_format($communityStats['total_members']) ?></span>
                            <span class="impact-label">Active Donors</span>
                        </div>
                        
                        <div class="impact-item">
                            <div class="impact-icon">
                                <i class="fas fa-tint"></i>
                            </div>
                            <span class="impact-number"><?= number_format($communityStats['total_donations']) ?></span>
                            <span class="impact-label">Lives Touched</span>
                        </div>
                        
                        <div class="impact-item">
                            <div class="impact-icon">
                                <i class="fas fa-calendar"></i>
                            </div>
                            <span class="impact-number"><?= $communityStats['this_month_donations'] ?></span>
                            <span class="impact-label">This Month</span>
                        </div>
                        
                        <div class="impact-item">
                            <div class="impact-icon">
                                <i class="fas fa-award"></i>
                            </div>
                            <span class="impact-number"><?= $communityStats['total_events'] ?></span>
                            <span class="impact-label">Events</span>
                        </div>
                    </div>
                </div>

                <!-- Blood Types Needed -->
                <div class="info-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-exclamation-triangle card-icon"></i>
                            Blood Types Needed
                        </h2>
                    </div>
                    
                    <div class="blood-needs">
                        <?php foreach ($bloodNeeds as $blood): ?>
                        <div class="blood-type <?= $blood['status'] ?>">
                            <span class="blood-type-label"><?= $blood['type'] ?></span>
                            <span class="blood-type-status"><?= $blood['label'] ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 10px; font-style: italic; color: #666;">
                        <p>Your donation can save up to 3 lives. Every drop counts!</p>
                    </div>
                </div>
            </div>

            <!-- Community Guidelines -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-info-circle card-icon"></i>
                        Community Guidelines & Tips
                    </h2>
                </div>
                
                <div class="guidelines-grid">
                    <div class="guideline-item">
                        <div class="guideline-icon">
                            <i class="fas fa-utensils"></i>
                        </div>
                        <div class="guideline-content">
                            <h4>Before Donating</h4>
                            <p>Eat a healthy meal and drink plenty of water. Get a good night's sleep and avoid alcohol 24 hours before.</p>
                        </div>
                    </div>
                    
                    <div class="guideline-item">
                        <div class="guideline-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="guideline-content">
                            <h4>Donation Process</h4>
                            <p>The entire process takes about 45-60 minutes. Actual blood collection is only 8-10 minutes.</p>
                        </div>
                    </div>
                    
                    <div class="guideline-item">
                        <div class="guideline-icon">
                            <i class="fas fa-heart"></i>
                        </div>
                        <div class="guideline-content">
                            <h4>After Donating</h4>
                            <p>Rest for 10-15 minutes, enjoy refreshments, and avoid heavy lifting for the rest of the day.</p>
                        </div>
                    </div>
                    
                    <div class="guideline-item">
                        <div class="guideline-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="guideline-content">
                            <h4>Frequency</h4>
                            <p>You can donate whole blood every 56 days (8 weeks). Mark your calendar for your next eligible date!</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function shareContent(text, url) {
    if (navigator.share) {
        navigator.share({
            title: document.title,
            text: text,
            url: url,
        })
        .then(() => console.log('Shared successfully'))
        .catch((error) => console.error('Error sharing', error));
    } else {
        // Fallback: copy to clipboard
        navigator.clipboard.writeText(url).then(() => {
            alert('Link copied to clipboard!');
        }, () => {
            alert('Could not copy link');
        });
    }
}
        function showSection(sectionName) {
            // Hide all sections
            const sections = document.querySelectorAll('.content-section');
            sections.forEach(section => {
                section.classList.remove('active');
            });
            
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.nav-tab');
            tabs.forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected section
            document.getElementById(sectionName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
            
            // Animate stats if showing overview
            if (sectionName === 'overview') {
                setTimeout(animateStats, 300);
            }
        }

        function animateStats() {
            const statNumbers = document.querySelectorAll('.stat-number');
            const impactNumbers = document.querySelectorAll('.impact-number');
            
            const animateNumber = (element) => {
                const target = element.textContent.replace(/,/g, '');
                if (target && !isNaN(target)) {
                    const targetNum = parseInt(target);
                    let current = 0;
                    const increment = targetNum / 50;
                    const timer = setInterval(() => {
                        current += increment;
                        if (current >= targetNum) {
                            current = targetNum;
                            clearInterval(timer);
                        }
                        element.textContent = Math.floor(current).toLocaleString();
                    }, 30);
                }
            };
            
            statNumbers.forEach(animateNumber);
            impactNumbers.forEach(animateNumber);
        }

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Check if there's a hash in URL to show specific section
            const hash = window.location.hash.substr(1);
            if (hash && ['overview', 'events', 'stories', 'community'].includes(hash)) {
                // Find and click the corresponding tab
                const targetTab = document.querySelector(`[onclick="showSection('${hash}')"]`);
                if (targetTab) {
                    targetTab.click();
                }
            } else {
                // Animate stats on initial load
                setTimeout(animateStats, 500);
            }

            // Add smooth hover effects
            const eventCards = document.querySelectorAll('.event-card');
            eventCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-8px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });

            const storyCards = document.querySelectorAll('.story-card');
            storyCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });

            console.log('Enhanced community page initialized successfully');
        });

        // Add URL hash support for direct section linking
        function updateURL(section) {
            history.pushState(null, null, `#${section}`);
        }

        // Override showSection to update URL
        const originalShowSection = showSection;
        showSection = function(sectionName) {
            originalShowSection.call(this, sectionName);
            updateURL(sectionName);
        };
    </script>

    <?php include('includes/footer.php'); ?>
</body>
</html>