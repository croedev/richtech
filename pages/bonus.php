<?php
// bonus.php - 수당 조회 페이지

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/richtech.club/pages/error.log');

session_start();
require_once __DIR__ . '/../includes/config.php';

// 로그인 확인
if (!isset($_SESSION['user_id'])) {
    header("Location: login");
    exit();
}

$user_id = $_SESSION['user_id'];
$conn = db_connect();

// 회원 정보 가져오기
$stmt = $conn->prepare("SELECT name, login_id, rank, point, bonus_referral, bonus_rank, bonus_center, is_center FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 요청된 수당 종류 및 날짜 범위 가져오기
$bonus_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 10; // 페이지당 표시할 항목 수
$offset = ($page - 1) * $limit;

$pageTitle = '수당 조회';
include __DIR__ . '/../includes/header.php';
?>

<style>
        /* 기본 스타일 */
        .bonus-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 15px;
            font-family: noto sans kr, sans-serif;
            font-weight: 300;
        }

        .summary-card {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            border: 1px solid rgba(212, 175, 55, 0.2);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr) !important;
            gap: 10px;
        }

        .stat-box {
            text-align: center;
            padding: 10px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .stat-box:hover {
            background: rgba(212, 175, 55, 0.1);
        }

        .stat-label {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.8rem;
            margin-bottom: 5px;
        }

        .stat-value {
            color: #d4af37;
            font-size: 1.2rem;
            font-weight: bold;
        }

        .date-range-form {
            background: rgba(26, 26, 26, 0.95);
            padding: 3px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 0px solid rgba(212, 175, 55, 0.1);
        }

        .date-input {
            background: #333;
            border: 1px solid #555;
            color: #fff;
            padding: 5px;
            border-radius: 5px;
            width: 130px;
            font-size: 0.8rem;
        }

        .nav-tabs {
            border-bottom: 1px solid #555;
            margin: 20px 0;
            display: flex;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .nav-tabs .nav-link {
            color: rgba(255, 255, 255, 0.8);
            border: none;
            padding: 5px 10px;
            margin-right: 5px;
            border-radius: 5px 5px 0 0;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .nav-tabs .nav-link:hover {
            background: rgba(212, 175, 55, 0.1);
            color: #d4af37;
        }

        .nav-tabs .nav-link.active {
            background: #d4af37;
            color: #000;
            font-weight: 600;
        }

        .bonus-card {
            background: rgba(26, 26, 26, 0.95);
            border: 1px solid rgba(212, 175, 55, 0.1);
            border-radius: 8px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .bonus-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(212, 175, 55, 0.1);
        }

        .bonus-header {
            background: rgba(212, 175, 55, 0.1);
            padding: 10px;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .bonus-body {
            padding: 15px;
        }

        .amount-label {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.6);
        }

        .amount-value {
            font-size: 1.0rem;
            color: #d4af37;
            font-weight: bold;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .detail-label {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.85rem;
        }

        .detail-value {
            color: #fff;
            font-weight: 500;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
        }

        .pagination .page-link {
            background: rgba(26, 26, 26, 0.95);
            border: 1px solid rgba(212, 175, 55, 0.2);
            color: #d4af37;
            padding: 8px 12px;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .pagination .page-link:hover {
            background: rgba(212, 175, 55, 0.1);
        }

        .pagination .page-item.active .page-link {
            background: #d4af37;
            color: #000;
            border-color: #d4af37;
        }

        .no-data {
            text-align: center;
            padding: 30px;
            color: rgba(255, 255, 255, 0.5);
            background: rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            margin: 20px 0;
        }

        /* 모바일 최적화 */
        @media (max-width: 640px) {
            .stat-value {
                font-size: 1rem;
            }

            .stat-label {
                font-size: 0.7rem;
            }

            .date-input {
                width: 130px;
                font-size: 0.9rem;
            }

            .bonus-header {
                flex-direction: row;
                gap: 4px;
            }

            .detail-row {
                flex-direction: row;
                gap: 2px;
            }

            .detail-value {
                text-align: right;
            }
        }

       /* 메뉴선택*/

       .bonus-nav-buttons {
        display: grid;
        grid-template-columns: repeat(<?php echo ($user['is_center'] == 1) ? '4' : '3'; ?>, 1fr);
        gap: 10px;
        margin: 20px 0;
    }
    
     .bonus-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    /* aspect-ratio: 1;*/
        padding: 10px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        text-decoration: none;
        text-align: center;
        transition: all 0.3s ease;
        background: transparent;
        border: 1px solid rgba(212, 175, 55, 0.2);
        color: rgba(212, 175, 55, 0.6);
    }

    .bonus-btn:hover {
        border-color: rgba(212, 175, 55, 0.8);
        color: rgba(212, 175, 55, 0.9);
        text-decoration: none;
    }

    .bonus-btn.active {
        background: linear-gradient(135deg, rgba(212, 175, 55, 0.8) 0%, rgba(212, 175, 55, 1) 100%);
        border: 1px solid #d4af37;
        color: #000;
        font-weight: 600;
        box-shadow: 0 4px 12px rgba(212, 175, 55, 0.2);
    }

    .bonus-btn i {
        font-size: 24px;
        margin-bottom: 8px;
    }

    .bonus-btn span {
        font-size: 12px;
    }
</style>



<div class="bonus-container">
    <!-- 상단 요약 정보 -->
    <div class="summary-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0 text-orange">
                <i class="fas fa-user-circle"></i>
                <?php echo htmlspecialchars($user['name']); ?>
                <span class="ml30 btn14 bg-gray50 text-orange"><?php echo htmlspecialchars($user['rank']); ?></span>
            </h5>
            <span class="ml10 text-muted small"><?php echo htmlspecialchars($user['login_id']); ?></span>
        </div>
        <div class="stats-grid">
            <div class="stat-box">
                <p class="stat-label">추천수당 누적</p>
                <p class="stat-value">$<?php echo number_format($user['bonus_referral'], 2); ?></p>
            </div>
            <div class="stat-box">
                <p class="stat-label">직급수당 누적</p>
                <p class="stat-value">$<?php echo number_format($user['bonus_rank'], 2); ?></p>
            </div>
            <div class="stat-box">
                <p class="stat-label">멘토수당 누적</p>
                <p class="stat-value">$<?php echo number_format($user['bonus_center'], 2); ?></p>
            </div>
        </div>
    </div>

    <!-- 날짜 선택 폼 -->
    <form method="get" class="date-range-form">
        <input type="hidden" name="type" value="<?php echo htmlspecialchars($bonus_type); ?>">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <input type="date" name="start_date" class="date-input"
                    value="<?php echo htmlspecialchars($start_date); ?>" required>
                <span class="text-white">~</span>
                <input type="date" name="end_date" class="date-input" value="<?php echo htmlspecialchars($end_date); ?>"
                    required>
            </div>
            <button type="submit" class="btn btn-gold btn-sm">
                <i class="fas fa-search"></i> 
            </button>
        </div>
    </form>

    <!-- 버튼 메뉴 -->
    <div class="bonus-nav-buttons">
        <a class="bonus-btn <?php echo $bonus_type == 'all' ? 'active' : ''; ?>"
            href="?type=all&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>">
            <i class="fas fa-list"></i>
            <span>전체보기</span>
        </a>
        <a class="bonus-btn <?php echo $bonus_type == 'referral' ? 'active' : ''; ?>"
            href="?type=referral&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>">
            <i class="fas fa-users"></i>
            <span>추천수당</span>
        </a>
        <a class="bonus-btn <?php echo $bonus_type == 'rank' ? 'active' : ''; ?>"
            href="?type=rank&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>">
            <i class="fas fa-medal"></i>
            <span>직급수당</span>
        </a>
        <?php if ($user['is_center'] == 1): ?>
        <a class="bonus-btn <?php echo $bonus_type == 'center' ? 'active' : ''; ?>"
            href="?type=center&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>">
            <i class="fas fa-building"></i>
            <span>멘토수당</span>
        </a>
        <?php endif; ?>
    </div>

   
   
    <!-- 수당 내역 표시 영역 -->
    <div class="bonus-content">
        <?php
        // 추천수당 조회 및 표시
        if ($bonus_type == 'referral' || $bonus_type == 'all') {
            // 전체 레코드 수 조회
            $stmt = $conn->prepare("
                SELECT COUNT(*) as total 
                FROM bonus_referral 
                WHERE user_id = ? AND DATE(created_at) BETWEEN ? AND ?
            ");
            $stmt->bind_param("iss", $user_id, $start_date, $end_date);
            $stmt->execute();
            $total = $stmt->get_result()->fetch_assoc()['total'];
            $total_pages = ceil($total / $limit);
            $stmt->close();

            // 해당 페이지의 데이터 조회
            $stmt = $conn->prepare("
                SELECT br.*, u.login_id, u.name
                FROM bonus_referral br
                JOIN users u ON br.source_user_id = u.id
                WHERE br.user_id = ? AND DATE(br.created_at) BETWEEN ? AND ?
                ORDER BY br.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->bind_param("issii", $user_id, $start_date, $end_date, $limit, $offset);
            $stmt->execute();
            $referral_bonuses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

         if (!empty($referral_bonuses)): ?>
    
    <hr>
        <h5 class="fs-18 mt30 mb-3">
            <i class="fas fa-users"></i> 추천수당 내역
        </h5>
        <?php foreach ($referral_bonuses as $bonus): ?>
        <div class="bonus-card">
            <div class="bonus-header d-flex justify-content-between ">

                <span class="fs-13 text-white">
                    <i class="far fa-calendar-alt"></i>
                    <?php echo date('Y-m-d H:i', strtotime($bonus['order_date'])); ?>
                </span>
                <span class="amount-value fs-14">
                    $<?php echo number_format($bonus['amount'], 2); ?>
                </span>
            </div>
            <div class="bonus-body">
                <div class="detail-row d-flex justify-content-between align-items-center">
                    <span class="detail-label">추천인</span>
                    <span class="detail-value fs-13">
                        <?php echo htmlspecialchars($bonus['name']); ?>
                        (<?php echo htmlspecialchars($bonus['login_id']); ?>)
                    </span>
                </div>
                <div class="detail-row d-flex justify-content-between align-items-center">
                    <span class="detail-label">매출액</span>
                    <span class="detail-value fs-13">
                        $<?php echo number_format($bonus['source_amount'], 2); ?>
                    </span>
                </div>
                <div class="detail-row d-flex justify-content-between align-items-center">
                    <span class="detail-label">수당율</span>
                    <span class="detail-value text-gold fs-13">
                        <?php echo $bonus['level']; ?>대 (<?php echo $bonus['commission_rate']; ?>%)
                    </span>
                </div>
            </div>
        </div>
        <?php endforeach;
            else: ?>
        <div class="no-data">
            <i class="fas fa-info-circle"></i>
            해당 기간에 발생한 추천수당이 없습니다.
        </div>
        <?php endif;
        }
        ?>



        <?php
        // 직급수당 조회 및 표시
        if ($bonus_type == 'rank' || $bonus_type == 'all') {
            // 전체 레코드 수 조회
            $stmt = $conn->prepare("
                SELECT COUNT(*) as total 
                FROM bonus_rank 
                WHERE user_id = ? AND calculation_date BETWEEN ? AND ?
            ");
            $stmt->bind_param("iss", $user_id, $start_date, $end_date);
            $stmt->execute();
            $total = $stmt->get_result()->fetch_assoc()['total'];
            $total_pages = ceil($total / $limit);
            $stmt->close();

            // 해당 페이지의 데이터 조회
            $stmt = $conn->prepare("
                SELECT *
                FROM bonus_rank
                WHERE user_id = ? AND calculation_date BETWEEN ? AND ?
                ORDER BY calculation_date DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->bind_param("issii", $user_id, $start_date, $end_date, $limit, $offset);
            $stmt->execute();
            $rank_bonuses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            if (!empty($rank_bonuses)): ?>
        <h5 class="text-gold mb-3">
            <i class="fas fa-crown"></i> 직급수당 내역
        </h5>
        <?php foreach ($rank_bonuses as $bonus): ?>
        <div class="bonus-card">
            <div class="bonus-header">
                <div>
                    <span class="text-white">
                        <i class="far fa-calendar-alt"></i>
                        <?php echo date('Y-m-d', strtotime($bonus['calculation_date'])); ?>
                    </span>
                </div>
                <div class="amount-value">
                    $<?php echo number_format($bonus['amount'], 2); ?>
                </div>
            </div>
            <div class="bonus-body">
                <div class="detail-row">
                    <div class="detail-label">수당 종류</div>
                    <div class="detail-value">
                        <?php echo htmlspecialchars($bonus['bonus_type']); ?>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">본인 직급</div>
                    <div class="detail-value">
                        <span class="badge bg-gold">
                            <?php echo htmlspecialchars($bonus['rank']); ?>
                        </span>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">적용 대상 직급</div>
                    <div class="detail-value">
                        <?php echo htmlspecialchars($bonus['applicable_ranks']); ?>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">공유 비율</div>
                    <div class="detail-value text-gold">
                        <?php echo $bonus['commission_rate']; ?>%
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach;
            elseif ($bonus_type == 'rank'): ?>
        <div class="no-data">
            <i class="fas fa-info-circle"></i>
            해당 기간에 발생한 직급수당이 없습니다.
        </div>
        <?php endif;
        }

        // 센터수당 조회 및 표시
        if ($bonus_type == 'center' || $bonus_type == 'all') {
            // 전체 레코드 수 조회
            $stmt = $conn->prepare("
                SELECT COUNT(*) as total 
                FROM bonus_center 
                WHERE user_id = ? AND sales_date BETWEEN ? AND ?
            ");
            $stmt->bind_param("iss", $user_id, $start_date, $end_date);
            $stmt->execute();
            $total = $stmt->get_result()->fetch_assoc()['total'];
            $total_pages = ceil($total / $limit);
            $stmt->close();

            // 해당 페이지의 데이터 조회
            $stmt = $conn->prepare("
                SELECT bc.*, o.name as organization_name
                FROM bonus_center bc
                LEFT JOIN organizations o ON bc.organization_id = o.id
                WHERE bc.user_id = ? AND bc.sales_date BETWEEN ? AND ?
                ORDER BY bc.sales_date DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->bind_param("issii", $user_id, $start_date, $end_date, $limit, $offset);
            $stmt->execute();
            $center_bonuses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            if (!empty($center_bonuses)): ?>
        <h5 class="text-gold mb-3">
            <i class="fas fa-building"></i> 센터수당 내역
        </h5>
        <?php foreach ($center_bonuses as $bonus): ?>
        <div class="bonus-card">
            <div class="bonus-header">
                <div>
                    <span class="text-white">
                        <i class="far fa-calendar-alt"></i>
                        <?php echo date('Y-m-d', strtotime($bonus['sales_date'])); ?>
                    </span>
                </div>
                <div class="amount-value">
                    $<?php echo number_format($bonus['amount'], 2); ?>
                </div>
            </div>
            <div class="bonus-body">
                <div class="detail-row">
                    <div class="detail-label">센터명</div>
                    <div class="detail-value">
                        <?php echo htmlspecialchars($bonus['organization_name']); ?>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">센터 매출</div>
                    <div class="detail-value">
                        $<?php echo number_format($bonus['total_sales'], 2); ?>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">수당율</div>
                    <div class="detail-value text-gold">
                        <?php echo $bonus['commission_rate']; ?>%
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach;
            elseif ($bonus_type == 'center'): ?>
        <div class="no-data">
            <i class="fas fa-info-circle"></i>
            해당 기간에 발생한 센터수당이 없습니다.
        </div>
        <?php endif;
        }
        ?>

        <!-- 페이지네이션 -->
        <?php if (isset($total_pages) && $total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link"
                        href="?type=<?php echo $bonus_type; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&page=1">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                </li>
                <li class="page-item">
                    <a class="page-link"
                        href="?type=<?php echo $bonus_type; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&page=<?php echo ($page - 1); ?>">
                        <i class="fas fa-angle-left"></i>
                    </a>
                </li>
                <?php endif; ?>

                <?php
                    // 표시할 페이지 번호 범위 계산
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);

                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                    <a class="page-link"
                        href="?type=<?php echo $bonus_type; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&page=<?php echo $i; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link"
                        href="?type=<?php echo $bonus_type; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&page=<?php echo ($page + 1); ?>">
                        <i class="fas fa-angle-right"></i>
                    </a>
                </li>
                <li class="page-item">
                    <a class="page-link"
                        href="?type=<?php echo $bonus_type; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&page=<?php echo $total_pages; ?>">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 날짜 선택 시 자동 서브밋
    const dateInputs = document.querySelectorAll('.date-input');
    dateInputs.forEach(input => {
        input.addEventListener('change', function() {
            const otherDateInput = input.getAttribute('name') === 'start_date' ?
                document.querySelector('input[name="end_date"]') :
                document.querySelector('input[name="start_date"]');

            if (otherDateInput.value) {
                input.form.submit();
            }
        });
    });

    // 탭 전환 시 스무스 스크롤
    const tabs = document.querySelectorAll('.nav-link');
    tabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            const targetHref = this.getAttribute('href');

            // 현재 active 클래스 제거
            tabs.forEach(t => t.classList.remove('active'));
            // 클릭된 탭에 active 클래스 추가
            this.classList.add('active');

            // 스무스 스크롤로 해당 섹션으로 이동
            const targetSection = document.querySelector(targetHref);
            if (targetSection) {
                targetSection.scrollIntoView({
                    behavior: 'smooth'
                });
            } else {
                window.location.href = targetHref;
            }
        });
    });

    // 금액에 천단위 콤마 적용
    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    // 모바일에서 탭 스크롤 위치 조정
    const tabContainer = document.querySelector('.nav-tabs');
    const activeTab = tabContainer.querySelector('.active');
    if (activeTab) {
        tabContainer.scrollLeft = activeTab.offsetLeft - tabContainer.offsetWidth / 2 + activeTab.offsetWidth /
            2;
    }
});
</script>

<?php
// 데이터베이스 연결 종료
$conn->close();

// 푸터 인클루드
include __DIR__ . '/../includes/footer.php';
?>