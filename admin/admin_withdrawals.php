<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/richtech.club/pages/error.log');

session_start();
require_once __DIR__ . '/../includes/config.php';

// 관리자 권한 체크
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_id'], [1, 2])) {
    header("Location: /login?redirect=admin/withdrawals");
    exit();
}

$conn = db_connect();

// 트랜잭션 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    try {
        if (isset($_POST['update_transaction'])) {
            $withdrawal_id = filter_var($_POST['withdrawal_id'], FILTER_VALIDATE_INT);
            $transaction_id = trim($_POST['transaction_id']);
            
            if (!$withdrawal_id) {
                throw new Exception("유효하지 않은 출금 ID입니다.");
            }
            
            if (!preg_match('/^0x[a-fA-F0-9]{64}$/', $transaction_id)) {
                throw new Exception("유효하지 않은 트랜잭션 해시입니다.");
            }

            $scan_link = "https://bscscan.com/tx/" . $transaction_id;
            
            $stmt = $conn->prepare("
                UPDATE withdrawals 
                SET transaction_id = ?,
                    scan_link = ?,
                    status = 'completed',
                    processed_at = NOW()
                WHERE id = ? AND status = 'pending'
            ");
            
            $stmt->bind_param("ssi", $transaction_id, $scan_link, $withdrawal_id);
            
            if (!$stmt->execute()) {
                throw new Exception("트랜잭션 정보 업데이트 실패");
            }

            $response = [
                'success' => true,
                'message' => '출금 처리가 완료되었습니다.'
            ];

        } elseif (isset($_POST['process_withdrawals'])) {
            if (!isset($_POST['selected_ids']) || !is_array($_POST['selected_ids'])) {
                throw new Exception("선택된 출금 요청이 없습니다.");
            }

            $processed = 0;
            foreach ($_POST['selected_ids'] as $withdrawal_id) {
                $withdrawal_id = filter_var($withdrawal_id, FILTER_VALIDATE_INT);
                if (!$withdrawal_id) continue;

                $stmt = $conn->prepare("
                    UPDATE withdrawals 
                    SET status = 'completed',
                        processed_at = NOW()
                    WHERE id = ? AND status = 'pending'
                ");
                
                $stmt->bind_param("i", $withdrawal_id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $processed++;
                }
            }

            $response = [
                'success' => true,
                'message' => "{$processed}건의 출금이 처리되었습니다."
            ];
        }
    } catch (Exception $e) {
        $response = [
            'success' => false,
            'message' => $e->getMessage()
        ];
        error_log("Admin withdrawal error: " . $e->getMessage());
    }

    echo json_encode($response);
    exit;
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

// 통계 데이터 조회
$stats = $conn->query("
    SELECT 
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
        SUM(CASE WHEN status = 'completed' THEN actual_amount_usdt ELSE 0 END) as total_withdrawn,
        SUM(CASE WHEN status = 'completed' AND DATE(processed_at) = CURDATE() THEN actual_amount_usdt ELSE 0 END) as today_withdrawn
    FROM withdrawals
")->fetch_assoc();

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

// 메인 쿼리
$query = "
    SELECT w.*, 
           u.name AS username,
           u.login_id AS user_login_id,
           DATE_FORMAT(w.created_at, '%Y-%m-%d %H:%i') AS formatted_created_at,
           DATE_FORMAT(w.processed_at, '%Y-%m-%d %H:%i') AS formatted_processed_at
    FROM withdrawals w
    JOIN users u ON w.user_id = u.id
    $where_clause
    ORDER BY w.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($query);
$params[] = $limit;
$params[] = $offset;
$param_types .= 'ii';
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$pageTitle = "출금 관리";
require_once __DIR__ . '/admin_header.php';
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
        font-size: 1.1rem;
        color: #fff;
        font-weight: 600;
    }

    .filter-section {
        background: rgba(45, 45, 45, 0.9);
        padding: 16px;
        border-radius: 8px;
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
        font-size: 0.9rem;
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
</style>

<div class="container">
    <h1 class="text-2xl font-bold mb-6 text-orange">출금 관리</h1>

    <!-- Stats Section -->
    <div class="stats-row">
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
    </div>

    <!-- Search Filters -->
    <div class="filter-section">
        <form method="get" class="flex items-center gap-2">
            <input type="text" name="search" placeholder="회원명/아이디/트랜잭션" 
                   value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"
                   class="p-2" style="width:200px">
            
            <select name="status" class="p-2" style="width:120px">
                <option value="">전체 상태</option>
                <option value="pending" <?php echo isset($_GET['status']) && $_GET['status'] === 'pending' ? 'selected' : ''; ?>>처리대기</option>
                <option value="completed" <?php echo isset($_GET['status']) && $_GET['status'] === 'completed' ? 'selected' : ''; ?>>완료</option>
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

    <!-- Bulk Actions -->
    <div class="bulk-actions">
        <button onclick="processBatchWithdrawals()" class="btn btn-gold">
            <i class="fas fa-check-double"></i> 선택 일괄처리
        </button>
    </div>




    <!-- Withdrawals Table -->
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
                            <input type="text" class="input-transaction" placeholder="트랜잭션 해시"
                                   onchange="updateTransaction(<?php echo $row['id']; ?>, this.value)">
                        <?php elseif ($row['transaction_id']): ?>
                            <a href="<?php echo $row['scan_link']; ?>" target="_blank" class="text-blue-400 hover:text-blue-300">
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
                                case 'pending': echo '처리중'; break;
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
                            <button class="btn btn-gold" onclick="processWithdrawal(<?php echo $row['id']; ?>)">
                                처리
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
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
    const checkboxes = document.getElementsByName('withdrawal_ids[]');
    checkboxes.forEach(checkbox => checkbox.checked = source.checked);
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text)
        .then(() => {
            showNotification('클립보드에 복사되었습니다.', 'success');
        })
        .catch(() => {
            showNotification('복사에 실패했습니다.', 'error');
        });
}

function updateTransaction(id, hash) {
    if (!hash) return;
    
    if (!hash.match(/^0x[a-fA-F0-9]{64}$/)) {
        showNotification('올바른 트랜잭션 해시를 입력하세요.', 'error');
        return;
    }

    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            update_transaction: true,
            withdrawal_id: id,
            transaction_id: hash
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            throw new Error(data.message);
        }
    })
    .catch(error => {
        showNotification(error.message, 'error');
    });
}

function processBatchWithdrawals() {
    const checkboxes = document.getElementsByName('withdrawal_ids[]');
    const selectedIds = Array.from(checkboxes)
        .filter(cb => cb.checked)
        .map(cb => cb.value);

    if (selectedIds.length === 0) {
        showNotification('처리할 항목을 선택해주세요.', 'error');
        return;
    }

    if (!confirm(`선택한 ${selectedIds.length}건의 출금을 처리하시겠습니까?`)) {
        return;
    }

    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            process_withdrawals: true,
            selected_ids: selectedIds
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            throw new Error(data.message);
        }
    })
    .catch(error => {
        showNotification(error.message, 'error');
    });
}

// Auto refresh if pending withdrawals exist
setInterval(() => {
    const pendingExists = document.querySelector('.status-pending') !== null;
    if (pendingExists) {
        location.reload();
    }
}, 30000);
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>