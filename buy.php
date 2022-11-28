<?php
require_once __DIR__."/connect.php";

if (!isset($_SESSION["id"])) {
    header("Location: /auth.php");
};

// Запрос пользователя по ID
$stmt = $db->prepare("SELECT * FROM `users` WHERE `id` = :id");
$stmt->execute(["id" => $_SESSION["id"]]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Сколько нужно раз оплатить чек для возможности оплаты бонусами
$need_purchases_count = 15;
// Процент скидки
$percent = 10;
// Цена
$price = 0;

// Запрос товаров в корзине
$stmt = $db->query("SELECT * FROM `basket` WHERE `user_id` = ".$_SESSION["id"]);
$products = $stmt->fetchAll();

// Перебор товаров из корзины и формирование цены
foreach($products as $product) {
    $stmt = $db->prepare("SELECT * FROM `products` WHERE `id` = :id");
    $stmt->execute([
        "id" => $product["product_id"]
    ]);
    $price += $product["count"] * $stmt->fetch(PDO::FETCH_ASSOC)["price"];
};

// Если цена равна или меньше нуля переход к корзине
if ($price <= 0) {
    header("Location: /basket.php");
};

// Бонусы
$bonuses = ($price * $percent) / 100;

// Если POST запрос с ценой
if (isset($_POST["price"])) {
    // Перевод значения в float
    $price = (float)$_POST["price"];
    // Добавляем бонусы
    $bonuses = $user["bonuses"] + (($price * $percent) / 100);

    // Увиличение количество оплаченных покупок
    $purchases_count = $user["purchases_count"] + 1;

    // Если количество покупок больше или равно необходимому, сбрасываем бонусы и количество покупок
    if ($purchases_count >= $need_purchases_count + 1) {
        $purchases_count = 0;
        $bonuses = 0;
    };

    // Обновление данных пользователя
    $stmt = $db->prepare("UPDATE `users` SET `purchases_count` = :purchases_count, `bonuses` = :bonuses WHERE `id` = :id");
    $stmt->execute([
        "id" => $user["id"],
        "purchases_count" => $purchases_count,
        "bonuses" => $bonuses
    ]);

    // Очистка корзины
    foreach($products as $product) {
        $stmt = $db->prepare("DELETE FROM `basket` WHERE `product_id` = :product_id and `user_id` = :user_id");
        $stmt->execute([
            "user_id" => $product["user_id"],
            "product_id" => $product["product_id"]
        ]);
    };

    // Вывод страницы после оплаты заказа
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
        <h1>Оплата прошла успешно!</h1>
        <script>
            setTimeout(() => {
                document.location.href = "/logout.php";
            }, 3000);
        </script>
    </body>
    </html>
    EOT;
    return;
};

// Необходимое количество покупок до возможности использования бонусов 
$purchases_count = $need_purchases_count - $user["purchases_count"];
$purchases_count_text = "Оплатите ещё $purchases_count раз, чтобы потратить бонусы";

// Склонение
if ($purchases_count % 10 >= 2 && $purchases_count % 10 <= 4) {
    $purchases_count_text = "Оплатите ещё $purchases_count раза, чтобы потратить бонусы";
};

// Если в данной покупке можно использовать бонусы, присваиваем соответствующий текст
if ($purchases_count == 0) {
    $purchases_count_text = "Вы можете потрать бонусы!";
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
        <input type="hidden" name="price" id="price" placeholder="Телефон" value="<?php echo $price ?>" readonly>
        <p class="price">К оплате: <?php echo $price ?> р.</p>
        <?php
        if (!$purchases_count) {
            echo "<button>Использовать бонусы</button>";
        };
        ?>
        <input type="submit" value="Оплатить">
        <a href="/basket.php">Отмена</a>
        <span class="add-bonuses"><?php
        if ($purchases_count == 0) {
            echo "После оплаты бонусы сгорят!";
        } else {
            echo "Бонусов будет зачислено: $bonuses";
        };
        ?></span>
        <span class="bonuses-info">Ваши бонусы: <?php echo $user["bonuses"] ?><br><?php echo $purchases_count_text ?></span>
    </form>

    <script>
        let priceInput = document.querySelector("#price");
        let priceText = document.querySelector(".price");
        let bonusesText = document.querySelector(".bonuses-info");
        let bt = document.querySelector("button");
        const bonuses = <?php echo $user["bonuses"] ?>;

        if (bt) {
            // Обработка нажатия на кнопку использования бонусов
            bt.onclick = () => {
                // Вывод соответствующего текста для остатка по бонусам и обновление цены
                if (+priceInput.value - bonuses > 0) {
                    priceInput.value = +priceInput.value - bonuses;
                    bonusesText.textContent = "Ваши бонусы: 0";
                } else {
                    bonusesText.textContent = `Ваши бонусы: ${bonuses - (+priceInput.value)}`;
                    priceInput.value = 0;
                };
                priceText.textContent = `К оплате: ${priceInput.value} р.`;
                // Удаление кнопки
                bt.remove();
            };
        };
    </script>
</body>
</html>