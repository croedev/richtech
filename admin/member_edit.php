<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/richtech.club/pages/error.log');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/admin_functions.php';

// 관리자 권한 체크
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_id'], [1, 2])) {
    header("Location: /login?redirect=admin/member_edit.php");
    exit;
}

// CSRF 토큰 생성
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$conn = db_connect();
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$success_message = $error_message = '';

// POST 요청 처리 (회원 정보 수정)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF 토큰 검증
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid CSRF token');
        }

        $conn->begin_transaction();

        // BSC 주소 유효성 검사
        if (!empty($_POST['bsc_address']) && !preg_match('/^0x[0-9a-fA-F]{40}$/', $_POST['bsc_address'])) {
            throw new Exception('유효하지 않은 BSC 주소 형식입니다.');
        }

        // 이메일 유효성 검사
        if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('유효하지 않은 이메일 주소입니다.');
        }

        // 전화번호 유효성 검사
        if (!empty($_POST['phone']) && !preg_match('/^[0-9-+()]{10,}$/', $_POST['phone'])) {
            throw new Exception('유효하지 않은 전화번호 형식입니다.');
        }

        // 기본 정보 업데이트 쿼리 준비
        $stmt = $conn->prepare("
            UPDATE users SET 
                login_id = ?,
                name = ?,
                email = ?,
                phone = ?,
                country = ?,
                rank = ?,
                referred_by = ?,
                sponsored_by = ?,
                position = ?,
                point = ?,
                myAmount = ?,
                referral_count = ?,
                left_amounts = ?,
                right_amounts = ?,
                left_members = ?,
                right_members = ?,
                commission_total = ?,
                bonus_referral = ?,
                bonus_rank = ?,
                bonus_center = ?,
                bsc_address = ?,
                stock_account = ?,
                stock = ?,
                token = ?,
                birth = ?,
                address = ?,
                organization = ?,
                is_center = ?,
                status = ?
            WHERE id = ?
        ");

        // 데이터 바인딩
        $is_center = isset($_POST['is_center']) ? 1 : 0;
        
        $stmt->bind_param(
            "ssssssiisddiddiiiddddssidssiisi",
            $_POST['login_id'],
            $_POST['name'],
            $_POST['email'],
            $_POST['phone'],
            $_POST['country'],
            $_POST['rank'],
            $_POST['referred_by'],
            $_POST['sponsored_by'],
            $_POST['position'],
            $_POST['point'],
            $_POST['myAmount'],
            $_POST['referral_count'],
            $_POST['left_amounts'],
            $_POST['right_amounts'],
            $_POST['left_members'],
            $_POST['right_members'],
            $_POST['commission_total'],
            $_POST['bonus_referral'],
            $_POST['bonus_rank'],
            $_POST['bonus_center'],
            $_POST['bsc_address'],
            $_POST['stock_account'],
            $_POST['stock'],
            $_POST['token'],
            $_POST['birth'],
            $_POST['address'],
            $_POST['organization'],
            $is_center,
            $_POST['status'],
            $user_id
        );

        if (!$stmt->execute()) {
            throw new Exception("회원 정보 업데이트 실패: " . $stmt->error);
        }

        // 비밀번호 변경이 요청된 경우
        if (!empty($_POST['new_password'])) {
            $hashed_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if (!$stmt->execute()) {
                throw new Exception("비밀번호 업데이트 실패");
            }
        }

        // 변경 이력 기록
        $admin_id = $_SESSION['user_id'];
        $changes = json_encode($_POST, JSON_UNESCAPED_UNICODE);
        $stmt = $conn->prepare("
            INSERT INTO admin_logs (admin_id, action, target_id, changes, created_at)
            VALUES (?, 'member_edit', ?, ?, NOW())
        ");
        $stmt->bind_param("iis", $admin_id, $user_id, $changes);
        $stmt->execute();

        $conn->commit();
        $success_message = "회원 정보가 성공적으로 업데이트되었습니다.";
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
        error_log("Member edit error: " . $error_message);
    }
}

