<?php
// order_complete.php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/richtech.club/pages/error.log');

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions_bonus.php';

// 로그인 여부 확인
if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if ($order_id <= 0) {
    die("유효하지 않은 접근입니다.");
}

$conn = db_connect();

try {
    // 주문 정보 조회
    $stmt = $conn->prepare("SELECT o.*, p.name AS product_name FROM orders o JOIN products p ON o.product_id = p.id WHERE o.id = ? AND o.user_id = ?");
    if (!$stmt) {
        throw new Exception("주문 조회 오류: " . $conn->error);
    }
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $order_result = $stmt->get_result();
    if ($order_result->num_rows === 0) {
        throw new Exception("주문 정보를 찾을 수 없습니다.");
    }
    $order = $order_result->fetch_assoc();
    $stmt->close();

    //추천수당 계산, 호출
    calculate_referral_bonus($order_id, $conn);

    echo "<h3>주문이 성공적으로 완료되었습니다!</h3>";

} catch (Exception $e) {
    error_log("order_complete.php 오류: " . $e->getMessage());
    echo "<h3>주문 처리 완료되었습니다. 추천수당 계산 중 오류가 발생했습니다.</h3>";
    
}

$pageTitle = '주문 완료';
include __DIR__ . '/../includes/header.php';
?>

<style>
    body { background-color: #000; color: #fff; }
    .order-complete-container {
        max-width: 600px;
        margin: 20px auto;
        padding: 20px;
        background: #111;
        border-radius: 12px;
        border: 1px solid #333;
    }
    .order-header {
        text-align: center;
        margin-bottom: 30px;
    }
    .success-icon {
        color: #4CAF50;
        font-size: 48px;
        margin-bottom: 20px;
    }
    .order-info {
        background: #222;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    .info-row {
        display: flex;
        justify-content: space-between;
        margin: 0 0;
        padding: 4px 0;
        border-bottom: 1px solid #333;
    }
    .info-label {
        color: #aaa;
        font-size: 0.9rem;
    }
    .info-value {
        color: #d4af37;
        font-weight: 500;
    }
    .btn-gold {
        background: linear-gradient(to right, #d4af37, #f2d06b);
        color: #000;
        padding: 12px 24px;
        border: none;
        border-radius: 6px;
        width: 100%;
        font-weight: bold;
        cursor: pointer;
        margin-top: 20px;
    }
    .btn-outline {
        background: transparent;
        color: #d4af37;
        border: 1px solid #d4af37;
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
        margin-right: 10px;
    }
    .btn-outline:hover {
        background: #d4af37;
        color: #000;
    }
</style>

<div class="order-complete-container mx-20">
    <div class="order-header">
        <div class="success-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <h2>결제가 완료되었습니다</h2>
    </div>

    <div class="order-info">
        <div class="info-row">
            <span class="info-label">상품명</span>
            <span class="info-value"><?php echo htmlspecialchars($order['product_name']); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">구매수량</span>
            <span class="info-value"><?php echo number_format($order['quantity']); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">구매금액</span>
            <span class="info-value"><?php echo number_format($order['total_amount'], 2); ?>$</span>
        </div>
        <div class="info-row">
            <span class="info-label">결제금액(USDP)</span>
            <span class="info-value"><?php echo number_format($order['point_used'], 2); ?>USDP</span>
        </div>
        

        <div class="info-row border-t2 mt30">
            <span class="info-label">주식지급</span>
            <span class="info-value"><?php echo number_format($order['stock']); ?>주</span>
        </div>
        <div class="info-row">
            <span class="info-label">토큰지급</span>
            <span class="info-value"><?php echo number_format($order['token'], 2); ?>개</span>
        </div>
        <div class="info-row">
            <span class="info-label">결제일시</span>
            <span class="info-value"><?php echo date('Y-m-d H:i:s', strtotime($order['payment_date'])); ?></span>
        </div>
    </div>

    <div class="action-buttons">
        <button onclick="location.href='/order_list'" class="btn-gold">주문내역 확인</button>
        <div class="btn-group mt-3">
            <button onclick="location.href='/order'" class="btn-outline">추가 구매하기</button>
            <button onclick="location.href='/'" class="btn-outline">홈으로</button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
