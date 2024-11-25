<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/richtech.club/pages/error.log');

session_start();
require_once __DIR__ . '/../includes/config.php';

// 관리자 권한 체크
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_id'], [1, 2])) {
    header("Location: /login?redirect=admin/admin_deposits.php");
    exit;
}

$conn = db_connect();

// CSRF 토큰 생성
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 페이지네이션 설정
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// 검색 조건 처리
$search_conditions = [];
$params = [];
$param_types = '';

if (!empty($_GET['search'])) {
    $search = $_GET['search'];
    $search_conditions[] = "(u.login_id LIKE ? OR u.name LIKE ? OR d.transaction_id LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
    $param_types .= 'sss';
}

if (!empty($_GET['status'])) {
    $status = $_GET['status'];
    $search_conditions[] = "d.status = ?";
    $params[] = $status;
    $param_types .= 's';
}

if (!empty($_GET['date_from'])) {
    $date_from = $_GET['date_from'];
    $search_conditions[] = "DATE(d.created_at) >= ?";
    $params[] = $date_from;
    $param_types .= 's';
}

if (!empty($_GET['date_to'])) {
    $date_to = $_GET['date_to'];
    $search_conditions[] = "DATE(d.created_at) <= ?";
    $params[] = $date_to;
    $param_types .= 's';
}

