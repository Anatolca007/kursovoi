<?php
require_once __DIR__."/connect.php";

// Создание переменной для пользователя
$user = null;

// Проверка наличие информации о ID в сессии
if (isset($_SESSION["id"])) {
    // Запрос пользователя по ID
    $stmt = $db->prepare("SELECT * FROM `users` WHERE `id` = :id");
    $stmt->execute(["id" => $_SESSION["id"]]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Если у пользователя нет пароля переводим на главную
    if ($user["password"] === null) {
        header("Location: /");
    } else if (isset($_POST["password"])) {
        header("Location: /admin.php");
    };
} else {
    $admin = false;
    // Если POST запрос имеет данные о password
    if (isset($_POST["password"])) {
        // Запрос пользователя по имени админа
        $stmt = $db->query("SELECT * FROM `users` WHERE `telephone` = 'admin'");
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Проверка совпадения пароля
        if (password_verify($_POST["password"], $user["password"])) {
            $_SESSION["id"] = $user["id"];
            $admin = true;
        } else {
            echo "<div class='admin-info'>Неверный пароль!</div>";
        };
    };

    // Если пароль не совпал с существующим, вывод страницы авторизации для администратора
    if (!$admin) {
        echo <<< EOT
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
                <input type="password" name="password" placeholder="Пароль" required>
                <input type="submit" value="Войти">
            </form>
        </body>
        </html>
        EOT;
        return;
    };
};

// Если данный запрос это - POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Проверка наличия в POST name и price
    if (isset($_POST["name"], $_POST["price"])) {
        // Проверка наличия в GET ID
        if (isset($_GET["id"])) {
            // Добавления в POST ID, для передачи в SQL запрос
            $_POST["id"] = $_GET["id"];
            // Если картинка передана
            if (isset($_FILES["preview"]) && $_FILES["preview"]["error"] == 0) {
                // Добавляем путь до места сохранения картинки 
                $_POST["preview"] = "/uploads/".bin2hex(random_bytes(2)).$_FILES["preview"]["name"];
                // Сохранение картинки
                move_uploaded_file($_FILES["preview"]["tmp_name"], __DIR__.$_POST["preview"]);

                // Обновление данных о товаре
                $stmt = $db->prepare("UPDATE `products` SET `name` = :name, `price` = :price, `preview` = :preview WHERE `id` = :id");
                $stmt->execute($_POST);
            } else {
                // Обновление данных о товаре
                $stmt = $db->prepare("UPDATE `products` SET `name` = :name, `price` = :price WHERE `id` = :id");
                $stmt->execute($_POST);
            };

            echo "<div class='admin-info'>Запись успешно изменена!</div>";
        } else {
            if (isset($_FILES["preview"])) {
                $_POST["preview"] = "/uploads/".bin2hex(random_bytes(2)).$_FILES["preview"]["name"];
                move_uploaded_file($_FILES["preview"]["tmp_name"], __DIR__.$_POST["preview"]);

                // Добавление данных о товаре
                $stmt = $db->prepare("INSERT INTO `products` (`name`, `price`, `preview`) VALUES (:name, :price, :preview)");
                $stmt->execute($_POST);

                echo "<div class='admin-info'>Запись успешно добавлена!</div>";
            } else {
                echo "<div class='admin-info'>Заполните все поля!</div>";
            };
        };
    } else if (!isset($_POST["password"])) {
        echo "<div class='admin-info'>Заполните все поля!</div>";
    };
};

// Если в GET запросе есть remove, удаление товара
if (isset($_GET["remove"])) {
    $stmt = $db->prepare("DELETE FROM `products` WHERE `id` = :id");
    $stmt->execute([
        "id" => $_GET["remove"]
    ]);
    echo "<div class='admin-info'>Товар успешно удалён!</div>";
};

$product = [];

// Проверка существования товара по ID
if (isset($_GET["id"])) {
    $stmt = $db->query("SELECT * FROM `products` WHERE `id` = ".$_GET["id"]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        echo "<div class='admin-info'>Товар не найден!</div>";
    };
};
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ панель</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body class="admin-panel">
    <a href="/">Список товаров</a>
    <form enctype="multipart/form-data" action="" method="POST">
        <input type="text" name="name" value="<?php echo $product["name"] ?? "" ?>" placeholder="Название товара" required>
        <input type="number" name="price" value="<?php echo $product["price"] ?? "" ?>" placeholder="Цена" min="0" required>
        <input type="file" name="preview" id="preview" accept="image/*">
        <input type="submit" value="<?php echo((isset($product["name"])) ? "Изменить" : "Создать") ?>">
        <?php
        // Если товар есть, добавления кнопки для удаления
        if (isset($product["name"])) {
            echo('<a href="/admin.php?remove='.$product["id"].'">Удалить</a>');
        };
        ?>
    </form>
</body>
</html>