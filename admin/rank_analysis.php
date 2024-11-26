<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/richtech.club/pages/error.log');


session_start();
require_once __DIR__ . '/../includes/config.php';


// 로그인 체크
if (!isset($_SESSION['user_id'])) {
    header("Location: /login?redirect=admin");
    exit;
}

$conn = db_connect();

// 직급별 승급 요구사항
$rank_requirements = [
    ['rank' => '1스타', 'requirements' => ['myAmount' => 1000, 'referral_count' => 2, 'lesser_leg_amount' => 10000]],
    ['rank' => '2스타', 'requirements' => ['myAmount' => 2000, 'referral_count' => 3, 'lesser_leg_amount' => 30000]],
    ['rank' => '3스타', 'requirements' => ['myAmount' => 3000, 'referral_count' => 4, 'lesser_leg_amount' => 50000]],
    ['rank' => '4스타', 'requirements' => ['myAmount' => 4000, 'referral_count' => 5, 'lesser_leg_amount' => 100000]],
    ['rank' => '5스타', 'requirements' => ['myAmount' => 5000, 'referral_count' => 6, 'lesser_leg_amount' => 300000]],
    ['rank' => '6스타', 'requirements' => ['myAmount' => 6000, 'referral_count' => 7, 'lesser_leg_amount' => 700000]],
    ['rank' => '7스타', 'requirements' => ['myAmount' => 7000, 'referral_count' => 8, 'lesser_leg_amount' => 1000000]],
];

function calculateUserPerformance($conn, $user_id) {
    $result = [
        'myAmount' => 0,
        'referral_count' => 0,
        'left_amounts' => 0,
        'right_amounts' => 0,
        'left_members' => 0,
        'right_members' => 0,
        'lesser_leg_amount' => 0,
        'current_rank' => ''
    ];

    // 본인 누적 매출 : orders테이블
    $stmt = $conn->prepare("SELECT SUM(total_amount) as myAmount FROM orders WHERE user_id = ? AND status = 'completed'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result['myAmount'] = $stmt->get_result()->fetch_assoc()['myAmount'] ?? 0;
    $stmt->close();

    // 직추천인 수: users테이블에서 referred_by = $user_id 인 레코드 수
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE referred_by = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result['referral_count'] = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();

    // 현재 직급: users테이블에서 id = $user_id 인 레코드의 rank
    $stmt = $conn->prepare("SELECT rank FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result['current_rank'] = $stmt->get_result()->fetch_assoc()['rank'];
    $stmt->close();

    // 좌우 실적 계산
    $left_result = calculate_leg_volume($conn, $user_id, 'left');
    $right_result = calculate_leg_volume($conn, $user_id, 'right');

    $result['left_members'] = $left_result['members'];
    $result['left_amounts'] = $left_result['amount'];

    $result['right_members'] = $right_result['members'];
    $result['right_amounts'] = $right_result['amount'];

    // 소실적 계산
    $result['lesser_leg_amount'] = min($result['left_amounts'], $result['right_amounts']);

    return $result;
}

function calculate_leg_volume($conn, $user_id, $position) {
    $sql = "
        WITH RECURSIVE downline AS (
            SELECT id, sponsored_by, position, myAmount
            FROM users
            WHERE sponsored_by = ? AND position = ?
            UNION ALL
            SELECT u.id, u.sponsored_by, u.position, u.myAmount
            FROM users u
            INNER JOIN downline d ON u.sponsored_by = d.id
        )
        SELECT COUNT(*) as members, COALESCE(SUM(myAmount), 0) as amount
        FROM downline
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $user_id, $position);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return [
        'members' => $data['members'],
        'amount' => $data['amount']
    ];
}

// 검색 조건 처리
$user_id_input = '';
$performance = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id_input = $_POST['user_id'] ?? '';

    if (!empty($user_id_input)) {
        // 사용자 ID로 회원 조회
        $stmt = $conn->prepare("SELECT id FROM users WHERE login_id = ?");
        $stmt->bind_param("s", $user_id_input);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user) {
            $performance = calculateUserPerformance($conn, $user['id']);
        } else {
            $errors[] = "해당 회원을 찾을 수 없습니다.";
        }
    } else {
        $errors[] = "회원 ID를 입력하세요.";
    }
}

$pageTitle = '직급 승급 분석';
include __DIR__ . '/admin_header.php';
?>

