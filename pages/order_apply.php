<?php
// order_apply.php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/richtech.club/pages/error.log');

session_start();
require_once __DIR__ . '/../includes/config.php';

// 로그인 여부 확인
if (!isset($_SESSION['user_id'])) {
    header("Location: /login?redirect=order_apply&id=" . ($_GET['id'] ?? ''));
    exit();
}

// 상품 ID 검증
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: /order");
    exit();
}

$conn = db_connect();
$product_id = intval($_GET['id']);
$user_id = intval($_SESSION['user_id']);

try {
    // 상품 정보 조회
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    if (!$stmt) {
        throw new Exception("상품 조회 오류: " . $conn->error);
    }
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $product_result = $stmt->get_result();
    if ($product_result->num_rows === 0) {
        throw new Exception("상품을 찾을 수 없습니다.");
    }
    $product = $product_result->fetch_assoc();
    $stmt->close();

    // 사용자 포인트 조회
    $stmt = $conn->prepare("SELECT point FROM users WHERE id = ?");
    if (!$stmt) {
        throw new Exception("사용자 정보 조회 오류: " . $conn->error);
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();
    if ($user_result->num_rows === 0) {
        throw new Exception("사용자 정보를 찾을 수 없습니다.");
    }
    $user = $user_result->fetch_assoc();
    $user_point = $user['point'];
    $stmt->close();
} catch (Exception $e) {
    error_log("order_apply.php 오류: " . $e->getMessage());
    die("오류가 발생했습니다. 관리자에게 문의하세요.");
}

$pageTitle = '상품 결제';
include __DIR__ . '/../includes/header.php';
?>

<style>
    body {
        background-color: #000;
        color: #fff;
        font-family: 'Noto Sans KR', sans-serif;
    }

    .payment-card {
        background-color: #1a1a1a;
        border: 1px solid #333;
        border-radius: 12px;
        overflow: hidden;
        margin: 10px auto;
        padding: 15px;
        max-width: 600px;
    }

    .product-image {
        width: 100%;
        max-width: 250px;
        margin: 0 auto 15px;
        display: block;
        border-radius: 8px;
    }

    .product-info {
        font-size: 0.95rem;
        margin-bottom: 10px;
    }

    .amount-control {
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 10px 0;
        gap: 8px;
    }

    .amount-btn {
        background: #333;
        color: #fff;
        border: none;
        width: 35px;
        height: 35px;
        border-radius: 18px;
        font-size: 1.1rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .amount-btn:hover {
        background: #444;
    }

    .amount-input {
        width: 70px;
        text-align: center;
        background: #222;
        border: 1px solid #444;
        color: #fff;
        padding: 6px;
        border-radius: 4px;
        font-size: 1rem;
    }

    .payment-summary {
        background: #222;
        padding: 15px;
        border-radius: 8px;
        margin: 15px 0;
    }

    .summary-row {
        display: flex;
        justify-content: space-between;
        margin: 8px 0;
        font-size: 0.85rem;
        color: #ccc;
    }

    .highlight {
        font-size: 1.1rem;
        color: #d4af37;
        font-weight: bold;
    }

    .btn-gold {
        background: linear-gradient(to right, #d4af37, #f2d06b);
        color: #000;
        border: none;
        padding: 12px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: bold;
        width: 100%;
        margin-top: 15px;
        font-size: 1rem;
        transition: all 0.3s ease;
    }

    .btn-gold:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(212, 175, 55, 0.3);
    }

    .insufficient {
        color: #ff6b6b;
        text-align: center;
        margin-top: 8px;
        font-size: 0.9rem;
    }
</style>

<div class="container mt-3 mb100">
    <?php
    // 에러 메시지 표시
    if (isset($_SESSION['error_message'])) {
        echo '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
        unset($_SESSION['error_message']);
    }
    ?>
    <div class="payment-card">
        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
        <div class="product-info">
            <h3 class="text-center mb-3"><?php echo htmlspecialchars($product['name']); ?></h3>

            <div class="mx-20 radius50 bg-yellow" style="border:1px solid orange;">
                <div class="summary-row flex-y-center" style="padding: 0 20px;">
                    <span>price</span>
                    <span class="highlight notoserif fw-bold rem-11"><?php echo number_format($product['price'], 0); ?> <span class="btn btn10 bg-gray90">USDP</span></span>
                </div>
            </div>

            <div class="amount-control">
                <button type="button" class="amount-btn" onclick="updateQuantity(-1)">-</button>
                <input type="number" id="quantity" name="quantity" value="1" min="1" class="amount-input" onchange="updateTotal()">
                <button type="button" class="amount-btn" onclick="updateQuantity(1)">+</button>
            </div>
            <div class="payment-summary">
                <div class="summary-row">
                    <span>총 결제금액:</span>
                    <span class="highlight" id="totalAmount"><?php echo number_format($product['price'], 0); ?> USDP</span>
                </div>
                <div class="summary-row">
                    <span class="notoserif">보유 포인트(USDP):</span>
                    <span class="text-red7"><i class="fas fa-coins"></i> <?php echo number_format($user_point, 1); ?> USDP</span>
                </div>
                <hr style="border-color:#ff333;margin:8px 0">
                <div class="summary-row">
                    <span class="notoserif">결제 후 잔액:</span>
                    <span id="remainingPoint" style="color:#00ff00;"><i class="fas fa-coins"></i> <?php echo number_format($user_point - $product['price'], 1); ?> USDP</span>
                </div>
            </div>
            <form action="/order_process" method="post" id="orderForm">
                <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                <input type="hidden" name="quantity" id="order_quantity" value="1">
                <?php if ($user_point >= $product['price']): ?>
                    <button type="submit" class="btn-gold" onclick="return confirm('결제를 진행하시겠습니까?');">결제하기</button>
                <?php else: ?>
                    <p class="insufficient">포인트가 부족합니다. 충전 후 이용해주세요.</p>
                    <button type="button" class="btn-gold" onclick="location.href='/deposits'">포인트 충전하기</button>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<script>
    function updateQuantity(change) {
        const quantityInput = document.getElementById('quantity');
        const newValue = Math.max(1, parseInt(quantityInput.value) + change);
        quantityInput.value = newValue;
        updateTotal();
    }

    function updateTotal() {
        const quantity = parseInt(document.getElementById('quantity').value);
        const price = <?php echo $product['price']; ?>;
        const userPoint = <?php echo $user_point; ?>;
        const totalAmount = quantity * price;
        const remaining = userPoint - totalAmount;
        document.getElementById('totalAmount').textContent = numberFormat(totalAmount.toFixed(2)) + ' USDP';
        document.getElementById('remainingPoint').textContent = numberFormat(remaining.toFixed(2)) + ' USDP';
        document.getElementById('order_quantity').value = quantity;
        const submitBtn = document.querySelector('.btn-gold[type="submit"]');
        if (submitBtn) {
            if (remaining < 0) {
                submitBtn.style.display = 'none';
                document.querySelector('.insufficient').style.display = 'block';
            } else {
                submitBtn.style.display = 'block';
                document.querySelector('.insufficient').style.display = 'none';
            }
        }
    }

    function numberFormat(num) {
        return new Intl.NumberFormat().format(num);
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
