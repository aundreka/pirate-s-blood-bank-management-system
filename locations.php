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

// Hospital locations data
$hospitals = [
    [
        "id" => "st-mercy",
        "name" => "St. Mercy General Hospital",
        "address" => "123 Aurora Blvd, Quezon City, Metro Manila, Philippines",
        "phone" => "+63 2 8123-4567",
        "visiting_hours" => "Monday - Friday: 8:00 AM - 6:00 PM<br>Saturday: 8:00 AM - 4:00 PM<br>Sunday: 10:00 AM - 2:00 PM",
        "department" => "Blood Transfusion Services - 3rd Floor, Wing B",
        "parking" => "Free parking available",
        "requirements" => "Valid ID and medical records required.",
        "contact_person" => "Dr. Maria Santos",
        "email" => "transfusion@stmercy.ph",
        "city" => "Quezon City",
        "region" => "Metro Manila",
        "image" => "https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?w=600&h=400&fit=crop&crop=center",
        "services" => ["Blood Transfusion", "Blood Banking", "Plasma Collection", "Emergency Services"],
        "specialties" => ["Hematology", "Oncology", "Surgery", "Emergency Medicine"],
        "rating" => 4.8,
        "established" => 1985
    ],
    [
        "id" => "metrocare",
        "name" => "MetroCare Medical Center",
        "address" => "456 West Avenue, Quezon City, Metro Manila, Philippines",
        "phone" => "+63 2 8234-5678",
        "visiting_hours" => "Monday - Saturday: 7:00 AM - 7:00 PM<br>Sunday: 9:00 AM - 3:00 PM",
        "department" => "Hematology & Transfusion Unit - 2nd Floor",
        "parking" => "Paid parking - PHP 20/hour",
        "requirements" => "Valid ID, medical prescription, and insurance required.",
        "contact_person" => "Dr. Jose Reyes",
        "email" => "hematology@metrocare.ph",
        "city" => "Quezon City",
        "region" => "Metro Manila",
        "image" => "https://images.unsplash.com/photo-1551601651-2a8555f1a136?w=600&h=400&fit=crop&crop=center",
        "services" => ["Hematology Services", "Blood Donation", "Platelet Collection", "Blood Testing"],
        "specialties" => ["Hematology", "Internal Medicine", "Pediatrics", "Laboratory Services"],
        "rating" => 4.6,
        "established" => 1992
    ],
    [
        "id" => "hopewell",
        "name" => "Hopewell Community Hospital",
        "address" => "87 Mabini St, Calamba, Laguna, Philippines",
        "phone" => "+63 49 502-3456",
        "visiting_hours" => "Monday - Friday: 8:00 AM - 5:00 PM<br>Saturday: 8:00 AM - 12:00 PM<br>Sunday: Emergency only",
        "department" => "Blood Bank & Transfusion - Ground Floor",
        "parking" => "Free parking available",
        "requirements" => "Government ID, medical prescription required.",
        "contact_person" => "Dr. Ana Cruz",
        "email" => "bloodbank@hopewell.ph",
        "city" => "Calamba",
        "region" => "Laguna",
        "image" => "https://images.unsplash.com/photo-1586773860418-d37222d8fce3?w=600&h=400&fit=crop&crop=center",
        "services" => ["Community Health", "Blood Banking", "Maternal Care", "General Medicine"],
        "specialties" => ["Family Medicine", "Obstetrics", "General Surgery", "Emergency Care"],
        "rating" => 4.4,
        "established" => 1998
    ],
    [
        "id" => "sunrise",
        "name" => "Sunrise Health Center",
        "address" => "99 J.P. Rizal Ave, Makati City, Metro Manila, Philippines",
        "phone" => "+63 2 8345-6789",
        "visiting_hours" => "Monday - Friday: 7:30 AM - 6:30 PM<br>Saturday: 8:00 AM - 3:00 PM<br>Sunday: Emergency only",
        "department" => "Transfusion Medicine - 4th Floor",
        "parking" => "Valet parking available - PHP 50",
        "requirements" => "Valid ID, physician's order required.",
        "contact_person" => "Dr. Michael Tan",
        "email" => "transfusion@sunrise.ph",
        "city" => "Makati City",
        "region" => "Metro Manila",
        "image" => "https://images.unsplash.com/photo-1512678080530-7760d81faba6?w=600&h=400&fit=crop&crop=center",
        "services" => ["Executive Health", "Blood Services", "Preventive Care", "Wellness Programs"],
        "specialties" => ["Internal Medicine", "Cardiology", "Endocrinology", "Preventive Medicine"],
        "rating" => 4.9,
        "established" => 2005
    ],
    [
        "id" => "nueva-vida",
        "name" => "Nueva Vida Medical Institute",
        "address" => "210 Bonifacio Street, Davao City, Davao del Sur, Philippines",
        "phone" => "+63 82 234-5678",
        "visiting_hours" => "Monday - Saturday: 8:00 AM - 6:00 PM<br>Sunday: 10:00 AM - 2:00 PM",
        "department" => "Blood Services & Transfusion - 1st Floor",
        "parking" => "Free parking for patients",
        "requirements" => "Valid ID, medical prescription, and insurance.",
        "contact_person" => "Dr. Carmen Lopez",
        "email" => "bloodservices@nuevavida.ph",
        "city" => "Davao City",
        "region" => "Davao del Sur",
        "image" => "https://assets.contenthub.wolterskluwer.com/api/public/content/b29dabdaa42a4bebac581ae91910f28b",
        "services" => ["Advanced Diagnostics", "Blood Banking", "Research", "Specialty Care"],
        "specialties" => ["Research Medicine", "Hematology", "Oncology", "Clinical Trials"],
        "rating" => 4.7,
        "established" => 2001
    ],
    [
        "id" => "unity",
        "name" => "Unity Regional Hospital",
        "address" => "300 Lopez Jaena St, Iloilo City, Iloilo, Philippines",
        "phone" => "+63 33 336-7890",
        "visiting_hours" => "Monday - Friday: 8:00 AM - 5:30 PM<br>Saturday: 8:00 AM - 1:00 PM<br>Sunday: Emergency only",
        "department" => "Transfusion Services - 2nd Floor",
        "parking" => "Free parking available",
        "requirements" => "Government-issued ID and doctor's order.",
        "contact_person" => "Dr. Roberto Flores",
        "email" => "transfusion@unity.ph",
        "city" => "Iloilo City",
        "region" => "Iloilo",
        "image" => "https://images.unsplash.com/photo-1504439468489-c8920d796a29?w=600&h=400&fit=crop&crop=center",
        "services" => ["Regional Care", "Blood Banking", "Trauma Services", "Community Outreach"],
        "specialties" => ["Emergency Medicine", "Trauma Surgery", "Regional Health", "Blood Banking"],
        "rating" => 4.5,
        "established" => 1988
    ],
    [
        "id" => "greenfields",
        "name" => "Greenfields Medical Plaza",
        "address" => "45 Don Rufino Alonzo St, Baguio City, Benguet, Philippines",
        "phone" => "+63 74 442-3456",
        "visiting_hours" => "Monday - Friday: 8:30 AM - 5:00 PM<br>Saturday: 9:00 AM - 2:00 PM<br>Sunday: Closed",
        "department" => "Blood Bank Services - 2nd Floor, Suite 201",
        "parking" => "Limited free parking",
        "requirements" => "Valid ID and physician's prescription.",
        "contact_person" => "Dr. Grace Mendoza",
        "email" => "bloodbank@greenfields.ph",
        "city" => "Baguio City",
        "region" => "Benguet",
        "image" => "https://gotnurse.wordpress.com/wp-content/uploads/2017/11/chong-hua1.jpg",
        "services" => ["Mountain Medicine", "Blood Services", "Respiratory Care", "Altitude Medicine"],
        "specialties" => ["Pulmonology", "Mountain Medicine", "Family Medicine", "Blood Banking"],
        "rating" => 4.3,
        "established" => 1995
    ],
    [
        "id" => "wellnesspoint",
        "name" => "WellnessPoint Hospital",
        "address" => "678 Ortigas Ave, Pasig City, Metro Manila, Philippines",
        "phone" => "+63 2 8456-7890",
        "visiting_hours" => "Daily: 24 hours (Blood transfusion: Mon-Sat 8AM-6PM)",
        "department" => "Transfusion Medicine - 5th Floor, Medical Tower",
        "parking" => "Multi-level parking - PHP 30/hour",
        "requirements" => "Valid ID, medical prescription, insurance clearance.",
        "contact_person" => "Dr. Patricia Valdez",
        "email" => "transfusion@wellnesspoint.ph",
        "city" => "Pasig City",
        "region" => "Metro Manila",
        "image" => "https://www.cmgassets.com/s3fs-public/styles/article_details_tablet_image/public/2022-01/philippine_general_hospital_covid-19_ward.jpg.webp?itok=CDFi4_jv",
        "services" => ["24/7 Services", "Advanced Blood Banking", "Critical Care", "Emergency Medicine"],
        "specialties" => ["Emergency Medicine", "Critical Care", "Hematology", "Transfusion Medicine"],
        "rating" => 4.8,
        "established" => 2010
    ],
    [
        "id" => "cedar-hill",
        "name" => "Cedar Hill Medical Complex",
        "address" => "12 Gen. Luna St, San Fernando, Pampanga, Philippines",
        "phone" => "+63 45 961-2345",
        "visiting_hours" => "Monday - Friday: 8:00 AM - 6:00 PM<br>Saturday: 8:00 AM - 4:00 PM<br>Sunday: 10:00 AM - 2:00 PM",
        "department" => "Blood Transfusion Center - 2nd Floor",
        "parking" => "Free parking for patients",
        "requirements" => "Valid ID, doctor's order, and insurance.",
        "contact_person" => "Dr. Antonio Garcia",
        "email" => "transfusion@cedarhill.ph",
        "city" => "San Fernando",
        "region" => "Pampanga",
        "image" => "https://images.unsplash.com/photo-1586773860418-d37222d8fce3?w=600&h=400&fit=crop&crop=center",
        "services" => ["Comprehensive Care", "Blood Banking", "Surgical Services", "Rehabilitation"],
        "specialties" => ["Surgery", "Rehabilitation", "Blood Banking", "Orthopedics"],
        "rating" => 4.6,
        "established" => 1990
    ],
    [
        "id" => "bayview",
        "name" => "Bayview General Medical Center",
        "address" => "81 Roxas Blvd, Parañaque City, Metro Manila, Philippines",
        "phone" => "+63 2 8567-8901",
        "visiting_hours" => "Monday - Saturday: 7:00 AM - 7:00 PM<br>Sunday: 9:00 AM - 4:00 PM",
        "department" => "Blood Bank & Transfusion - 3rd Floor",
        "parking" => "Free 3-hour parking for patients",
        "requirements" => "Government ID, medical prescription required.",
        "contact_person" => "Dr. Elena Rodriguez",
        "email" => "transfusion@bayview.ph",
        "city" => "Parañaque City",
        "region" => "Metro Manila",
        "image" => "https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?w=600&h=400&fit=crop&crop=center",
        "services" => ["Coastal Healthcare", "Blood Services", "Marine Medicine", "General Care"],
        "specialties" => ["General Medicine", "Marine Medicine", "Blood Banking", "Family Medicine"],
        "rating" => 4.4,
        "established" => 1987
    ]
];

