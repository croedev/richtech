<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // 화면에 에러 표시를 끔
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

require_once __DIR__ . '/../includes/config.php';

$conn = db_connect();

// ID 중복 체크 함수
function checkDuplicateLoginId($conn, $loginId) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE login_id = ?");
    $stmt->bind_param("s", $loginId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $count = $row['count'];
    $stmt->close();
    return $count > 0;
}

// 후원인 조회 함수 (좌우 포지션 정보 포함)
function checkSponsor($conn, $sponsorId) {
    $stmt = $conn->prepare("
        SELECT u.name, u.login_id, u.id AS sponsor_id,
            SUM(CASE WHEN s.position = 'left' THEN 1 ELSE 0 END) as left_count,
            SUM(CASE WHEN s.position = 'right' THEN 1 ELSE 0 END) as right_count
        FROM users u
        LEFT JOIN users s ON s.sponsored_by = u.id
        WHERE u.login_id = ?
        GROUP BY u.id
    ");
    $stmt->bind_param("s", $sponsorId);
    $stmt->execute();
    $result = $stmt->get_result();
    $sponsor = $result->fetch_assoc();
    $stmt->close();
    return $sponsor;
}

// 추천인 조회 함수
function checkReferrer($conn, $referrerId) {
    $stmt = $conn->prepare("SELECT name, login_id, id AS referrer_id FROM users WHERE login_id = ?");
    $stmt->bind_param("s", $referrerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $referrer = $result->fetch_assoc();
    $stmt->close();
    return $referrer;
}

// AJAX 요청 처리
if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'checkDuplicateLoginId') {
        $loginId = $_POST['loginId'];
        $isDuplicate = checkDuplicateLoginId($conn, $loginId);
        
        if (empty($loginId)) {
            $response = array('status' => 'error', 'message' => '아이디를 입력해주세요.');
            http_response_code(400);
        } elseif ($isDuplicate) {
            $response = array('status' => 'error', 'message' => '이미 가입된 ID입니다.');
            http_response_code(400);
        } else {
            $response = array('status' => 'success', 'message' => '등록 가능한 ID입니다.');
        }
        
        echo json_encode($response);
        exit;
    }

    if ($_POST['action'] === 'checkSponsor') {
        $sponsorId = $_POST['sponsorId'];
        $sponsor = checkSponsor($conn, $sponsorId);
        
        if (empty($sponsor)) {
            $response = array('status' => 'error', 'message' => '입력하신 ID는 존재하지 않습니다. 다시 후원인 ID를 입력하세요.');
            http_response_code(400);
        } else {
            $left_count = $sponsor['left_count'] ?: 0;
            $right_count = $sponsor['right_count'] ?: 0;

            $positions = [];
            if ($left_count < 1) $positions[] = 'left';
            if ($right_count < 1) $positions[] = 'right';

            if (empty($positions)) {
                $response = array('status' => 'error', 'message' => $sponsor['name'] . '(' . $sponsor['login_id'] . ')님은 좌우 모두 등록되어 추가 등록할 수 없습니다. 다른 후원인을 선택하세요.');
                http_response_code(400);
            } else {
                $response = array(
                    'status' => 'success',
                    'message' => $sponsor['name'] . '(' . $sponsor['login_id'] . ')님을 후원인으로 등록 가능합니다.',
                    'positions' => $positions
                );
            }
        }
        
        echo json_encode($response);
        exit;
    }

    if ($_POST['action'] === 'checkReferrer') {
        $referrerId = $_POST['referrerId'];
        $referrer = checkReferrer($conn, $referrerId);
        
        if (empty($referrer)) {
            $response = array('status' => 'error', 'message' => $referrerId . '님은 존재하지 않음. 다시 입력하세요.');
            http_response_code(400);
        } else {
            $response = array('status' => 'success', 'message' => $referrer['name'] . '(' . $referrer['login_id'] . ')님을 추천인 등록 가능.');
        }
        
        echo json_encode($response);
        exit;
    }
}

