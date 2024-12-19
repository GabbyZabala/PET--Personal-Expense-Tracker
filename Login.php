<?php
session_start();

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
    $input_username = $_POST["username"];
    $input_password = $_POST["password"];

    // Fetch user from database
    $sql = "SELECT Account_ID, Username, Password, Status FROM Account_Log WHERE Username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $input_username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $hashed_password = $row["Password"];

        // Verify password using password_verify()
        if (password_verify($input_password, $hashed_password)) {
            $_SESSION["account_id"] = $row["Account_ID"];

            // Check user's status and redirect accordingly
            if ($row["Status"] == 'Admin') {
                header("Location: root.php"); // Redirect to admin page
            } else {
                header("Location: index.php"); // Redirect to user page
            }
            exit();
        } else {
            $message = "Invalid password!";
        }
    } else {
        $message = "Invalid username!";
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
</head>
<body>

<h2>Login</h2>

<?php if ($message != "") : ?>
    <p><?= $message ?></p>
<?php endif; ?>

<form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
    Username: <input type="text" name="username" required><br><br>
    Password: <input type="password" name="password" required><br><br>
    <input type="submit" value="Login">
</form>

</body>
</html>