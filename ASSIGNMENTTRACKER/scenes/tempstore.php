<?php
session_start();
require_once __DIR__ . '/../database/admin.php';
require_once __DIR__ . '/../database/student.php';
require_once __DIR__ . '/../controllers/AdminCon.php';

$name       = $_SESSION['name'];
$classCode  = $_SESSION['code'];

$adminID = (int)getAdmin($pdo, $name)['adminID'];

$student = getStudent($pdo, getLeaderID($pdo, (int)$_SESSION['gID'])['studentID']);

$assignment = getStudentAssignment($pdo, (int)$_SESSION['currentGroupAsID'], $classCode, $student['studentID']);

$myFiles = getStudentFiles($pdo, (int) $student['studentID'], $assignment['as_title'], $classCode);

(int) $score = null;
$comment = null;
(int) $scoreOver = 100;

$hasFeedback = false;  

$feedbackID = null;// <-- added

if(isAdminHasFeedback($pdo, $assignment['sa_id']) == true){
  $hasFeedback = true;                                  // <-- added
  $f = getFeedback($pdo, $assignment['sa_id']);
  $feedbackID = (int)$f['feedback_id'];
  $score = $f['score'];
  $comment = $f['feedback'];
  $scoreOver = $f['score_over'];
}

if(isset($_POST['backToGroupAssignment'])){

header('Location: AdminCreateGroupAssignment.php');
exit;
}

if(isset($_POST['downloadFile'])){
downloadStudentFile($pdo, (int) $_POST['downloadFile'], $classCode);

header('Location: ' . $_SERVER['PHP_SELF']);   // prevents re-upload on refresh
exit;
}

/*if(isset($_POST['sendFeedback'])){
  $c = $_POST['comment'];
  $s = (int)$_POST['score'];
  $ms = (int)$_POST['maxScore'];

  createFeedback($pdo, $assignment['sa_id'], $adminID, $c, $s, $ms);

  header('Location: ' . $_SERVER['PHP_SELF'] . '#!');   // '#!' replaces any leftover #delete-feedback-modal
  exit;
}
if(isset($_POST['deleteFeedback'])){
  deleteFeedback($pdo, $feedbackID);

  header('Location: ' . $_SERVER['PHP_SELF'] . '#!');   // this is where the stale hash used to survive
  exit;
}*/
?>