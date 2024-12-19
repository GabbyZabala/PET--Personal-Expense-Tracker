<?php
// root.php
session_start();

// Include database functions
require_once 'Functions/db_functions.php';
require_once 'Functions/db_root_functions.php';
// Include getDisplayName function
require_once 'Functions/get_displayname.php';

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "expense_tracker";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if the user is logged in and has admin status
if (isset($_SESSION["account_id"])) {
    $account_id = $_SESSION["account_id"];
    $display_name = getDisplayName($conn, $account_id); // Get display name

    // Check if the user is an admin
    $check_admin_sql = "SELECT Status FROM Account_Log WHERE Account_ID = ? AND Status = 'Admin'";
    $check_admin_stmt = $conn->prepare($check_admin_sql);
    $check_admin_stmt->bind_param("i", $account_id);
    $check_admin_stmt->execute();
    $check_admin_result = $check_admin_stmt->get_result();

    if ($check_admin_result->num_rows == 0) {
        // User is not an admin, redirect to index.php
        header("Location: index.php");
        exit();
    }
    $check_admin_stmt->close();
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

// Fetch all categories for the filter
$categories = getAllCategories($conn);

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["delete"])) {
        // Delete expense (only allow deletion, not adding)
        $message = deleteExpense($conn, $_POST["delete"], $account_id);
    } elseif (isset($_POST["add_category"])) {
        // Add new category (only allow adding 'Global' categories)
        $new_category_name = trim($_POST["new_category_name"]);
        $message = addCategory($conn, $account_id, $new_category_name, 'Global'); // Note: 'Global' is passed here
        if ($message == "New category added successfully!") {
            $categories = getAllCategories($conn); // Refresh categories
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
            $categories = getAllCategories($conn); // Refresh categories after update
        }
    } elseif (isset($_POST["delete_category"])) {
        // Delete category
        $delete_category_id = $_POST["delete_category"];
        $message = deleteCategory($conn, $account_id, $delete_category_id);
        if ($message == "Category deleted successfully!" || $message == "Category removed from your account!") {
            $categories = getAllCategories($conn); // Refresh categories after deletion
        }
    } elseif (isset($_POST["delete_account"])) {
        // Handle account deletion
        $account_to_delete = $_POST["delete_account"];
        $delete_message = deleteAccount($conn, $account_to_delete);
        $accounts_result = getAllAccounts($conn); // Refresh the list of accounts
    }
}

// Fetch expenses with filter (for all accounts)
$filter_category_id = isset($_GET['filter_category_id']) ? $_GET['filter_category_id'] : '';
$expenses_result = getAdminExpenses($conn, $filter_category_id);

// Fetch total expenses (for filtered expenses)
$totalExpenses = getAdminTotalExpenses($conn, $filter_category_id);

// Fetch categories with details (for admin view)
$account_categories = getAdminCategories($conn);

// Fetch all accounts (for admin view)
$accounts_result = getAllAccounts($conn);
?>
<!DOCTYPE html>
<html>

<head>
    <title>Personal Expense Tracker - Admin</title>
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

    <h2>Personal Expense Tracker - Admin</h2>

    <!-- Logout Button -->
    <form method="post" action="Functions/logout.php">
        <button type="submit">Logout</button>
    </form>

    <?php if ($message != "") : ?>
        <p><?= $message ?></p>
    <?php endif; ?>

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
    <thead>
        <tr>
            <th>Date</th>
            <th>Description</th>
            <th>Amount</th>
            <th>Category</th>
            <th>Account</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = $expenses_result->fetch_assoc()) : ?>
            <tr>
                <td><?= $row["date"] ?></td>
                <td><?= $row["description"] ?></td>
                <td>$<?= number_format($row["amount"], 2) ?></td>
                <td><?= $row["Category_Name"] ?></td>
                <td><?= $row["Account_Display_Name"] ?></td>
                <td>
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                        <input type="hidden" name="delete" value="<?= $row["Expense_ID"] ?>">
                        <input type="submit" value="Delete">
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

    <p><strong>Total Expenses:</strong> $<?= number_format($totalExpenses, 2) ?></p>

    <!-- Edit Category Section -->
    <h3>Edit Categories</h3>

   <!-- Add New Category Form -->
   <h4>Add Category</h4>
    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
        <label for="new_category_name">Category Name:</label>
        <input type="text" id="new_category_name" name="new_category_name" required>
        <button type="submit" name="add_category">Add Category</button>
    </form>

    <!-- List of Categories with Edit and Delete Options -->
    <h4>Categories</h4>
    <table>
        <tr>
            <th>Category Name</th>
            <th>Type</th>
            <th>Associated Accounts</th>
            <th>Action</th>
        </tr>
        <?php foreach ($account_categories as $id => $category) : ?>
    <tr>
        <td>
            <?php if ($edit_category_id == $id) : ?>
                <!-- Edit Category Form -->
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <input type="hidden" name="update_category_id" value="<?= $id ?>">
                    <input type="text" name="update_category_name" value="<?= htmlspecialchars($category['name']) ?>" required>
                    <button type="submit" name="update_category">Save</button>
                </form>
            <?php else : ?>
                <?= htmlspecialchars($category['name']) ?>
            <?php endif; ?>
        </td>
        <td><?= htmlspecialchars($category['status']) ?></td>
        <td><?= htmlspecialchars($category['accounts']) ?></td>
        <td>
            <?php if ($category['status'] != 'Global') : ?>
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
            <?php endif; ?>
        </td>
    </tr>
<?php endforeach; ?>
    </table>

    <!-- Account List Section -->
    <h3>Account List</h3>
    <table>
        <thead>
            <tr>
                <th>Account ID</th>
                <th>Account Display Name</th>
                <th>Total Expenses</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($account_row = $accounts_result->fetch_assoc()) : ?>
                <tr>
                    <td><?= $account_row["Account_ID"] ?></td>
                    <td><?= $account_row["Account_Display_Name"] ?></td>
                    <td>$<?= number_format($account_row["Total_Spent"], 2) ?></td>
                    <td>
                        <!-- Delete Account Form -->
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                            <input type="hidden" name="delete_account" value="<?= $account_row["Account_ID"] ?>">
                            <button type="submit" onclick="return confirm('Are you sure you want to delete this account?');">
                                Delete
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

</body>

</html>