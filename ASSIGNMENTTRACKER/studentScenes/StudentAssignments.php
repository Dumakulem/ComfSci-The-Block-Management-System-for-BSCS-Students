<?php
session_start();
$id = (int) $_SESSION['currentStudent'];

echo "SUCCESS!";
echo $id;

?>