<?php
// Functions/db_functions.php

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

function addCategory($conn, $account_id, $category_name) {
    $message = "";
    if (!empty($category_name)) {
        // Check if the category name already exists
        $check_sql = "SELECT Category_ID, Category_Status FROM Category_Choices WHERE Category_Name = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $category_name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            // Category name exists
            $row = $check_result->fetch_assoc();
            $category_id = $row["Category_ID"];
            $category_status = $row["Category_Status"];

            // If the category is 'Global', do nothing, as it's already available to all users
            if ($category_status == 'Global') {
                $message = "Category already exists (Global).";
            } else {
                // Check if the association already exists in Account_Category
                $check_assoc_sql = "SELECT * FROM Account_Category WHERE Account_ID = ? AND Category_ID = ?";
                $check_assoc_stmt = $conn->prepare($check_assoc_sql);
                $check_assoc_stmt->bind_param("ii", $account_id, $category_id);
                $check_assoc_stmt->execute();
                $check_assoc_result = $check_assoc_stmt->get_result();

                if ($check_assoc_result->num_rows == 0) {
                    // Association doesn't exist, insert into Account_Category
                    $insert_assoc_sql = "INSERT INTO Account_Category (Account_ID, Category_ID) VALUES (?, ?)";
                    $insert_assoc_stmt = $conn->prepare($insert_assoc_sql);
                    $insert_assoc_stmt->bind_param("ii", $account_id, $category_id);

                    if ($insert_assoc_stmt->execute()) {
                        $message = "Category association added successfully!";
                    } else {
                        $message = "Error associating category with account: " . $insert_assoc_stmt->error;
                    }
                    $insert_assoc_stmt->close();
                } else {
                    $message = "Category already added by this user!";
                }
                $check_assoc_stmt->close();
            }
        } else {
            // Category name doesn't exist, insert it with 'User' status
            $insert_sql = "INSERT INTO Category_Choices (Category_Name, Category_Status) VALUES (?, 'User')";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("s", $category_name);

            if ($insert_stmt->execute()) {
                $category_id = $insert_stmt->insert_id;

                // Associate the new category with the user
                $insert_assoc_sql = "INSERT INTO Account_Category (Account_ID, Category_ID) VALUES (?, ?)";
                $insert_assoc_stmt = $conn->prepare($insert_assoc_sql);
                $insert_assoc_stmt->bind_param("ii", $account_id, $category_id);
                $insert_assoc_stmt->execute();
                $insert_assoc_stmt->close();

                $message = "New category added successfully!";
            } else {
                $message = "Error adding category: " . $insert_stmt->error;
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    } else {
        $message = "Category name cannot be empty!";
    }
    return $message;
}

function getCategories($conn, $account_id) {
    $categories = [];
    $sql = "SELECT c.Category_ID, c.Category_Name 
            FROM Category_Choices c
            LEFT JOIN Account_Category ac ON c.Category_ID = ac.Category_ID
            WHERE c.Category_Status = 'Global' OR ac.Account_ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $account_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $categories[$row["Category_ID"]] = $row["Category_Name"];
        }
    }
    $stmt->close();
    return $categories;
}

function updateCategory($conn, $account_id, $category_id, $category_name) {
    $message = "";
    if (!empty($category_name)) {
        // Check if the category is 'Global'
        $check_global_sql = "SELECT Category_Status FROM Category_Choices WHERE Category_ID = ?";
        $check_global_stmt = $conn->prepare($check_global_sql);
        $check_global_stmt->bind_param("i", $category_id);
        $check_global_stmt->execute();
        $check_global_result = $check_global_stmt->get_result();
        $is_global = false;
        if ($check_global_result->num_rows > 0) {
            $row = $check_global_result->fetch_assoc();
            if ($row["Category_Status"] == 'Global') {
                $is_global = true;
            }
        }
        $check_global_stmt->close();

        // Prevent updating if the category is 'Global'
        if ($is_global) {
            $message = "Cannot update a Global category.";
            return $message;
        }

        // Check if the new category name already exists for the current user or globally, excluding the current category being edited
        $check_sql = "SELECT Category_ID FROM Category_Choices WHERE Category_Name = ? AND Category_ID != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $category_name, $category_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows == 0) {
            // Category name is unique, so update it
            $update_sql = "UPDATE Category_Choices SET Category_Name = ? WHERE Category_ID = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("si", $category_name, $category_id);

            if ($update_stmt->execute()) {
                $message = "Category updated successfully!";
            } else {
                $message = "Error updating category: " . $update_stmt->error;
            }
            $update_stmt->close();
        } else {
            $message = "Category name already exists!";
        }
        $check_stmt->close();
    } else {
        $message = "Category name cannot be empty!";
    }
    return $message;
}

