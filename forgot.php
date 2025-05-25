<?php
include('includes/db.php');
session_start();
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_type'] == 'admin') {
            header('Location: admin/index.php');
            exit();
        } else {
            header('Location: index.php');
            exit();
        }
    }

$message = '';
$show_password_fields = false; 
$username = '';
$verification_question = '';
$verification_answer = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['username'], $_POST['verification_question'], $_POST['verification_answer']) && !isset($_POST['new_password'])) {
        $username = trim($_POST['username']);
        $verification_question = $_POST['verification_question'];
        $verification_answer = trim($_POST['verification_answer']);

        $sql = "SELECT verification_answer FROM login WHERE username = ? AND verification_question = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $username, $verification_question);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 1) {
            $stmt->bind_result($correct_answer);
            $stmt->fetch();

            if (strtoupper($verification_answer) === strtoupper($correct_answer)) {
                $show_password_fields = true;
            } else {
                $message = "<div class='alert error'>Verification answer is incorrect.</div>";
            }
        } else {
            $message = "<div class='alert error'>User not found or verification question mismatch.</div>";
        }

    } elseif (isset($_POST['username'], $_POST['verification_question'], $_POST['verification_answer'], $_POST['new_password'], $_POST['confirm_password'])) {
        $username = trim($_POST['username']);
        $verification_question = $_POST['verification_question'];
        $verification_answer = trim($_POST['verification_answer']);
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if ($new_password !== $confirm_password) {
            $message = "<div class='alert error'>Passwords do not match.</div>";
            $show_password_fields = true; 
        } else {
            $sql = "SELECT verification_answer FROM login WHERE username = ? AND verification_question = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $username, $verification_question);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows == 1) {
                $stmt->bind_result($correct_answer);
                $stmt->fetch();

                if (strcasecmp($verification_answer, $correct_answer) === 0) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_sql = "UPDATE login SET password = ? WHERE username = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("ss", $hashed_password, $username);

                    if ($update_stmt->execute()) {
                        $message = "<div class='alert success'>Password reset successful! You can now <a href='login.php'>log in</a>.</div>";
                        $username = $verification_question = $verification_answer = '';
                    } else {
                        $message = "<div class='alert error'>Error updating password: " . $conn->error . "</div>";
                        $show_password_fields = true;
                    }
                } else {
                    $message = "<div class='alert error'>Verification answer is incorrect.</div>";
                }
            } else {
                $message = "<div class='alert error'>User not found or verification question mismatch.</div>";
            }
        }
    }
}

$verification_questions = [
    "What is your mother's maiden name?",
    "What was your first pet's name?",
    "What is your favorite color?",
    "What city were you born in?"
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pirate's Blood Bank - Forgot Password</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cardo:ital,wght@0,400;0,700;1,400&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Quicksand:wght@300..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">

</head>
<body>
    <main>
        <div class="forgot">
                <h2 class="site-title">Reset Password</h2>
                <form action="forgot.php" method="POST">
                    <input type="text" name="username" id="username" placeholder="Enter username" value="<?php echo htmlspecialchars($username); ?>" required />

                    <select name="verification_question" id="verification_question" required>
                        <option value="" disabled <?php echo $verification_question ? '' : 'selected'; ?>>Select a verification question</option>
                        <?php foreach ($verification_questions as $question): ?>
                            <option value="<?php echo htmlspecialchars($question); ?>" <?php echo ($question === $verification_question) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($question); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <input type="text" name="verification_answer" id="verification_answer" placeholder="Your answer" value="<?php echo htmlspecialchars($verification_answer); ?>" required />

                    <?php if ($show_password_fields): ?>
                        <input type="password" name="new_password" id="new_password" placeholder="New Password" required />
                        <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" required />
                    <?php endif; ?>

                    <button type="submit"><?php echo $show_password_fields ? 'Reset Password' : 'Verify'; ?></button>
                </form>

                <?php
                if ($message) {
                    echo $message;
                }
                ?>
                <p class="forgot-password"><a href="login.php">Go back</a></p>

        </div>
    </main>
</body>
</html