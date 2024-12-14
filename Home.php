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

// Initialize variables
$date = date("Y-m-d");
$description = "";
$amount = "";
$category = "";
$message = "";

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["add"])) {
        // Add expense (with prepared statements for security)
        $date = $_POST["date"];
        $description = $_POST["description"];
        $amount = $_POST["amount"];
        $category = $_POST["category"];

        $stmt = $conn->prepare("INSERT INTO expenses (date, description, amount, category) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssds", $date, $description, $amount, $category); // "ssds" specifies the types: string, string, double, string

        if ($stmt->execute()) {
            $message = "Expense added successfully!";
        } else {
            $message = "Error adding expense: " . $stmt->error;
        }
        $stmt->close();
    } elseif (isset($_POST["delete"])) {
        // Delete expense (with prepared statements for security)
        $id = $_POST["delete"];

        $stmt = $conn->prepare("DELETE FROM expenses WHERE id = ?");
        $stmt->bind_param("i", $id); // "i" specifies integer type

        if ($stmt->execute()) {
            $message = "Expense deleted successfully!";
        } else {
            $message = "Error deleting expense: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Fetch expenses
$sql = "SELECT * FROM expenses ORDER BY date DESC";
$result = $conn->query($sql);

// Calculate total expenses (optimized)
$totalExpenses = 0;
$sql = "SELECT SUM(amount) AS total FROM expenses";
$sumResult = $conn->query($sql);
if ($sumResult && $sumResult->num_rows > 0) {
    $row = $sumResult->fetch_assoc();
    $totalExpenses = $row["total"] ?? 0; // Use 0 if total is NULL
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
        <select name="category">
            <option value="Food">Food</option>
            <option value="Housing">Housing</option>
            <option value="Transportation">Transportation</option>
            <option value="Entertainment">Entertainment</option>
            <option value="Other">Other</option>
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
        <?php if ($result && $result->num_rows > 0) : ?>
            <?php while ($row = $result->fetch_assoc()) : ?>
                <tr>
                    <td><?= $row["date"] ?></td>
                    <td><?= $row["description"] ?></td>
                    <td>$<?= number_format($row["amount"], 2) ?></td>
                    <td><?= $row["category"] ?></td>
                    <td>
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                            <input type="hidden" name="delete" value="<?= $row["id"] ?>">
                            <input type="submit" value="Delete">
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else : ?>
            <tr>
                <td colspan="5">No expenses found.</td>
            </tr>
        <?php endif; ?>
    </table>

    <p><strong>Total Expenses:</strong> $<?= number_format($totalExpenses, 2) ?></p>

</body>

</html>

<?php
$conn->close();
?>