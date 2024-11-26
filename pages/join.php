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
