<?php 
require_once __DIR__ . '/../../config/database.php';
/* 

This php file stores methods that allow you to query the database
for data about the current student using the platform.

*/

// Simple Error Output on PDOException, modifiable in the future to apply to all methods
function pdoException($e) {
  echo "Error: " . $e->getMessage();
}

function userIsStudent() {
  if ($_SESSION['user_type'] == 'student') {
    return true;
  } else { return false; }
}

/*
Returns a boolean, true if the user logged in is a Student and a 
Block Representative, false if not.
*/
function checkIfClassRep() {
  if (userIsStudent()) {
    try {
    $pdo = db();
    $sql = "SELECT isClassRep FROM tblstudent WHERE studentID={$_SESSION['user_id']}";
    $pstmt = $pdo->query($sql);
    $isRep = $pstmt->fetch();
    if ($isRep == 1) {
      return true;
    } else {
      return false;
    }

    } catch (PDOException $e) {
      pdoException($e);
    }
  }
}

//This one has a parameter, it returns the Block of the Student
function getStudentBlock($StudentID) {
  if (userIsStudent()) {
  try {
  $pdo = db();
  $sql = "SELECT blockID FROM tblstudent WHERE studentID={$StudentID}"; //Query
  $query = $pdo->query($sql);
  $blockID = $query->fetch();
  return $blockID['blockID'];
  } catch (PDOException $e) {
    pdoException($e);
  }
  }
}

//This one just returns the Block of the Student currently logged in
function getCurrentStudentBlock() {
  if ($_SESSION['user_type'] == 'student') {
  try {
  $pdo = db();
  $sql = "SELECT blockID FROM tblstudent WHERE studentID={$_SESSION['user_id']}"; //Query
  $query = $pdo->query($sql);
  $blockID = $query->fetch();
  return $blockID['blockID'];
  } catch (PDOException $e) {
    pdoException($e);
  }
  }
}

?>