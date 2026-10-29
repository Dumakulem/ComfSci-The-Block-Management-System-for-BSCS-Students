<?php

// eto database connection.

class Database {
    private $host = "localhost";
    private $dbname = "userdb";
    private $username = "root";
    private $pwd = "";
    public $conn = null;

    public function connect() {
        $this->conn = null;

        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4";// nag assign lng tayo, kumbaga preparation.

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]; // dito for PDO like yung default nila para hindi na mag fetch(PDO::FETCH_ASSOC) lagi. And yung error which is nasa baba.

            $this->conn = new PDO($dsn, $this->username, $this->pwd, $options);// dito naten ginamit yung inassign natin kanina to connect sa database.
        } catch (PDOException $e) {
            echo "Connection Error: " . $e->getMessage();// eto yung error na sinasabi ko.
        }
        return $this->conn;
    }
}

?>