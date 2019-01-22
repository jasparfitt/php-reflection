<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbName = "php-reflection";
try {
   $db = new PDO("mysql:host=localhost;dbname=php-reflection;charset=utf8mb4", $username, $password);
   // set the PDO error mode to exception
   $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(Exception $e) {
   echo "Connection failed: " . $e->getMessage();
   die();
}
