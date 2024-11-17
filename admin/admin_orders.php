<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/richtech.club/pages/error.log');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/admin_functions.php';

// 관리자 권한 체크
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_id'], [1, 2])) {
    header("Location: /login?redirect=admin/admin_orders");
    exit;
}

$conn = db_connect();

// 상단 통계 데이터 조회
function getStatistics($conn, $period = null) {
    $where = "";
    if ($period === 'today') {
        $where = "WHERE DATE(created_at) = CURDATE()";
    } else if ($period === 'month') {
        $where = "WHERE DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
    }

    $sql = "SELECT 
                COUNT(*) as total_orders,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
                IFNULL(SUM(total_amount), 0) as total_amount,
                IFNULL(SUM(quantity), 0) as total_quantity,
                SUM(CASE WHEN status = 'completed' AND 
                    (paid_referral = 'pending' OR 
                     paid_status = 'pending' OR 
                     paid_center = 'pending') 
                    THEN 1 ELSE 0 END) as pending_settlement
            FROM orders " . $where;

    $result = $conn->query($sql);
    if (!$result) {
        error_log("Statistics query failed: " . $conn->error);
        return null;
    }

    return $result->fetch_assoc();
}

// 검색 조건 설정
$search_term = isset($_GET['search_term']) ? trim($_GET['search_term']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$payment_method = isset($_GET['payment_method']) ? trim($_GET['payment_method']) : '';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

// WHERE 절 생성
$where = [];
$params = [];
$types = '';

if ($search_term) {
    $where[] = "(u.name LIKE ? OR u.login_id LIKE ? OR o.id LIKE ?)";
    $search_param = "%{$search_term}%";
    array_push($params, $search_param, $search_param, $search_param);
    $types .= 'sss';
}

if ($status) {
    $where[] = "o.status = ?";
    $params[] = $status;
    $types .= 's';
}

if ($payment_method) {
    $where[] = "o.payment_method = ?";
    $params[] = $payment_method;
    $types .= 's';
}

if ($start_date) {
    $where[] = "DATE(o.created_at) >= ?";
    $params[] = $start_date;
    $types .= 's';
}

if ($end_date) {
    $where[] = "DATE(o.created_at) <= ?";
    $params[] = $end_date;
    $types .= 's';
}

$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// 페이지네이션 설정
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$items_per_page = 50;
$offset = ($page - 1) * $items_per_page;

// 정렬 설정
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$direction = isset($_GET['direction']) && strtoupper($_GET['direction']) === 'ASC' ? 'ASC' : 'DESC';
$allowed_sort_columns = ['id', 'created_at', 'total_amount', 'quantity', 'status'];
if (!in_array($sort, $allowed_sort_columns)) {
    $sort = 'created_at';
}

// 총 레코드 수 조회
$count_sql = "SELECT COUNT(*) as total FROM orders o 
              LEFT JOIN users u ON o.user_id = u.id 
              $where_clause";

if ($params) {
    $stmt = $conn->prepare($count_sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total_records = $stmt->get_result()->fetch_assoc()['total'];
} else {
    $total_records = $conn->query($count_sql)->fetch_assoc()['total'];
}

$total_pages = ceil($total_records / $items_per_page);

// 주문 데이터 조회
$sql = "SELECT 
            o.*,
            u.name as user_name,
            u.login_id as user_login_id,
            p.name as product_name
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        LEFT JOIN products p ON o.product_id = p.id
        $where_clause
        ORDER BY o.$sort $direction
        LIMIT $items_per_page OFFSET $offset";

if ($params) {
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("Query prepare failed: " . $conn->error);
        die("데이터베이스 오류가 발생했습니다.");
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

if ($result === false) {
    error_log("Query execution failed: " . $conn->error);
    die("데이터베이스 오류가 발생했습니다.");
}

$orders = $result->fetch_all(MYSQLI_ASSOC);

// 통계 데이터 조회
$today_stats = getStatistics($conn, 'today');
$month_stats = getStatistics($conn, 'month');
$all_stats = getStatistics($conn);

// 뷰 포함
include 'admin_header.php';
?>

<!-- 여기서부터는 HTML/CSS 코드가 시작됩니다 -->
<style>
/* 스타일 코드는 이전과 동일하게 유지 */
.stats-container {
    font-size: 0.8rem;
    margin-bottom: 1rem;
}

.stat-card {
    padding: 0.5rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-bottom: 0.5rem;
}

.stat-value {
    font-size: 1rem;
    font-weight: 600;
    color: #2c3e50;
}

.stat-label {
    font-size: 0.7rem;
    color: #7f8c8d;
}

.table th, .table td {
    padding: 0.3rem;
    font-size: 0.75rem;
    vertical-align: middle;
}

.search-form {
    background: #f8f9fa;
    padding: 0.5rem;
    border-radius: 4px;
    margin-bottom: 1rem;
}

.btn-xs {
    padding: 0.1rem 0.3rem;
    font-size: 0.7rem;
}

.pagination {
    margin: 0;
    padding: 0;
}

.pagination .page-link {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}
</style>

<div class="container-fluid px-2">
    <!-- 통계 섹션 -->
    <div class="row stats-container">
        <div class="col-md-4">
            <div class="card stat-card">
                <h6 class="card-title text-muted mb-2">오늘 주문현황</h6>
                <div class="row g-0">
                    <div class="col-4 border-end">
                        <div class="text-center">
                            <div class="stat-value text-primary">
                                <?php echo number_format($today_stats['total_orders']); ?>
                            </div>
                            <div class="stat-label">총 주문</div>
                        </div>
                    </div>
                    <div class="col-4 border-end">
                        <div class="text-center">
                            <div class="stat-value text-warning">
                                <?php echo number_format($today_stats['pending_orders']); ?>
                            </div>
                            <div class="stat-label">대기중</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="text-center">
                            <div class="stat-value text-success">
                                <?php echo number_format($today_stats['completed_orders']); ?>
                            </div>
                            <div class="stat-label">완료</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card stat-card">
                <h6 class="card-title text-muted mb-2">이번달 실적</h6>
                <div class="row g-0">
                    <div class="col-4 border-end">
                        <div class="text-center">
                            <div class="stat-value">
                                $<?php echo number_format($month_stats['total_amount'], 2); ?>
                            </div>
                            <div class="stat-label">총 금액</div>
                        </div>
                    </div>
                    <div class="col-4 border-end">
                        <div class="text-center">
                            <div class="stat-value">
                                <?php echo number_format($month_stats['total_quantity']); ?>
                            </div>
                            <div class="stat-label">총 수량</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="text-center">
                            <div class="stat-value">
                                $<?php echo number_format($month_stats['total_amount'] / max(1, $month_stats['total_orders']), 2); ?>
                            </div>
                            <div class="stat-label">평균 주문액</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card stat-card">
                <h6 class="card-title text-muted mb-2">정산 현황</h6>
                <div class="row g-0">
                    <div class="col-6 border-end">
                        <div class="text-center">
                            <div class="stat-value text-danger">
                                <?php echo number_format($all_stats['pending_settlement']); ?>
                            </div>
                            <div class="stat-label">미정산 건수</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center">
                            <div class="stat-value text-info">
                                <?php echo number_format($all_stats['completed_orders'] - $all_stats['pending_settlement']); ?>
                            </div>
                            <div class="stat-label">정산 완료</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 검색 폼 -->
    <div class="search-form">
        <form method="GET" class="row g-2">
            <div class="col-md-2">
                <input type="text" name="search_term" class="form-control form-control-sm" 
                       placeholder="주문번호/회원명/ID" value="<?php echo htmlspecialchars($search_term); ?>">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-control form-control-sm">
                    <option value="">모든 상태</option>
                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>대기중</option>
                    <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>완료</option>
                    <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>취소됨</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="payment_method" class="form-control form-control-sm">
                    <option value="">모든 결제방식</option>
                    <option value="usdp" <?php echo $payment_method === 'usdp' ? 'selected' : ''; ?>>USDP</option>
                    <option value="point" <?php echo $payment_method === 'point' ? 'selected' : ''; ?>>포인트</option>
                </select>
            </div>
            <div class="col-md-4">
                <div class="input-group input-group-sm">
                    <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>">
                    <span class="input-group-text">~</span>
                    <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">검색</button>
            </div>
        </form>
    </div>

    <!-- 주문 목록 -->
    <div class="table-responsive">
        <?php if (empty($orders)): ?>
            <div class="alert alert-info">조회된 주문이 없습니다.</div>
        <?php else: ?>
            <table class="table table-sm table-hover table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>주문번호</th>
                        <th>회원정보</th>
                        <th>상품정보</th>
                        <th>수량</th>
                        <th>결제금액</th>
                        <th>포인트사용</th>
                        <th>토큰/주식</th>
                        <th>결제방식</th>
                        <th>상태</th>
                        <th>정산상태</th>
                        <th>주문일시</th>
                        <th>관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td class="text-center"><?php echo $order['id']; ?></td>
                        <td>
                            <div class="fs-11"><?php echo htmlspecialchars($order['user_name']); ?></div>
                            <div class="fs-10 text-muted"><?php echo htmlspecialchars($order['user_login_id']); ?></div>
                        </td>
                        <td>
                            <div class="fs-11"><?php echo htmlspecialchars($order['product_name']); ?></div>
                            <div class="fs-10 text-muted">단가: $<?php echo number_format($order['price_unit'], 2); ?></div>
                        </td>
                        <td class="text-end"><?php echo number_format($order['quantity']); ?></td>
                        <td class="text-end">$<?php echo number_format($order['total_amount'], 2); ?></td>
                        <td class="text-end">
                            <?php if ($order['point_used'] > 0): ?>
                                <div class="fs-11">P: <?php echo number_format($order['point_used'], 2); ?></div>
                            <?php endif; ?>
                            <?php if ($order['cash_point_used'] > 0): ?>
                                <div class="fs-11">CP: <?php echo number_format($order['cash_point_used'], 2); ?></div>
                            <?php endif; ?>
                            <?php if ($order['mileage_point_used'] > 0): ?>
                                <div class="fs-11">MP: <?php echo number_format($order['mileage_point_used'], 2); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($order['token'] > 0): ?>
                                <div class="fs-11">토큰: <?php echo number_format($order['token']); ?></div>
                            <?php endif; ?>
                            <?php if ($order['stock'] > 0): ?>
                                <div class="fs-11">주식: <?php echo number_format($order['stock']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php 
                            $badge_class = '';
                            switch($order['payment_method']) {
                                case 'usdp': $badge_class = 'primary'; break;
                                case 'point': $badge_class = 'info'; break;
                                default: $badge_class = 'secondary';
                            }
                            ?>
                            <span class="badge bg-<?php echo $badge_class; ?> fs-10">
                                <?php echo $order['payment_method']; ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <?php 
                            $status_class = '';
                            $status_text = '';
                            switch($order['status']) {
                                case 'pending':
                                    $status_class = 'warning';
                                    $status_text = '대기';
                                    break;
                                case 'completed':
                                    $status_class = 'success';
                                    $status_text = '완료';
                                    break;
                                case 'cancelled':
                                    $status_class = 'danger';
                                    $status_text = '취소';
                                    break;
                                default:
                                    $status_class = 'secondary';
                                    $status_text = $order['status'];
                            }
                            ?>
                            <span class="badge bg-<?php echo $status_class; ?> fs-10">
                                <?php echo $status_text; ?>
                            </span>
                        </td>
                        <td class="text-center fs-10">
                            <?php
                            $settlement_status = [];
                            if ($order['status'] === 'completed') {
                                if ($order['paid_referral'] === 'pending') $settlement_status[] = '추천';
                                if ($order['paid_status'] === 'pending') $settlement_status[] = '직급';
                                if ($order['paid_center'] === 'pending') $settlement_status[] = '센터';
                                
                                if (empty($settlement_status)) {
                                    echo '<span class="text-success">완료</span>';
                                } else {
                                    echo '<span class="text-danger">' . implode(',', $settlement_status) . ' 대기</span>';
                                }
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td>
                            <div class="fs-10"><?php echo date('Y-m-d H:i', strtotime($order['created_at'])); ?></div>
                            <?php if ($order['payment_date']): ?>
                                <div class="fs-10 text-success">
                                    결제: <?php echo date('Y-m-d H:i', strtotime($order['payment_date'])); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <?php if ($order['status'] === 'pending'): ?>
                                    <button type="button" class="btn btn-outline-success btn-xs" 
                                            onclick="confirmPayment(<?php echo $order['id']; ?>)">
                                        입금확인
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-xs" 
                                            onclick="cancelOrder(<?php echo $order['id']; ?>)">
                                        취소
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($order['status'] === 'completed' && !empty($settlement_status)): ?>
                                    <button type="button" class="btn btn-outline-warning btn-xs" 
                                            onclick="processSettlement(<?php echo $order['id']; ?>)">
                                        정산처리
                                    </button>
                                <?php endif; ?>
                                
                                <button type="button" class="btn btn-outline-primary btn-xs" 
                                        onclick="viewOrderDetail(<?php echo $order['id']; ?>)">
                                    상세
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- 페이지네이션 -->
            <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-center mt-3">
                <nav>
                    <ul class="pagination pagination-sm">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&<?php 
                                    echo http_build_query(array_diff_key($_GET, ['page' => ''])); 
                                ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
// 입금 확인 처리
function confirmPayment(orderId) {
    if (!confirm('이 주문의 입금을 확인하시겠습니까?')) return;
    processOrderAction('confirm_payment', orderId);
}

// 주문 취소 처리
function cancelOrder(orderId) {
    if (!confirm('이 주문을 취소하시겠습니까?')) return;
    processOrderAction('cancel_order', orderId);
}

// 정산 처리
function processSettlement(orderId) {
    if (!confirm('이 주문의 정산을 진행하시겠습니까?')) return;
    processOrderAction('process_settlement', orderId);
}

// 주문 액션 처리 함수
function processOrderAction(action, orderId) {
    fetch('order_actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=${action}&order_id=${orderId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert(data.message || '처리 중 오류가 발생했습니다.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('처리 중 오류가 발생했습니다.');
    });
}

// 주문 상세 정보 보기
function viewOrderDetail(orderId) {
    // 이 함수는 주문 상세 모달을 표시합니다.
    // order_detail.php 파일이 필요합니다.
    window.location.href = `order_detail.php?id=${orderId}`;
}
</script>

<?php include __DIR__ . '/admin_footer.php'; ?>