// 현재 회원 정보 조회
$stmt = $conn->prepare("
    SELECT u.*,
           ref.name as referrer_name,
           ref.login_id as referrer_login_id,
           sponsor.name as sponsor_name,
           sponsor.login_id as sponsor_login_id
    FROM users u
    LEFT JOIN users ref ON u.referred_by = ref.id
    LEFT JOIN users sponsor ON u.sponsored_by = sponsor.id
    WHERE u.id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    die("회원을 찾을 수 없습니다.");
}

// 전체 직급 목록
$ranks = ['회원', '1스타', '2스타', '3스타', '4스타', '5스타', '6스타', '7스타'];

// 소속 센터 목록
$organizations = $conn->query("SELECT DISTINCT organization FROM users WHERE organization IS NOT NULL ORDER BY organization");

$pageTitle = '회원 정보 수정';
include __DIR__ . '/admin_header.php';
?>

<!-- 스타일 시트 -->
<style>
.edit-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.section-card {
    background: rgba(26, 26, 26, 0.95);
    border: 1px solid rgba(212, 175, 55, 0.2);
    border-radius: 8px;
    margin-bottom: 20px;
}

.section-header {
    background: rgba(45, 45, 45, 0.9);
    padding: 12px 15px;
    border-bottom: 1px solid rgba(212, 175, 55, 0.2);
    color: #d4af37;
    font-size: 0.95rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
}

.section-body {
    padding: 15px;
}

.form-label {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.85rem;
    margin-bottom: 4px;
}

.form-control, .form-select {
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(212, 175, 55, 0.2);
    color: #fff;
    font-size: 0.9rem;
    padding: 6px 12px;
}

.form-control:focus, .form-select:focus {
    background: rgba(0, 0, 0, 0.3);
    border-color: rgba(212, 175, 55, 0.4);
    color: #fff;
    box-shadow: none;
}

.btn-gold {
    background: linear-gradient(135deg, #d4af37 0%, #f2d06b 100%);
    color: #000;
    border: none;
    font-weight: 500;
    padding: 8px 16px;
}

.btn-gold:hover {
    background: linear-gradient(135deg, #f2d06b 0%, #d4af37 100%);
    transform: translateY(-1px);
}

.status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
}

.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 15px 25px;
    border-radius: 4px;
    background: rgba(26, 26, 26, 0.95);
    border-left: 4px solid;
    color: #fff;
    z-index: 1000;
    animation: slideIn 0.3s ease-out;
}

.notification.success {
    border-color: #4caf50;
}

.notification.error {
    border-color: #f44336;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 2fr));
    gap: 10px;
    margin-bottom: 15px;
}

.info-label {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.6);
    margin-top: 4px;
}

.locked-field {
    background: rgba(0, 0, 0, 0.4) !important;
    cursor: not-allowed;
}

.validation-error {
    color: #f44336;
    font-size: 0.75rem;
    margin-top: 4px;
}
</style>

