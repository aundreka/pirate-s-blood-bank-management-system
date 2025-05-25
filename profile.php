<?php 
include('includes/db.php');
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Get user's donation statistics
$donation_query = "SELECT 
    COUNT(*) as total_donations,
    SUM(CASE WHEN donation_status = 'Completed' THEN 1 ELSE 0 END) as completed_donations,
    SUM(CASE WHEN donation_status = 'Pending' THEN 1 ELSE 0 END) as pending_donations,
    SUM(CASE WHEN donation_status = 'Approved' THEN 1 ELSE 0 END) as approved_donations
    FROM donate_blood WHERE donor_id = ?";
$stmt = $conn->prepare($donation_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$donation_stats = $stmt->get_result()->fetch_assoc();

// Get user's blood request statistics
$request_query = "SELECT 
    COUNT(*) as total_requests,
    SUM(CASE WHEN request_status = 'Fulfilled' THEN 1 ELSE 0 END) as fulfilled_requests,
    SUM(CASE WHEN request_status = 'Pending' THEN 1 ELSE 0 END) as pending_requests,
    SUM(CASE WHEN request_status = 'Approved' THEN 1 ELSE 0 END) as approved_requests
    FROM recipient_form WHERE user_id = ?";
$stmt = $conn->prepare($request_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$request_stats = $stmt->get_result()->fetch_assoc();

// Get user's event registrations
$event_query = "SELECT 
    COUNT(*) as total_events,
    SUM(CASE WHEN status = 'attended' THEN 1 ELSE 0 END) as attended_events,
    SUM(CASE WHEN status = 'registered' THEN 1 ELSE 0 END) as upcoming_events
    FROM event_registrations WHERE user_id = ?";
$stmt = $conn->prepare($event_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$event_stats = $stmt->get_result()->fetch_assoc();

// Get recent donations
$recent_donations_query = "SELECT donation_id, blood_type, date_of_donation, donation_status, hospital_location 
    FROM donate_blood WHERE donor_id = ? ORDER BY date_of_donation DESC LIMIT 5";
$stmt = $conn->prepare($recent_donations_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_donations = $stmt->get_result();

// Get recent requests
$recent_requests_query = "SELECT request_id, blood_type_needed, created_at, request_status, urgency_level 
    FROM recipient_form WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
$stmt = $conn->prepare($recent_requests_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_requests = $stmt->get_result();

// Get upcoming events
$upcoming_events_query = "SELECT e.title, e.event_date, e.start_time, e.location, er.status
    FROM events e 
    JOIN event_registrations er ON e.event_id = er.event_id 
    WHERE er.user_id = ? AND e.event_date >= CURDATE() 
    ORDER BY e.event_date ASC LIMIT 5";
$stmt = $conn->prepare($upcoming_events_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$upcoming_events = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Pirate's Blood Bank</title>
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

        .content-wrapper {
            margin-left: 70px;
            width: calc(100% - 70px);
            box-sizing: border-box;
            margin-top: 120px;
        }

        /* Profile Header Section */
        .profile-header {
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.9), rgba(176, 42, 55, 0.9));
            color: white;
            padding: 60px 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 20% 30%, rgba(255, 255, 255, 0.1) 2px, transparent 2px),
                radial-gradient(circle at 80% 70%, rgba(255, 255, 255, 0.08) 2px, transparent 2px);
            background-size: 100px 100px, 150px 150px;
            animation: floatParticles 20s infinite linear;
        }

        @keyframes floatParticles {
            0% { transform: translateY(0) rotate(0deg); }
            100% { transform: translateY(-100px) rotate(360deg); }
        }

        .profile-header-content {
            position: relative;
            z-index: 10;
        }

        .profile-header h1 {
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 15px;
            font-family: 'Poppins', sans-serif;
            color: white;
        }

        .profile-header p {
            font-size: 20px;
            opacity: 0.95;
            margin-bottom: 30px;
        }

        .settings-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 15px 30px;
            border: 2px solid white;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .settings-btn:hover {
            background: white;
            color: #dc3545;
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 10px 25px rgba(255,255,255,0.3);
        }

        /* Stats Section */
        .stats-section {
            background: white;
            padding: 60px 40px;
            margin: -30px 40px 0;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            position: relative;
            z-index: 5;
        }

        .stats-title {
            font-size: 32px;
            font-weight: 700;
            color: #495057;
            text-align: center;
            margin-bottom: 40px;
            font-family: 'Poppins', sans-serif;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: linear-gradient(135deg, #f8f9fa, #ffffff);
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            border-left: 5px solid;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent, rgba(220, 53, 69, 0.03), transparent);
            transform: translateX(-100%);
            transition: transform 0.6s ease;
        }

        .stat-card:hover::before {
            transform: translateX(100%);
        }

        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .stat-card.donations {
            border-left-color: #dc3545;
        }

        .stat-card.requests {
            border-left-color: #007bff;
        }

        .stat-card.events {
            border-left-color: #28a745;
        }

        .stat-card i {
            font-size: 48px;
            margin-bottom: 20px;
            opacity: 0.8;
        }

        .stat-card.donations i {
            color: #dc3545;
        }

        .stat-card.requests i {
            color: #007bff;
        }

        .stat-card.events i {
            color: #28a745;
        }

        .stat-card h3 {
            color: #495057;
            margin-bottom: 15px;
            font-size: 20px;
            font-weight: 600;
        }

        .stat-number {
            font-size: 36px;
            font-weight: 700;
            color: #495057;
            margin-bottom: 10px;
            display: block;
        }

        .stat-details {
            font-size: 14px;
            color: #6c757d;
            line-height: 1.5;
        }

        /* Content Sections */
        .content-section {
            background: white;
            padding: 50px 40px;
            width: 100%;
            box-sizing: border-box;
        }

        .section-title {
            font-size: 32px;
            font-weight: 700;
            color: #495057;
            margin-bottom: 20px;
            font-family: 'Poppins', sans-serif;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .section-title i {
            color: #dc3545;
            font-size: 28px;
        }

        .section-subtitle {
            color: #6c757d;
            font-size: 16px;
            margin-bottom: 40px;
            line-height: 1.6;
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            margin-bottom: 40px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-family: 'Poppins', sans-serif;
            background: white;
        }

        .data-table th {
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
            padding: 20px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .data-table td {
            padding: 20px;
            border-bottom: 1px solid #f8f9fa;
            font-size: 15px;
            transition: all 0.3s ease;
        }

        .data-table tr:hover {
            background: linear-gradient(90deg, rgba(220, 53, 69, 0.05), rgba(220, 53, 69, 0.02));
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            letter-spacing: 0.3px;
        }

        .status-completed, .status-fulfilled, .status-attended {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            color: white;
        }

        .status-pending, .status-registered {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: white;
        }

        .status-approved {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
        }

        .status-rejected, .status-cancelled {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }

        .urgency-high {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }

        .urgency-medium {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: white;
        }

        .urgency-low {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            color: white;
        }

        .no-data {
            text-align: center;
            color: #6c757d;
            font-style: italic;
            padding: 60px;
            background: #f8f9fa;
            border-radius: 15px;
        }

        .no-data i {
            font-size: 48px;
            color: #dc3545;
            opacity: 0.7;
            display: block;
            margin-bottom: 20px;
        }

        /* Responsive Design */
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

            .profile-header {
                padding: 40px 20px;
            }

            .profile-header h1 {
                font-size: 36px;
            }

            .profile-header p {
                font-size: 18px;
            }

            .stats-section {
                margin: -20px 20px 0;
                padding: 40px 20px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .content-section {
                padding: 40px 20px;
            }

            .section-title {
                font-size: 24px;
            }

            .data-table th,
            .data-table td {
                padding: 15px 10px;
            }
        }

        @media (max-width: 480px) {
            .profile-header h1 {
                font-size: 28px;
            }

            .stats-title {
                font-size: 24px;
            }

            .stat-number {
                font-size: 28px;
            }

            .section-title {
                font-size: 20px;
                flex-direction: column;
                gap: 10px;
                text-align: center;
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
        <!-- Profile Header -->
        <section class="profile-header">
            <div class="profile-header-content">
                <h1><i class="fas fa-user-circle"></i> My Profile</h1>
                <p>Welcome back, <?php echo htmlspecialchars($username); ?>! Here's your donation journey overview.</p>
                <a href="settings.php" class="settings-btn">
                    <i class="fas fa-cog"></i>
                    Profile Settings
                </a>
            </div>
        </section>

        <!-- Statistics Overview -->
        <section class="stats-section">
            <h2 class="stats-title">Your Impact Summary</h2>
            <div class="stats-grid">
                <div class="stat-card donations">
                    <i class="fas fa-heart"></i>
                    <h3>Blood Donations</h3>
                    <div class="stat-number"><?php echo $donation_stats['total_donations']; ?></div>
                    <div class="stat-details">
                        <?php echo $donation_stats['completed_donations']; ?> completed<br>
                        <?php echo $donation_stats['pending_donations']; ?> pending<br>
                        <?php echo $donation_stats['approved_donations']; ?> approved
                    </div>
                </div>

                <div class="stat-card requests">
                    <i class="fas fa-hand-holding-medical"></i>
                    <h3>Blood Requests</h3>
                    <div class="stat-number"><?php echo $request_stats['total_requests']; ?></div>
                    <div class="stat-details">
                        <?php echo $request_stats['fulfilled_requests']; ?> fulfilled<br>
                        <?php echo $request_stats['pending_requests']; ?> pending<br>
                        <?php echo $request_stats['approved_requests']; ?> approved
                    </div>
                </div>

                <div class="stat-card events">
                    <i class="fas fa-calendar-alt"></i>
                    <h3>Events Participated</h3>
                    <div class="stat-number"><?php echo $event_stats['total_events']; ?></div>
                    <div class="stat-details">
                        <?php echo $event_stats['attended_events']; ?> attended<br>
                        <?php echo $event_stats['upcoming_events']; ?> upcoming
                    </div>
                </div>
            </div>
        </section>

        <!-- Recent Donations -->
        <section class="content-section">
            <h2 class="section-title">
                <i class="fas fa-tint"></i>
                Recent Donations
            </h2>
            <p class="section-subtitle">Your latest blood donation activities and their current status</p>
            
            <div class="table-container">
                <?php if ($recent_donations->num_rows > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th><i class="fas fa-hashtag"></i> Donation ID</th>
                                <th><i class="fas fa-tint"></i> Blood Type</th>
                                <th><i class="fas fa-calendar"></i> Date</th>
                                <th><i class="fas fa-hospital"></i> Hospital</th>
                                <th><i class="fas fa-info-circle"></i> Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($donation = $recent_donations->fetch_assoc()): ?>
                                <tr>
                                    <td><strong>#<?php echo $donation['donation_id']; ?></strong></td>
                                    <td><strong style="color: #dc3545;"><?php echo htmlspecialchars($donation['blood_type']); ?></strong></td>
                                    <td><?php echo date('M d, Y', strtotime($donation['date_of_donation'])); ?></td>
                                    <td><?php echo htmlspecialchars($donation['hospital_location'] ?? 'Not specified'); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($donation['donation_status']); ?>">
                                            <i class="fas fa-circle"></i>
                                            <?php echo $donation['donation_status']; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-heart"></i>
                        <h3>No donations found</h3>
                        <p>Consider donating blood to save lives! Every donation makes a difference.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Recent Requests -->
        <section class="content-section" style="background: #f8f9fa;">
            <h2 class="section-title">
                <i class="fas fa-hand-holding-medical"></i>
                Recent Blood Requests
            </h2>
            <p class="section-subtitle">Your blood request history and current status updates</p>
            
            <div class="table-container">
                <?php if ($recent_requests->num_rows > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th><i class="fas fa-hashtag"></i> Request ID</th>
                                <th><i class="fas fa-tint"></i> Blood Type</th>
                                <th><i class="fas fa-calendar"></i> Date Requested</th>
                                <th><i class="fas fa-exclamation-triangle"></i> Urgency</th>
                                <th><i class="fas fa-info-circle"></i> Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($request = $recent_requests->fetch_assoc()): ?>
                                <tr>
                                    <td><strong>#<?php echo $request['request_id']; ?></strong></td>
                                    <td><strong style="color: #dc3545;"><?php echo htmlspecialchars($request['blood_type_needed']); ?></strong></td>
                                    <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                    <td>
                                        <span class="status-badge urgency-<?php echo strtolower($request['urgency_level']); ?>">
                                            <i class="fas fa-exclamation-circle"></i>
                                            <?php echo $request['urgency_level']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($request['request_status']); ?>">
                                            <i class="fas fa-circle"></i>
                                            <?php echo $request['request_status']; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-hand-holding-medical"></i>
                        <h3>No blood requests found</h3>
                        <p>If you need blood, don't hesitate to submit a request through our system.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Upcoming Events -->
        <section class="content-section">
            <h2 class="section-title">
                <i class="fas fa-calendar-check"></i>
                Upcoming Events
            </h2>
            <p class="section-subtitle">Blood donation events and activities you're registered for</p>
            
            <div class="table-container">
                <?php if ($upcoming_events->num_rows > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th><i class="fas fa-tag"></i> Event Title</th>
                                <th><i class="fas fa-calendar"></i> Date</th>
                                <th><i class="fas fa-clock"></i> Time</th>
                                <th><i class="fas fa-map-marker-alt"></i> Location</th>
                                <th><i class="fas fa-user-check"></i> Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($event = $upcoming_events->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($event['title']); ?></strong></td>
                                    <td><?php echo date('M d, Y', strtotime($event['event_date'])); ?></td>
                                    <td><?php echo date('g:i A', strtotime($event['start_time'])); ?></td>
                                    <td><?php echo htmlspecialchars($event['location']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($event['status']); ?>">
                                            <i class="fas fa-circle"></i>
                                            <?php echo ucfirst($event['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No upcoming events</h3>
                        <p>Check our events page to find and register for upcoming blood donation drives.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</main>

<script>
    // Add counter animation for stats
    document.addEventListener('DOMContentLoaded', function() {
        const statNumbers = document.querySelectorAll('.stat-number');
        
        const animateStats = () => {
            statNumbers.forEach(stat => {
                const target = parseInt(stat.textContent);
                if (!isNaN(target)) {
                    let current = 0;
                    const increment = target / 50;
                    const timer = setInterval(() => {
                        current += increment;
                        if (current >= target) {
                            current = target;
                            clearInterval(timer);
                        }
                        stat.textContent = Math.floor(current);
                    }, 30);
                }
            });
        };

        // Intersection Observer for stat animation
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    setTimeout(animateStats, 500);
                    observer.unobserve(entry.target);
                }
            });
        });

        const statsSection = document.querySelector('.stats-section');
        if (statsSection) {
            observer.observe(statsSection);
        }
    });
</script>

</body>
<?php include('includes/footer.php'); ?>
</html>