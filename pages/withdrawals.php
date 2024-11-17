<?php
// withdrawals.php

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

session_start();
require_once __DIR__ . '/../includes/config.php';

// 로그인 체크
if (!isset($_SESSION['user_id'])) {
    header("Location: login?redirect=withdrawals");
    exit();
}

$conn = db_connect();
$user_id = $_SESSION['user_id'];

// 사용자 정보 조회
$stmt = $conn->prepare("
    SELECT name, login_id, point, bsc_address 
    FROM users 
    WHERE id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_info = $stmt->get_result()->fetch_assoc();
$stmt->close();

$current_point = floatval($user_info['point']);
$saved_bsc_address = $user_info['bsc_address'] ?? '';

// BSC 주소 검증 함수
function isValidBscAddress($address) {
    return preg_match('/^0x[a-fA-F0-9]{40}$/i', $address);
}

// POST 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => '', 'redirect' => false];
    
    try {
        if (!isset($_POST['amount'], $_POST['bsc_address'])) {
            throw new Exception("필수 입력값이 누락되었습니다.");
        }

        $conn->begin_transaction();
        
        // 입력값 검증
        $request_amount_usdp = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT);
        $to_address = trim($_POST['bsc_address']);
        
        // 현재 사용자의 포인트 다시 확인
        $stmt = $conn->prepare("SELECT point FROM users WHERE id = ? FOR UPDATE");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $current_point = $stmt->get_result()->fetch_assoc()['point'];
        $stmt->close();
        
        // 유효성 검사
        if ($request_amount_usdp < 50) {
            throw new Exception("최소 출금 금액은 50 USDP입니다.");
        }
        
        if ($request_amount_usdp > $current_point) {
            throw new Exception("출금 요청 금액이 보유 포인트를 초과합니다.");
        }
        
        if (!isValidBscAddress($to_address)) {
            throw new Exception("유효하지 않은 BSC 주소입니다.");
        }
        
        // 수수료 계산
        $fee_percentage = 3.00;
        $fee_amount = round($request_amount_usdp * ($fee_percentage / 100), 4);
        $actual_amount_usdt = round($request_amount_usdp - $fee_amount, 6);

        // withdrawals 테이블에 저장
        $stmt = $conn->prepare("
            INSERT INTO withdrawals (
                user_id, 
                request_amount_usdp, 
                fee_percentage, 
                fee_amount, 
                actual_amount_usdt, 
                to_address, 
                status,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");

        $stmt->bind_param(
            "idddds",
            $user_id,
            $request_amount_usdp,
            $fee_percentage,
            $fee_amount,
            $actual_amount_usdt,
            $to_address
        );

        if (!$stmt->execute()) {
            throw new Exception("출금 신청 저장 실패: " . $stmt->error);
        }
        
        // 사용자 포인트 차감
        $stmt = $conn->prepare("
            UPDATE users 
            SET point = point - ? 
            WHERE id = ? AND point >= ?
        ");
        
        $stmt->bind_param("ddi", 
            $request_amount_usdp,
            $user_id,
            $request_amount_usdp
        );
        
        if (!$stmt->execute() || $stmt->affected_rows !== 1) {
            throw new Exception("포인트 차감 실패");
        }

        $conn->commit();
        $response = [
            'success' => true,
            'message' => '출금 신청이 접수되었습니다. 확인후 익일 지정시간에 출금됩니다.',
            'redirect' => true
        ];

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollback();
        }
        $response = [
            'success' => false,
            'message' => $e->getMessage(),
            'redirect' => false
        ];
        error_log("Withdrawal error: " . $e->getMessage());
    }

    echo json_encode($response);
    exit;
}

// 출금 신청 내역 조회
$stmt = $conn->prepare("
    SELECT *, 
           DATE_FORMAT(created_at, '%Y.%m.%d %H:%i') as formatted_created_at,
           DATE_FORMAT(processed_at, '%Y.%m.%d %H:%i') as formatted_processed_at
    FROM withdrawals 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$withdrawals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageTitle = '출금 신청';
include __DIR__ . '/../includes/header.php';
?>




<!-- withdrawals.php 계속 -->

<style>
    .withdrawal-container {
        max-width: 800px;
        margin: 20px auto;
        padding: 20px;
    }

    .info-card {
        background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 15px;
        padding: 25px;
        margin-bottom: 20px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .balance-info {
        background: linear-gradient(145deg, #2d2d2d, #1a1a1a);
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        border: 1px solid rgba(212, 175, 55, 0.3);
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-label {
        display: block;
        color: #d4af37;
        margin-bottom: 8px;
        font-size: 0.9rem;
        font-family: 'Noto Sans KR', sans-serif;
    }

    .form-control {
        width: 100%;
        padding: 12px;
        background: rgba(0, 0, 0, 0.2);
        border: 1px solid rgba(212, 175, 55, 0.3);
        border-radius: 8px;
        color: #ffffff;
        transition: all 0.3s ease;
    }

    .form-control:focus {
        border-color: #d4af37;
        box-shadow: 0 0 0 2px rgba(212, 175, 55, 0.2);
    }

    .btn-withdraw {
        width: 100%;
        background: linear-gradient(145deg, #d4af37, #aa8a2e);
        color: #000;
        border: none;
        padding: 12px 24px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-withdraw:hover {
        background: linear-gradient(145deg, #aa8a2e, #d4af37);
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(212, 175, 55, 0.2);
    }

    .fee-calculation {
        margin-top: 10px;
        padding: 10px;
        background: rgba(212, 175, 55, 0.05);
        border-radius: 4px;
        font-size: 0.9rem;
    }

    .history-section {
        margin-top: 30px;
    }

    .history-card {
        background: rgba(26, 26, 26, 0.95);
        border: 1px solid rgba(212, 175, 55, 0.1);
        border-radius: 10px;
        margin-bottom: 15px;
        overflow: hidden;
    }

    .history-header {
        background: rgba(212, 175, 55, 0.1);
        padding: 12px 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .status-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.8rem;
    }

    .status-pending {
        background: rgba(241, 196, 15, 0.2);
        color: #f1c40f;
    }

    .status-completed {
        background: rgba(46, 204, 113, 0.2);
        color: #2ecc71;
    }

    .status-failed {
        background: rgba(231, 76, 60, 0.2);
        color: #e74c3c;
    }
</style>

<div class="withdrawal-container">
    <!-- 정보 카드 -->
    <div class="info-card">
        <div class="flex justify-between items-center mb-4">
            <h2 class="fs-18 font-bold text-orange notosans">포인트 출금</h2>
            <span class="fs-14">
                현재 포인트: <span class="btn14 bg-gray60 text-orange font-bold">
                    <?php echo number_format($current_point, 4); ?> <small>USDP</small>
                </span>
            </span>
        </div>

        <!-- 출금 신청 폼 -->
        <form id="withdrawalForm" method="post" class="withdrawal-form">
            <div class="form-group">
                <label class="form-label">출금 신청 금액 (USDP)</label>
                <input type="number" name="amount" id="amount" class="form-control" 
                       step="0.0001" min="50" required placeholder="최소 50 USDP">
                <div class="fee-info text-green5 fs-12">* 최소 출금 금액: 50 USDP / 수수료: 3.00%</div>
            </div>

            <div class="form-group">
                <label class="form-label">출금 받을 BSC 주소 (USDT BEP-20)</label>
                <input type="text" name="bsc_address" id="bsc_address" class="form-control"
                       value="<?php echo htmlspecialchars($saved_bsc_address); ?>"
                       placeholder="0x로 시작하는 BSC 주소" required>
            </div>

            <button type="submit" class="btn-withdraw">출금 신청하기</button>
        </form>
    </div>

    <!-- 안내사항 섹션 -->
    <div class="notice-section bg-gray80">
        <div class="notice-title fs-14 notosans" onclick="toggleNotice()" style="cursor: pointer;">
            출금 안내사항 <span id="toggle-icon">▼</span>
        </div>
        <ul class="notice-list" id="notice-list" style="display: none;">
            <li class="fs-12 notosans">최소 출금 금액은 50 USDP입니다.</li>
            <li class="fs-12 notosans">출금 수수료는 3.00%가 적용됩니다.</li>
            <li class="fs-12 notosans">BSC(BEP-20) USDT로만 출금 가능합니다.</li>
            <li class="fs-12 notosans">실제 출금액은 신청금액에서 수수료를 제외한 금액입니다.</li>
            <li class="fs-12 notosans">출금은 익일 지정된 시간에 일괄 처리됩니다.</li>
            <li class="fs-12 notosans">정확한 BSC 주소 입력이 필요합니다.</li>
        </ul>
    </div>

    <!-- 출금 신청 내역 -->
    <div class="history-section mt60 mb100">
        <h6 class="fs-18 font-bold text-orange notosans">
            <i class="fas fa-list-ul mr10"></i> 출금 신청 내역
        </h6>
        
        <hr class="mb-4">
        <?php if (empty($withdrawals)): ?>
            <div class="text-center text-gray-500 py-8">
                출금 신청 내역이 없습니다.
            </div>
        <?php else: ?>
            <?php foreach ($withdrawals as $withdrawal): ?>
                <div class="history-card">
                    <div class="history-header">
                        <span class="text-sm">
                            <?php echo $withdrawal['formatted_created_at']; ?>
                        </span>
                        <span class="history-amount text-orange">
                            <?php echo number_format($withdrawal['request_amount_usdp'], 4); ?> USDP
                        </span>
                    </div>
                    <div class="history-body p-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <span class="text-gray-400">신청금액:</span>
                                <span class="text-orange">
                                    <?php echo number_format($withdrawal['request_amount_usdp'], 4); ?> USDP
                                </span>
                            </div>
                            <div>
                                <span class="text-gray-400">수수료(3%):</span>
                                <span class="text-orange">
                                    <?php echo number_format($withdrawal['fee_amount'], 4); ?> USDP
                                </span>
                            </div>
                            <div>
                                <span class="text-gray-400">실출금액:</span>
                                <span class="text-orange">
                                    <?php echo number_format($withdrawal['actual_amount_usdt'], 6); ?> USDT
                                </span>
                            </div>
                            <div>
                                <span class="text-gray-400">상태:</span>
                                <span class="status-badge status-<?php echo $withdrawal['status']; ?>">
                                    <?php
                                    switch($withdrawal['status']) {
                                        case 'completed': echo '출금완료'; break;
                                        case 'pending': echo '처리중'; break;
                                        case 'failed': echo '출금실패'; break;
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>
                        <?php if ($withdrawal['transaction_id']): ?>
                            <div class="mt-3">
                                <span class="text-gray-400">트랜잭션:</span>
                                <a href="<?php echo $withdrawal['scan_link']; ?>" 
                                   target="_blank" 
                                   class="text-blue-400 hover:underline">
                                    <?php echo $withdrawal['transaction_id']; ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
// 안내사항 토글
function toggleNotice() {
    const noticeList = document.getElementById('notice-list');
    const toggleIcon = document.getElementById('toggle-icon');
    
    if (noticeList.style.display === 'none') {
        noticeList.style.display = 'block';
        toggleIcon.textContent = '▲';
    } else {
        noticeList.style.display = 'none';
        toggleIcon.textContent = '▼';
    }
}

// 출금 폼 제출 처리
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('withdrawalForm');
    
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const amount = parseFloat(document.getElementById('amount').value);
        const bscAddress = document.getElementById('bsc_address').value;
        const currentPoint = <?php echo $current_point; ?>;
        
        // 유효성 검사
        if (amount < 50) {
            showNotification('최소 출금 금액은 50 USDP입니다.', 'error');
            return false;
        }
        
        if (amount > currentPoint) {
            showNotification('출금 금액이 보유 포인트를 초과합니다.', 'error');
            return false;
        }

        if (!/^0x[a-fA-F0-9]{40}$/i.test(bscAddress)) {
            showNotification('올바른 BSC 주소를 입력해주세요.', 'error');
            return false;
        }

        const fee = amount * 0.03;
        const actualAmount = amount - fee;

        if (!confirm(
            `아래 내용으로 출금 신청을 진행하시겠습니까?\n\n` +
            `신청 금액: ${amount.toFixed(4)} USDP\n` +
            `수수료(3%): ${fee.toFixed(4)} USDP\n` +
            `실수령액: ${actualAmount.toFixed(4)} USDT\n\n` +
            `BSC 주소: ${bscAddress}`
        )) {
            return false;
        }

        try {
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 처리중...';

            const formData = new FormData(form);
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            
            if (result.success) {
                showNotification(result.message, 'success');
                if (result.redirect) {
                    setTimeout(() => window.location.reload(), 1500);
                }
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            showNotification(error.message || '출금 신청 처리 중 오류가 발생했습니다.', 'error');
        } finally {
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '출금 신청하기';
        }
    });

    // 입력값 실시간 계산
    document.getElementById('amount').addEventListener('input', function(e) {
        const amount = parseFloat(this.value);
        const feeInfo = document.createElement('div');
        feeInfo.className = 'fee-calculation';
        
        if (amount && amount >= 50) {
            const fee = amount * 0.03;
            const actualAmount = amount - fee;
            feeInfo.innerHTML = `
                <div class="fee-detail">
                    <p class="text-gray-400">예상 수수료: ${fee.toFixed(4)} USDP</p>
                    <p class="text-orange">실수령액: ${actualAmount.toFixed(4)} USDT</p>
                </div>
            `;
        }

        const existingInfo = document.querySelector('.fee-calculation');
        if (existingInfo) {
            existingInfo.remove();
        }
        if (amount && amount >= 50) {
            this.parentElement.appendChild(feeInfo);
        }
    });
});

// 자동 새로고침 (pending 상태가 있는 경우)
function autoRefresh() {
    const pendingWithdrawals = document.querySelectorAll('.status-pending');
    if (pendingWithdrawals.length > 0) {
        setTimeout(() => window.location.reload(), 30000);
    }
}

// 페이지 로드 시 자동 새로고침 시작
document.addEventListener('DOMContentLoaded', autoRefresh);
</script>

<?php
// withdrawals.php 마지막 부분
$conn->close();

include __DIR__ . '/../includes/footer.php';
?>