// 수동 입금 확인 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_deposit'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }

    $deposit_id = (int)$_POST['deposit_id'];
    
    try {
        // 입금 정보 조회
        $stmt = $conn->prepare("
            SELECT d.*, u.point 
            FROM deposits d 
            JOIN users u ON d.user_id = u.id 
            WHERE d.id = ? AND d.status = 'pending'
        ");
        $stmt->bind_param("i", $deposit_id);
        $stmt->execute();
        $deposit = $stmt->get_result()->fetch_assoc();
        
        if ($deposit) {
            // BSCScan API로 트랜잭션 확인
            $api_key = BSC_API_KEY;
            $tx_hash = $deposit['transaction_id'];
            $url = "https://api.bscscan.com/api?module=account&action=tokentx&contractaddress=" . 
                   USDT_CONTRACT_ADDRESS . "&address=" . COMPANY_ADDRESS . 
                   "&page=1&offset=100&sort=desc&apikey=" . $api_key;

            $response = file_get_contents($url);
            $data = json_decode($response, true);

            $tx_found = false;
            if ($data['status'] === '1' && isset($data['result'])) {
                foreach ($data['result'] as $tx) {
                    if ($tx['hash'] === $tx_hash) {
                        $tx_found = true;
                        $amount = bcdiv($tx['value'], bcpow('10', '18', 0), 6);
                        $scan_link = "https://bscscan.com/tx/" . $tx_hash;

                        $conn->begin_transaction();

                        try {
                            // deposits 테이블 업데이트
                            $stmt = $conn->prepare("
                                UPDATE deposits 
                                SET status = 'completed',
                                    confirm_usdt = ?,
                                    amount_usdp = ?,
                                    from_address = ?,
                                    to_address = ?,
                                    scan_link = ?,
                                    processed_at = NOW()
                                WHERE id = ?
                            ");
                            $stmt->bind_param(
                                "ddsssi",
                                $amount,
                                $amount,
                                $tx['from'],
                                $tx['to'],
                                $scan_link,
                                $deposit_id
                            );
                            $stmt->execute();

                            // 사용자 포인트 업데이트
                            $stmt = $conn->prepare("
                                UPDATE users 
                                SET point = point + ? 
                                WHERE id = ?
                            ");
                            $stmt->bind_param("di", $amount, $deposit['user_id']);
                            $stmt->execute();

                            $conn->commit();
                            $message = "입금이 성공적으로 처리되었습니다.";

                            // 로그 기록
                            error_log(sprintf(
                                "Deposit processed: ID=%d, Amount=%f, User=%d, TX=%s",
                                $deposit_id,
                                $amount,
                                $deposit['user_id'],
                                $tx_hash
                            ));

                        } catch (Exception $e) {
                            $conn->rollback();
                            throw $e;
                        }
                        break;
                    }
                }
            }
            
            if (!$tx_found) {
                throw new Exception("트랜잭션을 찾을 수 없습니다.");
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("Deposit verification error: " . $error);
    }
}

// 통계 데이터 조회
$stats = $conn->query("
    SELECT 
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
        SUM(CASE WHEN status = 'completed' THEN confirm_usdt ELSE 0 END) as total_confirmed,
        SUM(CASE WHEN status = 'completed' AND DATE(processed_at) = CURDATE() THEN confirm_usdt ELSE 0 END) as today_confirmed
    FROM deposits
")->fetch_assoc();

// WHERE 절 구성
$where_clause = !empty($search_conditions) ? 'WHERE ' . implode(' AND ', $search_conditions) : '';

// 전체 레코드 수 조회
$count_query = "
    SELECT COUNT(*) as total 
    FROM deposits d 
    JOIN users u ON d.user_id = u.id 
    $where_clause
";

$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$total_records = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// 입금 내역 조회
$query = "
    SELECT d.*, u.name as username, u.login_id
    FROM deposits d
    JOIN users u ON d.user_id = u.id
    $where_clause
    ORDER BY d.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($query);
$params[] = $limit;
$params[] = $offset;
$param_types .= 'ii';
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

require_once __DIR__ . '/admin_header.php';
?>

<!-- CSS 스타일 -->
<style>
            .container {
                max-width: 100%;
                padding: 16px;
            }

            /* 통계 카드 스타일 */
            .stats-row {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 12px;
                margin-bottom: 20px;
            }

            .stat-card {
                background: linear-gradient(145deg, #2d2d2d, #1a1a1a);
                padding: 16px;
                border-radius: 8px;
                text-align: center;
                border: 1px solid rgba(212, 175, 55, 0.1);
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }

            .stat-card h3 {
                color: #d4af37;
                font-size: 0.85rem;
                margin-bottom: 8px;
            }

            .stat-value {
                font-size: 1.1rem;
                color: #fff;
                font-weight: 600;
            }

            /* 테이블 스타일 */
            .table-responsive {
                background: rgba(26, 26, 26, 0.95);
                border-radius: 8px;
                padding: 12px;
                overflow-x: auto;
                margin-bottom: 20px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }

            table {
                width: 100%;
                min-width: 1200px;
                border-collapse: separate;
                border-spacing: 0;
            }

            th, td {
                padding: 8px 12px;
                border-bottom: 1px solid rgba(255, 255, 255, 0.05);
                text-align: left;
                font-size: 0.9rem;
                line-height: 1.4;
            }

            th {
                background: rgba(212, 175, 55, 0.08);
                color: #d4af37;
                font-weight: 500;
                white-space: nowrap;
            }

            tr:hover {
                background: rgba(212, 175, 55, 0.03);
            }

            /* 상태 뱃지 */
            .status-badge {
                padding: 3px 8px;
                border-radius: 12px;
                font-size: 0.75rem;
                font-weight: 500;
                display: inline-block;
            }

            .status-pending {
                background: rgba(255, 193, 7, 0.1);
                color: #ffc107;
                border: 1px solid rgba(255, 193, 7, 0.2);
            }

            .status-completed {
                background: rgba(76, 175, 80, 0.1);
                color: #4caf50;
                border: 1px solid rgba(76, 175, 80, 0.2);
            }

            /* 페이지네이션 */
            .pagination {
                display: flex;
                justify-content: center;
                align-items: center;
                gap: 4px;
                margin-top: 24px;
            }

            .page-link {
                padding: 6px 12px;
                border: 1px solid rgba(212, 175, 55, 0.2);
                border-radius: 4px;
                color: #d4af37;
                text-decoration: none;
                font-size: 0.9rem;
                transition: all 0.2s ease;
                background: rgba(26, 26, 26, 0.95);
            }

            .page-link:hover {
                background: rgba(212, 175, 55, 0.1);
            }

            .page-link.active {
                background: #d4af37;
                color: #000;
                border-color: #d4af37;
            }

            /* 버튼 스타일 */
            .verify-btn {
                background: linear-gradient(135deg, #d4af37 0%, #f2d06b 100%);
                color: #000;
                border: none;
                padding: 4px 10px;
                border-radius: 4px;
                cursor: pointer;
                font-size: 0.75rem;
                font-weight: 500;
                transition: all 0.2s ease;
            }

            .verify-btn:hover {
                transform: translateY(-1px);
                box-shadow: 0 2px 8px rgba(212, 175, 55, 0.3);
            }

           
</style>

<div class="container">
    <h1 class="text-2xl font-bold mb-6 text-orange">입금 관리</h1>

    <?php if (isset($message)): ?>
        <div class="alert alert-success mb-4 p-4 bg-green-100 text-green-700 rounded">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger mb-4 p-4 bg-red-100 text-red-700 rounded">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <!-- 통계 섹션 -->
    <div class="stats-row">
        <div class="stat-card">
            <h3>처리 대기</h3>
            <div class="stat-value">
                <?php echo number_format($stats['pending_count']); ?>건
            </div>
        </div>
        <div class="stat-card">
            <h3>처리 완료</h3>
            <div class="stat-value">
                <?php echo number_format($stats['completed_count']); ?>건
            </div>
        </div>
        <div class="stat-card">
            <h3>총 입금액</h3>
            <div class="stat-value">
                <?php echo number_format($stats['total_confirmed'], 2); ?> USDT
            </div>
        </div>
        <div class="stat-card">
            <h3>오늘 입금액</h3>
            <div class="stat-value">
                <?php echo number_format($stats['today_confirmed'], 2); ?> USDT
            </div>
        </div>
    </div>

    <!-- 검색 필터 -->
 <div class="filter-section">
   <form method="get" class="search-form">
       <!-- 검색어 입력 -->
       <div class="search-group">
           <input type="text" name="search" 
                  placeholder="회원명/아이디/트랜잭션" 
                  value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"
                  class="search-input">
                  
           <select name="status" class="search-select">
               <option value="">전체 상태</option>
               <option value="pending" <?php echo isset($_GET['status']) && $_GET['status'] === 'pending' ? 'selected' : ''; ?>>처리대기</option>
               <option value="completed" <?php echo isset($_GET['status']) && $_GET['status'] === 'completed' ? 'selected' : ''; ?>>완료</option> 
               <option value="failed" <?php echo isset($_GET['status']) && $_GET['status'] === 'failed' ? 'selected' : ''; ?>>실패</option>
           </select>
       </div>

       <!-- 날짜 선택 -->
       <div class="date-group">
           <input type="date" name="date_from" 
                  value="<?php echo htmlspecialchars($_GET['date_from'] ?? ''); ?>"
                  class="date-input">
           <span class="date-separator">~</span>
           <input type="date" name="date_to" 
                  value="<?php echo htmlspecialchars($_GET['date_to'] ?? ''); ?>"
                  class="date-input">
       </div>

       <!-- 버튼 그룹 -->
       <div class="button-group">
           <button type="submit" class="btn-submit">
               <i class="fas fa-search"></i> 검색
           </button>
           <button type="button" onclick="exportToExcel()" class="btn-excel">
               <i class="fas fa-file-excel"></i> 엑셀
           </button>
       </div>
   </form>
</div>

<style>
.filter-section {
   background: rgba(45, 45, 45, 0.9);
   padding: 15px;
   border-radius: 8px;
   margin-bottom: 20px;
}

.search-form {
   display: flex;
   flex-wrap: wrap;
   gap: 10px;
}

.search-group {
   display: flex;
   gap: 10px;
   flex: 2;
   min-width: 300px;
}

.search-input {
   flex: 2;
   min-width: 200px;
   padding: 8px;
   background: rgb(26,26,26);
   border: 1px solid rgba(212,175,55,0.2);
   border-radius: 4px;
   color: #fff;
}

.search-select {
   width: 120px;
   padding: 8px;
   background: rgb(26,26,26);
   border: 1px solid rgba(212,175,55,0.2);
   border-radius: 4px;
   color: #fff;
}

.date-group {
   display: flex;
   align-items: center;
   gap: 10px;
   flex: 2;
   min-width: 300px;
}

.date-input {
   width: 140px;
   padding: 8px;
   background: rgb(26,26,26);
   border: 1px solid rgba(212,175,55,0.2);
   border-radius: 4px;
   color: #fff;
}

.date-separator {
   color: #666;
}

.button-group {
   display: flex;
   gap: 10px;
}

.btn-submit,
.btn-excel {
   padding: 8px 16px;
   border-radius: 4px;
   font-size: 14px;
   cursor: pointer;
}

.btn-submit {
   background: linear-gradient(135deg,#d4af37,#f2d06b);
   color: #000;
   border: none;
}

.btn-excel {
   background: #198754;
   color: #fff;
   border: none;
}

@media (max-width: 768px) {
   .search-form {
       grid-template-columns: repeat(2, 1fr);
   }
   
   .search-group,
   .date-group,
   .button-group {
       flex: 0 0 100%;
   }
   
   .button-group {
       display: grid;
       grid-template-columns: 1fr 1fr;
   }
}
</style>

    <!-- 입금 내역 테이블 -->
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>회원정보</th>
                    <th>신청금액</th>
                    <th>확인금액</th>
                    <th>충전금액</th>
                    <th>From 주소</th>
                    <th>To 주소</th>
                    <th>트랜잭션</th>
                    <th>상태</th>
                    <th>신청시간</th>
                    <th>처리시간</th>
                    <th>관리</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td>
                        <?php echo htmlspecialchars($row['username']); ?>
                        <br>
                        <small class="text-gray-400"><?php echo htmlspecialchars($row['login_id']); ?></small>
                    </td>
                    <td class="amount-cell">
                        <?php echo number_format($row['amount_usdt'], 6); ?> USDT
                    </td>
                    <td class="amount-cell">
                        <?php echo $row['confirm_usdt'] ? number_format($row['confirm_usdt'], 6) . ' USDT' : '-'; ?>
                    </td>
                    <td class="amount-cell">
                        <?php echo $row['amount_usdp'] ? number_format($row['amount_usdp'], 6) . ' USDP' : '-'; ?>
                    </td>
                    <td class="address-cell" title="<?php echo htmlspecialchars($row['from_address']); ?>">
                        <?php echo $row['from_address'] ? substr($row['from_address'], 0, 10) . '...' : '-'; ?>
                    </td>
                    <td class="address-cell" title="<?php echo htmlspecialchars($row['to_address']); ?>">
                        <?php echo $row['to_address'] ? substr($row['to_address'], 0, 10) . '...' : '-'; ?>
                    </td>
                    <td>
                        <?php if ($row['transaction_id']): ?>
                            <a href="<?php echo $row['scan_link']; ?>" 
                               target="_blank" 
                               class="text-blue-400 hover:text-blue-300">
                                <?php echo substr($row['transaction_id'], 0, 10); ?>...
                            </a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status-badge status-<?php echo $row['status']; ?>">
                            <?php
                            switch($row['status']) {
                                case 'pending': echo '대기중'; break;
                                case 'completed': echo '완료'; break;
                                case 'failed': echo '실패'; break;
                            }
                            ?>
                        </span>
                    </td>
                    <td><?php echo date('Y-m-d H:i:s', strtotime($row['created_at'])); ?></td>
                    <td>
                        <?php echo $row['processed_at'] ? date('Y-m-d H:i:s', strtotime($row['processed_at'])) : '-'; ?>
                    </td>
                    <td>
                        <?php if ($row['status'] === 'pending'): ?>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="deposit_id" value="<?php echo $row['id']; ?>">
                                <button type="submit" name="verify_deposit" class="verify-btn">
                                    입금확인
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- 페이지네이션 -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=1<?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>" 
               class="page-link">&laquo;</a>
        <?php endif; ?>

        <?php
        $start = max(1, $page - 2);
        $end = min($start + 4, $total_pages);
        $start = max(1, $end - 4);

        for ($i = $start; $i <= $end; $i++):
        ?>
            <a href="?page=<?php echo $i; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>" 
               class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $total_pages; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>" 
               class="page-link">&raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
// 자동 새로고침 (30초)
setInterval(() => {
    const pendingExists = document.querySelector('.status-pending') !== null;
    if (pendingExists) {
        location.reload();
    }
}, 30000);

// 주소 복사 기능
document.querySelectorAll('.address-cell').forEach(cell => {
    cell.addEventListener('click', function() {
        const text = this.getAttribute('title');
        navigator.clipboard.writeText(text).then(() => {
            showNotification('주소가 복사되었습니다.', 'success');
        });
    });
});

// Excel 내보내기 기능
function exportToExcel() {
    const table = document.querySelector('table');
    const wb = XLSX.utils.table_to_book(table, {sheet: "입금내역"});
    const fileName = `입금내역_${new Date().toLocaleDateString()}.xlsx`;
    XLSX.writeFile(wb, fileName);
}

// 알림 표시 함수
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    document.body.appendChild(notification);

    setTimeout(() => {
        notification.remove();
    }, 3000);
}
</script>

<!-- Excel 내보내기를 위한 라이브러리 -->
<script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>

<?php
$conn->close();
include __DIR__ . '/../includes/footer.php';
?>