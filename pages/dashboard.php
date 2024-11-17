<?php
// dashboard.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /login?redirect=dashboard");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$conn = db_connect();

try {
    $stmt = $conn->prepare("
        SELECT name, login_id, rank, commission_total, bonus_referral, bonus_rank, 
               point, stock, token, left_amounts, right_amounts, myAmount, 
               referral_count, left_members, right_members
        FROM users 
        WHERE id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    

} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    die("오류가 발생했습니다. 관리자에게 문의하세요.");
}
?>


<?php  
$pageTitle = '대시보드';   
include __DIR__ . '/../includes/header.php'; 
?>


<style>
        .dashboard-container {
            padding: 20px;
            background-color: #000;
            color: #d4af37;
            font-family: 'Noto Sans KR', sans-serif;
            font-size: 0.9rem;
        }

        .welcome-section {
            background: linear-gradient(45deg, #1a1a1a, #2d2d2d);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid rgba(212, 175, 55, 0.1);
        }

        .rank-badge {
            display: inline-block;
            padding: 4px 12px;
            background: rgba(212, 175, 55, 0.1);
            border: 1px solid #d4af37;
            border-radius: 15px;
            font-size: 0.8rem;
            margin-left: 10px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: #1c1c1c;
            border-radius: 8px;
            padding: 15px;
            border: 1px solid rgba(212, 175, 55, 0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            border-color: rgba(212, 175, 55, 0.3);
        }

        .stat-title {
            font-size: 0.75rem;
            color: #999;
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 1.1rem;
            color: #fff;
            font-weight: 600;
            font-family: 'Noto Serif KR', serif;
        }

        .performance-section {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .performance-card {
            background: #1c1c1c;
            border-radius: 8px;
            padding: 15px;
            border: 1px solid rgba(212, 175, 55, 0.1);
        }

        .performance-title {
            font-size: 0.8rem;
            color: #d4af37;
            margin-bottom: 15px;
            border-bottom: 1px solid rgba(212, 175, 55, 0.1);
            padding-bottom: 5px;
        }

        .performance-stat {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 0.75rem;
        }

        .performance-label {
            color: #999;
        }

        .performance-value {
            color: #fff;
            font-family: 'Noto Serif KR', serif;
        }

        .links-section {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .link-card {
            flex: 1;
            min-width: 200px;
            background: #1c1c1c;
            border-radius: 8px;
            padding: 15px;
            border: 1px solid rgba(212, 175, 55, 0.1);
            text-decoration: none;
            color: #d4af37;
            font-size: 0.8rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .link-card:hover {
            background: rgba(212, 175, 55, 0.1);
            border-color: #d4af37;
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .performance-section {
                grid-template-columns: 1fr;
            }
        }


        .dashboard-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 10px;
            padding: 20px;
        }

        .dashboard-card {
            background: #222;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .dashboard-card h6 {
            margin-bottom: 15px;
            color: #d4af37;
        }

        .dashboard-card p {
            margin-bottom: 5px;
            color: #ccc;
            font-size: 12px;
        }

        .referral-link {
            padding: 20px;
            text-align: left;
        }

        .referral-link input {
            width: 100%;
            padding: 10px;
            border: 1px solid #d4af37;
            border-radius: 5px;
            background: #000;
            color: #d4af37;
            text-align: center;
        }
</style>


<!-- 대시보드 -->
<div class="dashboard-container mt10 mb100">
    <!-- Start of Selection -->
    <h6 class=""><i class="fas fa-tachometer-alt text-yellow4"></i> 대시보드</h6>

    <!-- 나의 직급 카드 -->
    <div class="dashboard-card pb0 pt20 mb0 border-yellow05 bg-gray70">
        <h6 class="notoserif fs-14"><i class="fas fa-crown text-yellow4"></i> 나의 직급 :
            <span class="text-white btn14 bg-yellow60"><?php echo htmlspecialchars($user['rank']); ?></span>
            <h6 class="notoserif fs-14"><i class="fas fa-coins text-yellow4"></i> 나의 포인트 :
                <span class="text-white btn14 border-0"><?php echo number_format($user['point']); ?> usdp</span>
            </h6>
        </h6>
        <hr>
        <div class="performance-stat">
            <span class="performance-label"><i class="fas fa-share-alt"></i> 보유 주식</span>
            <span class="performance-value text-orange"><?php echo number_format($user['stock']); ?>주</span>
        </div>
        <div class="performance-stat">
            <span class="performance-label"><i class="fas fa-coins"></i> 보유 토큰</span>
            <span class="performance-value text-orange"><?php echo number_format($user['token']); ?></span>
        </div>

    </div>



        <!-- 좌우실적 카드 -->
    <div class="dashboard-card bg-gray90">
        <h6 class="notoserif"><i class="fas fa-chart-line"></i> 나의 네트워크</h6>
        <hr>
        <div class="d-flex justify-content-between">
            <!-- 좌측 실적 -->
            <div class="w-50 p-2 border-end">
                <h6 class="btn14 bg-gray50 text-center">LEFT</h6>
                <div class="stats-box mt20">
                    <p>회원수: <span
                            class="text-yellow4 fw-bold"><?php echo number_format($user['left_members']); ?>명</span></p>
                    <p>누적실적: <span
                            class="text-yellow4 fw-bold">$<?php echo number_format($user['left_amounts']); ?></span></p>
                    <?php
                // 오늘 실적은 별도 쿼리 필요
                $today_left = 0; // TODO: 오늘 실적 쿼리 추가
                ?>
                    <p>오늘실적: <span class="text-yellow4 fw-bold">$<?php echo number_format($today_left); ?></span></p>
                </div>
            </div>
            <!-- 우측 실적 -->
            <div class="w-50 p-2">
                <h6 class="btn14 bg-gray50 text-center">RIGHT</h6>
                <div class="stats-box mt20">
                    <p>회원수: <span
                            class="text-yellow4 fw-bold"><?php echo number_format($user['right_members']); ?>명</span>
                    </p>
                    <p>누적실적: <span
                            class="text-yellow4 fw-bold">$<?php echo number_format($user['right_amounts']); ?></span>
                    </p>
                    <?php
                // 오늘 실적은 별도 쿼리 필요
                $today_right = 0; // TODO: 오늘 실적 쿼리 추가
                ?>
                    <p>오늘실적: <span class="text-yellow4 fw-bold">$<?php echo number_format($today_right); ?></span></p>
                </div>
            </div>
        </div>
    </div>


    <!-- 나의실적,나의수수료 카드 -->
    <div class="d-flex justify-content-between">
        <div class="dashboard-card w-50 me-1">
            <h6 class="notoserif fs-14"><i class="fas fa-users text-yellow4"></i> 나의 실적</h6>
            <hr>
            <p class="">누적매출: <span class="text-yellow4 fw-bold"><?php echo number_format($user['myAmount']); ?>
                    usdt</span></p>
            <p class="">추천인 수: <span
                    class="text-yellow4 fw-bold"><?php echo number_format($user['referral_count']); ?>명</span></p>
        </div>
        <div class="dashboard-card w-50 ms-2">
            <h6 class="notoserif fs-14"><i class="fas fa-coins text-yellow4"></i> 나의 수수료</h6>
            <hr>
            <p>추천 수당: <span class="text-yellow4 fw-bold"><?php echo number_format($user['bonus_referral']); ?>
                    USDT</span></p>
            <p>직급 수당: <span class="text-yellow4 fw-bold"><?php echo number_format($user['bonus_rank']); ?> USDT</span>
            </p>
            <p class="fw-bold">합계: <span class="text-yellow4"><?php echo number_format($user['commission_total']); ?>
                    USDT</span></p>
        </div>
        
    </div>


    <div class="links-section mt20">
        <a href="/bonus" class="link-card bg-gray90">
            <i class="fas fa-users mr10"></i>추천-직급-보너스 조회
        </a>
        <a href="/deposits" class="link-card bg-yellow100">
            <i class="fas fa-crown mr10"></i>충전하기
        </a>
        <a href="/chart?type=sponsor" class="link-card bg-red100">
            <i class="fas fa-history mr10"></i>후원조직도
        </a>

        <a href="/chart?type=referral" class="link-card bg-red100">
            <i class="fas fa-history mr10"></i>추천조직도
        </a>
                <a href="/certificate" class="link-card bg-red100">
            <i class="fas fa-history mr10"></i>인증서
        </a>
                <a href="/withdrawals" class="link-card bg-red100">
            <i class="fas fa-history mr10"></i>출금신청
        </a>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>