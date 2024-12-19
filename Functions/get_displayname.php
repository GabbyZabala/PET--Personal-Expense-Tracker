<?php
// Functions/get_displayname.php

function getDisplayName($conn, $account_id) {
    $display_name = "";
    $sql = "SELECT Account_Display_Name FROM Account_Log WHERE Account_ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $account_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $display_name = $row["Account_Display_Name"];
    } else {
        $display_name = "User not found";
    }
    $stmt->close();
    return $display_name;
}
?>