// 회원가입 처리
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['action'])) {
    header('Content-Type: application/json');

    $errors = [];
    $success = '';

    try {
        // 입력 데이터 수집 및 검증
        $login_id = trim($_POST['user_id']); // 입력받은 로그인 아이디
        $name = trim($_POST['name']);
        $email = trim($_POST['email']); 
        $phone = preg_replace("/[^0-9]/", "", $_POST['phone']);
        $phone_formatted = substr($phone, 0, 3) . '-' . substr($phone, 3, 4) . '-' . substr($phone, 7);
        $country = isset($_POST['country']) ? trim($_POST['country']) : '';
        $organization = isset($_POST['organization']) ? trim($_POST['organization']) : '';
        $tron_address = isset($_POST['tron_wallet']) ? trim($_POST['tron_wallet']) : '';
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sponsored_by = trim($_POST['sponsored_by']);
        $position = isset($_POST['position']) ? $_POST['position'] : null;
        $referred_by = trim($_POST['referred_by']);
        $agree = isset($_POST['agree']) ? $_POST['agree'] : '';

        // 후원인과 추천인 ID 초기화
        $sponsored_by_id = null;
        $referred_by_id = null;

        if ($agree !== 'Y') {
            $errors['agree'] = "개인정보 수집 및 이용에 동의해주세요.";
        }

        if (empty($login_id)) {
            $errors['user_id'] = "아이디를 입력해주세요.";
        } else {
            // ID 중복 체크
            if (checkDuplicateLoginId($conn, $login_id)) {
                $errors['user_id'] = "이미 사용 중인 ID입니다.";
            }
        }

        if (empty($password)) {
            $errors['password'] = "비밀번호를 입력해주세요.";
        } else if ($password !== $confirm_password) {
            $errors['password'] = "비밀번호가 일치하지 않습니다.";
        }

        if (empty($name)) {
            $errors['name'] = "이름을 입력해주세요.";
        }

        if (empty($email)) {
            $errors['email'] = "이메일을 입력해주세요.";
        } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "유효한 이메일 주소를 입력해주세요.";
        }

        if (empty($phone)) {
            $errors['phone'] = "전화번호를 입력해주세요.";
        }

        if (empty($organization)) {
            $errors['organization'] = "소속을 선택해주세요.";
        }

        // 트론 지갑 주소 검증
        if (!empty($tron_address) && !preg_match('/^T[0-9a-zA-Z]{33}$/', $tron_address)) {
            $errors['tron_wallet'] = "유효하지 않은 트론 지갑 주소입니다.";
        }

        // 후원인 확인
        if (!empty($sponsored_by)) {
            $sponsor = checkSponsor($conn, $sponsored_by);
            if (empty($sponsor)) {
                $errors['sponsored_by'] = "존재하지 않는 후원인 ID입니다.";
            } else {
                $sponsored_by_id = $sponsor['sponsor_id']; // 후원인의 사용자 ID

                $left_count = $sponsor['left_count'] ?: 0;
                $right_count = $sponsor['right_count'] ?: 0;

                $available_positions = [];
                if ($left_count < 1) $available_positions[] = 'left';
                if ($right_count < 1) $available_positions[] = 'right';

                if (empty($available_positions)) {
                    $errors['sponsored_by'] = "후원인이 이미 좌우에 회원을 모두 보유하고 있습니다. 다른 후원인을 선택해 주세요.";
                } else {
                    if (empty($position)) {
                        $errors['position'] = "후원인의 위치를 선택해주세요.";
                    } elseif (!in_array($position, $available_positions)) {
                        $errors['position'] = "선택하신 위치는 사용할 수 없습니다.";
                    }
                }
            }
        }

        // 추천인 확인
        if (!empty($referred_by)) {
            $referrer = checkReferrer($conn, $referred_by);
            if (empty($referrer)) {
                $errors['referred_by'] = "존재하지 않는 추천인 ID입니다.";
            } else {
                $referred_by_id = $referrer['referrer_id']; // 추천인의 사용자 ID
            }
        }

        if (!empty($errors)) {
            throw new Exception("입력 오류가 발생했습니다.");
        }

        $conn->begin_transaction();

        // 사용자 정보 저장
        $stmt = $conn->prepare("INSERT INTO users (login_id, name, email, phone, country, organization, password, sponsored_by, position, referred_by, tron_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssisss", $login_id, $name, $email, $phone_formatted, $country, $organization, $hashed_password, $sponsored_by_id, $position, $referred_by_id, $tron_address);

        if (!$stmt->execute()) {
            throw new Exception("사용자 정보 저장 중 오류가 발생했습니다: " . $stmt->error);
        }

        $new_user_id = $conn->insert_id;

        // 추천 코드 및 QR 코드 생성 (함수는 별도로 구현되어 있어야 합니다)
        $new_referral_code = generateReferralCode($new_user_id);
        $new_referral_link = SITE_URL . "/join?ref=" . $new_referral_code;
        $qr_code = generateQRCode($new_referral_link);

        $stmt = $conn->prepare("UPDATE users SET referral_code = ?, referral_link = ?, qr_code = ? WHERE id = ?");
        $stmt->bind_param("sssi", $new_referral_code, $new_referral_link, $qr_code, $new_user_id);

        if (!$stmt->execute()) {
            throw new Exception("추천 코드 및 QR 코드 업데이트 중 오류가 발생했습니다: " . $stmt->error);
        }

        $conn->commit();

        $success = "회원가입이 성공적으로 완료되었습니다.";

        echo json_encode([
            'success' => true,
            'message' => $success
        ]);
    } catch (Exception $e) {
        if ($conn->in_transaction) {
            $conn->rollback();
        }
        $error_message = "회원가입 중 오류가 발생했습니다: " . $e->getMessage();
        error_log("Join error: " . $error_message);
        echo json_encode([
            'success' => false,
            'errors' => $errors,
            'message' => $error_message
        ]);
        http_response_code(500);
    }
    exit;
}

