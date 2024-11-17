<?php
// admin_company.php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/richtech.club/pages/error.log');

session_start();
require_once __DIR__ . '/../includes/config.php';


// 관리자 체크

// 관리자 권한 체크
if (!isset($_SESSION['user_id']) || ($_SESSION['user_id'] != '1' && $_SESSION['user_id'] != '2')) {
    header("Location: /login?admin/admin_company.php");
}

$conn = db_connect();

// 날짜 범위 가져오기
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

$pageTitle = '회사 통계 조회';
include __DIR__ . '/admin_header.php';
?>

<style>
/* 스타일링 */
.container {
    max-width: 100%;
    margin: 20px auto;
    color: #fff;
}
.container h2 {
    margin-bottom: 20px;
    color: #d4af37;
}
.table {
    width: 100%;
    border-collapse: collapse;
}
.table th, .table td {
    padding: 10px;
    border: 1px solid #333;
    text-align: left;
    font-size: 12px;
    font-family: 'Noto Sans KR', sans-serif;
}
.table th {
    background: #222;
    color: #d4af37;
}

.table-responsive-wrapper {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    margin-bottom: 1rem;
}

.table {
    margin-bottom: 0; /* 테이블 하단 마진 제거 */
    white-space: nowrap; /* 텍스트 줄바꿈 방지 */
}

/* 스크롤바 스타일링 (선택사항) */
.table-responsive-wrapper::-webkit-scrollbar {
    height: 8px;
}

.table-responsive-wrapper::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.table-responsive-wrapper::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 4px;
}
</style>

<div class="container">
    <h2>회사 통계 조회</h2>

    <form method="get" action="">
        <label for="start_date">시작일:</label>
        <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
        <label for="end_date">종료일:</label>
        <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
        <button type="submit">검색하기</button>
    </form>

    <?php
    // 회사 통계 데이터 가져오기
    $stmt = $conn->prepare("SELECT * FROM company_state WHERE date BETWEEN ? AND ? ORDER BY date DESC");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo '<div class="table-responsive-wrapper">';
    echo '<table class="table">';
    echo '<tr>';
    echo '<th>날짜</th>';
    echo '<th>가입 회원 수</th>';
    echo '<th>회사 매출</th>';
    echo '<th>회사 입금 금액</th>';
    echo '<th>주식 계좌</th>';
    echo '<th>마스터 계좌</th>';
    echo '<th>회사 출금 금액</th>';
    echo '<th>회사 지급 주식 수</th>';
    echo '<th>회사 지급 토큰 수</th>';
    echo '<th>추천수당 지급 합계</th>';
    echo '<th>직급수당 지급 합계</th>';
    echo '<th>센터수당 지급 합계</th>';
    echo '<th>1스타 수</th>';
    echo '<th>2스타 수</th>';
    echo '<th>3스타 수</th>';
    echo '<th>4스타 수</th>';
    echo '<th>5스타 수</th>';
    echo '<th>6스타 수</th>';
    echo '<th>7스타 수</th>';
    echo '<th>센터 수</th>';
    echo '</tr>';

    foreach ($stats as $stat) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($stat['date']) . '</td>';
        echo '<td>' . number_format($stat['new_members']) . '</td>';
        echo '<td>$' . number_format($stat['company_sales'], 2) . '</td>';
        echo '<td>$' . number_format($stat['company_deposits'], 2) . '</td>';
        echo '<td>' . number_format($stat['stock_account']) . '</td>';
        echo '<td>' . number_format($stat['master_account']) . '</td>';
        echo '<td>$' . number_format($stat['company_withdrawals'], 2) . '</td>';
        echo '<td>' . number_format($stat['company_stock_paid']) . '</td>';
        echo '<td>' . number_format($stat['company_token_paid'], 2) . '</td>';
        echo '<td>$' . number_format($stat['bonus_referral'], 2) . '</td>';
        echo '<td>$' . number_format($stat['bonus_rank'], 2) . '</td>';
        echo '<td>$' . number_format($stat['bonus_center'], 2) . '</td>';
        echo '<td>' . number_format($stat['rank_1star']) . '</td>';
        echo '<td>' . number_format($stat['rank_2star']) . '</td>';
        echo '<td>' . number_format($stat['rank_3star']) . '</td>';
        echo '<td>' . number_format($stat['rank_4star']) . '</td>';
        echo '<td>' . number_format($stat['rank_5star']) . '</td>';
        echo '<td>' . number_format($stat['rank_6star']) . '</td>';
        echo '<td>' . number_format($stat['rank_7star']) . '</td>';
        echo '<td>' . number_format($stat['total_centers']) . '</td>';
        echo '</tr>';
    }

    echo '</table>';
    echo '</div>';

    ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