function deleteCategory($conn, $account_id, $category_id) {
    $message = "";

    // Check if the category is 'Global'
    $check_global_sql = "SELECT Category_Status FROM Category_Choices WHERE Category_ID = ?";
    $check_global_stmt = $conn->prepare($check_global_sql);
    $check_global_stmt->bind_param("i", $category_id);
    $check_global_stmt->execute();
    $check_global_result = $check_global_stmt->get_result();
    $is_global = false;
    if ($check_global_result->num_rows > 0) {
        $row = $check_global_result->fetch_assoc();
        if ($row["Category_Status"] == 'Global') {
            $is_global = true;
        }
    }
    $check_global_stmt->close();

    // Prevent deleting if the category is 'Global'
    if ($is_global) {
        $message = "Cannot delete a Global category.";
        return $message;
    }

    // Begin transaction
    $conn->begin_transaction();

    try {
        // Delete the association from Account_Category
        $delete_assoc_sql = "DELETE FROM Account_Category WHERE Account_ID = ? AND Category_ID = ?";
        $delete_assoc_stmt = $conn->prepare($delete_assoc_sql);
        $delete_assoc_stmt->bind_param("ii", $account_id, $category_id);
        $delete_assoc_stmt->execute();
        $delete_assoc_stmt->close();

        // Check if the category is still associated with other accounts
        $check_assoc_sql = "SELECT * FROM Account_Category WHERE Category_ID = ?";
        $check_assoc_stmt = $conn->prepare($check_assoc_sql);
        $check_assoc_stmt->bind_param("i", $category_id);
        $check_assoc_stmt->execute();
        $check_assoc_result = $check_assoc_stmt->get_result();
        $check_assoc_stmt->close();

        if ($check_assoc_result->num_rows == 0) {
            // If not associated with other accounts, delete the category
            $delete_category_sql = "DELETE FROM Category_Choices WHERE Category_ID = ?";
            $delete_category_stmt = $conn->prepare($delete_category_sql);
            $delete_category_stmt->bind_param("i", $category_id);
            $delete_category_stmt->execute();
            $delete_category_stmt->close();
        }

        // Commit transaction
        $conn->commit();
        $message = "Category removed successfully!";
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $message = "Error: " . $e->getMessage();
    }

    return $message;
}

// New function to add an expense
function addExpense($conn, $account_id, $date, $description, $amount, $category_id) {
    $stmt = $conn->prepare("INSERT INTO expenses (Account_ID, date, description, amount, Category_ID) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issdi", $account_id, $date, $description, $amount, $category_id);

    if ($stmt->execute()) {
        $message = "Expense added successfully!";

        // Update total spent for the account
        $update_sql = "UPDATE Account_Log SET Total_Spent = Total_Spent + ? WHERE Account_ID = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("di", $amount, $account_id);
        if (!$update_stmt->execute()) {
            $message .= " Error updating total spent: " . $update_stmt->error;
        }
        $update_stmt->close();
    } else {
        $message = "Error adding expense: " . $stmt->error;
    }
    $stmt->close();
    return $message;
}

// New function to delete an expense
function deleteExpense($conn, $expense_id, $account_id) {
    // Get the amount of the expense being deleted
    $amount_sql = "SELECT Amount FROM Expenses WHERE Expense_ID = ? AND Account_ID = ?";
    $amount_stmt = $conn->prepare($amount_sql);
    $amount_stmt->bind_param("ii", $expense_id, $account_id);
    $amount_stmt->execute();
    $amount_result = $amount_stmt->get_result();

    if ($amount_result->num_rows == 1) {
        $amount_row = $amount_result->fetch_assoc();
        $deleted_amount = $amount_row["Amount"];
        $amount_stmt->close();

        // Delete the expense
        $sql = "DELETE FROM expenses WHERE Expense_ID = ? AND Account_ID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $expense_id, $account_id);

        if ($stmt->execute()) {
            $message = "Expense deleted successfully!";

            // Update total spent for the account
            $update_sql = "UPDATE Account_Log SET Total_Spent = Total_Spent - ? WHERE Account_ID = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("di", $deleted_amount, $account_id);
            if (!$update_stmt->execute()) {
                $message .= " Error updating total spent: " . $update_stmt->error;
            }
            $update_stmt->close();
        } else {
            $message = "Error deleting expense: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $amount_stmt->close();
        $message = "Expense not found or you do not have permission to delete it.";
    }
    return $message;
}

// New function to fetch expenses for the current account with category filter
function getExpenses($conn, $account_id, $filter_category_id = '') {
    $sql = "SELECT e.Expense_ID, e.date, e.description, e.amount, c.Category_Name 
            FROM expenses e
            INNER JOIN Category_Choices c ON e.Category_ID = c.Category_ID
            WHERE e.Account_ID = ?";

    if (!empty($filter_category_id)) {
        $sql .= " AND e.Category_ID = ?";
    }

    $sql .= " ORDER BY e.date DESC";
    $stmt = $conn->prepare($sql);

    if (!empty($filter_category_id)) {
        $stmt->bind_param("ii", $account_id, $filter_category_id);
    } else {
        $stmt->bind_param("i", $account_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    return $result;
}

// New function to fetch total expenses for the current account
function getTotalExpenses($conn, $account_id) {
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
    $total_stmt->close();
    return $totalExpenses;
}
?>