// 추천인 코드 처리
$referral_code = isset($_GET['ref']) ? $_GET['ref'] : '';
$referrer_info = '';
$referrer_user_id = '';
if ($referral_code) {
    $stmt = $conn->prepare("SELECT login_id, name FROM users WHERE referral_code = ?");
    $stmt->bind_param("s", $referral_code);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $referrer_info = $row['name'] . ' (' . $row['login_id'] . ')';
        $referrer_user_id = $row['login_id'];
    }
    $stmt->close();
}

// 소속단체 목록 가져오기 (조직 이름을 사용)
$org_query = "SELECT id, name FROM organizations ORDER BY name ASC";
$org_result = $conn->query($org_query);
$organizations = [];
while ($row = $org_result->fetch_assoc()) {
    $organizations[] = $row;
}

$error = '';
$success = '';

$pageTitle = '회원가입';
include __DIR__ . '/../includes/header.php';
?>

<style>
        .join-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 30px;
            overflow-y: auto;
            height: calc(100vh - 130px);
            background-color: #000;
            color: #fff;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-label {
            display: block;
            color: #d4af37;
            margin-bottom: 3px;
            font-size: 0.8rem;
            font-family: 'Noto Sans KR', sans-serif;
        }

        .form-control {
            width: 100%;
            padding: 7px 10px;
            border: 1px solid #555;
            background-color: #333;
            color: #fff;
            font-size: 0.9rem;
            border-radius: 4px;
        }

        .btn-gold {
            background: linear-gradient(to right, #d4af37, #f2d06b);
            color: #000;
            border: none;
            padding: 12px 20px;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            display: block;
            margin: 30px auto;
            font-weight: bold;
        }

        .error {
            color: #ff6b6b;
            margin-bottom: 10px;
            font-family: 'Noto Serif KR', serif;
        }

        .success {
            color: #4CAF50;
            margin-bottom: 20px;
            text-align: center;
            font-size: 1.1rem;
            font-family: 'Noto Serif KR', serif;
        }

        .check-button {
            background-color: #555;
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 10px;
            font-size: 0.7rem;
            width: 30%;
        }

        .password-group {
            display: flex;
            justify-content: space-between;
        }

        .password-group .form-group {
            width: 48%;
        }

        .validation-message {
            color: #ff6b6b;
            margin-top: 5px;
            font-size: 0.7rem;
            font-family: 'Noto Serif KR', serif;
            text-align: left;
        }

        .validation-message.success {
            color: #4CAF50;
            text-align: left;
        }

        .referral-group {
            margin-bottom: 20px;
        }

        .agree-checkbox {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .agree-checkbox input {
            margin-right: 10px;
        }

        .agree-checkbox label {
            color: #d4af37;
            font-size: 0.95rem;
        }
</style>

<div class="join-container pb100">
    <?php if ($error): ?>
    <p class="error"><?php echo $error; ?></p>
    <?php elseif ($success): ?>
    <p class="success"><?php echo $success; ?></p>
    <a href="/login" class="btn-gold">로그인하기</a>
    
    <?php else: ?>
    <form id="joinForm" method="post" action="">
        <!-- 아이디 입력 -->
        <div class="form-group">
            <label for="user_id" class="form-label">아이디</label>
            <div style="display: flex;">
                <input type="text" id="user_id" name="user_id" class="form-control" required>
                <button type="button" class="check-button w100" onclick="checkDuplicateLoginId()">중복확인</button>
            </div>
            <div id="user_id-validation" class="validation-message"></div>
        </div>
        <!-- 이름 입력 -->
        <div class="form-group">
            <label for="name" class="form-label">이름</label>
            <input type="text" id="name" name="name" class="form-control" required>
            <div id="name-validation" class="validation-message"></div>
        </div>
        <!-- 비밀번호 입력 -->
        <div class="password-group">
            <div class="form-group">
                <label for="password" class="form-label">비밀번호</label>
                <input type="password" id="password" name="password" class="form-control" required>
                <div id="password-validation" class="validation-message"></div>
            </div>
            <div class="form-group" style="margin-bottom: 5px;">
                <label for="confirm_password" class="form-label">비밀번호 확인</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
            </div>
        </div>
        <!-- 이메일 입력 -->
        <div class="form-group">
            <label for="email" class="form-label">이메일</label>
            <input type="email" id="email" name="email" class="form-control" required>
            <div id="email-validation" class="validation-message"></div>
        </div>

        <!-- 국적 선택 -->
        <div class="form-group">
            <label for="country" class="form-label">국적</label>
            <select id="country" name="country" class="form-control notosans fs-14">
                <option value="">국적 선택</option>
                <option value="KR">🇰🇷 한국(KOREA)</option>
                <option value="US">🇺🇸 미국(USA)</option>
                <option value="JP">🇯🇵 일본(JAPAN)</option>
                <option value="CN">🇨🇳 중국(CHINA)</option>
                <!-- 기타 국가 옵션 추가 -->
            </select>
        </div>


         <!-- 전화번호 입력 -->
        <div class="form-group">
            <label for="phone" class="form-label">전화번호</label>
            <input type="tel" id="phone" name="phone" class="form-control" required>
            <div id="phone-validation" class="validation-message"></div>
        </div>



        <?php /*
        <!-- 트론 지갑 주소 입력 -->
        <div class="form-group">
            <label for="tron_wallet" class="form-label">트론 지갑 주소 (USDT TRC-20)</label>
            <div style="display: flex;">
                <input type="text" id="tron_wallet" name="tron_wallet" class="form-control">
                <button type="button" class="check-button" onclick="validateTronWallet()">유효성검사</button>
            </div>
            <div id="tron_wallet-validation" class="validation-message"></div>
        </div>

        */?>
        
  <!-- 후원인 입력 -->
        <div class="referral-group">
            <label for="sponsored_by" class="form-label">후원인ID(등록후 변경불가)</label>
            <div style="display: flex;">
                <input type="text" id="sponsored_by" name="sponsored_by" class="form-control" placeholder="후원인 아이디 입력">
                <button type="button" class="check-button w200" onclick="checkSponsor()">후원인 검색</button>
            </div>
            <div id="sponsored_by-validation" class="validation-message"></div>
            <!-- 좌우 선택 라디오 버튼 -->
            <div id="position-selection" style="display: none; margin-top: 10px;">
                <label class="form-label">위치 선택:</label>
                <label><input type="radio" name="position" value="left"> 좌측</label>
                <label><input type="radio" name="position" value="right"> 우측</label>
                <div id="position-validation" class="validation-message"></div>
            </div>
        </div>


        <!-- 추천인 입력 -->
        <div class="referral-group">
            <label for="referred_by" class="form-label w200">추천인ID(등록후 변경불가)</label>
            <div style="display: flex;">
                <input type="text" id="referred_by" name="referred_by" class="form-control" placeholder="추천인 아이디 입력" value="<?php echo htmlspecialchars($referrer_user_id); ?>" <?php echo $referrer_user_id ? 'readonly' : ''; ?>>
                <?php if (!$referrer_user_id): ?>
                <button type="button" class="check-button w200" onclick="checkReferrer()">추천인 검색</button>
                <?php endif; ?>
            </div>
            <?php if ($referrer_user_id): ?>
            <div id="referred_by-validation" class="validation-message success">
                <?php echo $referrer_info; ?>님을 추천인으로 등록합니다.
            </div>
            <?php else: ?>
            <div id="referred_by-validation" class="validation-message"></div>
            <?php endif; ?>
        </div>

        
        <!-- 소속 선택 -->
        <div class="form-group">
            <label for="organization" class="form-label">소속</label>
            <select id="organization" name="organization" class="form-control fs-12" required>
                <option value="">소속 선택</option>
                <?php foreach ($organizations as $org): ?>
                <option value="<?php echo htmlspecialchars($org['name']); ?>">
                    <?php echo htmlspecialchars($org['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <div id="organization-validation" class="validation-message"></div>
        </div>




        <!-- 개인정보 수집 동의 -->
        <div class="agree-checkbox">
            <input type="checkbox" id="agree" name="agree" value="Y" required>
            <label for="agree">개인정보 수집 및 이용에 동의합니다.</label>
            <div id="agree-validation" class="validation-message"></div>
        </div>
        <!-- 회원가입 버튼 -->
        <button type="submit" class="btn-gold">회원가입</button>
    </form>
    <?php endif; ?>
</div>


<script>

// 검증 상태를 추적하는 플래그 객체
const validationFlags = {
    idChecked: false,
    sponsorChecked: false,
    referrerChecked: false
};

// 필드 순서 정의
const fieldOrder = [
    'user_id',          // 아이디
    'name',             // 이름
    'password',         // 비밀번호
    'confirm_password', // 비밀번호 확인
    'email',           // 이메일
    'phone',           // 전화번호
    'country',         // 국적
    'sponsored_by',    // 후원인
    'position',        // 후원인 위치
    'referred_by',     // 추천인
    'organization'     // 소속
];

// 바로 여기에 필드 포커스 이벤트 리스너 추가
fieldOrder.forEach((fieldId, index) => {
    const field = document.getElementById(fieldId);
    if (!field) return;

    field.addEventListener('focus', function(e) {
        // 이전 필드들의 검증
        for (let i = 0; i < index; i++) {
            const previousFieldId = fieldOrder[i];
            const previousField = document.getElementById(previousFieldId);
            
            // 이전 필드가 비어있는지 확인
            if (!previousField.value.trim()) {
                const validationMessage = document.getElementById(`${previousFieldId}-validation`);
                validationMessage.textContent = '이 필드를 먼저 입력해주세요.';
                validationMessage.className = 'validation-message error';
                previousField.focus();
                e.preventDefault();
                return;
            }

            // 특별한 검증이 필요한 필드 확인
            if (fieldValidations[previousFieldId]) {
                if (!fieldValidations[previousFieldId]()) {
                    e.preventDefault();
                    return;
                }
            }
        }
    });
});



// 각 필드별 검증 함수
const fieldValidations = {
    user_id: () => {
        if (!validationFlags.idChecked) {
            const validationMessage = document.getElementById('user_id-validation');
            validationMessage.textContent = '아이디 중복확인이 필요합니다.';
            validationMessage.className = 'validation-message error';
            document.getElementById('user_id').focus();
            return false;
        }
        return true;
    },
    sponsored_by: () => {
        if (!validationFlags.sponsorChecked) {
            const validationMessage = document.getElementById('sponsored_by-validation');
            validationMessage.textContent = '후원인 검색이 필요합니다.';
            validationMessage.className = 'validation-message error';
            document.getElementById('sponsored_by').focus();
            return false;
        }
        return true;
    },
    referred_by: () => {
        const referredByInput = document.getElementById('referred_by');
        if (!referredByInput.readOnly && !validationFlags.referrerChecked) {
            const validationMessage = document.getElementById('referred_by-validation');
            validationMessage.textContent = '추천인 검색이 필요합니다.';
            validationMessage.className = 'validation-message error';
            referredByInput.focus();
            return false;
        }
        return true;
    }
};




// 전화번호 형식 정의
const PHONE_FORMATS = {
    'KR': {
        pattern: /^01[0-9]-?[0-9]{3,4}-?[0-9]{4}$/,
        format: (num) => {
            const cleaned = num.replace(/\D/g, '');
            if (cleaned.length === 10) {
                return cleaned.replace(/(\d{3})(\d{3})(\d{4})/, '$1-$2-$3');
            } else if (cleaned.length === 11) {
                return cleaned.replace(/(\d{3})(\d{4})(\d{4})/, '$1-$2-$3');
            }
            return num;
        },
        placeholder: '010-0000-0000'
    },
    'US': {
        pattern: /^[+]?1?[-\s]?\(?[0-9]{3}\)?[-\s]?[0-9]{3}[-\s]?[0-9]{4}$/,
        format: (num) => {
            const cleaned = num.replace(/\D/g, '');
            if (cleaned.length === 10) {
                return cleaned.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
            }
            return num;
        },
        placeholder: '(000) 000-0000'
    },
    'JP': {
        pattern: /^0[789]0-?[0-9]{4}-?[0-9]{4}$/,
        format: (num) => {
            const cleaned = num.replace(/\D/g, '');
            if (cleaned.length === 11) {
                return cleaned.replace(/(\d{3})(\d{4})(\d{4})/, '$1-$2-$3');
            }
            return num;
        },
        placeholder: '090-0000-0000'
    },
    'CN': {
        pattern: /^1[3-9][0-9]-?[0-9]{4}-?[0-9]{4}$/,
        format: (num) => {
            const cleaned = num.replace(/\D/g, '');
            if (cleaned.length === 11) {
                return cleaned.replace(/(\d{3})(\d{4})(\d{4})/, '$1-$2-$3');
            }
            return num;
        },
        placeholder: '130-0000-0000'
    }
};

// 아이디 중복 체크 함수
function checkDuplicateLoginId() {
    const loginIdInput = document.getElementById('user_id');
    const loginId = loginIdInput.value.trim();
    const validationMessage = document.getElementById('user_id-validation');
    
    if (!loginId) {
        validationMessage.textContent = '아이디를 입력해주세요.';
        validationMessage.className = 'validation-message error';
        return;
    }
    
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=checkDuplicateLoginId&loginId=${encodeURIComponent(loginId)}`
    })
    .then(response => response.json())
    .then(data => {
        validationMessage.textContent = data.message;
        validationMessage.className = `validation-message ${data.status}`;
        validationFlags.idChecked = (data.status === 'success');
    })
    .catch(error => {
        console.error('Error:', error);
        validationMessage.textContent = '중복 확인 중 오류가 발생했습니다.';
        validationMessage.className = 'validation-message error';
    });
}

// ID 입력값 변경 감지
document.getElementById('user_id').addEventListener('input', function() {
    validationFlags.idChecked = false;
    const validationMessage = document.getElementById('user_id-validation');
    validationMessage.textContent = '아이디 중복확인이 필요합니다.';
    validationMessage.className = 'validation-message error';
});

// 후원인 검증 함수
function checkSponsor() {
    const sponsorInput = document.getElementById('sponsored_by');
    const sponsorId = sponsorInput.value.trim();
    const validationMessage = document.getElementById('sponsored_by-validation');
    const positionSelection = document.getElementById('position-selection');
    
    if (!sponsorId) {
        validationMessage.textContent = '후원인 아이디를 입력해주세요.';
        validationMessage.className = 'validation-message error';
        positionSelection.style.display = 'none';
        return;
    }
    
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=checkSponsor&sponsorId=${encodeURIComponent(sponsorId)}`
    })
    .then(response => response.json())
    .then(data => {
        validationMessage.textContent = data.message;
        validationMessage.className = `validation-message ${data.status}`;
        validationFlags.sponsorChecked = (data.status === 'success');

        if (data.status === 'success') {
            positionSelection.style.display = 'block';
            const positions = data.positions;
            const positionRadios = positionSelection.querySelectorAll('input[name="position"]');
            
            positionRadios.forEach(radio => {
                if (positions.includes(radio.value)) {
                    radio.parentElement.style.display = 'inline-block';
                    radio.disabled = false;
                } else {
                    radio.parentElement.style.display = 'none';
                    radio.disabled = true;
                }
            });
        } else {
            positionSelection.style.display = 'none';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        validationMessage.textContent = '후원인 검색 중 오류가 발생했습니다.';
        validationMessage.className = 'validation-message error';
        positionSelection.style.display = 'none';
    });
}

// 후원인 입력값 변경 감지
document.getElementById('sponsored_by').addEventListener('input', function() {
    validationFlags.sponsorChecked = false;
    const validationMessage = document.getElementById('sponsored_by-validation');
    validationMessage.textContent = '후원인 검색이 필요합니다.';
    validationMessage.className = 'validation-message error';
    document.getElementById('position-selection').style.display = 'none';
});

// 추천인 검증 함수
function checkReferrer() {
    const referrerInput = document.getElementById('referred_by');
    const referrerId = referrerInput.value.trim();
    const validationMessage = document.getElementById('referred_by-validation');
    
    if (!referrerId) {
        validationMessage.textContent = '추천인 아이디를 입력해주세요.';
        validationMessage.className = 'validation-message error';
        return;
    }
    
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=checkReferrer&referrerId=${encodeURIComponent(referrerId)}`
    })
    .then(response => response.json())
    .then(data => {
        validationMessage.textContent = data.message;
        validationMessage.className = `validation-message ${data.status}`;
        validationFlags.referrerChecked = (data.status === 'success');
    })
    .catch(error => {
        console.error('Error:', error);
        validationMessage.textContent = '추천인 검색 중 오류가 발생했습니다.';
        validationMessage.className = 'validation-message error';
    });
}

// 추천인 입력값 변경 감지
document.getElementById('referred_by').addEventListener('input', function() {
    if (!this.readOnly) {  // readonly가 아닌 경우에만 실행
        validationFlags.referrerChecked = false;
        const validationMessage = document.getElementById('referred_by-validation');
        validationMessage.textContent = '추천인 검색이 필요합니다.';
        validationMessage.className = 'validation-message error';
    }
});

// 비밀번호 일치 여부 검증
function validatePassword() {
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const validationMessage = document.getElementById('password-validation');
    
    if (passwordInput.value !== confirmPasswordInput.value) {
        validationMessage.textContent = '비밀번호가 일치하지 않습니다.';
        validationMessage.className = 'validation-message error';
        return false;
    } else if (passwordInput.value.trim() === '') {
        validationMessage.textContent = '비밀번호를 입력해주세요.';
        validationMessage.className = 'validation-message error';
        return false;
    } else {
        validationMessage.textContent = '';
        return true;
    }
}

// 비밀번호 입력값 변경 감지
document.getElementById('password').addEventListener('input', validatePassword);
document.getElementById('confirm_password').addEventListener('input', validatePassword);

// 트론 지갑 주소 검증
function validateTronWallet() {
    const tronWalletInput = document.getElementById('tron_wallet');
    const validationMessage = document.getElementById('tron_wallet-validation');
    
    if (tronWalletInput && tronWalletInput.value) {
        const walletAddress = tronWalletInput.value.trim();
        const isValid = /^T[0-9a-zA-Z]{33}$/.test(walletAddress);
        
        validationMessage.textContent = isValid ? 
            '유효한 트론 지갑 주소입니다.' : 
            '유효하지 않은 트론 지갑 주소입니다.';
        validationMessage.className = `validation-message ${isValid ? 'success' : 'error'}`;
        
        return isValid;
    }
    return true; // 빈 값은 허용
}

// 폼 제출 이벤트 리스너
document.getElementById('joinForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // 필수 필드 검증
    const requiredFields = {
        'user_id': '아이디를',
        'name': '이름을',
        'password': '비밀번호를',
        'confirm_password': '비밀번호 확인을',
        'email': '이메일을',
        'phone': '전화번호를',
        'country': '국적을',
        'organization': '소속을',
        'sponsored_by': '후원인을',
        'referred_by': '추천인을'
    };

    let isValid = true;

    // 필수 필드 입력 확인
    for (const [fieldId, message] of Object.entries(requiredFields)) {
        const field = document.getElementById(fieldId);
        const validationMessage = document.getElementById(`${fieldId}-validation`);
        
        if (!field.value.trim()) {
            if (validationMessage) {
                validationMessage.textContent = `${message} 입력해주세요.`;
                validationMessage.className = 'validation-message error';
            }
            isValid = false;
        }
    }

    // 아이디 중복 확인 체크
    if (!validationFlags.idChecked) {
        const idValidation = document.getElementById('user_id-validation');
        idValidation.textContent = '아이디 중복확인을 해주세요.';
        idValidation.className = 'validation-message error';
        isValid = false;
    }

    // 후원인 검색 확인 체크
    if (!validationFlags.sponsorChecked) {
        const sponsorValidation = document.getElementById('sponsored_by-validation');
        sponsorValidation.textContent = '후원인 검색을 해주세요.';
        sponsorValidation.className = 'validation-message error';
        isValid = false;
    }

    // 추천인 검색 확인 체크 (readonly가 아닌 경우에만)
    const referredByInput = document.getElementById('referred_by');
    if (!referredByInput.readOnly && !validationFlags.referrerChecked) {
        const referrerValidation = document.getElementById('referred_by-validation');
        referrerValidation.textContent = '추천인 검색을 해주세요.';
        referrerValidation.className = 'validation-message error';
        isValid = false;
    }

    // 비밀번호 일치 확인
    if (!validatePassword()) {
        isValid = false;
    }

    // 후원인 위치 선택 확인
    const positionSelection = document.getElementById('position-selection');
    if (positionSelection.style.display === 'block') {
        const selectedPosition = document.querySelector('input[name="position"]:checked');
        const positionValidation = document.getElementById('position-validation');
        
        if (!selectedPosition) {
            if (positionValidation) {
                positionValidation.textContent = '후원인의 위치를 선택해주세요.';
                positionValidation.className = 'validation-message error';
            }
            isValid = false;
        }
    }

    // 개인정보 수집 동의 확인
    const agreeCheckbox = document.getElementById('agree');
    const agreeValidation = document.getElementById('agree-validation');
    
    if (!agreeCheckbox.checked) {
        if (agreeValidation) {
            agreeValidation.textContent = '개인정보 수집 및 이용에 동의해주세요.';
            agreeValidation.className = 'validation-message error';
        }
        isValid = false;
    }

    // 트론 지갑 주소 검증 (선택적)
    const tronWalletInput = document.getElementById('tron_wallet');
    if (tronWalletInput && tronWalletInput.value && !validateTronWallet()) {
        isValid = false;
    }

    if (!isValid) {
        return;
    }

 


    // 폼 제출 처리
    const formData = new FormData(this);
// 폼 제출 처리 계속
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showModal("회원가입 완료", data.message, "로그인 페이지로", "/login");
        } else {
            if (data.errors) {
                // 서버에서 반환된 각 필드별 오류 처리
                for (const [field, message] of Object.entries(data.errors)) {
                    const validationMessage = document.getElementById(`${field}-validation`);
                    if (validationMessage) {
                        validationMessage.textContent = message;
                        validationMessage.className = 'validation-message error';
                    }
                }
            } else {
                // 일반 오류 메시지 표시
                alert(data.message || '회원가입 처리 중 오류가 발생했습니다.');
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('회원가입 처리 중 오류가 발생했습니다.');
    });
});

// 국가 선택에 따른 전화번호 형식 변경
document.getElementById('country').addEventListener('change', function() {
    const phoneInput = document.getElementById('phone');
    const selectedCountry = this.value;
    
    if (PHONE_FORMATS[selectedCountry]) {
        phoneInput.placeholder = PHONE_FORMATS[selectedCountry].placeholder;
    }
});

// 전화번호 입력 형식화
document.getElementById('phone').addEventListener('input', function() {
    const country = document.getElementById('country').value;
    if (PHONE_FORMATS[country]) {
        this.value = PHONE_FORMATS[country].format(this.value);
    }
});

// 모든 입력 필드에 대한 실시간 검증 메시지 초기화
document.querySelectorAll('input, select').forEach(element => {
    element.addEventListener('input', function() {
        const fieldName = this.id;
        const validationMessage = document.getElementById(`${fieldName}-validation`);
        
        if (validationMessage && !this.value.trim()) {
            validationMessage.textContent = '필수 입력 항목입니다.';
            validationMessage.className = 'validation-message error';
        } else if (validationMessage) {
            validationMessage.textContent = '';
        }
    });
});

// 이메일 유효성 검사
document.getElementById('email').addEventListener('input', function() {
    const validationMessage = document.getElementById('email-validation');
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if (!this.value.trim()) {
        validationMessage.textContent = '이메일을 입력해주세요.';
        validationMessage.className = 'validation-message error';
    } else if (!emailRegex.test(this.value)) {
        validationMessage.textContent = '유효한 이메일 주소를 입력해주세요.';
        validationMessage.className = 'validation-message error';
    } else {
        validationMessage.textContent = '';
    }
});

// Modal 표시 함수
function showModal(title, message, buttonText, redirectUrl) {
    // 기존 모달이 있다면 제거
    const existingModal = document.querySelector('.modal-container');
    if (existingModal) {
        existingModal.remove();
    }

    // 모달 컨테이너 생성
    const modalContainer = document.createElement('div');
    modalContainer.className = 'modal-container';
    modalContainer.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1000;
    `;

    // 모달 내용
    const modalContent = document.createElement('div');
    modalContent.className = 'modal-content';
    modalContent.style.cssText = `
        background-color: #000;
        padding: 20px;
        border-radius: 5px;
        border: 1px solid #d4af37;
        text-align: center;
        color: #fff;
        max-width: 400px;
        width: 90%;
    `;

    // 제목
    const titleElement = document.createElement('h2');
    titleElement.textContent = title;
    titleElement.style.cssText = `
        color: #d4af37;
        margin-bottom: 15px;
        font-size: 1.5em;
    `;

    // 메시지
    const messageElement = document.createElement('p');
    messageElement.textContent = message;
    messageElement.style.cssText = `
        margin-bottom: 20px;
        line-height: 1.5;
    `;

    // 버튼
    const button = document.createElement('button');
    button.textContent = buttonText;
    button.className = 'btn-gold';
    button.style.cssText = `
        background: linear-gradient(to right, #d4af37, #f2d06b);
        color: #000;
        border: none;
        padding: 10px 20px;
        border-radius: 4px;
        cursor: pointer;
        font-weight: bold;
        width: 100%;
    `;
    
    button.onclick = () => {
        if (redirectUrl) {
            window.location.href = redirectUrl;
        } else {
            modalContainer.remove();
        }
    };

    // 모달 조립
    modalContent.appendChild(titleElement);
    modalContent.appendChild(messageElement);
    modalContent.appendChild(button);
    modalContainer.appendChild(modalContent);
    document.body.appendChild(modalContainer);
}
</script>




<?php include __DIR__ . '/../includes/footer.php'; ?>
