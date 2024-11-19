<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/richtech.club/pages/error_bonus.log');

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions_bonus.php';


// 관리자 권한 체크
if (!isset($_SESSION['user_id']) || ($_SESSION['user_id'] != '1' && $_SESSION['user_id'] != '2')) {
    header("Location: /login?redirect=admin");
    exit;
}

$conn = db_connect();
$errors = [];
$messages = [];

$startDate = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d');
$endDate = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d');
$selectedBonuses = isset($_POST['bonuses']) ? $_POST['bonuses'] : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 입력 검증
    if (empty($selectedBonuses)) {
        $errors[] = "적어도 하나의 수당을 선택해야 합니다.";
    }

    if (strtotime($startDate) > strtotime($endDate)) {
        $errors[] = "시작일은 종료일보다 이전이어야 합니다.";
    }

    if (empty($errors)) {
        try {
            $conn->begin_transaction();

            $start_time = $startDate . ' 00:00:00';
            $end_time = $endDate . ' 23:59:59';

            // 주문 조회
            $stmt = $conn->prepare("
                SELECT id, created_at, total_amount 
                FROM orders 
                WHERE created_at BETWEEN ? AND ? 
                AND status = 'completed' 
                AND (paid_referral = 'pending' OR paid_status = 'pending' OR paid_center = 'pending')
            ");
            $stmt->bind_param("ss", $start_time, $end_time);
            $stmt->execute();
            $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // 날짜별로 처리
            $currentDate = $startDate;
            while (strtotime($currentDate) <= strtotime($endDate)) {
                $messages[] = "<strong>{$currentDate} 정산 시작</strong>";

                // 해당 날짜의 주문 필터링
                $dailyOrders = array_filter($orders, function($order) use ($currentDate) {
                    $orderDate = date('Y-m-d', strtotime($order['created_at']));
                    return $orderDate === $currentDate;
                });

                // 추천수당 계산
                if (in_array('referral', $selectedBonuses)) {
                    $messages[] = "{$currentDate} 추천수당 계산 중...";
                    foreach ($dailyOrders as $order) {
                        $orderId = $order['id'];
                        $orderAmount = number_format($order['total_amount'], 2);
                        $orderDate = date('Y-m-d', strtotime($order['created_at']));
                        $messages[] = "주문 ID: {$orderId}, 날짜: {$orderDate}, 매출: {$orderAmount} USDT - 추천수당 계산 중...";
                        calculate_referral_bonus($orderId, $conn);
                        $messages[] = "주문 ID: {$orderId} - 추천수당 계산 완료";
                    }
                    $messages[] = "{$currentDate} 추천수당 계산 완료";
                }

                // 직급수당 계산
                if (in_array('rank', $selectedBonuses)) {
                    $messages[] = "{$currentDate} 직급수당 계산 중...";
                    try {
                        calculate_rank_bonus($conn, $currentDate);
                        $messages[] = "{$currentDate} 직급수당 계산 완료";
                    } catch (Exception $e) {
                        $errors[] = "직급수당 계산 중 오류 발생: " . $e->getMessage();
                        error_log($e->getMessage());
                    }
                }

                // 센터수당 계산
                if (in_array('center', $selectedBonuses)) {
                    $messages[] = "{$currentDate} 센터수당 계산 중...";
                    try {
                        calculate_center_bonus($conn, $currentDate);
                        $messages[] = "{$currentDate} 센터수당 계산 완료";
                    } catch (Exception $e) {
                        $errors[] = "센터수당 계산 중 오류 발생: " . $e->getMessage();
                        error_log($e->getMessage());
                    }
                }

                // 회원들의 실적 업데이트 및 직급 승급 계산
                if (in_array('performance', $selectedBonuses)) {
                    $messages[] = "{$currentDate} 회원 실적 및 직급 업데이트 중...";
                    update_user_performance_and_rank($conn);
                    $messages[] = "{$currentDate} 회원 실적 및 직급 업데이트 완료";
                }

                // 회사 일일 통계 저장
                if (in_array('company', $selectedBonuses)) {
                    $messages[] = "{$currentDate} 회사 일일 통계 저장 중...";
                    save_company_state($conn, $currentDate);
                    $messages[] = "{$currentDate} 회사 일일 통계 저장 완료";
                }

                $messages[] = "<strong>{$currentDate} 정산 완료</strong>";
                $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
            }

            $conn->commit();
            $messages[] = "<strong>정산이 성공적으로 완료되었습니다.</strong>";
        } catch (Exception $e) {
            $conn->rollback();
            error_log("정산 중 오류 발생: " . $e->getMessage());
            $errors[] = "정산 중 오류가 발생했습니다: " . $e->getMessage();
        }
    }
}

