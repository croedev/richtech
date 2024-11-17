<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

// 로그인 체크
if (!isset($_SESSION['user_id'])) {
    header("Location: /login?redirect=order_list");
    exit();
}

$user_id = $_SESSION['user_id'];
$conn = db_connect();

try {
    // 사용자 정보 조회
    $stmt = $conn->prepare("
        SELECT name, email, rank, point 
        FROM users 
        WHERE id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    // 페이징 설정
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 5;
    $offset = ($page - 1) * $limit;

    // 주문 총계 조회
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_orders,
            SUM(quantity) as total_quantity,
            SUM(total_amount) as total_amount,
            SUM(stock) as total_stock,
            SUM(token) as total_token
        FROM orders 
        WHERE user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $totals = $stmt->get_result()->fetch_assoc();

    // 주문 내역 조회
    $stmt = $conn->prepare("
        SELECT o.*, p.name as product_name
        FROM orders o
        JOIN products p ON o.product_id = p.id
        WHERE o.user_id = ?
        ORDER BY o.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("iii", $user_id, $limit, $offset);
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // 총 페이지 수 계산
    $total_pages = ceil($totals['total_orders'] / $limit);

    $pageTitle = '구매내역';
    include __DIR__ . '/../includes/header.php';
} catch (Exception $e) {
    error_log("Order list error: " . $e->getMessage());
    die("오류가 발생했습니다.");
}
?>

<style>
.order-list-container {
    max-width: 1200px;
    margin: 20px auto;
    padding: 0 20px;
}

.summary-card {
    background: linear-gradient(145deg, #1a1a1a, #222);
    border: 1px solid #333;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 30px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    display: flex;
    flex-direction: column;
    row-gap: 0px;
    column-gap:0px;
}

.summary-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    border-bottom: 1px solid #333;
    padding-bottom: 15px;
}

.summary-title {
    color: #d4af37;
    font-size: 1.1rem;
    font-weight: 600;
}

.user-rank {
    background: #d4af37;
    color: #000;
    padding: 5px 15px;
    border-radius: 20px;
    font-weight: bold;
}

.summary-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.stat-item {
    text-align: center;
    padding: 15px;
    background: rgba(212, 175, 55, 0.1);
    border-radius: 8px;
}

.stat-value {
    color: #d4af37;
    font-size:1.1rem;
    font-weight: bold;
    margin-bottom: 3px;
}

.stat-label {
    color: #bdb9b9;
    font-size: 0.8rem;
    font-family: 'Noto Sans KR', sans-serif;
}

.order-cards {
    display: grid;
    gap: 20px;
}

.order-card {
    background: #1a1a1a;
    border: 1px solid #333;
    border-radius: 12px;
    padding: 20px;
    transition: transform 0.2s;
}

.order-card:hover {
    transform: translateY(-2px);
}

.order-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #333;
}

.order-date {
    color: #888;
    font-size: 0.8rem;
}

.order-id {
    color: #d4af37;
    font-weight: bold;
    font-size: 1.0rem;
}

.order-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 2px;
    margin-bottom: 3px;
    font-size: 0.85rem;
}

.detail-item {
    display: flex;
    flex-direction: row;
    gap: 30px;
}

.detail-label {
    color: #888;
    font-size: 0.8rem;
    margin-bottom: 5px;
}

.detail-value {
    color: #fff;
    font-weight: 500;
}

.order-status {
    display: flex;
    justify-content: space-between;
}

.status-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: bold;
}

.status-completed {
    background: #4CAF50;
    color: white;
}

.status-pending {
    background: #FFC107;
    color: black;
}

.pagination {
    display: flex;
    justify-content: center;
    margin-top: 30px;
    gap: 10px;
}

.pagination a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 35px;
    height: 35px;
    border: 1px solid #d4af37;
    border-radius: 50%;
    color: #d4af37;
    text-decoration: none;
    transition: all 0.3s;
}

.pagination a:hover,
.pagination a.active {
    background: #d4af37;
    color: #000;
}

.action-buttons {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.btn {
    padding: 4px 16px;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 0.9rem;
}

.btn-primary {
    background: #d4af37;
    color: #000;
    border: none;
}

.btn-outline {
    background: transparent;
    border: 1px solid #d4af37;
    color: #d4af37;
}

.btn:hover {
    transform: translateY(-2px);
}
</style>



<div class="order-list-container">
    <!-- 요약 정보 -->
    <div class="summary-card">
        <div class="summary-header">
            <div class="summary-title notosans">상품구매 현황</div>
            <div class="user-rank"><?php echo htmlspecialchars($user['rank']); ?></div>
        </div>
        <div class="summary-stats" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
            <div class="stat-item">
                <div class="stat-value"><?php echo number_format($totals['total_orders']); ?>건</div>
                <div class="stat-label">총 구매건수</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">$<?php echo number_format($totals['total_amount']); ?></div>
                <div class="stat-label">총 구매금액</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo number_format($totals['total_stock']); ?>주</div>
                <div class="stat-label">보유 주식</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo number_format($totals['total_token']); ?>개</div>
                <div class="stat-label">보유 토큰</div>
            </div>
        </div>
    </div>

    <!-- 액션 버튼 -->
    <div class="action-buttons flex-x-end">
        <button onclick="location.href='/order'" class="btn btn-outline">
            <i class="fas fa-plus"></i> 추가 구매
        </button>
        <button onclick="location.href='/commission'" class="btn btn-outline">
            <i class="fas fa-chart-line"></i> 수수료 조회
        </button>
    </div>




       <div class="summary-header mt30">
            <div class="summary-title notosans">구매내역</div>
          
        </div>

    <!-- 주문 목록 -->
    <div class="order-cards">
        <?php foreach ($orders as $order): ?>
            <div class="order-card">
                <div class="order-header">
                    <span class="order-id">주문번호: <?php echo $order['id']; ?></span>
                    <span class="order-date"><?php echo date('Y-m-d H:i', strtotime($order['created_at'])); ?></span>
                </div>
                <div class="order-details">
                    <div class="detail-item ">
                        <span class="detail-label">상품명</span>
                        <span class="detail-value"><?php echo htmlspecialchars($order['product_name']); ?></span>
                    </div>
                                        <div class="detail-item">
                        <span class="detail-label">구매수량</span>
                        <span class="detail-value"><?php echo number_format($order['quantity']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">결제금액</span>
                        <span class="detail-value mr10"><?php echo number_format($order['total_amount']); ?></span> USDP
                    </div>

                    <div class="detail-item">
                        <span class="detail-label">지급 주식</span>
                        <span class="detail-value"><?php echo number_format($order['stock']); ?>주</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">지급 토큰</span>
                        <span class="detail-value"><?php echo number_format($order['token']); ?>개</span>
                    </div>
                </div>
                <div class="order-status">
                      <span class="detail-label">처리결과 </span>
                    <span class="status-badge <?php echo $order['status'] === 'completed' ? 'status-completed' : 'status-pending'; ?>">
                        <?php echo $order['status'] === 'completed' ? '처리완료' : '처리중'; ?>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- 페이징 -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>"><i class="fas fa-chevron-left"></i></a>
            <?php endif; ?>

            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <a href="?page=<?php echo $i; ?>" <?php echo $i === $page ? 'class="active"' : ''; ?>><?php echo $i; ?></a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>"><i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>