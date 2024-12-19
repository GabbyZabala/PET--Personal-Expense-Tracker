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

// Check if the user is logged in
if (isset($_SESSION["account_id"])) {
    $account_id = $_SESSION["account_id"];
} else {
    // Redirect to login page if not logged in
    header("Location: login.php");
    exit();
}

// Initialize variables
$date = date("Y-m-d");
$description = "";
$amount = "";
$category_id = "";
$message = "";

// Fetch categories
$categories = [];
$sql = "SELECT Category_ID, Category_Name FROM Category_Choices";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $categories[$row["Category_ID"]] = $row["Category_Name"];
    }
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["add"])) {
        // Add expense
        $date = $_POST["date"];
        $description = $_POST["description"];
        $amount = $_POST["amount"];
        $category_id = $_POST["category_id"];

        $stmt = $conn->prepare("INSERT INTO expenses (Account_ID, date, description, amount, Category_ID) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isdii", $account_id, $date, $description, $amount, $category_id);

        if ($stmt->execute()) {
            $message = "Expense added successfully!";

            // Update total spent for the account
            $update_sql = "UPDATE Account_Log SET Total_Spent = Total_Spent + ? WHERE Account_ID = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("di", $amount, $account_id);
            $update_stmt->execute();
        } else {
            $message = "Error adding expense: " . $stmt->error;
        }
        $stmt->close();
        $update_stmt->close();
    } elseif (isset($_POST["delete"])) {
        // Delete expense
        $expense_id = $_POST["delete"];

        // Get the amount of the expense being deleted (to update Total_Spent)
        $amount_sql = "SELECT Amount FROM Expenses WHERE Expense_ID = ?";
        $amount_stmt = $conn->prepare($amount_sql);
        $amount_stmt->bind_param("i", $expense_id);
        $amount_stmt->execute();
        $amount_result = $amount_stmt->get_result();
        if ($amount_result->num_rows == 1) {
            $amount_row = $amount_result->fetch_assoc();
            $deleted_amount = $amount_row["Amount"];

            // Delete the expense
            $sql = "DELETE FROM expenses WHERE Expense_ID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $expense_id);

            if ($stmt->execute()) {
                $message = "Expense deleted successfully!";

                // Update total spent for the account
                $update_sql = "UPDATE Account_Log SET Total_Spent = Total_Spent - ? WHERE Account_ID = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("di", $deleted_amount, $account_id);
                $update_stmt->execute();
            } else {
                $message = "Error deleting expense: " . $stmt->error;
            }
            $stmt->close();
            $update_stmt->close();
        }
        $amount_stmt->close();
    }
}

// Fetch expenses for the current account
$sql = "SELECT e.Expense_ID, e.date, e.description, e.amount, c.Category_Name 
        FROM expenses e
        INNER JOIN Category_Choices c ON e.Category_ID = c.Category_ID
        WHERE e.Account_ID = ?
        ORDER BY e.date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $account_id);
$stmt->execute();
$result = $stmt->get_result();

// Fetch total expenses for the current account
$totalExpenses = 0;
$total_sql = "SELECT Total_Spent FROM Account_Log WHERE Account_ID = ?";
$total_stmt = $conn->prepare($total_sql);
$total_stmt->bind_param("i", $account_id);
$total_stmt->execute();
$total_result = $total_stmt->get_result();
if ($total_result->num_rows > 0) {
    $total_row = $total_result->fetch_assoc();
    $totalExpenses = $total_row["Total_Spent"];
}

?>

<!DOCTYPE html>
<html>

<head>
    <title>Personal Expense Tracker</title>
    <style>
        table {
            width: 80%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
    </style>
</head>

<body>

    <h2>Personal Expense Tracker</h2>

    <!-- Logout Button -->
    <form method="post" action="Functions/logout.php">
        <button type="submit">Logout</button>
    </form>

    <?php if ($message != "") : ?>
    <p><?= $message ?></p>
    <?php endif; ?>

    <!-- Add Expense Form -->
    <h3>Add Expense</h3>
    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
        Date: <input type="date" name="date" value="<?= $date ?>" required><br><br>
        Description: <input type="text" name="description" value="<?= $description ?>" required><br><br>
        Amount: <input type="number" name="amount" step="0.01" value="<?= $amount ?>" required><br><br>
        Category:
        <select name="category_id">
            <?php foreach ($categories as $id => $name) : ?>
                <option value="<?= $id ?>"><?= $name ?></option>
            <?php endforeach; ?>
        </select><br><br>
        <input type="submit" name="add" value="Add Expense">
    </form>

    <!-- Expense List -->
    <h3>Expenses</h3>
    <table>
        <tr>
            <th>Date</th>
            <th>Description</th>
            <th>Amount</th>
            <th>Category</th>
            <th>Action</th>
        </tr>
        <?php if ($result->num_rows > 0) : ?>
            <?php while ($row = $result->fetch_assoc()) : ?>
                <tr>
                    <td><?= $row["date"] ?></td>
                    <td><?= $row["description"] ?></td>
                    <td>$<?= number_format($row["amount"], 2) ?></td>
                    <td><?= $row["Category_Name"] ?></td>
                    <td>
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                            <input type="hidden" name="delete" value="<?= $row["Expense_ID"] ?>">
                            <input type="submit" value="Delete">
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else : ?>
            <tr>
                <td colspan="5">No expenses found for this account.</td>
            </tr>
        <?php endif; ?>
    </table>

    <p><strong>Total Expenses:</strong> $<?= number_format($totalExpenses, 2) ?></p>

</body>

</html>

<?php
$stmt->close();
$total_stmt->close();
$conn->close();
?>