<div class="edit-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="text-gold mb-0">회원 정보 수정</h2>
        <div>
            <button onclick="history.back()" class="btn btn-sm btn-secondary">
                <i class="fas fa-arrow-left"></i> 목록으로
            </button>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <form method="POST" id="memberEditForm" class="needs-validation" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

        <!-- 기본 정보 섹션 -->
        <div class="section-card">
            <div class="section-header">
                <i class="fas fa-user"></i> 기본 정보
            </div>
            <div class="section-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">로그인 ID</label>
                        <input type="text" class="form-control" name="login_id" 
                               value="<?php echo htmlspecialchars($user['login_id']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">이름</label>
                        <input type="text" class="form-control" name="name" 
                               value="<?php echo htmlspecialchars($user['name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">이메일</label>
                        <input type="email" class="form-control" name="email" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">전화번호</label>
                        <input type="text" class="form-control" name="phone" 
                               value="<?php echo htmlspecialchars($user['phone']); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">국가</label>
                        <select class="form-select" name="country">
                            <option value="KR" <?php echo $user['country'] == 'KR' ? 'selected' : ''; ?>>한국</option>
                            <option value="US" <?php echo $user['country'] == 'US' ? 'selected' : ''; ?>>미국</option>
                            <option value="JP" <?php echo $user['country'] == 'JP' ? 'selected' : ''; ?>>일본</option>
                            <option value="CN" <?php echo $user['country'] == 'CN' ? 'selected' : ''; ?>>중국</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">상태</label>
                        <select class="form-select" name="status">
                            <option value="active" <?php echo $user['status'] == 'active' ? 'selected' : ''; ?>>활성</option>
                            <option value="inactive" <?php echo $user['status'] == 'inactive' ? 'selected' : ''; ?>>비활성</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- 계정 정보 섹션 -->
        <div class="section-card">
            <div class="section-header">
                <i class="fas fa-user-shield"></i> 계정 정보
            </div>
            <div class="section-body">
                <div class="form-row">

                    <div class="form-group">
                        <label class="form-label">직급</label>
                        <select class="form-select" name="rank">
                            <?php foreach ($ranks as $rank): ?>
                                <option value="<?php echo $rank; ?>" 
                                        <?php echo $user['rank'] === $rank ? 'selected' : ''; ?>>
                                    <?php echo $rank; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">비밀번호 변경</label>
                        <input type="password" class="form-control" name="new_password" 
                               placeholder="변경시에만 입력">
                        <div class="info-label">8자 이상의 영문, 숫자 조합</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">센터장 여부</label>
                        <div class="form-check mt-2">
                            <input type="checkbox" class="form-check-input" name="is_center" value="1" 
                                   <?php echo $user['is_center'] ? 'checked' : ''; ?>>
                            <label class="form-check-label">센터장으로 지정</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 추천/후원 정보 -->
        <div class="section-card">
            <div class="section-header">
                <i class="fas fa-sitemap"></i> 추천/후원 관계
            </div>
            <div class="section-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">추천인</label>
                        <select class="form-select" name="referred_by">
                            <option value="">없음</option>
                            <?php
                            $stmt = $conn->prepare("SELECT id, name, login_id FROM users WHERE id != ? ORDER BY name");
                            $stmt->bind_param("i", $user_id);
                            $stmt->execute();
                            $referrers = $stmt->get_result();
                            while ($referrer = $referrers->fetch_assoc()): ?>
                                <option value="<?php echo $referrer['id']; ?>" 
                                        <?php echo $user['referred_by'] == $referrer['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($referrer['name'] . ' (' . $referrer['login_id'] . ')'); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">후원인</label>
                        <select class="form-select" name="sponsored_by">
                            <option value="">없음</option>
                            <?php
                            $stmt->execute();
                            $sponsors = $stmt->get_result();
                            while ($sponsor = $sponsors->fetch_assoc()): ?>
                                <option value="<?php echo $sponsor['id']; ?>" 
                                        <?php echo $user['sponsored_by'] == $sponsor['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sponsor['name'] . ' (' . $sponsor['login_id'] . ')'); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">위치</label>
                        <select class="form-select" name="position">
                            <option value="">선택안함</option>
                            <option value="left" <?php echo $user['position'] == 'left' ? 'selected' : ''; ?>>좌측</option>
                            <option value="right" <?php echo $user['position'] == 'right' ? 'selected' : ''; ?>>우측</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- 실적 정보 -->
        <div class="section-card">
            <div class="section-header">
                <i class="fas fa-chart-line"></i> 실적 정보
            </div>
            <div class="section-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">구매 누적액</label>
                        <input type="number" step="0.01" class="form-control" name="myAmount" 
                               value="<?php echo $user['myAmount']; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">포인트</label>
                        <input type="number" step="0.0001" class="form-control" name="point" 
                               value="<?php echo $user['point']; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">직추천 회원수</label>
                        <input type="number" class="form-control" name="referral_count" 
                               value="<?php echo $user['referral_count']; ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">좌측 실적</label>
                        <input type="number" step="0.01" class="form-control" name="left_amounts" 
                               value="<?php echo $user['left_amounts']; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">우측 실적</label>
                        <input type="number" step="0.01" class="form-control" name="right_amounts" 
                               value="<?php echo $user['right_amounts']; ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">좌측 회원수</label>
                        <input type="number" class="form-control" name="left_members" 
                               value="<?php echo $user['left_members']; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">우측 회원수</label>
                        <input type="number" class="form-control" name="right_members" 
                               value="<?php echo $user['right_members']; ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- 수당 정보 -->
        <div class="section-card">
            <div class="section-header">
                <i class="fas fa-dollar-sign"></i> 수당 정보
            </div>
            <div class="section-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">추천 수당</label>
                        <input type="number" step="0.01" class="form-control" name="bonus_referral" 
                               value="<?php echo $user['bonus_referral']; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">직급 수당</label>
                        <input type="number" step="0.01" class="form-control" name="bonus_rank" 
                               value="<?php echo $user['bonus_rank']; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">센터 수당</label>
                        <input type="number" step="0.01" class="form-control" name="bonus_center" 
                               value="<?php echo $user['bonus_center']; ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">보유 주식</label>
                        <input type="number" class="form-control" name="stock" 
                               value="<?php echo $user['stock']; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">보유 토큰</label>
                        <input type="number" step="0.01" class="form-control" name="token" 
                               value="<?php echo $user['token']; ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- 계좌 정보 -->
        <div class="section-card">
            <div class="section-header">
                <i class="fas fa-wallet"></i> 계좌 정보
            </div>
            <div class="section-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">BSC 지갑 주소</label>
                        <input type="text" class="form-control" name="bsc_address" 
                               value="<?php echo htmlspecialchars($user['bsc_address']); ?>"
                               placeholder="0x로 시작하는 42자리 주소">
                        <div id="bsc_address_validation" class="validation-error"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">증권계좌</label>
                        <input type="text" class="form-control" name="stock_account" 
                               value="<?php echo htmlspecialchars($user['stock_account']); ?>"
                               placeholder="계좌번호 입력">
                    </div>
                </div>
            </div>
        </div>

        <!-- 추가 정보 -->
        <div class="section-card">
            <div class="section-header">
                <i class="fas fa-info-circle"></i> 추가 정보
            </div>
            <div class="section-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">생년월일</label>
                        <input type="text" class="form-control" name="birth" 
                               value="<?php echo htmlspecialchars($user['birth']); ?>"
                               placeholder="YYYY-MM-DD">
                    </div>
                    <div class="form-group">
                        <label class="form-label">주소</label>
                        <input type="text" class="form-control" name="address" 
                               value="<?php echo htmlspecialchars($user['address']); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">소속</label>
                        <select class="form-select" name="organization">
                            <option value="">소속 선택</option>
                            <?php while ($org = $organizations->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($org['organization']); ?>"
                                        <?php echo $user['organization'] === $org['organization'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($org['organization']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-center gap-2 mt-4">
            <button type="submit" class="btn btn-gold">
                <i class="fas fa-save"></i> 정보 수정
            </button>
            <button type="button" class="btn btn-secondary" onclick="history.back()">
                <i class="fas fa-times"></i> 취소
            </button>
        </div>
    </form>
</div>

<script>
// BSC 주소 유효성 검사
function validateBscAddress(address) {
    return /^0x[0-9a-fA-F]{40}$/.test(address);
}

// 폼 제출 전 유효성 검사
document.getElementById('memberEditForm').addEventListener('submit', function(e) {
    let isValid = true;
    const bscAddress = document.querySelector('[name="bsc_address"]').value;
    const bscValidation = document.getElementById('bsc_address_validation');

    // BSC 주소 검증
    if (bscAddress && !validateBscAddress(bscAddress)) {
        bscValidation.textContent = '유효하지 않은 BSC 주소 형식입니다.';
        isValid = false;
    } else {
        bscValidation.textContent = '';
    }

    if (!isValid) {
        e.preventDefault();
    }
});

// 실시간 BSC 주소 유효성 검사
document.querySelector('[name="bsc_address"]').addEventListener('input', function() {
    const bscValidation = document.getElementById('bsc_address_validation');
    if (this.value && !validateBscAddress(this.value)) {
        bscValidation.textContent = '유효하지 않은 BSC 주소 형식입니다.';
    } else {
        bscValidation.textContent = '';
    }
});

// 성공/에러 메시지 표시 후 자동 삭제
setTimeout(() => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        alert.style.display = 'none';
    });
}, 3000);
</script>

<?php
$conn->close();
include __DIR__ . '/../includes/footer.php';
?>