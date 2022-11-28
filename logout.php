<?php
require_once __DIR__."/connect.php";

// Очистка значения ID в сессии
unset($_SESSION["id"]);
// Переход на главную
header("Location: /");

?>