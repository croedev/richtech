<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit;
}

$conn = db_connect();
$user_id = $_SESSION['user_id'];

// 사용자 정보 조회
$stmt = $conn->prepare("SELECT id, login_id, name, birth, phone, address, stock FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    header("Location: /logout");
    exit;
}

// 발급번호 생성
$issue_number = sprintf("RT-%s-%d%04d", $user['login_id'], date('y'), $user['id']);

$pageTitle = '주식보관증';
include __DIR__ . '/../includes/header.php';
?>

<style>
body {
    margin: 0;
    padding: 0;
    background: #000;
    overflow: hidden;
    touch-action: none;
}

.certificate-container {
    position: fixed;
    top: 50px;
    left: 0;
    right: 0;
    bottom: 80px;
    display: flex;
    justify-content: center;
    align-items: center;
    background: #000;
    overflow: hidden;
}

.certificate-wrapper {
    width: 100%;
    height: 100%;
    position: relative;
    overflow: hidden;
    display: flex;
    justify-content: center;
    align-items: center;
    transform-origin: center center;
    transition: transform 0.2s ease-out;
}

.certificate-content {
    width: 100%;
    height: 100%;
    position: relative;
    overflow: auto;
    -webkit-overflow-scrolling: touch;
}

svg {
    width: 100%;
    height: auto;
    max-height: 100%;
    display: block;
    margin: auto;
    transform-origin: center center;
}

