<?php

require_once "database.php";

class User {
    private $conn;
    private $error;

    public function __construct(){
        $database = new Database();
        $this->conn = $database->connect();
    }// ginawa ito ni sir marlon.
    
    //!!!!!!!!!!!!!!!!!!!!!!!!!     STUDENT ACTIONS       !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!

    public function nameTagStudent(){
        $stmt = $this->conn->query("SELECT * FROM studenttbl");
        return $stmt->fetchAll();
    }// to display the email of the user specifically the student.

    // public function roleIdentify($studentRole){
    //     $stmt = $this->conn->prepare("SELECT studentRole FROM studenttbl WHERE studentRole = ?");
    //     $stmt->execute([$studentRole]);
    //     return $stmt->fetch();
    // }

    public function studentLogIn($email, $password){
        $stmt = $this->conn->prepare("SELECT studentEmail, studentPassword FROM studenttbl WHERE studentEmail = ?");
        $stmt->execute([$email]);
        $student = $stmt->fetch();

        if (isset($student)){
            if ($student && password_verify($password, $student['studentPassword'])){
                unset($student['studentPassword']);
                return $student;
            }
        }
    }// for student lng din to para sa login.

    public function repDelete($email){
        $stmt = $this->conn->prepare("DELETE FROM studenttbl WHERE studentEmail = ?");
        $stmt->execute([$email]);
    }// eto sa representative lng to para sa delete ng student.


    //!!!!!!!!!!!!!!!!!!!!!!!!!     ADMIN ACTIONS       !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!

    public function nameTagAdmin(){
        $stmt = $this->conn->query("SELECT adminEmail FROM admintbl");
        return $stmt->fetch();

    }// eto sa admin nmn.

    public function adminLogIn($email, $password){
        $stmt = $this->conn->prepare("SELECT adminEmail, adminPassword FROM admintbl WHERE adminEmail = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if (isset($admin)){
            if ($admin && password_verify($password, $admin['adminPassword'])){
                unset($admin['adminPassword']);
                return $admin;
            } 

        }
    }// admin login for admin only may condition siya nasa loginChecker.php
}




?>