// Group hospitals by region
$hospitalsByRegion = [];
foreach ($hospitals as $hospital) {
    $hospitalsByRegion[$hospital['region']][] = $hospital;
}

// Get search parameters
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$regionFilter = isset($_GET['region']) ? trim($_GET['region']) : '';

// Filter hospitals based on search and region
$filteredHospitals = $hospitals;
if (!empty($searchQuery) || !empty($regionFilter)) {
    $filteredHospitals = array_filter($hospitals, function($hospital) use ($searchQuery, $regionFilter) {
        $matchesSearch = empty($searchQuery) || 
            stripos($hospital['name'], $searchQuery) !== false ||
            stripos($hospital['city'], $searchQuery) !== false ||
            stripos($hospital['address'], $searchQuery) !== false;
        
        $matchesRegion = empty($regionFilter) || $hospital['region'] === $regionFilter;
        
        return $matchesSearch && $matchesRegion;
    });
}

// Get unique regions for filter dropdown
$regions = array_unique(array_column($hospitals, 'region'));
sort($regions);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pirate's Blood Bank - Hospital Locations</title>
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
        
        .locations-container {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            box-shadow: 2px 0 15px rgba(220, 220, 220, 0.1);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: none;
            margin: 0;
            box-sizing: border-box;
        }
        
        .locations-header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 25px;
        }
        
        .locations-title {
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
        
        .locations-title i {
            color: #28a745;
            font-size: 32px;
        }
        
        .locations-subtitle {
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
            background: linear-gradient(135deg, #28a745, #1e7e34);
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
        
        .search-filter-section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .search-title {
            font-size: 20px;
            font-weight: 700;
            color: #495057;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .search-form {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 15px;
            align-items: end;
        }
        
        .search-group {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 15px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-input {
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #28a745;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
        }
        
        .search-btn {
            padding: 12px 20px;
            background: linear-gradient(135deg, #28a745, #1e7e34);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        
        .clear-btn {
            padding: 12px 20px;
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .clear-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.3);
            color: white;
            text-decoration: none;
        }
        
        .hospitals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .hospital-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        
        .hospital-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .hospital-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
            position: relative;
        }
        
        .hospital-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: linear-gradient(135deg, #28a745, #1e7e34);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .hospital-content {
            padding: 25px;
        }
        
        .hospital-header {
            margin-bottom: 20px;
        }
        
        .hospital-name {
            font-size: 22px;
            font-weight: 700;
            color: #495057;
            margin-bottom: 8px;
            line-height: 1.3;
        }
        
        .hospital-location {
            color: #6c757d;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
        }
        
        .hospital-rating {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 15px;
        }
        
        .rating-stars {
            color: #ffc107;
        }
        
        .rating-number {
            font-weight: 600;
            color: #495057;
        }
        
        .rating-year {
            color: #6c757d;
            font-size: 12px;
        }
        
        .hospital-info {
            margin-bottom: 20px;
        }
        
        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 12px;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .info-icon {
            color: #28a745;
            width: 16px;
            flex-shrink: 0;
            margin-top: 2px;
        }
        
        .info-text {
            color: #6c757d;
        }
        
        .hospital-services {
            margin-bottom: 20px;
        }
        
        .services-title {
            font-weight: 700;
            color: #495057;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .services-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .service-tag {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            color: #0d47a1;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .hospital-actions {
            display: flex;
            gap: 10px;
        }
        
        .action-btn {
            flex: 1;
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            color: white;
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
        }
        
        .btn-outline {
            background: white;
            color: #6c757d;
            border: 2px solid #e9ecef;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            text-decoration: none;
            color: white;
        }
        
        .btn-outline:hover {
            background: #6c757d;
            color: white;
            border-color: #6c757d;
        }
        
        .no-results {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .no-results i {
            font-size: 64px;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        
        .no-results h3 {
            color: #495057;
            margin-bottom: 10px;
        }
        
        .no-results p {
            color: #6c757d;
            margin-bottom: 20px;
        }
        
        .region-section {
            margin-bottom: 50px;
        }
        
        .region-title {
            font-size: 28px;
            font-weight: 700;
            color: #495057;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #28a745;
            display: inline-block;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            main {
                margin-left: 0;
                padding: 15px;
                width: 100vw;
            }
            
            .locations-container {
                padding: 25px;
                border-radius: 15px;
            }
            
            .locations-title {
                font-size: 28px;
                flex-direction: column;
                gap: 10px;
            }
            
            .search-form {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .search-group {
                grid-template-columns: 1fr;
            }
            
            .hospitals-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .hospital-actions {
                flex-direction: column;
            }
            
            .stats-summary {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .locations-title {
                font-size: 24px;
            }
            
            .hospital-card {
                margin: 0 -10px;
            }
            
            .hospital-content {
                padding: 20px;
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
    <div class="locations-container">
        <div class="locations-header">
            <h1 class="locations-title">
                <i class="fas fa-map-marker-alt"></i>
                Hospital Locations
            </h1>
            <p class="locations-subtitle">Find blood donation and transfusion centers across the Philippines</p>
            
            <!-- Statistics Summary -->
            <div class="stats-summary">
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-number"><?php echo count($hospitals); ?></div>
                        <div class="stat-label">Partner Hospitals</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-number"><?php echo count($regions); ?></div>
                        <div class="stat-label">Regions Covered</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-number">24/7</div>
                        <div class="stat-label">Emergency Services</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-number">100%</div>
                        <div class="stat-label">Certified</div>
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
        
        <!-- Search and Filter Section -->
        <div class="search-filter-section">
            <h3 class="search-title">
                <i class="fas fa-search"></i>
                Find a Hospital
            </h3>
            
            <form method="GET" class="search-form">
                <div class="search-group">
                    <div class="form-group">
                        <label class="form-label">Search by name or city</label>
                        <input type="text" name="search" class="form-input" 
                               placeholder="e.g. St. Mercy, Quezon City..." 
                               value="<?php echo htmlspecialchars($searchQuery); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Filter by region</label>
                        <select name="region" class="form-input">
                            <option value="">All Regions</option>
                            <?php foreach ($regions as $region): ?>
                                <option value="<?php echo htmlspecialchars($region); ?>" 
                                        <?php echo ($regionFilter === $region) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($region); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i>
                    Search
                </button>
                <?php if (!empty($searchQuery) || !empty($regionFilter)): ?>
                    <a href="locations.php" class="clear-btn">
                        <i class="fas fa-times"></i>
                        Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>
        
        <?php if (!empty($filteredHospitals)): ?>
            <!-- Show search results info -->
            <?php if (!empty($searchQuery) || !empty($regionFilter)): ?>
                <div style="margin-bottom: 30px; padding: 20px; background: linear-gradient(90deg, rgba(40, 167, 69, 0.1), rgba(40, 167, 69, 0.05)); border-radius: 12px; border-left: 4px solid #28a745;">
                    <h4 style="color: #155724; margin: 0 0 10px 0;">
                        <i class="fas fa-filter"></i>
                        Search Results
                    </h4>
                    <p style="color: #155724; margin: 0;">
                        Found <?php echo count($filteredHospitals); ?> hospital(s)
                        <?php if (!empty($searchQuery)): ?>
                            matching "<?php echo htmlspecialchars($searchQuery); ?>"
                        <?php endif; ?>
                        <?php if (!empty($regionFilter)): ?>
                            in <?php echo htmlspecialchars($regionFilter); ?>
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
            
            <!-- Hospitals List -->
            <?php if (empty($searchQuery) && empty($regionFilter)): ?>
                <!-- Group by region when no filters -->
                <?php foreach ($hospitalsByRegion as $region => $regionHospitals): ?>
                    <div class="region-section">
                        <h2 class="region-title"><?php echo htmlspecialchars($region); ?></h2>
                        <div class="hospitals-grid">
                            <?php foreach ($regionHospitals as $hospital): ?>
                                <div class="hospital-card">
                                    <div style="position: relative;">
                                        <img src="<?php echo $hospital['image']; ?>" 
                                             alt="<?php echo htmlspecialchars($hospital['name']); ?>" 
                                             class="hospital-image"
                                             loading="lazy">
                                        <div class="hospital-badge">
                                            <i class="fas fa-star"></i>
                                            <?php echo $hospital['rating']; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="hospital-content">
                                        <div class="hospital-header">
                                            <h3 class="hospital-name"><?php echo htmlspecialchars($hospital['name']); ?></h3>
                                            <div class="hospital-location">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <?php echo htmlspecialchars($hospital['city']); ?>, <?php echo htmlspecialchars($hospital['region']); ?>
                                            </div>
                                            <div class="hospital-rating">
                                                <div class="rating-stars">
                                                    <?php
                                                    $rating = $hospital['rating'];
                                                    $fullStars = floor($rating);
                                                    $hasHalfStar = ($rating - $fullStars) >= 0.5;
                                                    
                                                    for ($i = 0; $i < $fullStars; $i++) {
                                                        echo '<i class="fas fa-star"></i>';
                                                    }
                                                    if ($hasHalfStar) {
                                                        echo '<i class="fas fa-star-half-alt"></i>';
                                                    }
                                                    for ($i = $fullStars + ($hasHalfStar ? 1 : 0); $i < 5; $i++) {
                                                        echo '<i class="far fa-star"></i>';
                                                    }
                                                    ?>
                                                </div>
                                                <span class="rating-number"><?php echo $hospital['rating']; ?></span>
                                                <span class="rating-year">• Est. <?php echo $hospital['established']; ?></span>
                                            </div>
                                        </div>
                                        
                                        <div class="hospital-info">
                                            <div class="info-item">
                                                <i class="fas fa-phone info-icon"></i>
                                                <span class="info-text"><?php echo htmlspecialchars($hospital['phone']); ?></span>
                                            </div>
                                            <div class="info-item">
                                                <i class="fas fa-building info-icon"></i>
                                                <span class="info-text"><?php echo htmlspecialchars($hospital['department']); ?></span>
                                            </div>
                                            <div class="info-item">
                                                <i class="fas fa-user-md info-icon"></i>
                                                <span class="info-text"><?php echo htmlspecialchars($hospital['contact_person']); ?></span>
                                            </div>
                                            <div class="info-item">
                                                <i class="fas fa-clock info-icon"></i>
                                                <span class="info-text"><?php echo strip_tags($hospital['visiting_hours']); ?></span>
                                            </div>
                                            <div class="info-item">
                                                <i class="fas fa-car info-icon"></i>
                                                <span class="info-text"><?php echo htmlspecialchars($hospital['parking']); ?></span>
                                            </div>
                                        </div>
                                        
                                        <div class="hospital-services">
                                            <div class="services-title">Services</div>
                                            <div class="services-list">
                                                <?php foreach ($hospital['services'] as $service): ?>
                                                    <span class="service-tag"><?php echo htmlspecialchars($service); ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="hospital-actions">
                                            <a href="tel:<?php echo str_replace([' ', '-', '(', ')'], '', $hospital['phone']); ?>" 
                                               class="action-btn btn-primary">
                                                <i class="fas fa-phone"></i>
                                                Call
                                            </a>
                                            <a href="mailto:<?php echo $hospital['email']; ?>?subject=Blood Service Inquiry" 
                                               class="action-btn btn-secondary">
                                                <i class="fas fa-envelope"></i>
                                                Email
                                            </a>
                                            <a href="https://maps.google.com/?q=<?php echo urlencode($hospital['address']); ?>" 
                                               target="_blank" class="action-btn btn-outline">
                                                <i class="fas fa-map"></i>
                                                Directions
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Show filtered results without grouping -->
                <div class="hospitals-grid">
                    <?php foreach ($filteredHospitals as $hospital): ?>
                        <div class="hospital-card">
                            <div style="position: relative;">
                                <img src="<?php echo $hospital['image']; ?>" 
                                     alt="<?php echo htmlspecialchars($hospital['name']); ?>" 
                                     class="hospital-image"
                                     loading="lazy">
                                <div class="hospital-badge">
                                    <i class="fas fa-star"></i>
                                    <?php echo $hospital['rating']; ?>
                                </div>
                            </div>
                            
                            <div class="hospital-content">
                                <div class="hospital-header">
                                    <h3 class="hospital-name"><?php echo htmlspecialchars($hospital['name']); ?></h3>
                                    <div class="hospital-location">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?php echo htmlspecialchars($hospital['city']); ?>, <?php echo htmlspecialchars($hospital['region']); ?>
                                    </div>
                                    <div class="hospital-rating">
                                        <div class="rating-stars">
                                            <?php
                                            $rating = $hospital['rating'];
                                            $fullStars = floor($rating);
                                            $hasHalfStar = ($rating - $fullStars) >= 0.5;
                                            
                                            for ($i = 0; $i < $fullStars; $i++) {
                                                echo '<i class="fas fa-star"></i>';
                                            }
                                            if ($hasHalfStar) {
                                                echo '<i class="fas fa-star-half-alt"></i>';
                                            }
                                            for ($i = $fullStars + ($hasHalfStar ? 1 : 0); $i < 5; $i++) {
                                                echo '<i class="far fa-star"></i>';
                                            }
                                            ?>
                                        </div>
                                        <span class="rating-number"><?php echo $hospital['rating']; ?></span>
                                        <span class="rating-year">• Est. <?php echo $hospital['established']; ?></span>
                                    </div>
                                </div>
                                
                                <div class="hospital-info">
                                    <div class="info-item">
                                        <i class="fas fa-phone info-icon"></i>
                                        <span class="info-text"><?php echo htmlspecialchars($hospital['phone']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-building info-icon"></i>
                                        <span class="info-text"><?php echo htmlspecialchars($hospital['department']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-user-md info-icon"></i>
                                        <span class="info-text"><?php echo htmlspecialchars($hospital['contact_person']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-clock info-icon"></i>
                                        <span class="info-text"><?php echo strip_tags($hospital['visiting_hours']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-car info-icon"></i>
                                        <span class="info-text"><?php echo htmlspecialchars($hospital['parking']); ?></span>
                                    </div>
                                </div>
                                
                                <div class="hospital-services">
                                    <div class="services-title">Services</div>
                                    <div class="services-list">
                                        <?php foreach ($hospital['services'] as $service): ?>
                                            <span class="service-tag"><?php echo htmlspecialchars($service); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <div class="hospital-actions">
                                    <a href="tel:<?php echo str_replace([' ', '-', '(', ')'], '', $hospital['phone']); ?>" 
                                       class="action-btn btn-primary">
                                        <i class="fas fa-phone"></i>
                                        Call
                                    </a>
                                    <a href="mailto:<?php echo $hospital['email']; ?>?subject=Blood Service Inquiry" 
                                       class="action-btn btn-secondary">
                                        <i class="fas fa-envelope"></i>
                                        Email
                                    </a>
                                    <a href="https://maps.google.com/?q=<?php echo urlencode($hospital['address']); ?>" 
                                       target="_blank" class="action-btn btn-outline">
                                        <i class="fas fa-map"></i>
                                        Directions
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <!-- No Results Found -->
            <div class="no-results">
                <i class="fas fa-search"></i>
                <h3>No Hospitals Found</h3>
                <p>We couldn't find any hospitals matching your search criteria.</p>
                <a href="locations.php" class="action-btn btn-primary">
                    <i class="fas fa-list"></i>
                    View All Hospitals
                </a>
            </div>
        <?php endif; ?>
        
        <!-- Quick Facts Section -->
        <div style="margin-top: 50px; padding: 40px; background: linear-gradient(135deg, #e8f5e8, #c8e6c9); border-radius: 20px; border-left: 5px solid #28a745;">
            <h3 style="color: #2e7d32; margin: 0 0 25px 0; text-align: center; font-size: 24px;">
                <i class="fas fa-info-circle"></i>
                Why Choose Our Partner Hospitals?
            </h3>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 25px;">
                <div style="text-align: center; padding: 25px; background: rgba(255,255,255,0.8); border-radius: 15px;">
                    <i class="fas fa-certificate" style="font-size: 40px; color: #28a745; margin-bottom: 15px;"></i>
                    <h4 style="color: #2e7d32; margin: 0 0 10px 0;">Certified Excellence</h4>
                    <p style="color: #4caf50; margin: 0; font-size: 14px; line-height: 1.6;">
                        All partner hospitals meet strict quality standards for blood services and patient care.
                    </p>
                </div>
                
                <div style="text-align: center; padding: 25px; background: rgba(255,255,255,0.8); border-radius: 15px;">
                    <i class="fas fa-user-md" style="font-size: 40px; color: #28a745; margin-bottom: 15px;"></i>
                    <h4 style="color: #2e7d32; margin: 0 0 10px 0;">Expert Medical Staff</h4>
                    <p style="color: #4caf50; margin: 0; font-size: 14px; line-height: 1.6;">
                        Trained hematologists and transfusion specialists ensure safe blood procedures.
                    </p>
                </div>
                
                <div style="text-align: center; padding: 25px; background: rgba(255,255,255,0.8); border-radius: 15px;">
                    <i class="fas fa-shield-alt" style="font-size: 40px; color: #28a745; margin-bottom: 15px;"></i>
                    <h4 style="color: #2e7d32; margin: 0 0 10px 0;">Safety First</h4>
                    <p style="color: #4caf50; margin: 0; font-size: 14px; line-height: 1.6;">
                        Rigorous testing and screening protocols ensure the safety of all blood products.
                    </p>
                </div>
                
                <div style="text-align: center; padding: 25px; background: rgba(255,255,255,0.8); border-radius: 15px;">
                    <i class="fas fa-clock" style="font-size: 40px; color: #28a745; margin-bottom: 15px;"></i>
                    <h4 style="color: #2e7d32; margin: 0 0 10px 0;">24/7 Emergency</h4>
                    <p style="color: #4caf50; margin: 0; font-size: 14px; line-height: 1.6;">
                        Emergency blood services available round-the-clock for urgent medical needs.
                    </p>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 30px; padding: 25px; background: rgba(255,255,255,0.9); border-radius: 15px;">
                <h4 style="color: #2e7d32; margin: 0 0 15px 0;">
                    <i class="fas fa-heart" style="color: #dc3545;"></i>
                    Ready to Save Lives?
                </h4>
                <p style="color: #4caf50; margin: 0 0 20px 0; line-height: 1.6;">
                    Visit any of our partner hospitals to donate blood or request transfusion services. 
                    Your contribution helps save lives and strengthens our community's health network.
                </p>
                <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                    <a href="donate.php" style="padding: 12px 25px; background: linear-gradient(135deg, #dc3545, #b02a37); color: white; text-decoration: none; border-radius: 25px; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: all 0.3s ease;">
                        <i class="fas fa-heart"></i>
                        Donate Blood
                    </a>
                    <a href="request.php" style="padding: 12px 25px; background: linear-gradient(135deg, #17a2b8, #138496); color: white; text-decoration: none; border-radius: 25px; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: all 0.3s ease;">
                        <i class="fas fa-plus-circle"></i>
                        Request Blood
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add animation to hospital cards on scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
            if (entry.isIntersecting) {
                setTimeout(() => {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }, index * 100);
            }
        });
    }, observerOptions);
    
    // Observe all hospital cards
    document.querySelectorAll('.hospital-card').forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(card);
    });
    
    // Add click handler for hospital cards
    document.querySelectorAll('.hospital-card').forEach(card => {
        card.addEventListener('click', function(e) {
            // Don't trigger if clicking on action buttons
            if (!e.target.closest('.hospital-actions')) {
                const hospitalName = this.querySelector('.hospital-name').textContent;
                const phone = this.querySelector('.info-text').textContent;
                
                if (confirm(`Contact ${hospitalName}?\n\nPhone: ${phone}`)) {
                    window.location.href = `tel:${phone.replace(/[^0-9+]/g, '')}`;
                }
            }
        });
        
        // Add hover cursor
        card.style.cursor = 'pointer';
    });
    
    // Add smooth scroll for region sections
    if (window.location.hash) {
        const target = document.querySelector(window.location.hash);
        if (target) {
            setTimeout(() => {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }, 500);
        }
    }
    
    // Auto-focus search input if there's an error or search was performed
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput && (<?php echo !empty($searchQuery) ? 'true' : 'false'; ?> || <?php echo !empty($message) ? 'true' : 'false'; ?>)) {
        setTimeout(() => {
            searchInput.focus();
        }, 500);
    }
    
    // Add loading state to search form
    const searchForm = document.querySelector('.search-form');
    if (searchForm) {
        searchForm.addEventListener('submit', function() {
            const submitBtn = this.querySelector('.search-btn');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Searching...';
            submitBtn.disabled = true;
            
            // Re-enable after 3 seconds in case of issues
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 3000);
        });
    }
    
    // Add tooltips to action buttons
    document.querySelectorAll('.action-btn').forEach(btn => {
        btn.addEventListener('mouseenter', function() {
            const icon = this.querySelector('i');
            if (icon.classList.contains('fa-phone')) {
                this.title = 'Call hospital directly';
            } else if (icon.classList.contains('fa-envelope')) {
                this.title = 'Send email inquiry';
            } else if (icon.classList.contains('fa-map')) {
                this.title = 'Get directions via Google Maps';
            }
        });
    });
    
    console.log('Locations page initialized successfully');
    console.log('Total hospitals:', <?php echo count($hospitals); ?>);
    console.log('Filtered hospitals:', <?php echo count($filteredHospitals); ?>);
});
</script>

</body>
<?php include('includes/footer.php'); ?>
</html>