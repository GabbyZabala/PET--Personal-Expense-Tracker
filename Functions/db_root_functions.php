<?php
// Functions/db_root_functions.php

// Include necessary functions from db_functions.php
require_once 'db_functions.php';

function getAdminExpenses($conn, $filter_category_id = '') {
    $sql = "SELECT e.Expense_ID, e.date, e.description, e.amount, c.Category_Name, a.Account_Display_Name
            FROM expenses e
            INNER JOIN Category_Choices c ON e.Category_ID = c.Category_ID
            INNER JOIN Account_Log a ON e.Account_ID = a.Account_ID";

    if (!empty($filter_category_id)) {
        $sql .= " WHERE e.Category_ID = ?";
    }

    $sql .= " ORDER BY e.date DESC";
    $stmt = $conn->prepare($sql);

    if (!empty($filter_category_id)) {
        $stmt->bind_param("i", $filter_category_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    return $result;
}

function getAdminTotalExpenses($conn, $filter_category_id = '') {
    $totalExpenses = 0;
    $sql = "SELECT SUM(e.amount) AS Total_Spent
            FROM expenses e
            INNER JOIN Category_Choices c ON e.Category_ID = c.Category_ID";

    if (!empty($filter_category_id)) {
        $sql .= " WHERE e.Category_ID = ?";
    }

    $stmt = $conn->prepare($sql);

    if (!empty($filter_category_id)) {
        $stmt->bind_param("i", $filter_category_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $totalExpenses = $row["Total_Spent"] ?? 0;
    }
    $stmt->close();
    return $totalExpenses;
}

function getAdminCategories($conn) {
    $categories = [];
    $sql = "SELECT cc.Category_ID, cc.Category_Name, cc.Category_Status, GROUP_CONCAT(DISTINCT a.Account_Display_Name SEPARATOR ', ') AS Associated_Accounts
            FROM Category_Choices cc
            LEFT JOIN Account_Category ac ON cc.Category_ID = ac.Category_ID
            LEFT JOIN Account_Log a ON ac.Account_ID = a.Account_ID
            GROUP BY cc.Category_ID, cc.Category_Name, cc.Category_Status";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $categories[$row["Category_ID"]] = [
                'name' => $row["Category_Name"],
                'status' => $row["Category_Status"],
                'accounts' => $row["Associated_Accounts"]
            ];
        }
    }
    $stmt->close();
    return $categories;
}

function getAllAccounts($conn) {
    $sql = "SELECT Account_ID, Account_Display_Name, Total_Spent FROM Account_Log";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    return $result;
}

function getAllCategories($conn) {
    $categories = [];
    $sql = "SELECT Category_ID, Category_Name FROM Category_Choices";
    $stmt = $conn->prepare($sql);
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
function deleteAccount($conn, $account_id) {
    $message = "";

    // Begin transaction
    $conn->begin_transaction();

    try {
        // Delete associated entries in Account_Category
        $delete_account_category_sql = "DELETE FROM Account_Category WHERE Account_ID = ?";
        $delete_account_category_stmt = $conn->prepare($delete_account_category_sql);
        $delete_account_category_stmt->bind_param("i", $account_id);
        $delete_account_category_stmt->execute();
        $delete_account_category_stmt->close();

        // Delete associated entries in Expenses
        $delete_expenses_sql = "DELETE FROM Expenses WHERE Account_ID = ?";
        $delete_expenses_stmt = $conn->prepare($delete_expenses_sql);
        $delete_expenses_stmt->bind_param("i", $account_id);
        $delete_expenses_stmt->execute();
        $delete_expenses_stmt->close();

        // Delete the account from Account_Log
        $delete_account_sql = "DELETE FROM Account_Log WHERE Account_ID = ?";
        $delete_account_stmt = $conn->prepare($delete_account_sql);
        $delete_account_stmt->bind_param("i", $account_id);
        $delete_account_stmt->execute();
        $delete_account_stmt->close();

        // Commit transaction
        $conn->commit();
        $message = "Account and all associated data deleted successfully!";
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $message = "Error: " . $e->getMessage();
    }

    return $message;
}
?>