<?php
// index.php
session_start();

// Include database functions
require_once 'Functions/db_functions.php';
// Include getDisplayName function
require_once 'Functions/get_displayname.php'; 

// Database configuration (no changes)
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "expense_tracker";

// Create connection (no changes)
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if the user is logged in
if (isset($_SESSION["account_id"])) {
    $account_id = $_SESSION["account_id"];
    $display_name = getDisplayName($conn, $account_id); // Get display name
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
$edit_category_id = null;
$edit_category_name = "";

// Fetch categories for the current user
$categories = getCategories($conn, $account_id);

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["add"])) {
        // Add expense using the new function
        $message = addExpense($conn, $account_id, $_POST["date"], $_POST["description"], $_POST["amount"], $_POST["category_id"]);
    } elseif (isset($_POST["delete"])) {
        // Delete expense using the new function
        $message = deleteExpense($conn, $_POST["delete"], $account_id);
    } elseif (isset($_POST["add_category"])) {
        // Add new category
        $new_category_name = trim($_POST["new_category_name"]);
        $message = addCategory($conn, $account_id, $new_category_name);
        if ($message == "New category added successfully!") {
            $categories = getCategories($conn, $account_id);
        }
    } elseif (isset($_POST["edit_category"])) {
        // Set category ID and name for editing
        $edit_category_id = $_POST["edit_category"];
        $edit_category_name = $categories[$edit_category_id];
    } elseif (isset($_POST["update_category"])) {
        // Update existing category
        $update_category_id = $_POST["update_category_id"];
        $update_category_name = trim($_POST["update_category_name"]);
        $message = updateCategory($conn, $account_id, $update_category_id, $update_category_name);
        if ($message == "Category updated successfully!") {
            $categories = getCategories($conn, $account_id);
        }
    } elseif (isset($_POST["delete_category"])) {
        // Delete category
        $delete_category_id = $_POST["delete_category"];
        $message = deleteCategory($conn, $account_id, $delete_category_id); // Pass $account_id
        if ($message == "Category deleted successfully!" || $message == "Category removed from your account!") {
            $categories = getCategories($conn, $account_id);
        }
    }
}

// Fetch expenses for the current account (with filter)
$filter_category_id = isset($_GET['filter_category_id']) ? $_GET['filter_category_id'] : '';
$result = getExpenses($conn, $account_id, $filter_category_id);

// Fetch total expenses for the current account
$totalExpenses = getTotalExpenses($conn, $account_id);

// Fetch categories for the current user, excluding 'Global' categories
$account_categories = [];
$account_categories_sql = "SELECT cc.Category_ID, cc.Category_Name 
                           FROM Category_Choices cc
                           INNER JOIN Account_Category ac ON cc.Category_ID = ac.Category_ID
                           WHERE ac.Account_ID = ? AND cc.Category_Status <> 'Global'";
$account_categories_stmt = $conn->prepare($account_categories_sql);
$account_categories_stmt->bind_param("i", $account_id);
$account_categories_stmt->execute();
$account_categories_result = $account_categories_stmt->get_result();

if ($account_categories_result->num_rows > 0) {
    while ($row = $account_categories_result->fetch_assoc()) {
        $account_categories[$row["Category_ID"]] = $row["Category_Name"];
    }
}
$account_categories_stmt->close();

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
    <p>DisplayName: <span id="displayname"><?php echo htmlspecialchars($display_name); ?></span></p>

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

    <!-- Category Filter Form -->
    <h3>Filter Expenses by Category</h3>
    <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
        <label for="filter_category_id">Category:</label>
        <select name="filter_category_id" id="filter_category_id">
            <option value="">All Categories</option>
            <?php foreach ($categories as $id => $name) : ?>
                <option value="<?= $id ?>" <?= (isset($_GET['filter_category_id']) && $_GET['filter_category_id'] == $id) ? 'selected' : '' ?>>
                    <?= $name ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Filter</button>
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

    <!-- Edit Category Section -->
    <h3>Edit Categories</h3>

    <!-- Add New Category Form -->
    <h4>Add Category</h4>
    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
        Category Name: <input type="text" name="new_category_name" required><br><br>
        <input type="submit" name="add_category" value="Add Category">
    </form>

    <!-- List of Categories with Edit and Delete Options -->
    <h4>Categories</h4>
    <table>
        <tr>
            <th>Category Name</th>
            <th>Action</th>
        </tr>
        <?php foreach ($account_categories as $id => $category_name) : ?>
            <tr>
                <td>
                    <?php if ($edit_category_id == $id) : ?>
                        <!-- Edit Category Form -->
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                            <input type="hidden" name="update_category_id" value="<?= $id ?>">
                            <input type="text" name="update_category_name" value="<?= htmlspecialchars($category_name) ?>" required>
                            <button type="submit" name="update_category">Save</button>
                        </form>
                    <?php else : ?>
                        <?= htmlspecialchars($category_name) ?>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($edit_category_id != $id) : ?>
                        <!-- Edit and Delete Buttons -->
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                            <input type="hidden" name="edit_category" value="<?= $id ?>">
                            <button type="submit">Edit</button>
                        </form>
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                            <input type="hidden" name="delete_category" value="<?= $id ?>">
                            <button type="submit" onclick="return confirm('Are you sure you want to delete this category?')">Delete</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

</body>

</html>

<?php
// Close the database connection at the very end
$conn->close();
?>