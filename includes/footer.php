
</div>

<?php  //end of body ?>

<div class="nav-menu">
    <a href="/" class="nav-item" data-page="home">
        <i class="fas fa-home"></i>
        <span>홈</span>
    </a>

        <a href="/profile" class="nav-item" data-page="profile">
        <i class="fas fa-user"></i>
        <span>회원</span>
    </a>
    
    <a href="/order" class="nav-item" data-page="order">
        <i class="fas fa-store"></i>
        <span>구매</span>
    </a>
    <a href="/dashboard" class="nav-item" data-page="wallet">
        <i class="fas fa-wallet"></i>
        <span>전자지갑</span>
    </a>

    <a href="/certificate" class="nav-item" data-page="certificate">
        <i class="fas fa-certificate"></i>
        <span>인증서</span>
    </a>
    <a href="/notice" class="nav-item" data-page="notice">
        <i class="fas fa-bell"></i>
        <span>공지사항</span>
    </a>
</div>

    
<script src="https://developers.kakao.com/sdk/js/kakao.js"></script>
<script>
    // 카카오 SDK 초기화 함수
    function initKakao() {
        Kakao.init('a8daa00bf1bae802fb03ee35f7f298a3');
        console.log(Kakao.isInitialized());
    }

    // 카카오톡 공유 함수 
    function shareKakao() {
        // 로그인한 사용자의 추천 코드 가져오기
        var referralCode = '<?php echo isset($_SESSION["referral_code"]) ? $_SESSION["referral_code"] : ""; ?>';
        var shareUrl = 'https://yammi.farm';
        
        // 추천 코드가 있으면 URL에 추가
        if(referralCode) {
            shareUrl += '?ref=' + referralCode;
        }

        Kakao.Link.sendDefault({
            objectType: 'feed',
            content: {
                title: '부자들의모임_리치테크(Rich Tech club)',
                description: '국내외 검증된 자산(주식,토큰 등)을 통해 정보와 성공을 함께하세요!',
                imageUrl: 'https://richtech.club/assets/images/rich1.png',
                link: {
                    mobileWebUrl: shareUrl,
                    webUrl: shareUrl
                }
            },
            buttons: [
                {
                    title: '웹으로 보기',
                    link: {
                        mobileWebUrl: shareUrl,
                        webUrl: shareUrl
                    }
                }
            ]
        });
    }


    // 현재 페이지 하이라이트 함수
    function highlightCurrentPage() {
        var currentPage = '<?php echo basename($_SERVER['PHP_SELF'], '.php'); ?>';
        var navItems = document.querySelectorAll('.nav-item');
        
        navItems.forEach(function(item) {
            if (item.getAttribute('data-page') === currentPage) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });
    }

    // 페이지 로드 완료 후 실행
    document.addEventListener('DOMContentLoaded', function() {
        initKakao();
        highlightCurrentPage();
    });

    // 카카오 SDK 로드 실패 시 대체 처리
    window.onload = function() {
        if (typeof Kakao === 'undefined') {
            console.error('카카오 SDK 로드 실패');
            // 대체 공유 방법 구현 또는 사용자에게 알림
            document.querySelector('a[onclick="shareKakao()"]').onclick = function() {
                alert('카카오톡 공유 기능을 사용할 수 없습니다.');
                return false;
            };
        }
    };
</script>

