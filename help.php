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

$contact_submitted = false;
$contact_message = '';

// Handle contact form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_contact'])) {
    $user_id = $_SESSION['user_id'];
    $subject = trim($_POST['subject']);
    $category = $_POST['category'];
    $priority = $_POST['priority'];
    $message = trim($_POST['message']);
    $phone = trim($_POST['phone']);
    
    // Basic validation
    if (!empty($subject) && !empty($message) && !empty($category)) {
        try {
            // Insert into support tickets table (you may need to create this table)
            $stmt = $pdo->prepare("INSERT INTO support_tickets (user_id, subject, category, priority, message, phone, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'open', NOW())");
            $stmt->execute([$user_id, $subject, $category, $priority, $message, $phone]);
            
            $contact_submitted = true;
            $contact_message = "Your support ticket has been submitted successfully! We'll respond within 24-48 hours.";
        } catch (PDOException $e) {
            $contact_message = "There was an error submitting your request. Please try again later.";
        }
    } else {
        $contact_message = "Please fill in all required fields.";
    }
}

// FAQ data
$faqs = [
    'general' => [
        [
            'question' => 'What are the basic requirements to donate blood?',
            'answer' => 'You must be between 17-65 years old, weigh at least 50kg (males) or 45kg (females), be in good health, and pass our medical screening. You should also have eaten a meal and be well-hydrated before donating.'
        ],
        [
            'question' => 'How often can I donate blood?',
            'answer' => 'You can donate whole blood every 8 weeks (56 days). This waiting period allows your body to replenish the donated blood cells and maintain healthy iron levels.'
        ],
        [
            'question' => 'How long does the donation process take?',
            'answer' => 'The entire process typically takes 45-60 minutes, including registration, medical screening, the actual donation (8-10 minutes), and recovery time. The actual blood collection only takes about 8-10 minutes.'
        ],
        [
            'question' => 'Is blood donation safe?',
            'answer' => 'Yes, blood donation is very safe. We use sterile, single-use equipment for each donor. Our trained medical staff follow strict safety protocols, and all equipment is disposed of safely after use.'
        ]
    ],
    'eligibility' => [
        [
            'question' => 'Can I donate if I\'m taking medication?',
            'answer' => 'It depends on the medication. Most common medications like vitamins, birth control, and blood pressure medications don\'t disqualify you. However, antibiotics, blood thinners, and certain other medications may temporarily prevent donation. Please consult with our medical staff.'
        ],
        [
            'question' => 'Can I donate if I have a tattoo or piercing?',
            'answer' => 'You can donate if your tattoo or piercing was done more than 6 months ago at a licensed facility. If it was done within the last 6 months, you\'ll need to wait until the 6-month period has passed.'
        ],
        [
            'question' => 'Can I donate if I have diabetes?',
            'answer' => 'People with well-controlled diabetes who don\'t use insulin can usually donate. However, if you use insulin or have poorly controlled diabetes, you may not be eligible. Our medical team will assess your individual situation.'
        ],
        [
            'question' => 'What if I\'ve traveled recently?',
            'answer' => 'Recent travel to certain countries, especially those with malaria or other infectious diseases, may require a waiting period before you can donate. The duration depends on the specific countries visited.'
        ]
    ],
    'process' => [
        [
            'question' => 'What should I do before donating?',
            'answer' => 'Eat a healthy meal 2-3 hours before donating, drink plenty of water, get a good night\'s sleep, and bring a valid ID. Avoid alcohol for 24 hours before donation and avoid fatty foods on the day of donation.'
        ],
        [
            'question' => 'What happens during the medical screening?',
            'answer' => 'We\'ll check your temperature, blood pressure, pulse, and hemoglobin levels. You\'ll also complete a health questionnaire about your medical history, medications, and recent activities. This ensures your safety and the safety of blood recipients.'
        ],
        [
            'question' => 'What should I do after donating?',
            'answer' => 'Rest for 15-20 minutes, drink plenty of fluids, eat a snack, and avoid heavy lifting or strenuous exercise for 24 hours. Keep the bandage on for at least 4 hours and avoid getting it wet.'
        ],
        [
            'question' => 'Will I know my blood type after donating?',
            'answer' => 'Yes! We\'ll test your blood and inform you of your blood type, usually within a few days. This information will also be available in your donor portal.'
        ]
    ],
    'health' => [
        [
            'question' => 'Will donating blood affect my health?',
            'answer' => 'For healthy individuals, blood donation has minimal impact on health and may even provide health benefits like reducing iron levels and stimulating new blood cell production. Your body replaces the donated plasma within 24 hours and red blood cells within 4-6 weeks.'
        ],
        [
            'question' => 'What are the side effects of donating blood?',
            'answer' => 'Most people experience no side effects. Some may feel lightheaded, dizzy, or fatigued immediately after donation. Rarely, you might experience bruising at the needle site or feel faint. These effects are usually mild and temporary.'
        ],
        [
            'question' => 'Can I donate if I\'m feeling unwell?',
            'answer' => 'No, you should not donate if you\'re feeling unwell, have a cold, flu, or any infection. Wait until you\'re completely recovered and feeling well before scheduling a donation.'
        ],
        [
            'question' => 'What if I have low iron or anemia?',
            'answer' => 'You cannot donate if your hemoglobin levels are below the minimum requirements (12.0 g/dL for females, 13.0 g/dL for males). If you have anemia, focus on iron-rich foods and consult your doctor before attempting to donate.'
        ]
    ],
    'appointment' => [
        [
            'question' => 'How do I schedule an appointment?',
            'answer' => 'You can schedule an appointment through our online portal, by calling our helpline, or by visiting one of our donation centers. We recommend scheduling in advance to ensure your preferred time slot.'
        ],
        [
            'question' => 'Can I walk in without an appointment?',
            'answer' => 'While we accept walk-ins when possible, having an appointment ensures shorter wait times and guaranteed availability. Walk-in availability depends on our current schedule and staffing.'
        ],
        [
            'question' => 'What if I need to cancel or reschedule?',
            'answer' => 'You can cancel or reschedule your appointment up to 2 hours before your scheduled time through our online portal or by calling us. We appreciate as much notice as possible.'
        ],
        [
            'question' => 'What should I bring to my appointment?',
            'answer' => 'Bring a valid government-issued photo ID, a list of any medications you\'re taking, and your donor card if you have one. Make sure to have eaten and hydrated well before your appointment.'
        ]
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pirate's Blood Bank - Help & Support</title>
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
        
        .help-container {
            max-width: 1200px;
            margin: 0 auto;
            margin-top: 20px;
            margin-bottom: 40px;
        }
        
        .help-header {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            border-radius: 20px;
            padding: 60px 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
            margin-bottom: 40px;
        }
        
        .help-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #dc3545, #28a745, #17a2b8);
            border-radius: 20px 20px 0 0;
        }
        
        .help-icon {
            font-size: 60px;
            color: #dc3545;
            margin-bottom: 20px;
        }
        
        .help-title {
            font-size: 32px;
            font-weight: 700;
            color: #495057;
            margin-bottom: 15px;
            font-family: 'Poppins', sans-serif;
        }
        
        .help-subtitle {
            font-size: 18px;
            color: #6c757d;
            line-height: 1.6;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .quick-action-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border: 1px solid #f0f0f0;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
        }
        
        .quick-action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
            text-decoration: none;
            color: inherit;
        }
        
        .quick-action-icon {
            font-size: 40px;
            margin-bottom: 15px;
            color: #dc3545;
        }
        
        .quick-action-title {
            font-size: 18px;
            font-weight: 700;
            color: #495057;
            margin-bottom: 10px;
        }
        
        .quick-action-desc {
            font-size: 14px;
            color: #6c757d;
            line-height: 1.5;
        }
        
        .content-tabs {
            background: white;
            border-radius: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-bottom: 40px;
        }
        
        .tab-navigation {
            display: flex;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            overflow-x: auto;
        }
        
        .tab-btn {
            padding: 20px 25px;
            background: none;
            border: none;
            font-size: 14px;
            font-weight: 600;
            color: #6c757d;
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .tab-btn:hover {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        
        .tab-btn.active {
            background: #dc3545;
            color: white;
        }
        
        .tab-content {
            padding: 40px;
        }
        
        .tab-pane {
            display: none;
        }
        
        .tab-pane.active {
            display: block;
        }
        
        .faq-section {
            margin-bottom: 30px;
        }
        
        .faq-item {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            margin-bottom: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .faq-item:hover {
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .faq-question {
            background: #f8f9fa;
            padding: 20px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            color: #495057;
            transition: all 0.3s ease;
        }
        
        .faq-question:hover {
            background: rgba(220, 53, 69, 0.05);
            color: #dc3545;
        }
        
        .faq-question.active {
            background: #dc3545;
            color: white;
        }
        
        .faq-toggle {
            font-size: 18px;
            transition: transform 0.3s ease;
        }
        
        .faq-toggle.active {
            transform: rotate(180deg);
        }
        
        .faq-answer {
            padding: 0 20px;
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s ease;
            background: white;
        }
        
        .faq-answer.active {
            padding: 20px;
            max-height: 200px;
        }
        
        .faq-answer p {
            margin: 0;
            color: #495057;
            line-height: 1.6;
        }
        
        .contact-section {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 40px;
        }
        
        .contact-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .contact-title {
            font-size: 24px;
            font-weight: 700;
            color: #495057;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .contact-subtitle {
            color: #6c757d;
            font-size: 16px;
        }
        
        .contact-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .contact-method {
            text-align: center;
            padding: 25px;
            background: #f8f9fa;
            border-radius: 15px;
            transition: all 0.3s ease;
        }
        
        .contact-method:hover {
            background: rgba(220, 53, 69, 0.05);
            transform: translateY(-3px);
        }
        
        .contact-method-icon {
            font-size: 30px;
            color: #dc3545;
            margin-bottom: 15px;
        }
        
        .contact-method-title {
            font-size: 16px;
            font-weight: 700;
            color: #495057;
            margin-bottom: 8px;
        }
        
        .contact-method-info {
            color: #6c757d;
            font-size: 14px;
        }
        
        .contact-form {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 15px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
            box-sizing: border-box;
            font-family: inherit;
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 120px;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #dc3545;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
        }
        
        .submit-btn {
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
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
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            color: #155724;
            border: 1px solid rgba(40, 167, 69, 0.2);
        }
        
        .alert-error {
            background: rgba(220, 53, 69, 0.1);
            color: #721c24;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }
        
        .emergency-notice {
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));
            border: 2px solid rgba(220, 53, 69, 0.2);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 40px;
            text-align: center;
        }
        
        .emergency-notice h4 {
            color: #721c24;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .emergency-notice p {
            color: #721c24;
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
            
            .help-header {
                padding: 40px 25px;
                border-radius: 15px;
            }
            
            .help-title {
                font-size: 26px;
            }
            
            .help-icon {
                font-size: 50px;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .tab-navigation {
                flex-wrap: wrap;
            }
            
            .tab-btn {
                padding: 15px 20px;
                font-size: 13px;
            }
            
            .tab-content {
                padding: 25px;
            }
            
            .contact-methods {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }
        
        @media (max-width: 480px) {
            main {
                padding: 15px 10px;
            }
            
            .help-header {
                padding: 30px 20px;
                border-radius: 10px;
            }
            
            .help-title {
                font-size: 22px;
            }
            
            .help-icon {
                font-size: 40px;
            }
            
            .quick-action-card {
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
        <div class="help-container">
            <!-- Header Section -->
            <div class="help-header">
                <div class="help-icon">
                    <i class="fas fa-question-circle"></i>
                </div>
                <h1 class="help-title">Help & Support Center</h1>
                <p class="help-subtitle">
                    Find answers to frequently asked questions, get help with donations, 
                    or contact our support team for personalized assistance.
                </p>
            </div>
            
            <!-- Emergency Notice -->
            <div class="emergency-notice">
                <h4>
                    <i class="fas fa-exclamation-triangle"></i>
                    Medical Emergency?
                </h4>
                <p>
                    If you're experiencing a medical emergency, please call <strong>911</strong> immediately. 
                    For urgent blood donation inquiries, call our 24/7 hotline at <strong>(555) 123-BLOOD</strong>.
                </p>
            </div>
            
            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="eligibility.php" class="quick-action-card">
                    <div class="quick-action-icon">
                        <i class="fas fa-heartbeat"></i>
                    </div>
                    <div class="quick-action-title">Check Eligibility</div>
                    <div class="quick-action-desc">Take our quick assessment to see if you're eligible to donate blood</div>
                </a>
                
                <a href="appointments.php" class="quick-action-card">
                    <div class="quick-action-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="quick-action-title">My Appointments</div>
                    <div class="quick-action-desc">View, schedule, or manage your donation appointments</div>
                </a>
                
                <a href="donate.php" class="quick-action-card">
                    <div class="quick-action-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <div class="quick-action-title">Donate Blood</div>
                    <div class="quick-action-desc">Start your blood donation application process</div>
                </a>
                
                <a href="#contact" class="quick-action-card">
                    <div class="quick-action-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <div class="quick-action-title">Contact Support</div>
                    <div class="quick-action-desc">Get help from our customer support team</div>
                </a>
            </div>
            
            <!-- FAQ Tabs -->
            <div class="content-tabs">
                <div class="tab-navigation">
                    <button class="tab-btn active" data-tab="general">
                        <i class="fas fa-info-circle"></i>
                        General
                    </button>
                    <button class="tab-btn" data-tab="eligibility">
                        <i class="fas fa-user-check"></i>
                        Eligibility
                    </button>
                    <button class="tab-btn" data-tab="process">
                        <i class="fas fa-clipboard-list"></i>
                        Process
                    </button>
                    <button class="tab-btn" data-tab="health">
                        <i class="fas fa-medical-kit"></i>
                        Health & Safety
                    </button>
                    <button class="tab-btn" data-tab="appointment">
                        <i class="fas fa-calendar"></i>
                        Appointments
                    </button>
                </div>
                
                <div class="tab-content">
                    <?php foreach ($faqs as $category => $questions): ?>
                    <div class="tab-pane <?php echo $category === 'general' ? 'active' : ''; ?>" id="<?php echo $category; ?>">
                        <div class="faq-section">
                            <?php foreach ($questions as $index => $faq): ?>
                            <div class="faq-item">
                                <div class="faq-question" onclick="toggleFAQ('<?php echo $category . '_' . $index; ?>')">
                                    <span><?php echo htmlspecialchars($faq['question']); ?></span>
                                    <i class="fas fa-chevron-down faq-toggle" id="toggle_<?php echo $category . '_' . $index; ?>"></i>
                                </div>
                                <div class="faq-answer" id="answer_<?php echo $category . '_' . $index; ?>">
                                    <p><?php echo htmlspecialchars($faq['answer']); ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Contact Section -->
            <div class="contact-section" id="contact">
                <div class="contact-header">
                    <h2 class="contact-title">
                        <i class="fas fa-envelope"></i>
                        Contact Support Team
                    </h2>
                    <p class="contact-subtitle">
                        Can't find what you're looking for? Our support team is here to help!
                    </p>
                </div>
                
                <!-- Contact Methods -->
                <div class="contact-methods">
                    <div class="contact-method">
                        <div class="contact-method-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div class="contact-method-title">Phone Support</div>
                        <div class="contact-method-info">
                            (555) 123-BLOOD<br>
                            Mon-Fri: 8AM-8PM<br>
                            Sat-Sun: 9AM-5PM
                        </div>
                    </div>
                    
                    <div class="contact-method">
                        <div class="contact-method-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="contact-method-title">Email Support</div>
                        <div class="contact-method-info">
                            support@piratesblood.com<br>
                            Response within 24 hours<br>
                            Mon-Sun: Available
                        </div>
                    </div>
                    
                    <div class="contact-method">
                        <div class="contact-method-icon">
                            <i class="fas fa-comments"></i>
                        </div>
                        <div class="contact-method-title">Live Chat</div>
                        <div class="contact-method-info">
                            Available on website<br>
                            Mon-Fri: 9AM-6PM<br>
                            Average wait: 2 minutes
                        </div>
                    </div>
                </div>
                
                <!-- Contact Form -->
                <div class="contact-form">
                    <?php if ($contact_message): ?>
                    <div class="alert <?php echo $contact_submitted ? 'alert-success' : 'alert-error'; ?>">
                        <i class="fas <?php echo $contact_submitted ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                        <?php echo htmlspecialchars($contact_message); ?>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" id="contactForm">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="subject">Subject *</label>
                                <input type="text" id="subject" name="subject" class="form-input" required maxlength="200" placeholder="Brief description of your issue">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="category">Category *</label>
                                <select id="category" name="category" class="form-select" required>
                                    <option value="">Select Category</option>
                                    <option value="donation_inquiry">Donation Inquiry</option>
                                    <option value="appointment">Appointment Issues</option>
                                    <option value="eligibility">Eligibility Questions</option>
                                    <option value="medical_concern">Medical Concerns</option>
                                    <option value="technical_support">Technical Support</option>
                                    <option value="account_issues">Account Issues</option>
                                    <option value="feedback">Feedback/Suggestions</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="priority">Priority Level</label>
                                <select id="priority" name="priority" class="form-select">
                                    <option value="low">Low - General inquiry</option>
                                    <option value="medium" selected>Medium - Need assistance</option>
                                    <option value="high">High - Urgent issue</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="phone">Phone Number (Optional)</label>
                                <input type="tel" id="phone" name="phone" class="form-input" placeholder="+63 912 345 6789">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="message">Message *</label>
                            <textarea id="message" name="message" class="form-textarea" required placeholder="Please describe your question or issue in detail. Include any relevant information that might help us assist you better." maxlength="1000"></textarea>
                            <div class="character-count">
                                <span id="charCount">0</span>/1000 characters
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" name="submit_contact" class="submit-btn">
                                <i class="fas fa-paper-plane"></i>
                                Send Message
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Additional Resources -->
            <div class="additional-resources">
                <div class="resources-header">
                    <h3>Additional Resources</h3>
                    <p>Helpful links and information for blood donors</p>
                </div>
                
                <div class="resources-grid">
                    <div class="resource-card">
                        <div class="resource-icon">
                            <i class="fas fa-download"></i>
                        </div>
                        <div class="resource-content">
                            <h4>Donation Guidelines</h4>
                            <p>Download our comprehensive donor guidelines and preparation checklist.</p>
                            <a href="#" class="resource-link">Download PDF <i class="fas fa-external-link-alt"></i></a>
                        </div>
                    </div>
                    
                    <div class="resource-card">
                        <div class="resource-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="resource-content">
                            <h4>Donation Centers</h4>
                            <p>Find blood donation centers and mobile drives near your location.</p>
                            <a href="locations.php" class="resource-link">Find Locations <i class="fas fa-external-link-alt"></i></a>
                        </div>
                    </div>
                    
                    <div class="resource-card">
                        <div class="resource-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <div class="resource-content">
                            <h4>Mobile App</h4>
                            <p>Download our mobile app for easy appointment scheduling and tracking.</p>
                            <a href="#" class="resource-link">Get App <i class="fas fa-external-link-alt"></i></a>
                        </div>
                    </div>
                    
                    <div class="resource-card">
                        <div class="resource-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="resource-content">
                            <h4>Community Forum</h4>
                            <p>Connect with other donors and share experiences in our community forum.</p>
                            <a href="#" class="resource-link">Join Forum <i class="fas fa-external-link-alt"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <br><br>
    </main>
    
    <style>
        .character-count {
            text-align: right;
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .additional-resources {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 40px;
        }
        
        .resources-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .resources-header h3 {
            font-size: 24px;
            font-weight: 700;
            color: #495057;
            margin-bottom: 10px;
        }
        
        .resources-header p {
            color: #6c757d;
            font-size: 16px;
            margin: 0;
        }
        
        .resources-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
        }
        
        .resource-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }
        
        .resource-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            background: white;
        }
        
        .resource-icon {
            font-size: 30px;
            color: #dc3545;
            margin-bottom: 15px;
        }
        
        .resource-content h4 {
            font-size: 16px;
            font-weight: 700;
            color: #495057;
            margin-bottom: 8px;
        }
        
        .resource-content p {
            color: #6c757d;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 15px;
        }
        
        .resource-link {
            color: #dc3545;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }
        
        .resource-link:hover {
            color: #b02a37;
            text-decoration: none;
            transform: translateX(3px);
        }
        
        @media (max-width: 768px) {
            .resources-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .additional-resources {
                padding: 25px;
            }
        }
    </style>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab functionality
            const tabButtons = document.querySelectorAll('.tab-btn');
            const tabPanes = document.querySelectorAll('.tab-pane');
            
            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const targetTab = this.getAttribute('data-tab');
                    
                    // Remove active classes
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabPanes.forEach(pane => pane.classList.remove('active'));
                    
                    // Add active classes
                    this.classList.add('active');
                    document.getElementById(targetTab).classList.add('active');
                });
            });
            
            // Character counter for message textarea
            const messageTextarea = document.getElementById('message');
            const charCount = document.getElementById('charCount');
            
            if (messageTextarea && charCount) {
                messageTextarea.addEventListener('input', function() {
                    const currentLength = this.value.length;
                    charCount.textContent = currentLength;
                    
                    if (currentLength > 900) {
                        charCount.style.color = '#dc3545';
                    } else {
                        charCount.style.color = '#6c757d';
                    }
                });
            }
            
            // Form validation
            const contactForm = document.getElementById('contactForm');
            if (contactForm) {
                contactForm.addEventListener('submit', function(e) {
                    const subject = document.getElementById('subject').value.trim();
                    const category = document.getElementById('category').value;
                    const message = document.getElementById('message').value.trim();
                    
                    if (!subject || !category || !message) {
                        e.preventDefault();
                        alert('Please fill in all required fields.');
                        return;
                    }
                    
                    if (message.length < 10) {
                        e.preventDefault();
                        alert('Please provide a more detailed message (at least 10 characters).');
                        return;
                    }
                    
                    // Show loading state
                    const submitBtn = contactForm.querySelector('.submit-btn');
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
                    submitBtn.disabled = true;
                });
            }
            
            // Phone number formatting
            const phoneInput = document.getElementById('phone');
            if (phoneInput) {
                phoneInput.addEventListener('input', function() {
                    let value = this.value.replace(/\D/g, '');
                    if (value.length >= 6) {
                        value = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
                    } else if (value.length >= 3) {
                        value = value.replace(/(\d{3})(\d{0,3})/, '($1) $2');
                    }
                    this.value = value;
                });
            }
            
            // Smooth scrolling for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
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
            
            // Animation for cards on scroll
            const cards = document.querySelectorAll('.quick-action-card, .resource-card');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, { threshold: 0.1 });
            
            cards.forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                card.style.transition = 'all 0.6s ease';
                observer.observe(card);
            });
            
            console.log('Help & Support page initialized successfully');
        });
        
        // FAQ toggle functionality
        function toggleFAQ(faqId) {
            const question = document.querySelector(`#answer_${faqId}`).previousElementSibling;
            const answer = document.getElementById(`answer_${faqId}`);
            const toggle = document.getElementById(`toggle_${faqId}`);
            
            // Close all other FAQs in the same section
            const currentSection = answer.closest('.tab-pane');
            const allFAQs = currentSection.querySelectorAll('.faq-item');
            
            allFAQs.forEach(faq => {
                const faqAnswer = faq.querySelector('.faq-answer');
                const faqQuestion = faq.querySelector('.faq-question');
                const faqToggle = faq.querySelector('.faq-toggle');
                
                if (faqAnswer !== answer) {
                    faqAnswer.classList.remove('active');
                    faqQuestion.classList.remove('active');
                    faqToggle.classList.remove('active');
                }
            });
            
            // Toggle current FAQ
            answer.classList.toggle('active');
            question.classList.toggle('active');
            toggle.classList.toggle('active');
        }
        
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-20px)';
                    setTimeout(() => {
                        alert.remove();
                    }, 300);
                }, 5000);
            });
        });
    </script>
    
    <?php include('includes/footer.php'); ?>
    </body>
    </html>