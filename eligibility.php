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

$eligibility_result = null;
$disqualifying_factors = [];
$recommendations = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process eligibility check
    $age = intval($_POST['age']);
    $weight = floatval($_POST['weight']);
    $gender = $_POST['gender'];
    $blood_pressure_systolic = intval($_POST['blood_pressure_systolic']);
    $blood_pressure_diastolic = intval($_POST['blood_pressure_diastolic']);
    $hemoglobin = floatval($_POST['hemoglobin']);
    $last_donation = $_POST['last_donation'];
    $medications = $_POST['medications'] ?? [];
    $medical_conditions = $_POST['medical_conditions'] ?? [];
    $travel_history = $_POST['travel_history'];
    $tattoo_piercing = $_POST['tattoo_piercing'];
    $pregnancy = $_POST['pregnancy'] ?? '';
    $alcohol_24h = $_POST['alcohol_24h'];
    $sleep_hours = intval($_POST['sleep_hours']);
    
    $is_eligible = true;
    
    // Age check
    if ($age < 17 || $age > 65) {
        $is_eligible = false;
        $disqualifying_factors[] = "Age must be between 17-65 years";
        $recommendations[] = "Blood donation is restricted to individuals between 17-65 years old for safety reasons";
    }
    
    // Weight check
    $min_weight = ($gender === 'female') ? 45 : 50;
    if ($weight < $min_weight) {
        $is_eligible = false;
        $disqualifying_factors[] = "Weight below minimum requirement (" . $min_weight . "kg for " . $gender . "s)";
        $recommendations[] = "Maintain a healthy weight through proper nutrition and consult with a healthcare provider";
    }
    
    // Blood pressure check
    if ($blood_pressure_systolic < 90 || $blood_pressure_systolic > 180 || 
        $blood_pressure_diastolic < 60 || $blood_pressure_diastolic > 100) {
        $is_eligible = false;
        $disqualifying_factors[] = "Blood pressure outside acceptable range (90-180/60-100 mmHg)";
        $recommendations[] = "Consult with your doctor about blood pressure management before donating";
    }
    
    // Hemoglobin check
    $min_hb = ($gender === 'female') ? 12.0 : 13.0;
    if ($hemoglobin < $min_hb) {
        $is_eligible = false;
        $disqualifying_factors[] = "Hemoglobin level below minimum (" . $min_hb . "g/dL for " . $gender . "s)";
        $recommendations[] = "Increase iron-rich foods in your diet and consider iron supplements after consulting a doctor";
    }
    
    // Last donation check
    if ($last_donation === 'less_than_8_weeks') {
        $is_eligible = false;
        $disqualifying_factors[] = "Must wait at least 8 weeks between whole blood donations";
        $recommendations[] = "Schedule your next donation at least 8 weeks after your last donation";
    }
    
    // Medications check
    $restricted_medications = ['antibiotics', 'blood_thinners', 'insulin', 'immunosuppressants'];
    $user_restricted_meds = array_intersect($medications, $restricted_medications);
    if (!empty($user_restricted_meds)) {
        $is_eligible = false;
        $med_names = [
            'antibiotics' => 'Antibiotics',
            'blood_thinners' => 'Blood thinners',
            'insulin' => 'Insulin',
            'immunosuppressants' => 'Immunosuppressants'
        ];
        $restricted_list = implode(', ', array_map(function($med) use ($med_names) {
            return $med_names[$med];
        }, $user_restricted_meds));
        $disqualifying_factors[] = "Currently taking restricted medications: " . $restricted_list;
        $recommendations[] = "Consult with medical staff about medication compatibility before donating";
    }
    
    // Medical conditions check
    $serious_conditions = ['heart_disease', 'diabetes_insulin', 'cancer_current', 'hiv_aids', 'hepatitis'];
    $user_serious_conditions = array_intersect($medical_conditions, $serious_conditions);
    if (!empty($user_serious_conditions)) {
        $is_eligible = false;
        $condition_names = [
            'heart_disease' => 'Heart disease',
            'diabetes_insulin' => 'Insulin-dependent diabetes',
            'cancer_current' => 'Current cancer treatment',
            'hiv_aids' => 'HIV/AIDS',
            'hepatitis' => 'Hepatitis'
        ];
        $condition_list = implode(', ', array_map(function($condition) use ($condition_names) {
            return $condition_names[$condition];
        }, $user_serious_conditions));
        $disqualifying_factors[] = "Medical conditions that restrict donation: " . $condition_list;
        $recommendations[] = "These conditions require medical clearance from your healthcare provider";
    }
    
    // Travel history check
    if ($travel_history === 'malaria_risk_area') {
        $is_eligible = false;
        $disqualifying_factors[] = "Recent travel to malaria risk areas";
        $recommendations[] = "Wait 3 months after returning from malaria-endemic areas before donating";
    }
    
    // Tattoo/piercing check
    if ($tattoo_piercing === 'less_than_6_months') {
        $is_eligible = false;
        $disqualifying_factors[] = "Recent tattoo or piercing within 6 months";
        $recommendations[] = "Wait at least 6 months after getting tattoos or piercings before donating";
    }
    
    // Pregnancy check
    if ($pregnancy === 'currently_pregnant' || $pregnancy === 'gave_birth_6_months') {
        $is_eligible = false;
        if ($pregnancy === 'currently_pregnant') {
            $disqualifying_factors[] = "Currently pregnant";
            $recommendations[] = "Blood donation is not recommended during pregnancy";
        } else {
            $disqualifying_factors[] = "Gave birth within the last 6 months";
            $recommendations[] = "Wait at least 6 months after giving birth before donating";
        }
    }
    
    // Alcohol consumption check
    if ($alcohol_24h === 'yes') {
        $is_eligible = false;
        $disqualifying_factors[] = "Consumed alcohol within the last 24 hours";
        $recommendations[] = "Avoid alcohol for 24 hours before donating blood";
    }
    
    // Sleep check
    if ($sleep_hours < 6) {
        $is_eligible = false;
        $disqualifying_factors[] = "Insufficient sleep (less than 6 hours)";
        $recommendations[] = "Ensure you get at least 6-8 hours of sleep before donating";
    }
    
    // Set result
    $eligibility_result = $is_eligible ? 'eligible' : 'not_eligible';
    
    // Add general recommendations for eligible users
    if ($is_eligible) {
        $recommendations = [
            "Eat a iron-rich meal 2-3 hours before donating",
            "Drink plenty of water before and after donation",
            "Bring a valid ID and list of any medications",
            "Plan to rest for 15-20 minutes after donation",
            "Avoid heavy lifting or strenuous exercise for 24 hours after donation"
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pirate's Blood Bank - Donation Eligibility Check</title>
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
            padding: 40px 20px;
            width: calc(100vw - 70px);
            box-sizing: border-box;
            overflow-y: auto;
            display: block;
        }
        
        .eligibility-container {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            border-radius: 20px;
            padding: 60px 40px;
            width: 100%;
            max-width: 900px;
            position: relative;
            overflow: hidden;
            margin: 0 auto;
            margin-top: 20px;
            margin-bottom: 40px;
        }
        
        .eligibility-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #dc3545, #28a745, #17a2b8);
            border-radius: 20px 20px 0 0;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .page-icon {
            font-size: 60px;
            color: #dc3545;
            margin-bottom: 20px;
        }
        
        .page-title {
            font-size: 32px;
            font-weight: 700;
            color: #495057;
            margin-bottom: 15px;
            font-family: 'Poppins', sans-serif;
        }
        
        .page-subtitle {
            font-size: 18px;
            color: #6c757d;
            line-height: 1.6;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .form-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border: 1px solid #f0f0f0;
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: #495057;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f8f9fa;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-input, .form-select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }
        
        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: #dc3545;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
        }
        
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .checkbox-item:hover {
            background: #e9ecef;
        }
        
        .checkbox-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #dc3545;
        }
        
        .checkbox-item label {
            font-size: 14px;
            color: #495057;
            cursor: pointer;
            margin-bottom: 0;
        }
        
        .radio-group {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        
        .radio-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 15px;
            background: #f8f9fa;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .radio-item:hover {
            background: #e9ecef;
        }
        
        .radio-item input[type="radio"] {
            accent-color: #dc3545;
        }
        
        .radio-item label {
            font-size: 14px;
            color: #495057;
            cursor: pointer;
            margin-bottom: 0;
        }
        
        .submit-section {
            text-align: center;
            margin-top: 40px;
        }
        
        .submit-btn {
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
            padding: 18px 50px;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .submit-btn:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.3);
        }
        
        /* Results Styles */
        .result-container {
            margin-top: 40px;
            padding: 40px;
            border-radius: 20px;
            text-align: center;
        }
        
        .result-eligible {
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.1), rgba(40, 167, 69, 0.05));
            border: 2px solid rgba(40, 167, 69, 0.2);
        }
        
        .result-not-eligible {
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));
            border: 2px solid rgba(220, 53, 69, 0.2);
        }
        
        .result-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        
        .result-icon.eligible {
            color: #28a745;
        }
        
        .result-icon.not-eligible {
            color: #dc3545;
        }
        
        .result-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 15px;
            font-family: 'Poppins', sans-serif;
        }
        
        .result-title.eligible {
            color: #155724;
        }
        
        .result-title.not-eligible {
            color: #721c24;
        }
        
        .result-subtitle {
            font-size: 16px;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .result-subtitle.eligible {
            color: #155724;
        }
        
        .result-subtitle.not-eligible {
            color: #721c24;
        }
        
        .factors-section, .recommendations-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin: 20px 0;
            text-align: left;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .factors-title, .recommendations-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .factors-list, .recommendations-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .factors-list li, .recommendations-list li {
            padding: 12px 0;
            border-bottom: 1px solid #f8f9fa;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        
        .factors-list li:last-child, .recommendations-list li:last-child {
            border-bottom: none;
        }
        
        .factors-list li::before {
            content: '⚠️';
            flex-shrink: 0;
        }
        
        .recommendations-list li::before {
            content: '💡';
            flex-shrink: 0;
        }
        
        .action-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 30px;
        }
        
        .action-btn {
            padding: 15px 30px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 14px;
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
        
        .info-box {
            background: linear-gradient(90deg, rgba(23, 162, 184, 0.1), rgba(23, 162, 184, 0.05));
            color: #0c5460;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            border-left: 4px solid #17a2b8;
        }
        
        .info-box h4 {
            margin: 0 0 10px 0;
            font-size: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-box p {
            margin: 0;
            font-size: 14px;
            line-height: 1.5;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            main {
                margin-left: 0;
                padding: 20px 15px;
                width: 100vw;
            }
            
            .eligibility-container {
                padding: 40px 25px;
                border-radius: 15px;
                margin-top: 10px;
                margin-bottom: 20px;
            }
            
            .page-title {
                font-size: 26px;
            }
            
            .page-icon {
                font-size: 50px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .checkbox-group {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .radio-group {
                flex-direction: column;
                gap: 10px;
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
            
            .eligibility-container {
                padding: 30px 20px;
                border-radius: 10px;
                margin-top: 5px;
                margin-bottom: 15px;
            }
            
            .page-title {
                font-size: 22px;
            }
            
            .page-icon {
                font-size: 40px;
            }
            
            .form-section {
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
        <div class="eligibility-container">
            <?php if (!$eligibility_result): ?>
            <div class="page-header">
                <div class="page-icon">
                    <i class="fas fa-heartbeat"></i>
                </div>
                <h1 class="page-title">Blood Donation Eligibility Check</h1>
                <p class="page-subtitle">
                    Complete this quick assessment to determine if you're eligible to donate blood. 
                    This screening helps ensure the safety of both donors and recipients.
                </p>
            </div>
            
            <div class="info-box">
                <h4>
                    <i class="fas fa-info-circle"></i>
                    Before You Begin
                </h4>
                <p>
                    This eligibility check is based on standard blood donation guidelines. Results are preliminary 
                    and a final assessment will be conducted by medical staff before donation.
                </p>
            </div>
            
            <form method="POST" action="">
                <!-- Basic Information -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-user"></i>
                        Basic Information
                    </h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="age">Age (years)</label>
                            <input type="number" id="age" name="age" class="form-input" min="16" max="70" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="weight">Weight (kg)</label>
                            <input type="number" id="weight" name="weight" class="form-input" min="40" max="200" step="0.1" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="gender">Gender</label>
                            <select id="gender" name="gender" class="form-select" required>
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Health Metrics -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-stethoscope"></i>
                        Health Metrics
                    </h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="blood_pressure_systolic">Systolic Blood Pressure (mmHg)</label>
                            <input type="number" id="blood_pressure_systolic" name="blood_pressure_systolic" class="form-input" min="80" max="200" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="blood_pressure_diastolic">Diastolic Blood Pressure (mmHg)</label>
                            <input type="number" id="blood_pressure_diastolic" name="blood_pressure_diastolic" class="form-input" min="50" max="120" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="hemoglobin">Hemoglobin Level (g/dL)</label>
                            <input type="number" id="hemoglobin" name="hemoglobin" class="form-input" min="8" max="20" step="0.1" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="sleep_hours">Hours of Sleep Last Night</label>
                            <input type="number" id="sleep_hours" name="sleep_hours" class="form-input" min="0" max="12" required>
                        </div>
                    </div>
                </div>
                
                <!-- Donation History -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-history"></i>
                        Donation History
                    </h3>
                    <div class="form-group">
                        <label class="form-label">When was your last blood donation?</label>
                        <div class="radio-group">
                            <div class="radio-item">
                                <input type="radio" id="never_donated" name="last_donation" value="never" required>
                                <label for="never_donated">Never donated</label>
                            </div>
                            <div class="radio-item">
                                <input type="radio" id="more_than_8_weeks" name="last_donation" value="more_than_8_weeks" required>
                                <label for="more_than_8_weeks">More than 8 weeks ago</label>
                            </div>
                            <div class="radio-item">
                                <input type="radio" id="less_than_8_weeks" name="last_donation" value="less_than_8_weeks" required>
                                <label for="less_than_8_weeks">Less than 8 weeks ago</label>
                            </div>
                        </div>
                    </div>
                </div>
                
 <!-- Medications -->
<div class="form-section">
    <h3 class="section-title">
        <i class="fas fa-pills"></i>
        Current Medications
    </h3>
    <div class="form-group">
        <label class="form-label">Are you currently taking any of these medications?</label>
        <div class="checkbox-group">
            <div class="checkbox-item">
                <input type="checkbox" id="antibiotics" name="medications[]" value="antibiotics">
                <label for="antibiotics">Antibiotics</label>
            </div>
            <div class="checkbox-item">
                <input type="checkbox" id="blood_thinners" name="medications[]" value="blood_thinners">
                <label for="blood_thinners">Blood Thinners</label>
            </div>
            <div class="checkbox-item">
                <input type="checkbox" id="insulin" name="medications[]" value="insulin">
                <label for="insulin">Insulin</label>
            </div>
            <div class="checkbox-item">
                <input type="checkbox" id="immunosuppressants" name="medications[]" value="immunosuppressants">
                <label for="immunosuppressants">Immunosuppressants</label>
            </div>
            <div class="checkbox-item">
                <input type="checkbox" id="none_medications" name="medications[]" value="none">
                <label for="none_medications">None of the above</label>
            </div>
        </div>
    </div>
</div>
                
                <!-- Medical Conditions -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-file-medical"></i>
                        Medical Conditions
                    </h3>
                    <div class="form-group">
                        <label class="form-label">Do you have any of these medical conditions?</label>
                        <div class="checkbox-group">
                            <div class="checkbox-item">
                                <input type="checkbox" id="heart_disease" name="medical_conditions[]" value="heart_disease">
                                <label for="heart_disease">Heart Disease</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" id="diabetes_insulin" name="medical_conditions[]" value="diabetes_insulin">
                                <label for="diabetes_insulin">Diabetes (Insulin-dependent)</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" id="cancer_current" name="medical_conditions[]" value="cancer_current">
                                <label for="cancer_current">Current Cancer Treatment</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" id="hiv_aids" name="medical_conditions[]" value="hiv_aids">
                                <label for="hiv_aids">HIV/AIDS</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" id="hepatitis" name="medical_conditions[]" value="hepatitis">
                                <label for="hepatitis">Hepatitis</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" id="hypertension" name="medical_conditions[]" value="hypertension">
                                <label for="hypertension">Hypertension (Controlled)</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" id="none_conditions" name="medical_conditions[]" value="none">
                                <label for="none_conditions">None of the above</label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Travel and Lifestyle -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-globe-americas"></i>
                        Travel & Lifestyle
                    </h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Recent Travel History</label>
                            <div class="radio-group">
                                <div class="radio-item">
                                    <input type="radio" id="no_travel" name="travel_history" value="no_recent_travel" required>
                                    <label for="no_travel">No recent travel</label>
                                </div>
                                <div class="radio-item">
                                    <input type="radio" id="malaria_risk" name="travel_history" value="malaria_risk_area" required>
                                    <label for="malaria_risk">Traveled to malaria-endemic area</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Recent Tattoos or Piercings</label>
                            <div class="radio-group">
                                <div class="radio-item">
                                    <input type="radio" id="no_tattoo" name="tattoo_piercing" value="none_or_old" required>
                                    <label for="no_tattoo">None or older than 6 months</label>
                                </div>
                                <div class="radio-item">
                                    <input type="radio" id="recent_tattoo" name="tattoo_piercing" value="less_than_6_months" required>
                                    <label for="recent_tattoo">Within the last 6 months</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Alcohol Consumption</label>
                            <div class="radio-group">
                                <div class="radio-item">
                                    <input type="radio" id="no_alcohol" name="alcohol_24h" value="no" required>
                                    <label for="no_alcohol">No alcohol in 24 hours</label>
                                </div>
                                <div class="radio-item">
                                    <input type="radio" id="yes_alcohol" name="alcohol_24h" value="yes" required>
                                    <label for="yes_alcohol">Had alcohol in last 24 hours</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Pregnancy (For Female Users) -->
                <div class="form-section" id="pregnancy-section" style="display: none;">
                    <h3 class="section-title">
                        <i class="fas fa-baby"></i>
                        Pregnancy Status
                    </h3>
                    <div class="form-group">
                        <label class="form-label">Pregnancy Status</label>
                        <div class="radio-group">
                            <div class="radio-item">
                                <input type="radio" id="not_pregnant" name="pregnancy" value="not_pregnant">
                                <label for="not_pregnant">Not pregnant</label>
                            </div>
                            <div class="radio-item">
                                <input type="radio" id="currently_pregnant" name="pregnancy" value="currently_pregnant">
                                <label for="currently_pregnant">Currently pregnant</label>
                            </div>
                            <div class="radio-item">
                                <input type="radio" id="gave_birth_recently" name="pregnancy" value="gave_birth_6_months">
                                <label for="gave_birth_recently">Gave birth within 6 months</label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="submit-section">
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-check-circle"></i>
                        Check My Eligibility
                    </button>
                </div>
            </form>
            
            <?php else: ?>
            <!-- Results Section -->
            <div class="result-container <?php echo $eligibility_result === 'eligible' ? 'result-eligible' : 'result-not-eligible'; ?>">
                <div class="result-icon <?php echo $eligibility_result === 'eligible' ? 'eligible' : 'not-eligible'; ?>">
                    <i class="fas <?php echo $eligibility_result === 'eligible' ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                </div>
                
                <h2 class="result-title <?php echo $eligibility_result === 'eligible' ? 'eligible' : 'not-eligible'; ?>">
                    <?php echo $eligibility_result === 'eligible' ? 'You Are Eligible to Donate!' : 'You Are Currently Not Eligible'; ?>
                </h2>
                
                <p class="result-subtitle <?php echo $eligibility_result === 'eligible' ? 'eligible' : 'not-eligible'; ?>">
                    <?php if ($eligibility_result === 'eligible'): ?>
                        Great news! Based on your responses, you meet the basic requirements for blood donation. 
                        Please proceed to schedule your donation appointment.
                    <?php else: ?>
                        Based on your responses, you don't currently meet all the requirements for blood donation. 
                        Please review the factors below and consult with medical staff if needed.
                    <?php endif; ?>
                </p>
                
                <?php if (!empty($disqualifying_factors)): ?>
                <div class="factors-section">
                    <h4 class="factors-title">
                        <i class="fas fa-exclamation-triangle"></i>
                        Disqualifying Factors
                    </h4>
                    <ul class="factors-list">
                        <?php foreach ($disqualifying_factors as $factor): ?>
                        <li><?php echo htmlspecialchars($factor); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($recommendations)): ?>
                <div class="recommendations-section">
                    <h4 class="recommendations-title">
                        <i class="fas fa-lightbulb"></i>
                        <?php echo $eligibility_result === 'eligible' ? 'Pre-Donation Tips' : 'Recommendations'; ?>
                    </h4>
                    <ul class="recommendations-list">
                        <?php foreach ($recommendations as $recommendation): ?>
                        <li><?php echo htmlspecialchars($recommendation); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <div class="action-buttons">
                    <?php if ($eligibility_result === 'eligible'): ?>
                    <a href="donate.php" class="action-btn btn-primary">
                        <i class="fas fa-heart"></i>
                        Schedule Donation
                    </a>
                    <a href="appointments.php" class="action-btn btn-secondary">
                        <i class="fas fa-calendar-alt"></i>
                        View My Appointments
                    </a>
                    <?php else: ?>
                    <a href="help.php" class="action-btn btn-secondary">
                        <i class="fas fa-phone"></i>
                        Contact Medical Staff
                    </a>
                    <?php endif; ?>
                    <a href="eligibility.php" class="action-btn btn-outline">
                        <i class="fas fa-redo"></i>
                        Take Test Again
                    </a>
                    <a href="index.php" class="action-btn btn-outline">
                        <i class="fas fa-home"></i>
                        Back to Dashboard
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <br><br>
    </main>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Show/hide pregnancy section based on gender
            const genderSelect = document.getElementById('gender');
            const pregnancySection = document.getElementById('pregnancy-section');
            
            function togglePregnancySection() {
                if (genderSelect.value === 'female') {
                    pregnancySection.style.display = 'block';
                    // Make pregnancy radio buttons required
                    const pregnancyRadios = document.querySelectorAll('input[name="pregnancy"]');
                    pregnancyRadios.forEach(radio => radio.setAttribute('required', 'required'));
                } else {
                    pregnancySection.style.display = 'none';
                    // Remove required attribute and clear selection
                    const pregnancyRadios = document.querySelectorAll('input[name="pregnancy"]');
                    pregnancyRadios.forEach(radio => {
                        radio.removeAttribute('required');
                        radio.checked = false;
                    });
                }
            }
            
            genderSelect.addEventListener('change', togglePregnancySection);
            togglePregnancySection(); // Initial check
            
            // Handle "None" checkboxes
            function handleNoneCheckbox(noneCheckboxId, groupName) {
                const noneCheckbox = document.getElementById(noneCheckboxId);
                const otherCheckboxes = document.querySelectorAll(`input[name="${groupName}[]"]:not(#${noneCheckboxId})`);
                
                noneCheckbox.addEventListener('change', function() {
                    if (this.checked) {
                        otherCheckboxes.forEach(checkbox => checkbox.checked = false);
                    }
                });
                
                otherCheckboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        if (this.checked) {
                            noneCheckbox.checked = false;
                        }
                    });
                });
            }
            
            handleNoneCheckbox('none_medications', 'medications');
            handleNoneCheckbox('none_conditions', 'medical_conditions');
            
            // Form validation
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    // Validate that at least one medication checkbox is selected
                    const medicationCheckboxes = document.querySelectorAll('input[name="medications[]"]');
                    const medicationChecked = Array.from(medicationCheckboxes).some(cb => cb.checked);
                    
                    if (!medicationChecked) {
                        e.preventDefault();
                        alert('Please select your current medications or "None of the above"');
                        return;
                    }
                    
                    // Validate that at least one medical condition checkbox is selected
                    const conditionCheckboxes = document.querySelectorAll('input[name="medical_conditions[]"]');
                    const conditionChecked = Array.from(conditionCheckboxes).some(cb => cb.checked);
                    
                    if (!conditionChecked) {
                        e.preventDefault();
                        alert('Please select your medical conditions or "None of the above"');
                        return;
                    }
                    
                    // Show loading state
                    const submitBtn = document.querySelector('.submit-btn');
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking Eligibility...';
                    submitBtn.disabled = true;
                });
            }
            
            // Add smooth animations to form sections
            const formSections = document.querySelectorAll('.form-section');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, { threshold: 0.1 });
            
            formSections.forEach(section => {
                section.style.opacity = '0';
                section.style.transform = 'translateY(20px)';
                section.style.transition = 'all 0.6s ease';
                observer.observe(section);
            });
            
            // Blood pressure helper
            const systolicInput = document.getElementById('blood_pressure_systolic');
            const diastolicInput = document.getElementById('blood_pressure_diastolic');
            
            function validateBloodPressure() {
                const systolic = parseInt(systolicInput.value);
                const diastolic = parseInt(diastolicInput.value);
                
                if (systolic && diastolic && systolic <= diastolic) {
                    diastolicInput.setCustomValidity('Diastolic pressure must be lower than systolic pressure');
                } else {
                    diastolicInput.setCustomValidity('');
                }
            }
            
            systolicInput.addEventListener('input', validateBloodPressure);
            diastolicInput.addEventListener('input', validateBloodPressure);
            
            // Hemoglobin helper tooltip
            const hemoglobinInput = document.getElementById('hemoglobin');
            hemoglobinInput.addEventListener('focus', function() {
                if (!this.title) {
                    this.title = 'Normal range: Males 13.8-17.2 g/dL, Females 12.1-15.1 g/dL';
                }
            });
            
            console.log('Eligibility checker initialized successfully');
        });
        
        // Add some visual feedback for form interactions
        document.querySelectorAll('.form-input, .form-select').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.02)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        });
        
        // Result page animations (if results are shown)
        <?php if ($eligibility_result): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const resultIcon = document.querySelector('.result-icon');
            const resultTitle = document.querySelector('.result-title');
            const resultSubtitle = document.querySelector('.result-subtitle');
            
            // Animate result appearance
            setTimeout(() => {
                if (resultIcon) {
                    resultIcon.style.transform = 'scale(1.1)';
                    setTimeout(() => {
                        resultIcon.style.transform = 'scale(1)';
                    }, 300);
                }
            }, 500);
            
            // Animate sections
            const sections = document.querySelectorAll('.factors-section, .recommendations-section');
            sections.forEach((section, index) => {
                section.style.opacity = '0';
                section.style.transform = 'translateY(30px)';
                setTimeout(() => {
                    section.style.transition = 'all 0.6s ease';
                    section.style.opacity = '1';
                    section.style.transform = 'translateY(0)';
                }, 800 + (index * 200));
            });
        });
        <?php endif; ?>
    </script>
    
    <?php include('includes/footer.php'); ?>
</body>
</html>