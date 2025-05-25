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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM login WHERE username = '$username'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_type'] = $user['user_type'];
            if ($user['user_type'] == 'admin') {
                header('Location: admin/index.php');
                exit();
            } else {
                header('Location: index.php');
                exit();
            }
        } else {
            $_SESSION['message'] = "<div class='alert error'>Your password is incorrect.</div>";
        }
    } else {
        $_SESSION['message'] = "<div class='alert error'>User not found.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pirate's Blood Bank - Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cardo:ital,wght@0,400;0,700;1,400&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Quicksand:wght@300..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">

</head>
<body>
    <main>
        <div class="slideshow" id="slideshow">
            <div class="slides active">
                 <img src="assets/img/login.svg" alt="Slide 1" />
                 <div class="caption">REAL-TIME BLOOD. REAL-LIFE IMPACT.</div>
             </div>
        </div>

        <div class="login-form">
            <div class="logo">
                <img src="assets/img/logo.svg" alt="Pirate's Blood Logo" />
            </div>
            <form action="login.php" method="POST">
                <input type="text" name="username" placeholder="Enter username" required />
                <input type="password" name="password" placeholder="Enter password" required />
                <p class="forgot-password"><a href="forgot.php">Forgot password?</a></p>
                <button type="submit">Continue</button>
                
                            <?php
                if (isset($_SESSION['message'])) {
                    echo $_SESSION['message'];
                    unset($_SESSION['message']); 
                }
                ?>
            </form>

            <p class="action">Don't have an account? <a href="signup.php">Sign up</a></p>
        </div>
    </main-login>
</body>
</html>