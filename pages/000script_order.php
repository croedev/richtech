<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$mysqli = new mysqli("localhost", "lidyahkc_0", "lidya2016$", "lidyahkc_rich");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$orderId = 1;
$users = range(2, 30); // 회원 ID 2~30
$products = [1, 2, 3, 4, 5]; // 상품 ID
$success_count = 0;
$failures = [];

$stmt = $mysqli->prepare("
    INSERT INTO orders (`id`, `user_id`, `product_id`, `price_unit`, `quantity`, `total_amount`, `point_used`, `stock`, `token`, `payment_method`, `cash_point_used`, `mileage_point_used`, `payment_date`, `status`, `paid_status`, `currency`, `ip_address`, `updated_at`, `created_at`)
    VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, 'card', 0, 0, NOW(), 'completed', 'pending', 'USD', '127.0.0.1', NOW(), NOW())
");

if (!$stmt) {
    die("SQL preparation failed: " . $mysqli->error);
}

foreach ($users as $userId) {
    // 각 회원이 1~2개의 주문을 생성
    $orderCount = rand(1, 3);
    for ($i = 0; $i < $orderCount; $i++) {
        $productId = $products[array_rand($products)];
        $quantity = rand(1, 5);

        // 상품 정보 가져오기
        switch ($productId) {
            case 1:
                $priceUnit = 100.00;
                $stock = 10;
                $token = 10.00;
                break;
            case 2:
                $priceUnit = 200.00;
                $stock = 20;
                $token = 20.00;
                break;
            case 3:
                $priceUnit = 300.00;
                $stock = 30;
                $token = 30.00;
                break;
            case 4:
                $priceUnit = 400.00;
                $stock = 40;
                $token = 40.00;
                break;
            case 5:
                $priceUnit = 500.00;
                $stock = 50;
                $token = 50.00;
                break;
        }

        $totalAmount = $priceUnit * $quantity;

        // SQL 실행
        $stmt->bind_param(
            "iiiddidd",
            $orderId,
            $userId,
            $productId,
            $priceUnit,
            $quantity,
            $totalAmount,
            $stock,
            $token
        );

        if ($stmt->execute()) {
            $success_count++;
        } else {
            $failures[] = [
                'order_id' => $orderId,
                'error' => $stmt->error
            ];
        }

        $orderId++;
    }
}

$stmt->close();
$mysqli->close();

// 결과 출력
echo "<h2>주문 저장 결과</h2>";
echo "<p>성공적으로 저장된 주문 수: <strong>{$success_count}</strong></p>";

if (!empty($failures)) {
    echo "<h3>실패한 주문</h3>";
    echo "<ul>";
    foreach ($failures as $failure) {
        echo "<li>Order ID: {$failure['order_id']} - Error: {$failure['error']}</li>";
    }
    echo "</ul>";
} else {
    echo "<p>모든 주문이 성공적으로 저장되었습니다.</p>";
}
?>