<style>
    .nav-menu {
        background: linear-gradient(90deg, #d4af37, #b18528);
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        display: flex;
        justify-content: space-around;
        padding: 13px 0 8px 0;
        border: 0;
        z-index: 11;
    }
    .nav-menu a {
        color: #000;
        text-decoration: none;
        text-align: center;
        flex: 1;
        transition: color 0.3s;
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    .nav-menu a:hover { color: #ffffff; }
    .nav-menu a.active {
        color: #000;
    }
    .nav-menu i {
        font-size: 24px;
        margin-bottom: 2px;
    }
    .nav-menu span {
        display: block;
        font-size: 10px;
    }
</style>




<script>
            // 페이지 상단에 알림 메시지를 표시하는 공통 함수
        function showNotification(message, type = 'success') {
            // 기존 알림이 있다면 제거
            const existingNotification = document.querySelector('.notification');
            if (existingNotification) {
                existingNotification.remove();
            }

            // 새로운 알림 요소 생성
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.style.cssText = `
                position: fixed;
                top: 60px;
                left: 50%;
                transform: translateX(-50%);
                padding: 15px 25px;
                border-radius: 5px;
                font-size: 0.9rem;
                z-index: 1000;
                animation: slideDown 0.5s ease-out;
                text-align: center;
                min-width: 280px;
            `;

            // 알림 타입에 따른 스타일 지정
            if (type === 'success') {
                notification.style.backgroundColor = '#066106';
                notification.style.border = 'solid 1px #a79018';
                notification.style.color = '#fff';
                notification.classList.add('notoserif');
            } else if (type === 'error') {
                notification.style.backgroundColor = 'rgba(244, 67, 54, 0.95)';
                notification.style.border = 'solid 1px #a79018';
                notification.style.color = '#fff';
                notification.classList.add('notoserif');
            }

            notification.textContent = message;

            // body에 알림 추가
            document.body.appendChild(notification);

            // 3초 후 자동으로 사라지게 함
            setTimeout(() => {
                notification.style.animation = 'slideUp 0.5s ease-out';
                setTimeout(() => notification.remove(), 450);
            }, 3000);
        }

        // 애니메이션 스타일 추가
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideDown {
                from { transform: translate(-50%, -100%); opacity: 0; }
                to { transform: translate(-50%, 0); opacity: 1; }
            }
            
            @keyframes slideUp {
                from { transform: translate(-50%, 0); opacity: 1; }
                to { transform: translate(-50%, -100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
</script>



    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick.min.js"></script>

</body>
</html>

<?php  /*
showModal("회원가입 완료", "회원가입이 성공적으로 완료되었습니다.", "로그인하기", "/login");
showModal("알림", "작업이 완료되었습니다.", "닫기");

*/ ?>

<style>
/* 모달 기본 스타일 */
.modal {
    display: flex;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    justify-content: center;
    align-items: center;
    z-index: 1000;
}

.modal-content {
    background: #fff;
    border-radius: 12px;
    padding: 0 20px 20px 20px; /* 상단 패딩을 0으로 변경 */
    width: 90%;
    max-width: 400px;
    text-align: center;
    position: relative;
    animation: modalFadeIn 0.3s ease-out;
}

.modal-content h4 {
    background-color: #f2f2f2; /* 제목 부분에 배경색 추가 */
    color: #4CAF50;
    font-size: 1.2rem;
    margin: 0; /* 마진 제거 */
    padding: 15px; /* 패딩 추가 */
    font-weight: bold;
    border-top-left-radius: 12px; /* 모서리 둥글게 */
    border-top-right-radius: 12px;
}

.modal-content p {
    color: #333;
    margin: 20px 0;
}

.btn-gold {
    background: linear-gradient(to right, #794707, #b79b04);
    color: white;
    border: none;
    padding: 7px 14px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.btn-gold:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

@keyframes modalFadeIn {
    from {
        transform: translateY(-20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}
</style>

<script>
function showModal(title, message, buttonText, redirectPage) {
    // 기존 모달 제거
    const existingModal = document.querySelector('.modal');
    if (existingModal) {
        existingModal.remove();
    }

    // 새 모달 생성
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content">
            <h4 class="notoserif">${title}</h4>
            <p>${message}</p>
            <button id="modal-button" class="btn-gold">${buttonText}</button>
        </div>
    `;

    // body에 모달 추가
    document.body.appendChild(modal);

    // 버튼 클릭 이벤트
    const modalButton = document.getElementById('modal-button');
    modalButton.addEventListener('click', () => {
        if (redirectPage) {
            window.location.href = redirectPage;
        } else {
            modal.remove();
        }
    });

    // ESC 키 이벤트
    const handleEsc = (e) => {
        if (e.key === 'Escape') {
            modal.remove();
            document.removeEventListener('keydown', handleEsc);
        }
    };
    document.addEventListener('keydown', handleEsc);

    // 모달 외부 클릭 이벤트
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.remove();
        }
    });
}
</script>