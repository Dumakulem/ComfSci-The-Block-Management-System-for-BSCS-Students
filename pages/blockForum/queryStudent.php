<?php 

function getStudent($StudentID) {
  require_once __DIR__ . "/../../config/database.php";
  $pdo = db();

  try {
  $query = "SELECT * FROM tblstudent WHERE studentID ={$StudentID}";
  $result = $pdo->query($query);
  $result->fetch();

  } catch (PDOException $e) {
  echo "Error: " . $e->getMessage();
  }
  $pdo = null;
}

?>