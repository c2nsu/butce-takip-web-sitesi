<?php
$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "butce_db";
$port = 3306;

$conn = new mysqli($servername, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    die("Veritabanı bağlantı hatası: " . $conn->connect_error);
}
