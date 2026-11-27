<?php
static $pdo = db();

function getMessages() {
  try {
  require_once __DIR__ . "/../../config/database.php";
  $pdo = db();
  $query = "SELECT studentID, messageContent, createdAt  FROM tblmessage WHERE blockID=?";  
  $pstmt = $pdo->query($query);  
  $studentID[] = [];
  $messageContent[] = [];
  $createdAt[] = [];

  if ($pstmt->rowCount > 0) {
    while ($row = $pstmt->fetch()) {
      array_push($studentID, $row['studentID']);
    }
  }

  $pdo = null;
  } catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
  }
}


?>