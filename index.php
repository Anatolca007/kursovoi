<?php
// Подключение файла
require_once __DIR__."/connect.php";

// Проверка наличие информации о ID в сессии
if (!isset($_SESSION["id"])) {
    // Переход на авторизацию
    header("Location: /login.php");
};

// Формирование SQL запроса, получение пользователя по ID
$stmt = $db->prepare("SELECT * FROM `users` WHERE `id` = :id");
// Вставка данных и выполнение SQL запроса
$stmt->execute(["id" => $_SESSION["id"]]);
// Присваивание user результата запроса
$user = $stmt->fetch(PDO::FETCH_ASSOC);
// Если у пользователя есть пароль, то это администратор
$admin = $user["password"] !== null;

// Проверка наличия ID в POST запросе
if (isset($_POST["id"])) {
    // Получение товара по ID
    $stmt = $db->prepare("SELECT * FROM `products` WHERE `id` = :id");
    $stmt->execute([
        "id" => $_POST["id"]
    ]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Проверка существования товара
    if ($product) {
        // Получение товара в корзине
        $stmt = $db->prepare("SELECT * FROM `basket` WHERE `product_id` = :product_id and `user_id` = :user_id");
        $stmt->execute([
            "user_id" => $_SESSION["id"],
            "product_id" => $_POST["id"]
        ]);
        $basket_item = $stmt->fetch(PDO::FETCH_ASSOC);

        // Если товара в корзине нет добавляем его, если есть удаляем
        if (!$basket_item) {
            $stmt = $db->prepare("INSERT INTO `basket` (`product_id`, `user_id`) VALUES (:product_id, :user_id)");
        } else {
            $stmt = $db->prepare("DELETE FROM `basket` WHERE `product_id` = :product_id and `user_id` = :user_id");
        };

        $stmt->execute([
            "user_id" => $_SESSION["id"],
            "product_id" => $_POST["id"]
        ]);
    };
};

// Если в GET запросе есть search, то формируем SQL запрос с поиском по имени
if (isset($_GET["search"])) {
    $stmt = $db->query("SELECT * FROM `products` WHERE `name` like '%".$_GET["search"]."%'");
} else {
    $stmt = $db->query("SELECT * FROM `products`");
};
$products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Бонусная система</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body style="display: block;">
    <header>
        <a href="/basket.php">Корзина</a>
        <?php
        // Если пользователь администратор, добавляем ссылку на админку
        if ($admin) {
            echo '<a href="/admin.php">Админ панель</a>';
        };
        ?>
        <a href="/logout.php">Выход</a>
    </header>
    <form style="display: flex; flex-direction: row; gap: 10px; margin: 15px auto 0 auto; width: 100%; max-width: 600px;">
        <input type="search" name="search" value="<?php echo $_GET["search"] ?? "" ?>" placeholder="Поиск">
        <input type="submit" value="Поиск">
    </form>
    <div class="product-container">
    <?php
        // Если товары есть
        if (count($products)) {
            // Перебор всех товаров
            foreach($products as $product) {
                // Запрос товара в корзине
                $stmt = $db->prepare("SELECT * FROM `basket` WHERE `product_id` = :product_id and `user_id` = :user_id");
                $stmt->execute([
                    "product_id" => $product["id"],
                    "user_id" => $_SESSION["id"]
                ]);
                $basket_item = $stmt->fetch(PDO::FETCH_ASSOC);

                // Если товара в корзине есть
                if ($basket_item) {
                    // Если количество одного товара равно или меньше нуля удаляем запись из базы
                    if ($basket_item["count"] <= 0) {
                        $stmt = $db->prepare("DELETE FROM `basket` WHERE `product_id` = :product_id and `user_id` = :user_id");
                        $stmt->execute([
                            "user_id" => $_SESSION["id"],
                            "product_id" => $product["id"]
                        ]);
                        $basket_item = null;
                    };
                };

                // Если есть товар в корзине, оставляем значение пустым, дальше нужно для добавления имени класса
                $add = ($basket_item) ? "" : "add";

                // Если администратор, то добавляем кнопку редактировать товар
                $edit = $admin ? '<a href="/admin.php?id='.$product["id"].'">Редактировать товар</a>' : "";

                echo <<< EOT
                <div class="product">
                    <img src="{$product["preview"]}">
                    <div class="info">
                        <div class="price">{$product["price"]} р.</div>
                        <div class="name">{$product["name"]}</div>
                        <button id="{$product['id']}" class="{$add}">Добавить в корзину</button>
                        {$edit}
                    </div>
                </div>
                EOT;
            };
        } else {
            echo '<h1 class="admin-info">Товары не найдены!</h1>';
        };
    ?>
    </div>
    <a href="/basket.php" class="buy">Корзина</a>
    <script>
        // Количество товаров
        let count = 0;
        // Получение кнопку по классу
        let btBuy = document.querySelector(".buy");
        // Скрытие кнопки
        btBuy.style.display = "none";

        // Перебор кнопок со всех блоков с классом product
        document.querySelectorAll(".product button").forEach(bt => {
            // Если у кнопки есть класс add, изменяем текст кнопки
            // Если у кнопки нету класса, значит показываем кнопку для перехода в корзину
            if (bt.className.includes("add")) {
                bt.textContent = "Добавить в корзину";
            } else {
                count++;
                btBuy.style.display = null;
                bt.textContent = "Убрать из корзины";
            };

            // Создание формы для отправки запроса на сервер
            const formData = new FormData();
            // Добавление ID в форму
            formData.append("id", bt.id);

            bt.onclick = () => {
                // Запрет на нажатие кнопки
                bt.disabled = true;

                // Отправка данных на сервер
                fetch("/", {method: "POST", body: formData})
                    .then(response => bt.disabled = false); // После получения ответа, разрешаем нажатие на кнопку
                
                // Если есть класс add, то удаляем, если нет, то задаём
                bt.classList.toggle("add");

                if (bt.className.includes("add")) {
                    count--;
                    bt.textContent = "Добавить в корзину";
                } else {
                    count++;
                    bt.textContent = "Убрать из корзины";
                };

                // Если количество товаров не равно нулю, то показываем кнопку для перехода в корзину
                btBuy.style.display = count ? null : "none";
            };
        });
    </script>
</body>
</html>