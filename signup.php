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

$verification_questions = [
    "What is your mother's maiden name?",
    "What was the name of your first pet?",
    "What is your favorite color?",
    "What city were you born in?"
];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $verification_question = $_POST['verification_question'] ?? '';
    $verification_answer = strtoupper(trim($_POST['verification_answer']));


    if ($password != $confirm_password) {
        echo "<div class='alert error'>Passwords do not match</div>";
    } elseif (!in_array($verification_question, $verification_questions)) {
        echo "<div class='alert error'>Invalid verification question selected.</div>";
    } elseif (empty($verification_answer)) {
        echo "<div class='alert error'>Please provide an answer to the verification question.</div>";
    } else {
        $sql = "SELECT username FROM login WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $_SESSION['message'] = "<div class='alert error'>This username is already taken. Please use a different username or <a href='login.php'>log in</a>.</div>";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO login (username, password, verification_question, verification_answer, user_type) VALUES (?, ?, ?, ?, 'user')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssss", $username, $hashed_password, $verification_question, $verification_answer);

            if ($stmt->execute()) {
                $_SESSION['message'] = "<div class='alert success'>Registration successful! You can now <a href='login.php'>login</a>.</div>";
                header('Location: signup.php');
                exit();
            } else {
                echo "<div class='alert error'>Error: " . $conn->error . "</div>";
            }
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pirate's Blood Bank - Register</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cardo:ital,wght@0,400;0,700;1,400&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Quicksand:wght@300..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">

</head>
<body>
    <main-signup>
        <div class="signup">
            <img src="assets/img/logo.svg" alt="Pirate's Blood Logo" />
                <form action="signup.php" method="POST">
                    <input type="text" name="username" id="username" placeholder="Enter username" required>

                    <input type="password" name="password" id="password" placeholder="Enter password" required>

                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm password" required>

                    <select name="verification_question" id="verification_question" required>
                        <option value="" disabled selected>Select a verification question</option>
                        <?php foreach ($verification_questions as $question): ?>
                        <option value="<?= htmlspecialchars($question) ?>"><?= htmlspecialchars($question) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <input type="text" name="verification_answer" id="verification_answer" placeholder="Your answer" crequired>

                    <button type="submit">Sign Up</button>
                                    <?php
                if (isset($_SESSION['message'])) {
                    echo $_SESSION['message'];
                    unset($_SESSION['message']); 
                }
                ?>
                </form>

                <p class="action">Already have an account? <a href="login.php">Log in</a></p>
        </div>
    </main-signup>
</body>
</html>