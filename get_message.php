<?php
session_start();
include "db.php";

// Protect page from unauthorized access
if(!isset($_SESSION['admin']) && !isset($_SESSION['employee_id'])){
    header("Content-Type: application/json");
    echo json_encode(array('error' => 'Unauthorized'));
    exit();
}

// Get message ID
$messageId = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : 0;

// Determine user type
$isAdmin = isset($_SESSION['admin']);
$userType = $isAdmin ? 'admin' : 'employee';
$userId = $isAdmin ? $_SESSION['admin'] : $_SESSION['employee_id'];

// Fetch message
$query = "SELECT * FROM messages WHERE message_id = '$messageId' AND ((sender_id = '$userId' AND sender_type = '$userType') OR (recipient_id = '$userId' AND recipient_type = '$userType'))";
$result = mysqli_query($conn, $query);

header("Content-Type: application/json");

if($result && mysqli_num_rows($result) > 0){
    $message = mysqli_fetch_assoc($result);
    echo json_encode($message);
} else {
    echo json_encode(array('error' => 'Message not found'));
}

