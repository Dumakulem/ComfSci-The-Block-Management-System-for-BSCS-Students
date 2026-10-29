<?php

require_once "../SQL/actions.php";
    
    $error = "";// we add errors gagamitin if ever hindi valid and account.

    // for explanation lang, Ang nangyayari lang dito is to check kung yung REQUEST_METHOD ay Post which is nasa loginPage.php naten sa form. Then, nagset lng tayo ng variables para sa $_POST, sa if !empty, kung hindi empty then proceed tas gagamitin and student function to login, under that i-che-check muna kung and role ba ay student or admin, if student then it will redirect to studentPage.php, if admin then it will redirect to adminPage.php, if hindi valid then it will display error na "Unauthorized user!".

    if ($_SERVER['REQUEST_METHOD'] === 'POST'){
        $email = $_POST['email'];
        $password = $_POST['password'];

        if (!empty($email) && !empty($password)){
            $user = new User();
            $student = $user->studentLogIn($email, $password);

            if (isset($student)){
                $_SESSION['studentEmail'] = $student['studentEmail'];
                $_SESSION['role'] = 'student';

                header('Location: ../Pages/studentPage.php');
                exit();
            }
            else {
                $admin = $user->adminLogIn($email, $password);

                if (isset($admin)){
                    $_SESSION['adminEmail'] = $admin['adminEmail'];
                    $_SESSION['role'] = 'admin';

                    header('Location: ../Pages/adminPage.php');
                    exit();
                } else {
                    $error = "Unauthorized user!";
                }
            }
        }   
 }


?>