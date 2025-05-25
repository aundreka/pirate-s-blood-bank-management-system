<?php 
include('includes/db.php');
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Get current user data
$user_query = "SELECT * FROM login WHERE user_id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        // Verify current password
        $current_password = $_POST['current_password'];
        
        if (password_verify($current_password, $user_data['password'])) {
            $new_username = trim($_POST['username']);
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            $verification_question = $_POST['verification_question'];
            $verification_answer = trim($_POST['verification_answer']);
            
            // Validate inputs
            if (empty($new_username)) {
                $error = "Username cannot be empty.";
            } elseif (!empty($new_password) && $new_password !== $confirm_password) {
                $error = "New passwords do not match.";
            } elseif (!empty($new_password) && strlen($new_password) < 6) {
                $error = "New password must be at least 6 characters long.";
            } else {
                // Check if username is already taken (excluding current user)
                $check_username = "SELECT user_id FROM login WHERE username = ? AND user_id != ?";
                $stmt = $conn->prepare($check_username);
                $stmt->bind_param("si", $new_username, $user_id);
                $stmt->execute();
                
                if ($stmt->get_result()->num_rows > 0) {
                    $error = "Username is already taken.";
                } else {
                    // Update user data
                    if (!empty($new_password)) {
                        // Update with new password
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $update_query = "UPDATE login SET username = ?, password = ?, verification_question = ?, verification_answer = ? WHERE user_id = ?";
                        $stmt = $conn->prepare($update_query);
                        $stmt->bind_param("ssssi", $new_username, $hashed_password, $verification_question, $verification_answer, $user_id);
                    } else {
                        // Update without changing password
                        $update_query = "UPDATE login SET username = ?, verification_question = ?, verification_answer = ? WHERE user_id = ?";
                        $stmt = $conn->prepare($update_query);
                        $stmt->bind_param("sssi", $new_username, $verification_question, $verification_answer, $user_id);
                    }
                    
                    if ($stmt->execute()) {
                        $_SESSION['username'] = $new_username;
                        $message = "Profile updated successfully!";
                        // Refresh user data
                        $stmt = $conn->prepare($user_query);
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $user_data = $stmt->get_result()->fetch_assoc();
                    } else {
                        $error = "Error updating profile. Please try again.";
                    }
                }
            }
        } else {
            $error = "Current password is incorrect.";
        }
    }
    
    if (isset($_POST['appeal_admin'])) {
        // Check if user already has a pending appeal
        $check_appeal = "SELECT * FROM admin_appeals WHERE user_id = ? AND status = 'pending'";
        $stmt = $conn->prepare($check_appeal);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            $error = "You already have a pending admin appeal.";
        } else {
            $appeal_reason = trim($_POST['appeal_reason']);
            
            if (empty($appeal_reason)) {
                $error = "Please provide a reason for your admin appeal.";
            } else {
                // Create admin_appeals table if it doesn't exist
                $create_table = "CREATE TABLE IF NOT EXISTS admin_appeals (
                    appeal_id INT(11) AUTO_INCREMENT PRIMARY KEY,
                    user_id INT(11) NOT NULL,
                    reason TEXT NOT NULL,
                    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    processed_at TIMESTAMP NULL,
                    processed_by INT(11) NULL,
                    admin_notes TEXT NULL,
                    KEY user_id_idx (user_id),
                    KEY processed_by_idx (processed_by),
                    KEY status_idx (status)
                )";
                
                if ($conn->query($create_table)) {
                    // Insert appeal
                    $insert_appeal = "INSERT INTO admin_appeals (user_id, reason) VALUES (?, ?)";
                    $stmt = $conn->prepare($insert_appeal);
                    $stmt->bind_param("is", $user_id, $appeal_reason);
                    
                    if ($stmt->execute()) {
                        $message = "Admin appeal submitted successfully! You will be notified once it's reviewed.";
                    } else {
                        $error = "Error submitting appeal. Please try again.";
                    }
                } else {
                    $error = "Database error. Please contact support.";
                }
            }
        }
    }
}

// Create admin_appeals table if it doesn't exist (moved to top to ensure it exists)
$create_table = "CREATE TABLE IF NOT EXISTS admin_appeals (
    appeal_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    processed_by INT(11) NULL,
    admin_notes TEXT NULL,
    FOREIGN KEY (user_id) REFERENCES login(user_id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES login(user_id) ON DELETE SET NULL
)";
$conn->query($create_table);

