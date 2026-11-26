<?php 

require_once __DIR__ . "/../../config/database.php";

function getStudent($StudentID) {
  $pdo = db();

  try {
  $query = "SELECT * FROM tblstudent WHERE studentID ={$StudentID}";
  $result = $pdo->query($query);
  $result->fetch();
  $studentsMessage[] = [];

  if ($result->rowCount() > 0) {
    while ($row = $result->fetch()) {
      
    }
  }
  } catch (PDOException $e) {
  echo "Error: " . $e->getMessage();
    
  }
  $pdo = null;
}

?>