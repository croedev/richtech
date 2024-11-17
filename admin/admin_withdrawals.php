<?php
// admin_withdrawals.php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/error.log');

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

// 프로세스 처리 부분 수정
if (isset($_POST['process_withdrawals'])) {
    if (!isset($_POST['selected_ids']) || !is_array($_POST['selected_ids'])) {
        throw new Exception("선택된 출금 요청이 없습니다.");
    }

    $processed = 0;
    $conn->begin_transaction();

    try {
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

        $conn->commit();
        $response = [
            'success' => true,
            'message' => "{$processed}건의 출금이 처리되었습니다."
        ];
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

// 출금 목록 조회
$sql = "
    SELECT w.*, 
           u.name AS username,
           u.login_id AS user_login_id,
           DATE_FORMAT(w.created_at, '%Y-%m-%d %H:%i') AS formatted_created_at,
           DATE_FORMAT(w.processed_at, '%Y-%m-%d %H:%i') AS formatted_processed_at
    FROM withdrawals w
    JOIN users u ON w.user_id = u.id
    ORDER BY w.created_at DESC
";

$result = $conn->query($sql);

$pageTitle = "출금 관리";
require_once __DIR__ . '/admin_header.php';
?>



<style>
    /* 기본 레이아웃 */
    body {
        background-color: #1a1a1a;
        color: #e0e0e0;
        margin: 0;
        padding: 0;
        min-width: 1400px; /* 최소 너비 설정 */
    }

    .admin-container {
        width: 100%;
        margin: 0;
        padding: 20px;
        box-sizing: border-box;
    }

    /* 테이블 스타일 */
    .table-container {
        overflow-x: auto;
        background: #2d2d2d;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }

    .admin-table {
        width: 100%;
        border-collapse: collapse;
        white-space: nowrap;
        font-size: 0.85rem;
    }

    .admin-table th,
    .admin-table td {
        padding: 8px 12px;
        text-align: left;
        border-bottom: 1px solid #404040;
        color: #e0e0e0;
        line-height: 1.2;
    }

    .admin-table th {
        background: #333333;
        font-weight: 600;
        color: #ffffff;
        font-size: 0.9rem;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .admin-table tr:hover {
        background: #3d3d3d;
    }

    /* 입력 필드 */
    .input-transaction {
        background: #333333;
        color: #e0e0e0;
        border: 1px solid #404040;
        padding: 4px 8px;
        font-size: 0.85rem;
        width: 180px;
    }

    /* 버튼 스타일 */
    .btn {
        padding: 4px 8px;
        font-size: 0.75rem;
        border-radius: 3px;
        margin-left: 4px;
        cursor: pointer;
        border: none;
        color: #ffffff;
    }

    .btn-copy { background: #4a4a4a; }
    .btn-update { background: #0056b3; }
    .btn-process { background: #1e7e34; }

    /* 상태 배지 */
    .status-badge {
        padding: 4px 8px;
        border-radius: 3px;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-block;
        min-width: 60px;
        text-align: center;
    }

    .status-pending { background: #856404; color: #fff3cd; }
    .status-completed { background: #155724; color: #d4edda; }
    .status-failed { background: #721c24; color: #f8d7da; }

    /* 링크 스타일 */
    a {
        color: #66b3ff;
        text-decoration: none;
    }

    a:hover {
        color: #99ccff;
    }

    /* 사용자 정보 */
    .user-info {
        font-size: 0.85rem;
        line-height: 1.2;
    }

    .user-info small {
        color: #999;
        font-size: 0.75rem;
    }

    /* 상단 제목 */
    .text-2xl {
        color: #ffffff;
        margin-bottom: 20px;
    }

    /* 상단 컨트롤 영역 */
    .top-controls {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
</style>

<div class="admin-container">
    <div class="top-controls">
        <h1 class="text-2xl font-bold">출금 관리</h1>
        <button onclick="processSelected()" class="btn btn-process">
            선택 항목 처리
        </button>
    </div>

    <div class="table-container">
        <table class="admin-table">
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
                        <?php echo htmlspecialchars($row['username']); ?>
                        <br>
                        <small><?php echo htmlspecialchars($row['user_login_id']); ?></small>
                    </td>
                    <td><?php echo number_format($row['request_amount_usdp'], 4); ?> USDP</td>
                    <td><?php echo number_format($row['fee_amount'], 4); ?> USDP</td>
                    <td>
                        <?php echo number_format($row['actual_amount_usdt'], 6); ?> USDT
                        <?php if ($row['status'] === 'pending'): ?>
                        <button class="btn btn-copy" onclick="copyToClipboard('<?php echo $row['actual_amount_usdt']; ?>')">
                            복사
                        </button>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo $row['to_address']; ?>
                        <?php if ($row['status'] === 'pending'): ?>
                        <button class="btn btn-copy" onclick="copyToClipboard('<?php echo $row['to_address']; ?>')">
                            복사
                        </button>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($row['status'] === 'pending'): ?>
                        <input type="text" class="input-transaction" 
                               placeholder="트랜잭션 해시"
                               onchange="updateTransaction(<?php echo $row['id']; ?>, this.value)">
                        <?php elseif ($row['transaction_id']): ?>
                        <a href="<?php echo $row['scan_link']; ?>" target="_blank">
                            <?php echo substr($row['transaction_id'], 0, 16) . '...'; ?>
                        </a>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status-badge status-<?php echo $row['status']; ?>">
                            <?php
                            switch($row['status']) {
                                case 'pending': echo '처리중';
                                    break;
                                case 'completed': echo '완료';
                                    break;
                                case 'failed': echo '실패';
                                    break;
                            }
                            ?>
                        </span>
                    </td>
                    <td><?php echo $row['formatted_created_at']; ?></td>
                    <td><?php echo $row['formatted_processed_at'] ?: '-'; ?></td>
                    <td>
                        <?php if ($row['status'] === 'pending'): ?>
                        <button class="btn btn-process" onclick="processWithdrawal(<?php echo $row['id']; ?>)">
                            처리
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showNotification('복사되었습니다.', 'success');
    }).catch(() => {
        showNotification('복사 실패', 'error');
    });
}

function toggleSelectAll(source) {
    const checkboxes = document.getElementsByName('withdrawal_ids[]');
    checkboxes.forEach(checkbox => checkbox.checked = source.checked);
}

async function updateTransaction(id, hash) {
    if (!hash) return;
    
    if (!hash.match(/^0x[a-fA-F0-9]{64}$/)) {
        showNotification('올바른 트랜잭션 해시를 입력하세요.', 'error');
        return;
    }

    try {
        const response = await fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                update_transaction: true,
                withdrawal_id: id,
                transaction_id: hash
            })
        });

        const data = await response.json();
        
        if (data.success) {
            showNotification(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        showNotification(error.message, 'error');
    }
}

// 상태 업데이트 함수 수정
async function processSelected() {
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

    try {
        const formData = new FormData();
        formData.append('process_withdrawals', '1');
        selectedIds.forEach(id => formData.append('selected_ids[]', id));

        const response = await fetch('', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        
        if (data.success) {
            // 상태 업데이트 UI 반영
            selectedIds.forEach(id => {
                const row = document.querySelector(`tr[data-id="${id}"]`);
                if (row) {
                    // 체크박스 제거
                    row.querySelector('td.checkbox-column').innerHTML = '';
                    // 상태 배지 업데이트
                    const statusBadge = row.querySelector('.status-badge');
                    if (statusBadge) {
                        statusBadge.className = 'status-badge status-completed';
                        statusBadge.textContent = '완료';
                    }
                    // 처리 버튼 제거
                    const actionCell = row.querySelector('td:last-child');
                    if (actionCell) {
                        actionCell.innerHTML = '';
                    }
                    // 처리일시 업데이트
                    const processedAtCell = row.querySelector('td:nth-last-child(2)');
                    if (processedAtCell) {
                        processedAtCell.textContent = new Date().toLocaleString();
                    }
                }
            });
            
            showNotification(data.message, 'success');
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        showNotification(error.message, 'error');
    }
}



async function processWithdrawal(id) {
    if (!confirm('이 출금 요청을 처리하시겠습니까?')) {
        return;
    }

    try {
        const response = await fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                process_withdrawals: true,
                selected_ids: [id]
            })
        });

        const data = await response.json();
        
        if (data.success) {
            showNotification(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        showNotification(error.message, 'error');
    }
}

// 자동 새로고침 (5분마다)
setInterval(() => location.reload(), 300000);
</script>

<?php
$conn->close();
include '../includes/footer.php'; 
?>