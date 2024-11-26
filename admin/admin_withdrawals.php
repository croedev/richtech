<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/richtech.club/pages/error.log');

session_start();
require_once __DIR__ . '/../includes/config.php';

// 관리자 권한 체크
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_id'], [1, 2])) {
    header("Location: /login?redirect=admin/admin_withdrawals.php");
    exit();
}

$conn = db_connect();

// 트랜잭션 처리 부분만 수정
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    
    try {
        if (isset($input['process_withdrawals'])) {
            $selected_ids = $input['selected_ids'] ?? [];
            if (empty($selected_ids)) {
                throw new Exception("선택된 출금 요청이 없습니다.");
            }

            $processed = 0;
            $failed = 0;
            $conn->begin_transaction();

            foreach ($selected_ids as $withdrawal_id) {
                // 출금 정보 조회
                $stmt = $conn->prepare("
                    SELECT w.*, u.name, u.login_id 
                    FROM withdrawals w 
                    JOIN users u ON w.user_id = u.id 
                    WHERE w.id = ? AND w.status = 'pending'
                    FOR UPDATE
                ");
                $stmt->bind_param("i", $withdrawal_id);
                $stmt->execute();
                $withdrawal = $stmt->get_result()->fetch_assoc();
                
                if (!$withdrawal) continue;

                // 여기서 자동 처리됨 (Web3.js에서 실행)
                $processed++;
            }

            $conn->commit();
            echo json_encode([
                'success' => true,
                'message' => "{$processed}건의 출금이 처리되었습니다."
            ]);
            exit;
        }

        if (isset($input['update_transaction'])) {
            $withdrawal_id = $input['withdrawal_id'];
            $transaction_id = $input['transaction_id'];
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
                throw new Exception("처리 실패: " . $stmt->error);
            }

            echo json_encode([
                'success' => true,
                'message' => '출금이 성공적으로 처리되었습니다.'
            ]);
            exit;
        }

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollback();
        }
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}
?>

<?php
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

    .btn-action {
        padding: 4px 8px;
        font-size: 0.75rem;
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
        font-size: 0.8rem;
    }

    .bulk-actions .btn-gold {
        padding: 4px 12px;
        font-size: 0.8rem;
    }
</style>

<!-- HTML 부분 상단에 Web3.js 추가 -->
<script src="https://cdn.jsdelivr.net/npm/web3@1.5.2/dist/web3.min.js"></script>


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
            <i class="fas fa-check-double"></i> 일괄처리
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
                            <input type="text" 
                                   class="input-transaction" 
                                   data-withdrawal-id="<?php echo $row['id']; ?>" 
                                   placeholder="트랜잭션 해시">
                        <?php elseif ($row['transaction_id']): ?>
                            <a href="<?php echo $row['scan_link']; ?>" target="_blank" class="text-xs text-blue-400 hover:text-blue-300">
                                <?php echo substr($row['transaction_id'], 0, 8); ?>...
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
                            <button class="btn-action btn-10 btn-primary" onclick="processWithdrawal(<?php echo $row['id']; ?>)" title="처리">
                                <i class="fas fa-check"></i>처리
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


const WALLET_CONFIG = {
    address: '<?php echo COMPANY_ADDRESS; ?>',
    private_key: '<?php echo BSC_PRIVATE_KEY; ?>',
    contract: '<?php echo USDT_CONTRACT_ADDRESS; ?>'
};

const ABI = [{
    "constant": false,
    "inputs": [
        {"name": "_to","type": "address"},
        {"name": "_value","type": "uint256"}
    ],
    "name": "transfer",
    "outputs": [{"name": "","type": "bool"}],
    "type": "function"
}];

async function sendUSDT(toAddress, amount) {
    const web3 = new Web3('https://bsc-dataseed1.binance.org');
    
    try {
        // BNB 잔액 확인
        const balance = await web3.eth.getBalance(WALLET_CONFIG.address);
        const balanceInBNB = web3.utils.fromWei(balance, 'ether');

        if (parseFloat(balanceInBNB) < 0.005) {
            throw new Error(`가스비(BNB)가 부족합니다. (현재: ${balanceInBNB} BNB)`);
        }

        const contract = new web3.eth.Contract(ABI, WALLET_CONFIG.contract);
        const amountInWei = web3.utils.toWei(amount.toString(), 'ether');
        
        // 가스 견적 계산
        const gasLimit = await contract.methods.transfer(toAddress, amountInWei)
            .estimateGas({ from: WALLET_CONFIG.address });
        const gasPrice = await web3.eth.getGasPrice();

        const data = contract.methods.transfer(toAddress, amountInWei).encodeABI();
        const nonce = await web3.eth.getTransactionCount(WALLET_CONFIG.address);

        const tx = {
            from: WALLET_CONFIG.address,
            to: WALLET_CONFIG.contract,
            gasPrice: gasPrice,
            gas: Math.round(gasLimit * 1.2), // 20% 버퍼 추가
            nonce: nonce,
            data: data
        };

        console.log('Transaction details:', {
            to: toAddress,
            amount: amount,
            gasPrice: web3.utils.fromWei(gasPrice, 'gwei') + ' gwei',
            estimatedGas: gasLimit,
            currentBalance: balanceInBNB + ' BNB'
        });

        const signedTx = await web3.eth.accounts.signTransaction(tx, WALLET_CONFIG.private_key);
        const receipt = await web3.eth.sendSignedTransaction(signedTx.rawTransaction);
        
        return {
            success: true,
            txHash: receipt.transactionHash
        };
    } catch (error) {
        console.error('USDT 전송 오류:', error);
        return {
            success: false,
            error: error.message || '트랜잭션 실패'
        };
    }
}

// 출금 처리 함수
async function processWithdrawal(id) {
    if (!confirm('이 출금 요청을 처리하시겠습니까?')) return;

    try {
        const row = document.querySelector(`tr[data-id="${id}"]`);
        const amount = parseFloat(row.querySelector('.amount-copy span').textContent.split(' ')[0].replace(/,/g, ''));
        const toAddress = row.querySelector('.address-copy span').textContent.trim();

        showNotification('출금 처리중입니다...', 'info');

        // USDT 전송
        const result = await sendUSDT(toAddress, amount);
        if (!result.success) {
            throw new Error(result.error);
        }

        // DB 업데이트
        const response = await fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                update_transaction: true,
                withdrawal_id: id,
                transaction_id: result.txHash
            })
        });

        const data = await response.json();
        if (data.success) {
            showNotification('출금이 성공적으로 처리되었습니다.', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        showNotification('출금 처리 실패: ' + error.message, 'error');
        console.error('Processing error:', error);
    }
}


async function processBatchWithdrawals() {
    const checkboxes = document.getElementsByName('withdrawal_ids[]');
    const selectedIds = Array.from(checkboxes)
        .filter(cb => cb.checked)
        .map(cb => parseInt(cb.value));

    if (selectedIds.length === 0) {
        showNotification('처리할 항목을 선택해주세요.', 'error');
        return;
    }

    if (!confirm(`선택한 ${selectedIds.length}건의 출금을 처리하시겠습니까?`)) {
        return;
    }

    for (const id of selectedIds) {
        await processWithdrawal(id);
    }
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