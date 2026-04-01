<?php
$host = "sql112.infinityfree.com";
$username = "if0_41498396";
$password = "w4rbm5cnwcAQ8gh";
$dbname = "if0_41498396_pbbms";
$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>  