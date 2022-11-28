<?php
// Открытие сессии
session_start();

// Полкючение к базе с помощью PDO
$db = new PDO("sqlite:db.sqlite3");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
?>