<style>
    .analysis-container {
        max-width: 1000px;
        margin: 20px auto;
        padding: 20px;
    }
    
    .analysis-form {
        background: rgba(17, 17, 17, 0.95);
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .result-card {
        background: rgba(17, 17, 17, 0.95);
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .metric {
        padding: 15px;
        border-bottom: 1px solid rgba(212, 175, 55, 0.1);
    }

    .metric-label {
        color: #d4af37;
        font-size: 0.9rem;
    }

    .metric-value {
        font-size: 1.2rem;
        font-weight: bold;
        color: #fff;
    }

    .rank-requirements {
        margin-top: 20px;
        padding: 15px;
        background: rgba(0, 0, 0, 0.2);
        border-radius: 5px;
    }

    .requirement {
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
    }

    .status-icon {
        margin-left: 10px;
    }

    .status-met {
        color: #4CAF50;
    }

    .status-unmet {
        color: #f44336;
    }
</style>

<div class="analysis-container">
    <form method="post" class="analysis-form">
        <div class="grid grid-cols-1 gap-4">
            <div class="form-group">
                <label class="form-label">조회할 회원 ID</label>
                <input type="text" name="user_id" class="form-control" value="<?php echo htmlspecialchars($user_id_input); ?>" required>
            </div>
        </div>
        <button type="submit" class="btn-gold mt-4">조회하기</button>
    </form>

    <?php if (!empty($errors)): ?>
        <div class="messages">
            <?php foreach ($errors as $error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($performance): ?>
        <div class="result-card">
            <h3 class="text-xl mb-4">실적 분석 결과</h3>

            <div class="grid grid-cols-2 gap-4">
                <div class="metric">
                    <div class="metric-label">본인 누적매출</div>
                    <div class="metric-value">$<?php echo number_format($performance['myAmount'], 2); ?></div>
                </div>
                <div class="metric">
                    <div class="metric-label">직접추천인 수</div>
                    <div class="metric-value"><?php echo $performance['referral_count']; ?>명</div>
                </div>
                <div class="metric">
                    <div class="metric-label">좌측실적 (회원수)</div>
                    <div class="metric-value">
                        $<?php echo number_format($performance['left_amounts'], 2); ?>
                        (<?php echo $performance['left_members']; ?>명)
                    </div>
                </div>
                <div class="metric">
                    <div class="metric-label">우측실적 (회원수)</div>
                    <div class="metric-value">
                        $<?php echo number_format($performance['right_amounts'], 2); ?>
                        (<?php echo $performance['right_members']; ?>명)
                    </div>
                </div>
                <div class="metric">
                    <div class="metric-label">소실적</div>
                    <div class="metric-value">$<?php echo number_format($performance['lesser_leg_amount'], 2); ?></div>
                </div>
                <div class="metric">
                    <div class="metric-label">현재 직급</div>
                    <div class="metric-value"><?php echo $performance['current_rank']; ?></div>
                </div>
            </div>

            <div class="rank-requirements">
                <h4 class="text-lg mb-3">직급 승급 요건 분석</h4>
                <?php
                $current_rank_index = -1;
                foreach ($rank_requirements as $index => $rank_info) {
                    if ($rank_info['rank'] == $performance['current_rank']) {
                        $current_rank_index = $index;
                        break;
                    }
                }

                for ($i = $current_rank_index + 1; $i < count($rank_requirements); $i++) {
                    $rank_info = $rank_requirements[$i];
                    $requirements = $rank_info['requirements'];
                    ?>
                    <div class="requirement">
                        <div class="requirement-title"><?php echo $rank_info['rank']; ?> 승급 조건:</div>
                        <div>
                            <div>
                                본인매출: $<?php echo number_format($requirements['myAmount']); ?>
                                <?php if ($performance['myAmount'] >= $requirements['myAmount']): ?>
                                    <span class="status-icon status-met">✓</span>
                                <?php else: ?>
                                    <span class="status-icon status-unmet">✗</span>
                                <?php endif; ?>
                            </div>
                            <div>
                                추천인수: <?php echo $requirements['referral_count']; ?>명
                                <?php if ($performance['referral_count'] >= $requirements['referral_count']): ?>
                                    <span class="status-icon status-met">✓</span>
                                <?php else: ?>
                                    <span class="status-icon status-unmet">✗</span>
                                <?php endif; ?>
                            </div>
                            <div>
                                소실적: $<?php echo number_format($requirements['lesser_leg_amount']); ?>
                                <?php if ($performance['lesser_leg_amount'] >= $requirements['lesser_leg_amount']): ?>
                                    <span class="status-icon status-met">✓</span>
                                <?php else: ?>
                                    <span class="status-icon status-unmet">✗</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>