$pageTitle = '정산하기';
include __DIR__ . '/admin_header.php';
?>

<style>
    .settlement-container {
        max-width: 800px;
        margin: 20px auto;
        padding: 20px;
        color: #d4af37;
        font-family: 'Noto Sans KR', sans-serif;
    }

    .settlement-form {
        background: rgba(17, 17, 17, 0.95);
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-label {
        display: block;
        color: #d4af37;
        margin-bottom: 5px;
        font-size: 0.9rem;
    }

    .form-control {
        width: 100%;
        background: rgba(0, 0, 0, 0.2);
        border: 1px solid rgba(212, 175, 55, 0.3);
        color: #fff;
        padding: 8px;
        border-radius: 4px;
        font-size: 0.9rem;
    }

    .btn-gold {
        background: linear-gradient(135deg, #d4af37, #aa8a2e);
        color: #000;
        border: none;
        padding: 10px 20px;
        border-radius: 5px;
        cursor: pointer;
        font-weight: 600;
        width: 100%;
        margin-top: 15px;
        transition: all 0.3s ease;
    }

    .btn-gold:hover {
        background: linear-gradient(135deg, #aa8a2e, #d4af37);
        transform: translateY(-1px);
    }

    .messages {
        background: rgba(0, 0, 0, 0.8);
        border: 1px solid rgba(212, 175, 55, 0.3);
        border-radius: 10px;
        padding: 20px;
        color: #fff;
        font-size: 0.8rem;
        font-family: 'Noto Sans KR', sans-serif;
        overflow-y: auto;
        font-weight: 300;
    }

    .message {
        margin-bottom: 10px;
    }

    .error {
        color: #ff6b6b;
    }

    .success {
        color: #4caf50;
    }
</style>


<div class="settlement-container">
    <h2>정산하기</h2>
 

    <form method="post" action="" class="settlement-form">
        <div class="form-group">
            <label class="form-label">정산 기간 설정</label>
            <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($startDate); ?>" required>
            ~
            <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($endDate); ?>" required>
        </div>

        <div class="form-group">
            <label class="form-label">수당 계산 선택</label>
            <div>
                <label>
                    <input type="checkbox" name="bonuses[]" value="referral" onclick="confirmReferralBonus(this)" <?php echo in_array('referral', $selectedBonuses) ? 'checked' : ''; ?>>
                    추천수당 계산
                </label>
            </div>
            <script>
            function confirmReferralBonus(checkbox) {
                if(checkbox.checked) {
                    if(!confirm('이미 매출주문시 자동으로 추천수당은 실시간으로 정산되었습니다.\n따라서 중복정산우려가 발생할수 있습니다.\n다시한번 확인하시고 추천수당 계산을 선택하시겠습니까?')) {
                        checkbox.checked = false;
                    }
                }
            }
            </script>
            <div>
                <label>
                    <input type="checkbox" name="bonuses[]" value="rank" <?php echo in_array('rank', $selectedBonuses) ? 'checked' : ''; ?>>
                    직급수당 계산
                </label>
            </div>
            <div>
                <label>
                    <input type="checkbox" name="bonuses[]" value="center" <?php echo in_array('center', $selectedBonuses) ? 'checked' : ''; ?>>
                    센터수당 계산
                </label>
            </div>
            <div>
                <label>
                    <input type="checkbox" name="bonuses[]" value="performance" <?php echo in_array('performance', $selectedBonuses) ? 'checked' : ''; ?>>
                    회원 실적 업데이트 및 직급 승급
                </label>
            </div>
            <div>
                <label>
                    <input type="checkbox" name="bonuses[]" value="company" <?php echo in_array('company', $selectedBonuses) ? 'checked' : ''; ?>>
                    회사 일일 통계 저장
                </label>
            </div>
        </div>

        <button type="submit" class="btn-gold">정산하기</button>
    </form>
</div>

   <?php if (!empty($errors)): ?>
        <div class="messages">
            <?php foreach ($errors as $error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($messages)): ?>
        <div class="messages">
            <?php foreach ($messages as $message): ?>
                <div class="message"><?php echo $message; ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>


<?php include __DIR__ . '/admin_footer.php'; ?>