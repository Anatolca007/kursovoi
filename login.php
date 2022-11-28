<?php
require_once __DIR__."/connect.php";

if (isset($_SESSION["id"])) {
    header("Location: /");
};

// Проверка необходимых значений POST
if (isset($_POST["telephone"])) {
    // Проверка что телефон имеет 11 символов
    if (strlen($_POST["telephone"]) == 11) {
        // Запрос пользователя
        $stmt = $db->prepare("SELECT * FROM `users` WHERE `telephone` = :telephone");
        $stmt->execute(["telephone" => $_POST["telephone"]]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Если пользователь найден, сохраняем ID в сессии
        // Иначе, создаём пользователя и сохраняем ID в сессии
        if ($user) {
            $_SESSION["id"] = $user["id"];
        } else {
            // Создание пользователя в БД
            $stmt = $db->prepare("INSERT INTO `users` (`telephone`) VALUES (:telephone)");
            $stmt->execute(["telephone" => $_POST["telephone"]]);

            // Запрос пользователя и сохранение ID в сессии
            $stmt = $db->prepare("SELECT * FROM `users` WHERE `telephone` = :telephone");
            $stmt->execute(["telephone" => $_POST["telephone"]]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $_SESSION["id"] = $user["id"];
        };

        // Переход на главную
        header("Location: /");
    };
};
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Бонусная система</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <form action="" method="POST">
        <label>Введите номер телефона</label>
        <input type="tel" name="telephone" id="telephone" placeholder="Телефон" value="8" maxlength="11">
        <input type="submit" value="Войти" id="submit" disabled>
    </form>

    <script>
        let input = document.querySelector("#telephone");
        let submut = document.querySelector("#submit");

        const adminValue = "00000000000";

        // Обработка ввода в input
        input.oninput = (e) => {
            // Запоминаем позицию курсора
            const pointer = input.selectionStart;

            // Если значение input пустое, присваиваем значение 8
            // Если в номере все 0, то перебрасываем на вход в админ панель
            if (input.value.length < 1) input.value = "8";
            else if (input.value === adminValue) window.location = "/admin.php";

            // Удаление всех символов кроме чисел
            input.value = input.value.replace(/[^\d]/gi, "");
            // Блокировка кнопки отправки если значение не равно 11
            submut.disabled = !(input.value.length == 11) && input.value !== adminValue;

            // Возрат позиции курсора, нужно для того чтобы при изменении значения курсор не был в конце
            input.selectionStart = pointer;
            input.selectionEnd = pointer;
        };
        input.oninput();
    </script>
</body>
</html>