.zoom-controls {
    position: fixed;
    bottom: 100px;
    right: 20px;
    z-index: 1000;
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.zoom-btn {
    background: rgba(212, 175, 55, 0.2);
    border: 1px solid rgba(212, 175, 55, 0.5);
    color: #d4af37;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.zoom-btn:hover {
    background: rgba(212, 175, 55, 0.3);
}

.zoom-btn:active {
    transform: scale(0.95);
}
</style>

<div class="certificate-container">
    <div class="certificate-wrapper" id="certificate">
        <div class="certificate-content">
            <svg viewBox="0 0 595 842" xmlns="http://www.w3.org/2000/svg">
                <!-- 배경 이미지 -->
                <image href="/assets/images/certificate_ycons.png" 
                       width="100%" height="100%" 
                       preserveAspectRatio="xMidYMid meet"/>
                
                <!-- 발급번호 -->
                <text x="220" y="103" 
                      text-anchor="middle" 
                      font-size="10" 
                      fill="red"
                      font-family="'Noto Sans KR', sans-serif">
                    <?php echo htmlspecialchars($issue_number); ?>
                </text>
                <!-- 사용자 정보 -->
                <g font-family="'Noto Serif KR', serif" fill="#000">
                    <g transform="translate(80, 180)">
                        <!-- 이름 -->
                        <text y="80">                           
                            <tspan x="120" font-size="14" font-weight="900" fill="#092c92">
                                <?php echo htmlspecialchars($user['name']); ?>
                            </tspan>
                        </text>
                        
                        <!-- 생년월일 -->
                        <text y="102">                            
                            <tspan x="120" font-size="14" font-weight="700" fill="#092c92">
                                <?php echo htmlspecialchars($user['birth']); ?>
                            </tspan>
                        </text>
                        
                        <!-- 연락처 -->
                        <text y="123">                       
                            <tspan x="120" font-size="14"  font-weight="700" fill="#092c92">
                                <?php echo htmlspecialchars($user['phone']); ?>
                            </tspan>
                        </text>
                        
                        <!-- 주소 -->
                        <text y="143">
                            
                            <tspan x="120" font-size="14" font-weight="600" fill="#092c92">
                                <?php echo htmlspecialchars($user['address']); ?>
                            </tspan>
                        </text>
                    </g>
                    
                    <!-- 주식수 -->
                    <g transform="translate(80, 400)">
                        <text y="50" font-size="24" font-weight="bold">                          
                            <tspan x="150" font-size="20" fill="#941b07">
                                <?php echo number_format($user['stock']); ?>
                            </tspan>
                            <tspan dx="10" font-size="14">주</tspan>
                        </text>
                    </g>
                    
                    <!-- 발행일자 -->
                    <text x="300" y="580" 
                          font-size="14" 
                          text-anchor="middle" font-weight="500" fill="#092c92">
                        <?php echo date('Y년 m월 d일'); ?>
                    </text>
                </g>
                
               
            </svg>
        </div>
    </div>
</div>

<div class="zoom-controls">
    <button class="zoom-btn" onclick="zoomIn()">+</button>
    <button class="zoom-btn" onclick="zoomOut()">-</button>
    <button class="zoom-btn" onclick="resetZoom()">⟲</button>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/hammer.js/2.0.8/hammer.min.js"></script>
<script>
let currentScale = 1;
const SCALE_STEP = 0.2;
const MAX_SCALE = 3;
const MIN_SCALE = 0.5;

const certificateWrapper = document.querySelector('.certificate-wrapper');
const certificateContent = document.querySelector('.certificate-content');
const hammer = new Hammer(certificateWrapper, {
    touchAction: 'none'
});

// 핀치 줌 설정
hammer.get('pinch').set({
    enable: true,
    threshold: 0
});

let lastScale = 1;
let startX = 0;
let startY = 0;
let isDragging = false;

hammer.on('pinchstart', function(e) {
    lastScale = currentScale;
});

hammer.on('pinch', function(e) {
    e.preventDefault();
    currentScale = Math.min(Math.max(lastScale * e.scale, MIN_SCALE), MAX_SCALE);
    applyTransform();
});

hammer.on('pinchend', function(e) {
    lastScale = currentScale;
});

// 줌 컨트롤 함수
function zoomIn() {
    if (currentScale < MAX_SCALE) {
        currentScale += SCALE_STEP;
        applyTransform();
    }
}

function zoomOut() {
    if (currentScale > MIN_SCALE) {
        currentScale -= SCALE_STEP;
        applyTransform();
    }
}

function resetZoom() {
    currentScale = 1;
    applyTransform();
    certificateContent.scrollTo(0, 0);
}

function applyTransform() {
    certificateWrapper.style.transform = `scale(${currentScale})`;
    
    if (currentScale > 1) {
        certificateContent.style.overflow = 'auto';
        enableDragging();
    } else {
        certificateContent.style.overflow = 'hidden';
        disableDragging();
    }
}

// 드래그 기능
function enableDragging() {
    certificateContent.addEventListener('mousedown', startDragging);
    certificateContent.addEventListener('touchstart', startDragging);
}

function disableDragging() {
    certificateContent.removeEventListener('mousedown', startDragging);
    certificateContent.removeEventListener('touchstart', startDragging);
}

function startDragging(e) {
    isDragging = true;
    startX = e.type === 'mousedown' ? e.pageX : e.touches[0].pageX;
    startY = e.type === 'mousedown' ? e.pageY : e.touches[0].pageY;
    
    document.addEventListener('mousemove', onDrag);
    document.addEventListener('touchmove', onDrag);
    document.addEventListener('mouseup', stopDragging);
    document.addEventListener('touchend', stopDragging);
}

function onDrag(e) {
    if (!isDragging) return;
    
    e.preventDefault();
    const x = e.type === 'mousemove' ? e.pageX : e.touches[0].pageX;
    const y = e.type === 'mousemove' ? e.pageY : e.touches[0].pageY;
    
    const deltaX = (x - startX) * 2;
    const deltaY = (y - startY) * 2;
    
    certificateContent.scrollLeft -= deltaX;
    certificateContent.scrollTop -= deltaY;
    
    startX = x;
    startY = y;
}

function stopDragging() {
    isDragging = false;
    document.removeEventListener('mousemove', onDrag);
    document.removeEventListener('touchmove', onDrag);
    document.removeEventListener('mouseup', stopDragging);
    document.removeEventListener('touchend', stopDragging);
}

// 초기 설정
applyTransform();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>