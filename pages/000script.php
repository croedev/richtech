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
    // 각 회원이 1~3개의 주문을 생성
    $orderCount = rand(1, 3);
    for ($i = 0; $i < $orderCount; $i++) {
        $productId = $products[array_rand($products)];
        $quantity = rand(1, 2);

        // 상품 정보 가져오기
        switch ($productId) {
            case 1:
                $priceUnit = 500.00;
                $stock = 100;
                $token = 200.00;
                break;
            case 2:
                $priceUnit = 1000.00;
                $stock = 200;
                $token = 400.00;
                break;
            case 3:
                $priceUnit = 2000.00;
                $stock = 400;
                $token = 800.00;
                break;
            case 4:
                $priceUnit = 3000.00;
                $stock = 600;
                $token = 1200.00;
                break;
            case 5:
                $priceUnit = 5000.00;
                $stock = 1000;
                $token = 2000.00;
                break;
            case 6:
                $priceUnit = 7000.00;
                $stock = 1400;
                $token = 2800.00;
                break;
            case 7:
                $priceUnit = 10000.00;
                $stock = 2000;
                $token = 4000.00;
                break;
            case 8:
                $priceUnit = 30000.00;
                $stock = 6000;
                $token = 12000.00;
                break;
            case 9:
                $priceUnit = 50000.00;
                $stock = 10000;
                $token = 20000.00;
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
