<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/userQuery.php';

$pdo = db();

$message = trim($_POST['forumMessage'] ?? '');
 //User's message submitted in Form
//Create Prepared Statement
if ($message !== '') {
 try  { 
  $sql = "INSERT INTO tblmessage (blockID, studentID, messageContent, messageType) VALUES (?, ?, ?, ?)";
  $pstmt = $pdo->prepare($sql);

  $blockID = getStudentBlock($_SESSION['user_id']);
  $studentID = $_SESSION['user_id'];
// Add Message and metadata to query
  $messageType = "message"; //The type of message to be added to the database
// "message" is only for the Message Stack feature of Block Forum

  $pstmt->execute([$blockID, $studentID, $message, $messageType]);
  } catch (PDOException $e) {
    echo "SQL Connection Error" . $e->getMessage();
  }
}
?>