<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/richtech.club/pages/error.log');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/config.php';

// Web3 관련 클래스 사용을 위해 네임스페이스 선언
use Web3\Web3;
use Web3\Contract;
use Web3\Utils;
use Web3\Providers\HttpProvider;
use Web3\RequestManagers\HttpRequestManager;

session_start();

// 관리자 권한 체크
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_id'], [1, 2])) {
    header("Location: /login.php?redirect=admin/admin_withdrawals.php");
    exit();
}

$conn = db_connect();


// 개인키를 환경 변수에서 가져옵니다.
$privateKey = getenv('BSC_PRIVATE_KEY') ?: $_ENV['BSC_PRIVATE_KEY'] ?? null;

// 디버깅을 위해 개인키의 값을 확인합니다.

if (!$privateKey) {
    throw new Exception('환경 변수 BSC_PRIVATE_KEY가 설정되지 않았습니다.');
}


// 페이징 처리
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// 검색 조건 처리
$search_conditions = [];
$params = [];
$param_types = '';

if (!empty($_GET['search'])) {
    $search = $_GET['search'];
    $search_conditions[] = "(u.login_id LIKE ? OR u.name LIKE ? OR w.transaction_id LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
    $param_types .= 'sss';
}

if (!empty($_GET['status'])) {
    $status = $_GET['status'];
    $search_conditions[] = "w.status = ?";
    $params[] = $status;
    $param_types .= 's';
}

if (!empty($_GET['date_from'])) {
    $date_from = $_GET['date_from'];
    $search_conditions[] = "DATE(w.created_at) >= ?";
    $params[] = $date_from;
    $param_types .= 's';
}

if (!empty($_GET['date_to'])) {
    $date_to = $_GET['date_to'];
    $search_conditions[] = "DATE(w.created_at) <= ?";
    $params[] = $date_to;
    $param_types .= 's';
}

// WHERE 절 구성
$where_clause = !empty($search_conditions) ? 'WHERE ' . implode(' AND ', $search_conditions) : '';

// 전체 레코드 수 조회
$count_query = "
    SELECT COUNT(*) as total 
    FROM withdrawals w 
    JOIN users u ON w.user_id = u.id 
    $where_clause
";

$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$total_records = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// 출금 목록 조회
$query = "
    SELECT w.*, 
           u.name AS username,
           u.login_id AS user_login_id,
           u.bsc_address,
           DATE_FORMAT(w.created_at, '%Y-%m-%d %H:%i') AS formatted_created_at,
           DATE_FORMAT(w.processed_at, '%Y-%m-%d %H:%i') AS formatted_processed_at
    FROM withdrawals w
    JOIN users u ON w.user_id = u.id
    $where_clause
    ORDER BY w.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;
