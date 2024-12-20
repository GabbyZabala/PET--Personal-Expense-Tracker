<?php
// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "expense_tracker";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $account_display_name = $_POST["account_display_name"];
    $username = $_POST["username"];
    $password = $_POST["password"];

    // Hash the password using password_hash()
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO Account_Log (Account_Display_Name, Username, Password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $account_display_name, $username, $hashed_password);

    if ($stmt->execute()) {
        $message = "Registration successful! You can now login.";

        // Redirect to login.php after successful registration
        header("Location: login.php");
        exit(); // Make sure to exit after redirection

    } else {
        $message = "Error during registration: " . $stmt->error;
    }

    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html>

<head>
    <title>Register</title>
    <link href="css/background.css" rel="stylesheet" >
    <link href="css/intro.css" rel="stylesheet" >
    <link rel="icon" href="Images/PET-LOGO.png" type="image/png">
</head>

<body>
    <div class="Main-container">
        <h2>Register a Tracker Account</h2>

        <?php if ($message != "") : ?>
            <script>
                alert("<?= $message ?>");
            </script>
        <?php endif; ?>

        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <label for="account_display_name">Account Display Name: </label>    
            <input type="text" id="account_display_name" name="account_display_name" required><br>
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required><br>
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required><br>
            <input type="submit" value="Register">
        </form>
        <p>Already have an account? <a href="login.php">Login here</a></p>
    </div>
</body>

</html>