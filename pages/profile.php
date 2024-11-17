<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/richtech.club/pages/error.log');

session_start();
require_once __DIR__ . '/../includes/config.php';

// 로그인 체크
if (!is_logged_in()) {
    header("Location: /login");
    exit;
}

$conn = db_connect();
$user_id = $_SESSION['user_id'];

// profile.php의 POST 처리 부분 상단에 추가
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (isset($data['action']) && $data['action'] === 'update_field') {
        header('Content-Type: application/json');
        
        try {
            $field = $data['field'];
            $value = trim($data['value']);
            
            // 허용된 필드만 수정 가능
            $allowed_fields = ['email', 'phone'];
            if (!in_array($field, $allowed_fields)) {
                throw new Exception("수정할 수 없는 필드입니다.");
            }
            
            // 이메일 유효성 검사
            if ($field === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("유효하지 않은 이메일 주소입니다.");
            }
            
            // 전화번호 유효성 검사
            if ($field === 'phone' && !preg_match('/^[0-9-]+$/', $value)) {
                throw new Exception("유효하지 않은 전화번호입니다.");
            }
            
            $stmt = $conn->prepare("UPDATE users SET $field = ? WHERE id = ?");
            $stmt->bind_param("si", $value, $user_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
                exit;
            }
            
            throw new Exception("데이터 업데이트 실패");
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
    
    // ... 기존의 다른 POST 처리 코드 ...
}

// 개인정보 업데이트 처리
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    header('Content-Type: application/json');
    
    try {
        $birth = trim($_POST['birth'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $bsc_address = trim($_POST['bsc_address'] ?? '');        
        $stock_account = trim($_POST['stock_account'] ?? '');

        // 입력값 검증
if (!empty($bsc_address)) {
    if (!preg_match('/^0x[0-9a-fA-F]{40}$/', $bsc_address)) {
        throw new Exception("유효하지 않은 BSC 지갑 주소입니다.");
    }
}

        if (!empty($stock_account)) {
            if (!preg_match('/^[0-9-]+$/', $stock_account)) {
                throw new Exception("유효하지 않은 증권계좌 번호입니다.");
            }
        }

        if (!empty($birth)) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth)) {
                throw new Exception("생년월일 형식이 올바르지 않습니다 (YYYY-MM-DD).");
            }
        }

        // 현재 사용자 정보 조회
        $stmt = $conn->prepare("SELECT birth, address, bsc_address, stock_account FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $current_user = $stmt->get_result()->fetch_assoc();
        
        // 이미 입력된 필드는 업데이트하지 않음
        if (!empty($current_user['birth'])) $birth = $current_user['birth'];
        if (!empty($current_user['address'])) $address = $current_user['address'];
        if (!empty($current_user['tron_address'])) $tron_address = $current_user['tron_address'];
        if (!empty($current_user['stock_account'])) $stock_account = $current_user['stock_account'];

        // 데이터 업데이트
$stmt = $conn->prepare("UPDATE users SET 
    birth = NULLIF(?, ''),
    address = NULLIF(?, ''),
    bsc_address = NULLIF(?, ''),
    stock_account = NULLIF(?, '')
    WHERE id = ?");
            
        $stmt->bind_param("ssssi", $birth, $address, $tron_address, $stock_account, $user_id);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => '개인정보가 성공적으로 업데이트되었습니다.',
                'data' => [
                    'birth' => $birth,
                    'address' => $address,
                    'tron_address' => $tron_address,
                    'stock_account' => $stock_account
                ]
            ]);
            exit;
        }
        
        throw new Exception("데이터 업데이트 실패");
        
    } catch (Exception $e) {
        error_log("Profile update error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}


// 사용자 상세 정보 조회
$stmt = $conn->prepare("
    SELECT u.*, 
           r.name as referrer_name,
           r.login_id as referrer_login_id,
           r.referral_code as referrer_code,
           s.name as sponsor_name,
           s.login_id as sponsor_login_id,
           o.name as organization_name
    FROM users u 
    LEFT JOIN users r ON u.referred_by = r.id
    LEFT JOIN users s ON u.sponsored_by = s.id
    LEFT JOIN organizations o ON u.organization = o.id
    WHERE u.id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    error_log("User not found: " . $user_id);
    header("Location: /logout");
    exit;
}

$pageTitle = '내 정보';
include __DIR__ . '/../includes/header.php';
?>

<style>
        .profile-container {
            max-width: 800px;
            margin: 0px auto;
            padding: 20px;
            color: #d4af37;
            font-family: 'Noto Sans KR', sans-serif;
        }

        .profile-card {
            background: rgba(17, 17, 17, 0.95);
            border: 1px solid rgba(212, 175, 55, 0.2);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .profile-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(212, 175, 55, 0.2);
            padding-bottom: 10px;
            margin-bottom: 10px;
        }

        .profile-title {
            font-size: 1.1rem;
            color: #d4af37;
            font-family: 'Noto Serif KR', serif;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .profile-title i {
            font-size: 0.9rem;
        }

        .collapse-toggle {
            cursor: pointer;
            color: rgba(212, 175, 55, 0.7);
            transition: all 0.3s ease;
        }

        .collapse-toggle:hover {
            color: #d4af37;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            font-size: 0.85rem;
            line-height: 1.3;
        }

        @media (max-width: 768px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
        }

        .profile-item {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .item-label {
            color: rgba(212, 175, 55, 0.8);
            font-size: 0.8rem;
        }

        .item-value {
            color: #fff;
            font-family: 'Noto Serif KR', serif;
            text-align: right;
        }

        .profile-form {
            display: none;
            padding: 15px 0;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-label {
            display: block;
            color: #d4af37;
            margin-bottom: 5px;
            font-size: 0.8rem;
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

        .form-control:disabled {
            background: rgba(0, 0, 0, 0.4);
            color: #666;
            cursor: not-allowed;
        }

        .form-control:focus {
            outline: none;
            border-color: #d4af37;
            box-shadow: 0 0 0 2px rgba(212, 175, 55, 0.2);
        }


        .validation-message {
            color: #ff6b6b;
            font-size: 0.75rem;
            margin-top: 5px;
        }

        .qr-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
            margin-top: 20px;
        }

        .qr-code {
            width: 150px;
            height: 150px;
            padding: 10px;
            background: #fff;
            border-radius: 5px;
        }

        .link-input {
            width: 100%;
            padding: 8px;
            font-family: monospace;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(212, 175, 55, 0.3);
            color: #fff;
            border-radius: 4px;
            text-align: center;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-top: 15px;
        }

        .stat-card {
            background: rgba(0, 0, 0, 0.2);
            padding: 10px;
            border-radius: 5px;
            text-align: center;
        }

        .stat-value {
            font-size: 1.2rem;
            color: #d4af37;
            font-weight: 600;
        }

        .stat-label {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.7);
            margin-top: 5px;
        }

        .input-group {
            display: flex;
            gap: 8px;
            align-items: center;
            width: 100%;
        }

        .input-group .form-control {
            flex: 1;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(212, 175, 55, 0.3);
            color: #fff;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .btn-gold-sm {
            padding: 4px 8px;
            font-size: 0.8rem;
            white-space: nowrap;
        }
</style>

<div class="mx-20 mt20 ">
    <div class="flex-x-end ">

         <button class="btn-outline" onclick="location.href='/order'"><i class="fas fa-list"></i> 구매하기</button>
                 <button class="btn-outline" onclick="location.href='/certificate'"><i class="fas fa-list"></i> 증서보기</button>
         <button class="btn-outline" onclick="location.href='/chart'"><i class="fas fa-list"></i> 조직도</button>
        <button class="btn-outline" onclick="location.href='/logout'"><i class="fas fa-chart-line"></i>
            로그아웃</button>
    </div>
</div>

<div class="profile-container">
    <!-- 기본 정보 카드 -->
    <div class="profile-card">
        <div class="profile-header">
            <h3 class="profile-title">
                <i class="fas fa-user-circle"></i>기본 회원 정보
            </h3>
        </div>
        <div class="profile-grid">
            <div class="profile-item">
                <span class="item-label">회원ID</span>
                <span class="item-value"><?php echo htmlspecialchars($user['login_id']); ?></span>
            </div>
            <div class="profile-item">
                <span class="item-label">회원직급</span>
                <span class="item-value"><button
                        class="btn btn-gold-sm"><?php echo htmlspecialchars($user['rank']); ?></button></span>
            </div>

<div class="profile-item">
        <span class="item-label">이메일</span>
        <div class="input-group">
            <input type="email" id="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>">
            <button type="button" class="btn-gold-sm" onclick="updateField('email')">수정</button>
        </div>
    </div>
    <div class="profile-item">
        <span class="item-label">전화번호</span>
        <div class="input-group">
            <input type="tel" id="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>">
            <button type="button" class="btn-gold-sm" onclick="updateField('phone')">수정</button>
        </div>
    </div>
            <div class="profile-item">
                <span class="item-label">전화번호</span>
                <span class="item-value"><?php echo htmlspecialchars($user['phone']); ?></span>
            </div>

            <div class="profile-grid">
                <div class="profile-item">
                    <span class="item-label">추천인</span>
                    <span class="item-value">
                        <?php 
                    if (!empty($user['referrer_login_id'])) {
                        echo '(' . htmlspecialchars($user['referrer_login_id']) . ')';
                    } else {
                        echo '없음';
                    }
                    ?>
                    </span>
                </div>
                <div class="profile-item">
                    <span class="item-label">후원인</span>
                    <span class="item-value">
                        <?php 
                    if (!empty($user['sponsor_login_id'])) {
                        echo '(' . htmlspecialchars($user['sponsor_login_id']) . ')';
                    } else {
                        echo '없음';
                    }
                    ?>
                    </span>
                </div>
                <div class="profile-item">
                    <span class="item-label">소속</span>
                    <span class="item-value"><?php echo htmlspecialchars($user['organization'] ?? '없음'); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- 추천인 정보 카드 -->
    <div class="profile-card">
        <div class="profile-header">
            <h3 class="profile-title">
                <i class="fas fa-users"></i>나의 추천링크
            </h3>
        </div>


        <!-- QR코드 및 추천링크 섹션 -->
        <div class="qr-section">
            <img src="https://api.qrserver.com/v1/create-qr-code/?data=<?php 
                echo urlencode($user['referral_link']); 
                ?>&size=150x150" alt="추천 QR코드" class="qr-code">
            <input type="text" value="<?php echo htmlspecialchars($user['referral_link']); ?>" class="link-input"
                readonly>
            <button class="btn-outline" onclick="copyReferralLink()">추천링크 복사</button>
        </div>
    </div>



    <!-- 개인정보 관리 카드 -->
    <div class="profile-card bg-gray80">
        <div class="profile-header">
            <h3 class="profile-title">
                <i class="fas fa-user-edit"></i>개인정보 관리
            </h3>
            <span class="collapse-toggle"  onclick="togglePersonalInfo()"  id="toggleIcon">▼</span>
        </div>
        <div id="personalInfoContent" class="profile-form">
            <form id="personalInfoForm" method="POST">
                <div class="form-group">
                    <label class="form-label">생년월일 (YYYY-MM-DD)</label>
                    <input type="text" name="birth" class="form-control" placeholder="예: 1990-01-01"
                        value="<?php echo htmlspecialchars($user['birth'] ?? ''); ?>"
                        <?php echo !empty($user['birth']) ? 'disabled' : ''; ?>>
                    <div id="birthValidation" class="validation-message"></div>
                </div>

                <div class="form-group">
                    <label class="form-label">주소</label>
                    <input type="text" name="address" class="form-control" placeholder="상세주소를 입력하세요"
                        value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>"
                        <?php echo !empty($user['address']) ? 'disabled' : ''; ?>>
                    <div id="addressValidation" class="validation-message"></div>
                </div>


                <div class="form-group">
                    <label class="form-label">삼성증권 계좌</label>
                    <input type="text" name="stock_account" class="form-control" placeholder="계좌번호 (숫자와 - 만 입력)"
                        value="<?php echo htmlspecialchars($user['stock_account'] ?? ''); ?>"
                        <?php echo !empty($user['stock_account']) ? 'disabled' : ''; ?>>
                    <div id="stockAccountValidation" class="validation-message"></div>
                </div>

<div class="form-group">
    <label class="form-label">BEP20 BSC지갑 주소 (USDT BEP-20)</label>
    <input type="text" name="bsc_address" class="form-control" placeholder="0x로 시작하는 42자리 주소"
        value="<?php echo htmlspecialchars($user['bsc_address'] ?? ''); ?>"
        <?php echo !empty($user['bsc_address']) ? 'disabled' : ''; ?>>
    <div id="bscAddressValidation" class="validation-message"></div>
</div>


                <?php if (empty($user['birth']) || empty($user['address']) || empty($user['tron_address']) || empty($user['stock_account'])): ?>
                <button type="submit" class="btn-gold">저장하기</button>
                <?php endif; ?>
            </form>
        </div>
    </div>


    <!-- 나의 실적 -->
    <div class="profile-card">
        <div class="profile-header">
            <h3 class="profile-title">
                <i class="fas fa-chart-line"></i>실적 정보
            </h3>
        </div>
        <div class="stats-grid">

            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($user['myAmount']); ?></div>
                <div class="stat-label">구매금액 누적</div>
            </div>

            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($user['stock']); ?></div>
                <div class="stat-label">보유주식</div>
            </div>

            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($user['token']); ?></div>
                <div class="stat-label">보유토큰</div>
            </div>
            <div class="stat-card">
                <?php $bonus_total=$user['bonus_referral'] + $user['bonus_rank'] + $user['bonus_center'] ?>
                <div class="stat-value"><?php echo number_format($bonus_total); ?></div>
                <div class="stat-label">수수료 총액</div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePersonalInfo() {
    const content = document.getElementById('personalInfoContent');
    const icon = document.getElementById('toggleIcon');
    if (content.style.display === 'none' || !content.style.display) {
        content.style.display = 'block';
        icon.textContent = '▲';
    } else {
        content.style.display = 'none';
        icon.textContent = '▼';
    }
}

function copyReferralLink() {
    const referralLink = document.querySelector('.link-input').value;
    navigator.clipboard.writeText(referralLink).then(() => {
        showNotification('추천 링크가 복사되었습니다.', 'success');
    }).catch(err => {
        showNotification('링크 복사에 실패했습니다. 다시 시도해주세요.', 'error');
    });
}


function updateField(fieldName) {
    const value = document.getElementById(fieldName).value;
    
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'update_field',
            field: fieldName,
            value: value
        })
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            showNotification('수정이 완료되었습니다.', 'success');
        } else {
            showNotification(data.message || '수정 중 오류가 발생했습니다.', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('수정 중 오류가 발생했습니다.', 'error');
    });
}


document.getElementById('personalInfoForm')?.addEventListener('submit', function(e) {
    e.preventDefault();

    // 입력값 검증
    let isValid = true;
    const validations = {
        birth: {
            pattern: /^\d{4}-\d{2}-\d{2}$/,
            message: '생년월일 형식이 올바르지 않습니다 (YYYY-MM-DD)'
        },
bsc_address: {
    pattern: /^0x[0-9a-fA-F]{40}$/,
    message: '유효하지 않은 BSC 지갑 주소입니다'
        },
        stock_account: {
            pattern: /^[0-9-]+$/,
            message: '유효하지 않은 증권계좌 번호입니다'
        }
    };

    Object.keys(validations).forEach(field => {
        const input = this.querySelector(`[name="${field}"]`);
        const validation = document.getElementById(`${field}Validation`);

        if (input && !input.disabled && input.value) {
            if (!validations[field].pattern.test(input.value)) {
                validation.textContent = validations[field].message;
                isValid = false;
            } else {
                validation.textContent = '';
            }
        }
    });

    if (!isValid) return;

    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;

    fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(error => {
            showNotification('서버 오류가 발생했습니다.', 'error');
            console.error('Error:', error);
        })
        .finally(() => {
            submitBtn.disabled = false;
        });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>