$param_types .= 'ii';
$stmt = $conn->prepare($query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// 통계 데이터 조회
$stats_query = "
    SELECT 
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
        SUM(CASE WHEN status = 'completed' THEN actual_amount_usdt ELSE 0 END) as total_withdrawn,
        SUM(CASE WHEN status = 'completed' AND DATE(processed_at) = CURDATE() THEN actual_amount_usdt ELSE 0 END) as today_withdrawn
    FROM withdrawals
";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// 여기서 회사 계정의 USDT 및 BNB 잔고를 조회합니다.

// 설정 정보 가져오기
$bscNodeUrl = BSC_NODE_URL;
$companyAddress = COMPANY_ADDRESS;
$usdtContractAddress = USDT_CONTRACT_ADDRESS;

// Web3 초기화
$provider = new HttpProvider(new HttpRequestManager($bscNodeUrl, 5)); // 타임아웃 5초
$web3 = new Web3($provider);

// 회사 계정의 BNB 잔고 조회 (동기식 호출)
$bnbBalanceWei = null;
$web3->eth->getBalance($companyAddress, function ($err, $result) use (&$bnbBalanceWei) {
    if ($err !== null) {
        $bnbBalanceWei = 'error';
    } else {
        // BigNumber 객체를 문자열로 변환
        if (method_exists($result, 'toString')) {
            $bnbBalanceWei = $result->toString();
        } elseif (is_array($result)) {
            $bnbBalanceWei = $result['hex'] ?? $result[0] ?? 'error';
        } else {
            $bnbBalanceWei = (string)$result;
        }
    }
});

if ($bnbBalanceWei === 'error' || $bnbBalanceWei === null) {
    $bnbBalance = '잔액 조회 실패';
} else {
    try {
        // 16진수인 경우 10진수로 변환
        if (substr($bnbBalanceWei, 0, 2) === '0x') {
            $bnbBalanceWei = hexdec($bnbBalanceWei);
        }
        
        // Wei를 BNB로 변환 (18자리 소수점)
        $bnbBalance = bcdiv((string)$bnbBalanceWei, bcpow('10', '18', 0), 8);
    } catch (Exception $e) {
        error_log("BNB 잔액 변환 오류: " . $e->getMessage());
        $bnbBalance = '잔액 변환 실패';
    }
}

// 회사 계정의 USDT 잔고 조회 (동기식 호출)
$usdtBalanceWei = null;
$err = null;
$contract = new Contract($provider, '[]'); // ABI는 빈 배열로 처리
$data = '0x70a08231' . str_pad(substr($companyAddress, 2), 64, '0', STR_PAD_LEFT);

$web3->eth->call([
    'to' => $usdtContractAddress,
    'data' => $data
], function ($error, $result) use (&$usdtBalanceWei, &$err) {
    $err = $error;
    $usdtBalanceWei = $result;
});

if ($err !== null) {
    $usdtBalance = '잔액 조회 실패';
} else {
    $decimals = 18; // USDT(BEP20)의 소수점 자리수
    $balanceWei = Utils::toBn($usdtBalanceWei);
    $usdtBalance = bcdiv($balanceWei, bcpow('10', $decimals, 0), $decimals);
}

// 페이지 제목 설정 및 헤더 포함
$pageTitle = "출금 관리";
include __DIR__ . '/admin_header.php';

?>



<style>
    .container {
        max-width: 100%;
        padding: 16px;
    }

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
        font-size: 0.9rem;
        color: #fff;
        font-weight: 600;
    }

    .filter-section {
        background: rgba(45, 45, 45, 0.9);
        padding: 16px;
        border-radius: 8px;
        margin-top: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .filter-section form {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: center;
    }

    .filter-section input,
    .filter-section select {
        padding: 8px 12px;
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 6px;
        background: rgba(26, 26, 26, 0.95);
        color: #fff;
        font-size: 0.9rem;
    }

    .table-responsive {
        background: rgba(26, 26, 26, 0.95);
        border-radius: 8px;
        padding: 12px;
        overflow-x: auto;
        margin-bottom: 20px;
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
        font-size: 0.7rem;
        line-height: 1.4;
    }

    th {
        background: rgba(212, 175, 55, 0.08);
        color: #d4af37;
        font-weight: 500;
        white-space: nowrap;
    }

    .status-badge {
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 0.7rem;
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

    .checkbox-column {
        width: 30px;
        text-align: center;
    }

    .amount-copy,
    .address-copy {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .btn-copy {
        padding: 2px 8px;
        font-size: 0.7rem;
        background: #4a4a4a;
        border: none;
        color: #fff;
        border-radius: 3px;
        cursor: pointer;
    }

    .btn-copy:hover {
        background: #5a5a5a;
    }

    .bulk-actions {
        display: flex;
        justify-content: flex-end;
        padding: 10px 0;
    }

    .btn-gold {
        background: linear-gradient(135deg, #d4af37 0%, #f2d06b 100%);
        color: #000;
        border: none;
        padding: 4px 10px;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.2s ease;
    }

    .btn-gold:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(212, 175, 55, 0.3);
    }

    .pagination {
        display: flex;
        justify-content: center;
        gap: 4px;
        margin-top: 20px;
    }

    .page-link {
        padding: 6px 12px;
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 4px;
        color: #d4af37;
        text-decoration: none;
        background: rgba(26, 26, 26, 0.95);
    }

    .page-link:hover {
        background: rgba(212, 175, 55, 0.1);
    }

    .page-link.active {
        background: #d4af37;
        color: #000;
    }

    .btn-action {
        padding: 4px 8px;
        font-size: 0.7rem;
        min-width: auto;
        background: transparent;
        border: none;
        color: #d4af37;
        cursor: pointer;
    }

    .btn-action:hover {
        color: #f2d06b;
    }

    .input-transaction {
        width: 120px;
        padding: 2px 4px;
        font-size: 0.75rem;
        background: rgba(0, 0, 0, 0.2);
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 3px;
    }

    .table td {
        padding: 4px 8px;
        font-size: 0.7rem;
    }

    .bulk-actions .btn-gold {
        padding: 4px 12px;
        font-size: 0.7rem;
    }
</style>


<div class="container">
    <h1 class="text-2xl font-bold mb-6 text-orange">출금 관리</h1>

    <!-- 통계 섹션 -->
    <div class="stats-row">
        <!-- 통계 카드들 -->
        <div class="stat-card">
            <h3>처리 대기</h3>
            <div class="stat-value"><?php echo number_format($stats['pending_count']); ?>건</div>
        </div>
        <div class="stat-card">
            <h3>처리 완료</h3>
            <div class="stat-value"><?php echo number_format($stats['completed_count']); ?>건</div>
        </div>
        <div class="stat-card">
            <h3>총 출금액</h3>
            <div class="stat-value"><?php echo number_format($stats['total_withdrawn'], 2); ?> USDT</div>
        </div>
        <div class="stat-card">
            <h3>오늘 출금액</h3>
            <div class="stat-value"><?php echo number_format($stats['today_withdrawn'], 2); ?> USDT</div>
        </div>


        <!-- 회사 계정 잔고 카드들 -->
        <div class="stat-card">
            <h3>회사 계정 BNB 잔액</h3>
            <div class="stat-value">
                <?php 
                if (is_string($bnbBalance) && in_array($bnbBalance, ['잔액 조회 실패', '잔액 변환 실패'])) {
                    echo $bnbBalance;
                } else {
                    echo number_format((float)$bnbBalance, 6) . ' BNB';
                }
                ?>
            </div>
        </div>
        <div class="stat-card">
            <h3>회사 계정 USDT 잔액</h3>
            <div class="stat-value"><?php echo is_numeric($usdtBalance) ? number_format($usdtBalance, 6) . ' USDT' : $usdtBalance; ?></div>
        </div>
    </div>

    <!-- 검색 필터 -->
    <div class="filter-section">
        <form method="get" class="flex items-center gap-2">
            <input type="text" name="search" placeholder="회원명/아이디/트랜잭션" 
                   value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"
                   class="p-2" style="width:200px">
            
            <select name="status" class="p-2" style="width:120px">
                <option value="">전체 상태</option>
                <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] === 'pending') ? 'selected' : ''; ?>>처리대기</option>
                <option value="completed" <?php echo (isset($_GET['status']) && $_GET['status'] === 'completed') ? 'selected' : ''; ?>>완료</option>
            </select>

            <input type="date" name="date_from" 
                   value="<?php echo htmlspecialchars($_GET['date_from'] ?? ''); ?>"
                   class="p-2" style="width:140px">
            
            <span class="text-gray-400">~</span>
            
            <input type="date" name="date_to" 
                   value="<?php echo htmlspecialchars($_GET['date_to'] ?? ''); ?>"
                   class="p-2" style="width:140px">
            
            <button type="submit" class="btn btn-gold">
                <i class="fas fa-search"></i> 검색
            </button>
        </form>
    </div>

    <!-- 일괄 처리 버튼 -->
    <div class="bulk-actions">
        <button onclick="processBatchWithdrawals()" class="btn btn-gold">
            <i class="fas fa-check-double"></i> 일괄처리
        </button>
    </div>

    <!-- 출금 목록 테이블 -->
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th class="checkbox-column">
                        <input type="checkbox" id="select-all" onclick="toggleSelectAll(this)">
                    </th>
                    <th>ID</th>
                    <th>회원정보</th>
                    <th>신청금액</th>
                    <th>수수료</th>
                    <th>실출금액</th>
                    <th>출금주소</th>
                    <th>트랜잭션ID</th>
                    <th>상태</th>
                    <th>신청일시</th>
                    <th>처리일시</th>
                    <th>관리</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr data-id="<?php echo $row['id']; ?>">
                    <td class="checkbox-column">
                        <?php if ($row['status'] === 'pending'): ?>
                            <input type="checkbox" name="withdrawal_ids[]" value="<?php echo $row['id']; ?>">
                        <?php endif; ?>
                    </td>
                    <td><?php echo $row['id']; ?></td>
                    <td>
                        <?php echo htmlspecialchars($row['username']); ?><br>
                        <small class="text-gray-400"><?php echo htmlspecialchars($row['user_login_id']); ?></small>
                    </td>
                    <td><?php echo number_format($row['request_amount_usdp'], 4); ?> USDP</td>
                    <td><?php echo number_format($row['fee_amount'], 4); ?> USDP</td>
                    <td>
                        <div class="amount-copy">
                            <span><?php echo number_format($row['actual_amount_usdt'], 6); ?> USDT</span>
                            <?php if ($row['status'] === 'pending'): ?>
                                <button class="btn btn-copy" onclick="copyToClipboard('<?php echo $row['actual_amount_usdt']; ?>')">
                                    복사
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <div class="address-copy">
                            <span><?php echo $row['to_address']; ?></span>
                            <?php if ($row['status'] === 'pending'): ?>
                                <button class="btn btn-copy" onclick="copyToClipboard('<?php echo $row['to_address']; ?>')">
                                    복사
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <?php if ($row['status'] === 'pending'): ?>
                            <!-- 처리 중인 경우 트랜잭션 해시 입력 가능 -->
                            <input type="text" 
                                   class="input-transaction" 
                                   data-withdrawal-id="<?php echo $row['id']; ?>" 
                                   placeholder="트랜잭션 해시 입력 후 처리">
                        <?php elseif ($row['transaction_id']): ?>
                            <!-- 완료된 경우 트랜잭션 링크 제공 -->
                            <a href="<?php echo $row['scan_link']; ?>" target="_blank" class="text-xs text-blue-400 hover:text-blue-300">
                                <?php echo substr($row['transaction_id'], 0, 8); ?>...
                            </a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status-badge fs-10  status-<?php echo $row['status']; ?>">
                            <?php
                            switch($row['status']) {
                                case 'pending': echo '처리대기'; break;
                                case 'completed': echo '완료'; break;
                                case 'failed': echo '실패'; break;
                            }
                            ?>
                        </span>
                    </td>
                    <td><?php echo $row['formatted_created_at']; ?></td>
                    <td><?php echo $row['formatted_processed_at'] ?: '-'; ?></td>
                    <td>
                        <?php if ($row['status'] === 'pending'): ?>
                            <button class="btn-action btn-outline border-1 btn-10" onclick="processWithdrawal(<?php echo $row['id']; ?>)" title="처리">
                                <i class="fas fa-check"></i> 처리
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- 페이징 -->
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

    function toggleSelectAll(source) {
        const checkboxes = document.querySelectorAll('input[name="withdrawal_ids[]"]');
        checkboxes.forEach(checkbox => checkbox.checked = source.checked);
    }

    function copyToClipboard(text) {
        navigator.clipboard.writeText(text)
            .then(() => {
                alert('클립보드에 복사되었습니다.');
            })
            .catch(() => {
                alert('복사에 실패했습니다.');
            });
    }

    function processWithdrawal(withdrawalId) {
        if (!confirm('해당 출금을 처리하시겠습니까?')) return;

        fetch('withdrawals_process.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ withdrawal_id: withdrawalId }),
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('출금 처리 실패: ' + data.message + (data.errors ? '\n' + data.errors.join('\n') : ''));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('출금 처리 중 오류가 발생했습니다.');
        });
    }

    function processBatchWithdrawals() {
        const checkboxes = document.querySelectorAll('input[name="withdrawal_ids[]"]:checked');
        const selectedIds = Array.from(checkboxes).map(cb => parseInt(cb.value));

        if (selectedIds.length === 0) {
            alert('처리할 출금을 선택해주세요.');
            return;
        }

        if (!confirm(`선택한 ${selectedIds.length}건의 출금을 처리하시겠습니까?`)) {
            return;
        }

        fetch('withdrawals_process.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ withdrawal_ids: selectedIds }),
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('출금 처리 실패: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('출금 처리 중 오류가 발생했습니다.');
        });
    }

</script>


<?php 
$conn->close();
include __DIR__ . '/../includes/footer.php';
?>