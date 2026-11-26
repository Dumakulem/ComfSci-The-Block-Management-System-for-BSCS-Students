<?php 

function getStudentBlock($StudentID) {
  if ($_SESSION['user_type'] == 'student') {
  try {
  require_once __DIR__ . '/../../config/database.php';
  $pdo = db();
  $sql = "SELECT blockID FROM tblstudent WHERE studentID={$StudentID}"; //Query
  $query = $pdo->query($sql);
  $blockID = $query->fetch();
  return $blockID['blockID'];
  } catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
  }
  }
}
function getCurrentStudentBlock() {
  if ($_SESSION['user_type'] == 'student') {
  try {
  require_once __DIR__ . '/../../config/database.php';
  $pdo = db();
  $sql = "SELECT blockID FROM tblstudent WHERE studentID={$_SESSION['user_id']}"; //Query
  $query = $pdo->query($sql);
  $blockID = $query->fetch();
  return $blockID['blockID'];
  } catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
  }
  }
}

?>