// Check if user has pending appeals
$pending_appeal_query = "SELECT * FROM admin_appeals WHERE user_id = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 1";
$stmt = $conn->prepare($pending_appeal_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pending_appeal = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - Pirate's Blood Bank</title>
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

        /* Settings Header Section */
        .settings-header {
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.9), rgba(176, 42, 55, 0.9));
            color: white;
            padding: 60px 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .settings-header::before {
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

        .settings-header-content {
            position: relative;
            z-index: 10;
        }

        .settings-header h1 {
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 15px;
            font-family: 'Poppins', sans-serif;
                            color: white;

        }

        .settings-header p {
            font-size: 18px;
            opacity: 0.95;
            margin-bottom: 30px;
        }

        .back-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 12px 25px;
            border: 2px solid white;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 40px;
        }

        .back-btn:hover {
            background: white;
            color: #dc3545;
            transform: translateY(-2px);
        }

        /* Settings Content */
        .settings-content {
            background: white;
            padding: 60px 40px;
            margin: -30px 40px 0;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            position: relative;
            z-index: 5;
        }

        /* Messages */
        .message, .error {
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .message {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Current Account Info */
        .current-info {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 40px;
            border-left: 5px solid #2196f3;
        }

        .current-info h3 {
            color: #1976d2;
            margin-bottom: 15px;
            font-size: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .current-info p {
            color: #424242;
            margin-bottom: 8px;
            font-size: 15px;
        }

        /* Form Sections */
        .form-section {
            background: linear-gradient(135deg, #f8f9fa, #ffffff);
            padding: 40px;
            border-radius: 15px;
            margin-bottom: 30px;
            border-left: 5px solid #dc3545;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .form-section h2 {
            color: #495057;
            margin-bottom: 25px;
            font-size: 24px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .form-section h2 i {
            color: #dc3545;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 16px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
            background: white;
        }

        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #dc3545;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
            transform: translateY(-2px);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }

        .password-note {
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
            font-style: italic;
        }

        /* Buttons */
        .btn {
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            font-family: 'Poppins', sans-serif;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(220, 53, 69, 0.3);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #495057);
        }

        .btn-secondary:hover {
            box-shadow: 0 10px 25px rgba(108, 117, 125, 0.3);
        }

        /* Admin Appeal Section */
        .admin-appeal {
            border-left-color: #f39c12;
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
        }

        .admin-appeal h2 {
            color: #856404;
        }

        .admin-appeal h2 i {
            color: #f39c12;
        }

        .pending-appeal {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            border: 2px solid #ffeaa7;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
        }

        .pending-appeal h4 {
            color: #856404;
            margin-bottom: 15px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .pending-appeal p {
            color: #856404;
            margin-bottom: 8px;
        }

        /* Admin Status Section */
        .admin-status {
            border-left-color: #28a745;
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
        }

        .admin-status h2 {
            color: #155724;
        }

        .admin-status h2 i {
            color: #28a745;
        }

        .admin-badge {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            color: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
        }

        .admin-badge h3 {
            margin-bottom: 10px;
            font-size: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        /* Security Tips */
        .security-tips {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            padding: 25px;
            border-radius: 15px;
            border-left: 5px solid #ffc107;
        }

        .security-tips h4 {
            color: #856404;
            margin-bottom: 15px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .security-tips ul {
            color: #856404;
            margin-left: 20px;
            line-height: 1.8;
        }

        .security-tips li {
            margin-bottom: 8px;
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

            .settings-header {
                padding: 40px 20px;
            }

            .settings-header h1 {
                font-size: 36px;
                color: white;
            }

            .settings-content {
                margin: -20px 20px 0;
                padding: 40px 20px;
            }

            .form-section {
                padding: 30px 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .settings-header h1 {
                font-size: 28px;
            }

            .form-section h2 {
                font-size: 20px;
                flex-direction: column;
                gap: 8px;
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
        <!-- Settings Header -->
        <section class="settings-header">
            <div class="settings-header-content">
                <a href="profile.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                    Back to Profile
                </a>
                <h1><i class="fas fa-cogs"></i> Profile Settings</h1>
                <p>Manage your account details, security settings, and preferences</p>
            </div>
        </section>

        <!-- Settings Content -->
        <section class="settings-content">
            <?php if ($message): ?>
                <div class="message">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Current Information -->
            <div class="current-info">
                <h3><i class="fas fa-info-circle"></i> Current Account Information</h3>
                <p><strong>Username:</strong> <?php echo htmlspecialchars($user_data['username']); ?></p>
                <p><strong>User Type:</strong> <?php echo ucfirst($user_data['user_type']); ?></p>
                <p><strong>Account Created:</strong> <?php echo date('M d, Y g:i A', strtotime($user_data['created_at'])); ?></p>
            </div>

            <!-- Update Profile Section -->
            <div class="form-section">
                <h2><i class="fas fa-user-edit"></i> Update Profile Information</h2>
                <form method="POST">
                    <div class="form-group">
                        <label for="current_password">Current Password (Required for any changes):</label>
                        <input type="password" id="current_password" name="current_password" required placeholder="Enter your current password">
                    </div>

                    <div class="form-group">
                        <label for="username">Username:</label>
                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user_data['username']); ?>" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password">New Password:</label>
                            <input type="password" id="new_password" name="new_password" placeholder="Enter new password">
                            <div class="password-note">Leave blank to keep current password</div>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password:</label>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="verification_question">Security Question:</label>
                        <select id="verification_question" name="verification_question" required>
                            <option value="What is your mother's maiden name?" <?php echo ($user_data['verification_question'] == "What is your mother's maiden name?") ? 'selected' : ''; ?>>What is your mother's maiden name?</option>
                            <option value="What was the name of your first pet?" <?php echo ($user_data['verification_question'] == "What was the name of your first pet?") ? 'selected' : ''; ?>>What was the name of your first pet?</option>
                            <option value="What is your favorite color?" <?php echo ($user_data['verification_question'] == "What is your favorite color?") ? 'selected' : ''; ?>>What is your favorite color?</option>
                            <option value="What city were you born in?" <?php echo ($user_data['verification_question'] == "What city were you born in?") ? 'selected' : ''; ?>>What city were you born in?</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="verification_answer">Security Answer:</label>
                        <input type="text" id="verification_answer" name="verification_answer" value="<?php echo htmlspecialchars($user_data['verification_answer']); ?>" required>
                    </div>

                    <button type="submit" name="update_profile" class="btn">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </form>
            </div>

            <!-- Admin Appeal Section -->
            <?php if ($user_data['user_type'] == 'user'): ?>
                <div class="form-section admin-appeal">
                    <h2><i class="fas fa-crown"></i> Request Admin Status</h2>
                    
                    <?php if ($pending_appeal): ?>
                        <div class="pending-appeal">
                            <h4><i class="fas fa-hourglass-half"></i> Pending Admin Appeal</h4>
                            <p><strong>Submitted:</strong> <?php echo date('M d, Y g:i A', strtotime($pending_appeal['created_at'])); ?></p>
                            <p><strong>Reason:</strong> <?php echo htmlspecialchars($pending_appeal['reason']); ?></p>
                            <p><em><i class="fas fa-info-circle"></i> Your appeal is currently being reviewed by administrators. You will be notified once a decision is made.</em></p>
                        </div>
                    <?php else: ?>
                        <p style="margin-bottom: 25px; color: #6c757d; line-height: 1.6;">
                            If you believe you should have administrative privileges to help manage the blood donation system, 
                            you can submit an appeal to be reviewed by current administrators.
                        </p>
                        
                        <form method="POST">
                            <div class="form-group">
                                <label for="appeal_reason">Reason for Admin Request:</label>
                                <textarea id="appeal_reason" name="appeal_reason" placeholder="Please explain why you should be granted admin status. Include your qualifications, experience, and how you plan to contribute to the blood donation system..." required></textarea>
                            </div>
                            
                            <button type="submit" name="appeal_admin" class="btn btn-secondary">
                                <i class="fas fa-paper-plane"></i> Submit Admin Appeal
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="form-section admin-status">
                    <h2><i class="fas fa-shield-alt"></i> Admin Status</h2>
                    <div class="admin-badge">
                        <h3><i class="fas fa-check-circle"></i> Administrator Account</h3>
                        <p>You have administrative privileges in this system. You can manage users, approve donations, handle blood requests, and review admin appeals through the admin dashboard.</p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Account Security Information -->
            <div class="form-section">
                <h2><i class="fas fa-lock"></i> Account Security</h2>
                <div class="security-tips">
                    <h4><i class="fas fa-lightbulb"></i> Security Best Practices</h4>
                    <ul>
                        <li>Use a strong password with at least 8 characters</li>
                        <li>Include uppercase, lowercase, numbers, and special characters</li>
                        <li>Never share your login credentials with anyone</li>
                        <li>Keep your security question answer private and memorable</li>
                        <li>Always log out when using shared or public computers</li>
                        <li>Update your password regularly for better security</li>
                    </ul>
                </div>
            </div>
        </section>
    </div>
</main>

<script>
    // Password confirmation validation
    document.getElementById('confirm_password').addEventListener('input', function() {
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = this.value;
        
        if (newPassword && confirmPassword && newPassword !== confirmPassword) {
            this.style.borderColor = '#dc3545';
            this.style.boxShadow = '0 0 0 3px rgba(220, 53, 69, 0.1)';
        } else {
            this.style.borderColor = '#e9ecef';
            this.style.boxShadow = 'none';
        }
    });

    // Form validation
    document.querySelector('form').addEventListener('submit', function(e) {
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        if (newPassword && newPassword !== confirmPassword) {
            e.preventDefault();
            alert('New passwords do not match!');
            return false;
        }
        
        if (newPassword && newPassword.length < 6) {
            e.preventDefault();
            alert('New password must be at least 6 characters long!');
            return false;
        }
    });

    // Add input focus animations
    document.querySelectorAll('input, select, textarea').forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.style.transform = 'scale(1.02)';
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.style.transform = 'scale(1)';
        });
    });
</script>

</body>
<?php include('includes/footer.php'); ?>
</html>