<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // 프로덕션 환경에서는 화면에 에러 표시 안함
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/richtech.club/pages/error.log');

session_start();
require_once __DIR__ . '/../includes/config.php';

// 관리자 권한 체크
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_id'], [1, 2])) {
    header("Location: /login?redirect=admin");
    exit;
}

$conn = db_connect();

// 통계 데이터 가져오기 함수들
function getTotalMembers($conn) {
    try {
        $query = "SELECT COUNT(*) as total FROM users WHERE status = 'active'";
        $result = $conn->query($query);
        if (!$result) {
            throw new Exception("Query failed: " . $conn->error);
        }
        return $result->fetch_assoc()['total'];
    } catch (Exception $e) {
        error_log("getTotalMembers Error: " . $e->getMessage());
        return 0;
    }
}

function getTotalOrders($conn) {
    try {
        $query = "SELECT COUNT(*) as total FROM orders WHERE status = 'completed'";
        $result = $conn->query($query);
        if (!$result) {
            throw new Exception("Query failed: " . $conn->error);
        }
        return $result->fetch_assoc()['total'];
    } catch (Exception $e) {
        error_log("getTotalOrders Error: " . $e->getMessage());
        return 0;
    }
}

function getTotalSales($conn) {
    try {
        $query = "SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE status = 'completed'";
        $result = $conn->query($query);
        if (!$result) {
            throw new Exception("Query failed: " . $conn->error);
        }
        return $result->fetch_assoc()['total'];
    } catch (Exception $e) {
        error_log("getTotalSales Error: " . $e->getMessage());
        return 0;
    }
}

function getTotalCommissions($conn) {
    try {
        $query = "SELECT COALESCE(SUM(commission_total), 0) as total FROM users";
        $result = $conn->query($query);
        if (!$result) {
            throw new Exception("Query failed: " . $conn->error);
        }
        return $result->fetch_assoc()['total'];
    } catch (Exception $e) {
        error_log("getTotalCommissions Error: " . $e->getMessage());
        return 0;
    }
}

// 최근 가입 회원 조회
function getRecentMembers($conn) {
    try {
        $query = "SELECT id, name, phone, rank, created_at 
                 FROM users 
                 WHERE status = 'active' 
                 ORDER BY created_at DESC 
                 LIMIT 10";
        $result = $conn->query($query);
        if (!$result) {
            throw new Exception("Query failed: " . $conn->error);
        }
        return $result->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        error_log("getRecentMembers Error: " . $e->getMessage());
        return [];
    }
}

// 최근 주문 내역 조회
function getRecentOrders($conn) {
    try {
        $query = "SELECT o.id, o.total_amount, o.created_at, 
                         u.name as user_name, u.rank as user_rank
                 FROM orders o
                 LEFT JOIN users u ON o.user_id = u.id
                 WHERE o.status = 'completed'
                 ORDER BY o.created_at DESC
                 LIMIT 10";
        $result = $conn->query($query);
        if (!$result) {
            throw new Exception("Query failed: " . $conn->error);
        }
        return $result->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        error_log("getRecentOrders Error: " . $e->getMessage());
        return [];
    }
}

// 데이터 가져오기
try {
    $totalMembers = getTotalMembers($conn);
    $totalOrders = getTotalOrders($conn);
    $totalSales = getTotalSales($conn);
    $totalCommissions = getTotalCommissions($conn);
    $recentMembers = getRecentMembers($conn);
    $recentOrders = getRecentOrders($conn);
} catch (Exception $e) {
    error_log("Admin dashboard error: " . $e->getMessage());
    die("데이터를 불러오는 중 오류가 발생했습니다.");
}

$pageTitle = '관리자 대시보드';
include __DIR__ . '/admin_header.php';
?>

<div class="admin-container">
    <!-- 요약 통계 -->
    <div class="summary-stats">
        <div class="stat-card">
            <h3>총 회원수</h3>
            <p class="text-gold"><?php echo number_format($totalMembers); ?>명</p>
        </div>
        <div class="stat-card">
            <h3>총 주문수</h3>
            <p class="text-gold"><?php echo number_format($totalOrders); ?>건</p>
        </div>
        <div class="stat-card">
            <h3>총 매출</h3>
            <p class="text-gold">$<?php echo number_format($totalSales, 2); ?></p>
        </div>
        <div class="stat-card">
            <h3>총 수수료</h3>
            <p class="text-gold">$<?php echo number_format($totalCommissions, 2); ?></p>
        </div>
    </div>

    <!-- 최근 가입 회원 -->
    <div class="recent-section">
        <div class="section-header">
            <h2>최근 가입 회원</h2>
            <a href="/admin/admin_members.php" class="btn btn-gold">전체보기</a>
        </div>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>이름</th>
                        <th>전화번호</th>
                        <th>직급</th>
                        <th>가입일</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentMembers as $member): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($member['name']); ?></td>
                        <td><?php echo htmlspecialchars($member['phone']); ?></td>
                        <td><?php echo htmlspecialchars($member['rank']); ?></td>
                        <td><?php echo date('Y-m-d', strtotime($member['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 최근 주문 -->
    <div class="recent-section">
        <div class="section-header">
            <h2>최근 주문</h2>
            <a href="/admin/admin_orders.php" class="btn btn-gold">전체보기</a>
        </div>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>주문번호</th>
                        <th>회원명</th>
                        <th>직급</th>
                        <th>금액</th>
                        <th>주문일</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentOrders as $order): ?>
                    <tr>
                        <td><?php echo $order['id']; ?></td>
                        <td><?php echo htmlspecialchars($order['user_name'] ?? '알 수 없음'); ?></td>
                        <td><?php echo htmlspecialchars($order['user_rank'] ?? '-'); ?></td>
                        <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                        <td><?php echo date('Y-m-d', strtotime($order['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
        .admin-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #1a1a1a;
            border: 1px solid rgba(212, 175, 55, 0.2);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
        }

        .stat-card h3 {
            color: #d4af37;
            font-size: 1rem;
            margin-bottom: 10px;
        }

        .text-gold {
            color: #d4af37;
            font-size: 1.5rem;
            font-weight: bold;
        }

        .recent-section {
            background: #1a1a1a;
            border: 1px solid rgba(212, 175, 55, 0.2);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-header h2 {
            color: #d4af37;
            font-size: 1.2rem;
            margin: 0;
        }

        .btn-gold {
            background: linear-gradient(to right, #d4af37, #aa8a2e);
            color: #000;
            padding: 5px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .admin-table {
            width: 100%;
            border-collapse: collapse;
        }

        .admin-table th,
        .admin-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid rgba(212, 175, 55, 0.1);
        }

        .admin-table th {
            background: rgba(212, 175, 55, 0.1);
            color: #d4af37;
            font-weight: normal;
        }

        .table-responsive {
            overflow-x: auto;
        }

        @media (max-width: 768px) {
            .summary-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .summary-stats {
                grid-template-columns: 1fr;
            }
        }
</style>

<?php
$conn->close();
include __DIR__ . '/../includes/footer.php';
?>