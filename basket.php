<?php
require_once __DIR__."/connect.php";

if (!isset($_SESSION["id"])) {
    header("Location: /login.php");
};

// Получение пользователя
$stmt = $db->prepare("SELECT * FROM `users` WHERE `id` = :id");
$stmt->execute(["id" => $_SESSION["id"]]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$admin = $user["password"] !== null;

// Проверка необходимых значений в POST и GET
if (isset($_POST["id"], $_GET["add"])) {
    // Получение товара из корзины
    $stmt = $db->prepare("SELECT * FROM `basket` WHERE `product_id` = :product_id and `user_id` = :user_id");
    $stmt->execute([
        "user_id" => $_SESSION["id"],
        "product_id" => $_POST["id"]
    ]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    // Если товар есть в корзине
    if ($product) {
        // Обновление данных о товаре в корзине
        $stmt = $db->prepare("UPDATE `basket` SET `count` = :count WHERE `product_id` = :product_id and `user_id` = :user_id");
        $stmt->execute([
            "count" => $product["count"] + (int)$_GET["add"],
            "user_id" => $_SESSION["id"],
            "product_id" => $_POST["id"]
        ]);
    };

    return;
};

// Получение всех товаров в корзине
$stmt = $db->query("SELECT * FROM `basket` WHERE `user_id` = ".$_SESSION["id"]);
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
<body class="basket-list">
    <header>
        <a href="/">Главная</a>
        <?php
        if ($admin) {
            echo '<a href="/admin.php">Админ панель</a>';
        };
        ?>
        <a href="/logout.php">Выход</a>
    </header>
    <div class="product-container">
    <?php
        // Переменная для определения количества товаров в корзине
        $count = 0;

        // Если товары есть
        if (count($products)) {
            // Перебор товаров в корзине
            foreach($products as $basket_item) {
                // Получение товара из списка всех товаров
                $stmt = $db->query("SELECT * FROM `products` WHERE `id` = ".$basket_item["product_id"]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);

                // Если товара не существует или его количество меньше 1, удаляем из корзины
                if ($basket_item["count"] <= 0 || !$product) {
                    $stmt = $db->prepare("DELETE FROM `basket` WHERE `product_id` = :product_id and `user_id` = :user_id");
                    $stmt->execute([
                        "user_id" => $_SESSION["id"],
                        "product_id" => $basket_item["product_id"]
                    ]);
                    continue;
                };

                $count++;
                
                // Вывод блока с продуктом
                echo <<< EOT
                <div class="product" id="{$product["id"]}">
                    <img src="{$product["preview"]}">
                    <div class="info">
                        <div class="price">{$product["price"]} р.</div>
                        <div class="name">{$product["name"]}</div>
                        <div class="bt-container">
                            <button class="reduce">-</button>
                            <span>{$basket_item["count"]}</span>
                            <button class="add">+</button>
                        </div>
                        <div class="total-price">{$product["price"]} р.</div>
                    </div>
                </div>
                EOT;
            };
        };

        if (!$count) {
            echo '<h1 style="position: absolute; text-align: center; width: 100%;">Корзина пустая!</h1>';
        };
    ?>
    </div>
    <?php
        if ($count) echo '<a href="/buy.php" class="buy">Перейти к оплате<span>0 р.</span></a>';
    ?>
    <script>
        let totalPrice = 0;
        let buyLink = document.querySelector(".buy");
        let totalPriceText = buyLink.querySelector("span");

        // Перебор всех блоков с классом product
        document.querySelectorAll(".product").forEach(item => {
            // Получение элемента для вывода количества
            const span = item.querySelector("span");
            // Элемент для вывода цены за товар
            const priceText = item.querySelector(".total-price");

            // Цена за товар
            const price = +item.querySelector(".price").textContent.replace(/[^\d]/gi, "");
            // Количество одного товара
            let count = +span.textContent;

            // Форма для отправки на сервер
            const formData = new FormData();
            formData.append("id", item.id);
            
            // Обработка нажатия на кнопку для уменьшение количества
            item.querySelector(".reduce").onclick = () => {
                // Если количество больше нуля, обновляем конечную цену, обновляем данные на сервере
                if (count > 0) {
                    count--;
                    totalPrice -= price;
                    fetch("/basket.php?add=-1", {method: "POST", body: formData});
                };
                
                // Обновление текста
                priceText.textContent = `${price * count} р.`;
                span.textContent = count;
                totalPriceText.textContent = `${totalPrice} р.`;
                
                buyLink.style.display = totalPrice ? null : "none";
            };

            // Обработка нажатия на кнопку для увеличения количества
            item.querySelector(".add").onclick = () => {
                // Обновление количества и конечной цены
                count++;
                totalPrice += price;

                // Обновление данных на сервере
                fetch("/basket.php?add=1", {method: "POST", body: formData});

                // Обновление текста
                priceText.textContent = `${price * count} р.`;
                span.textContent = count;
                totalPriceText.textContent = `${totalPrice} р.`;

                buyLink.style.display = totalPrice ? null : "none";
            };

            // Подсчёт общей стоимости
            totalPrice += price * count;
            // Цена за товар
            priceText.textContent = `${price * count} р.`;
            // Обновление текста общей стоимости
            totalPriceText.textContent = `${totalPrice} р.`;

            // Если цена положительна, то выводим кнопку покупки
            buyLink.style.display = totalPrice > 0 ? null : "none";
        });
    </script>
</body>
</html>