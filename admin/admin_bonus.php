<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/richtech.club/pages/error.log');

session_start();
require_once __DIR__ . '/../includes/config.php';

// 관리자 권한 체크
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_id'], [1, 2])) {
    header("Location: /login?redirect=admin/admin_bonus.php");
    exit;
}

$conn = db_connect();

// 검색 조건 처리
$bonus_type = $_GET['bonus_type'] ?? '';
$search_name = $_GET['search_name'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// 수정된 수당 총액 계산 쿼리
try {
    $total_stats = $conn->query("
        SELECT 
            (SELECT CAST(COALESCE(SUM(amount), 0) AS DECIMAL(15,2)) FROM bonus_referral) as total_referral,
            (SELECT CAST(COALESCE(SUM(amount), 0) AS DECIMAL(15,2)) FROM bonus_rank) as total_rank,
            (SELECT CAST(COALESCE(SUM(amount), 0) AS DECIMAL(15,2)) FROM bonus_center) as total_center
    ")->fetch_assoc();
} catch (Exception $e) {
    error_log("통계 쿼리 오류: " . $e->getMessage());
    $total_stats = [
        'total_referral' => 0,
        'total_rank' => 0,
        'total_center' => 0
    ];
}

// 페이지네이션 설정
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// 검색 조건 구성
$where_conditions = [];
$params = [];
$types = '';

if ($search_name) {
    $where_conditions[] = "(u.name LIKE ? OR u.login_id LIKE ?)";
    $params[] = "%$search_name%";
    $params[] = "%$search_name%";
    $types .= 'ss';
}

if ($start_date) {
    $where_conditions[] = "DATE(b.created_at) >= ?";
    $params[] = $start_date;
    $types .= 's';
}

if ($end_date) {
    $where_conditions[] = "DATE(b.created_at) <= ?";
    $params[] = $end_date;
    $types .= 's';
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// 수당별 상세 쿼리 준비
try {
    switch($bonus_type) {
        case 'referral':
            $query = "
                SELECT 
                    'referral' as bonus_type,
                    b.*,
                    u.name as user_name,
                    u.login_id,
                    su.name as source_user_name,
                    su.login_id as source_login_id
                FROM bonus_referral b
                JOIN users u ON b.user_id = u.id
                LEFT JOIN users su ON b.source_user_id = su.id
                $where_clause
                ORDER BY b.created_at DESC 
                LIMIT ?, ?
            ";
            break;

        case 'rank':
            $query = "
                SELECT 
                    'rank' as bonus_type,
                    b.*,
                    u.name as user_name,
                    u.login_id
                FROM bonus_rank b
                JOIN users u ON b.user_id = u.id
                $where_clause
                ORDER BY b.created_at DESC 
                LIMIT ?, ?
            ";
            break;

        case 'center':
            $query = "
                SELECT 
                    'center' as bonus_type,
                    b.*,
                    u.name as user_name,
                    u.login_id,
                    o.name as organization_name
                FROM bonus_center b
                JOIN users u ON b.user_id = u.id
                LEFT JOIN organizations o ON b.organization_id = o.id
                $where_clause
                ORDER BY b.created_at DESC 
                LIMIT ?, ?
            ";
            break;

        default:
            // 전체 수당 통합 조회 수정된 쿼리
            $query = "
                SELECT 
                    'referral' as bonus_type,
                    b.created_at,
                    b.amount,
                    u.name as user_name,
                    u.login_id,
                    b.commission_rate,
                    COALESCE(su.name, '') as source_user_name,
                    COALESCE(su.login_id, '') as source_login_id,
                    COALESCE(b.source_amount, 0) as source_amount,
                    COALESCE(b.level, 0) as level,
                    '' as rank,
                    0 as total_company_sales,
                    '' as organization_name,
                    0 as total_sales
                FROM bonus_referral b
                JOIN users u ON b.user_id = u.id
                LEFT JOIN users su ON b.source_user_id = su.id
                $where_clause

                UNION ALL

                SELECT 
                    'rank' as bonus_type,
                    b.created_at,
                    b.amount,
                    u.name as user_name,
                    u.login_id,
                    b.commission_rate,
                    '' as source_user_name,
                    '' as source_login_id,
                    0 as source_amount,
                    0 as level,
                    b.rank,
                    b.total_company_sales,
                    '' as organization_name,
                    0 as total_sales
                FROM bonus_rank b
                JOIN users u ON b.user_id = u.id
                $where_clause

                UNION ALL

                SELECT 
                    'center' as bonus_type,
                    b.created_at,
                    b.amount,
                    u.name as user_name,
                    u.login_id,
                    b.commission_rate,
                    '' as source_user_name,
                    '' as source_login_id,
                    0 as source_amount,
                    0 as level,
                    '' as rank,
                    0 as total_company_sales,
                    o.name as organization_name,
                    b.total_sales
                FROM bonus_center b
                JOIN users u ON b.user_id = u.id
                LEFT JOIN organizations o ON b.organization_id = o.id
                $where_clause

                ORDER BY created_at DESC
                LIMIT ?, ?
            ";
    }

    // 전체 레코드 수 계산을 위한 수정된 카운트 쿼리
    if ($bonus_type) {
        $count_query = preg_replace('/SELECT.*?FROM/s', 'SELECT COUNT(*) as total FROM', $query);
        $count_query = preg_replace('/ORDER BY.*$/i', '', $count_query);
        $count_query = preg_replace('/LIMIT \?, \?/i', '', $count_query);
    } else {
        $count_query = "
            SELECT (
                SELECT COUNT(*) FROM bonus_referral b 
                JOIN users u ON b.user_id = u.id 
                $where_clause
            ) + (
                SELECT COUNT(*) FROM bonus_rank b 
                JOIN users u ON b.user_id = u.id 
                $where_clause
            ) + (
                SELECT COUNT(*) FROM bonus_center b 
                JOIN users u ON b.user_id = u.id 
                $where_clause
            ) as total
        ";
    }

    // 바인딩 파라미터 처리
    if ($where_conditions) {
        if (!$bonus_type) {
            // UNION ALL 쿼리의 경우 WHERE 조건이 3번 반복되므로 파라미터도 3번 반복
            $params = array_merge($params, $params, $params);
            $types = str_repeat($types, 3);
        }
    }

    // LIMIT 파라미터 추가
    $params[] = $offset;
    $params[] = $limit;
    $types .= 'ii';

    // 전체 레코드 수 조회
    $stmt = $conn->prepare($count_query);
    if (!empty($params)) {
        $bind_params = array_merge([$types], $params);
        call_user_func_array([$stmt, 'bind_param'], $bind_params);
    }
    $stmt->execute();
    $total_records = $stmt->get_result()->fetch_assoc()['total'];
    $total_pages = ceil($total_records / $limit);

    // 메인 데이터 조회
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $bind_params = array_merge([$types], $params);
        call_user_func_array([$stmt, 'bind_param'], $bind_params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

} catch (Exception $e) {
    error_log("쿼리 실행 오류: " . $e->getMessage());
    die("데이터 조회 중 오류가 발생했습니다.");
}

require_once __DIR__ . '/admin_header.php';
?>


<!-- CSS 스타일 -->
<style>
/* 다크모드 기본 스타일 */

.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.stat-card {
    background: linear-gradient(145deg, #2d2d2d, #1a1a1a);
    padding: 15px;
    border-radius: 8px;
    border: 1px solid rgba(212, 175, 55, 0.1);
    color: #fff;
}

.stat-card h3 {
    color: #d4af37;
    font-size: 0.9rem;
    margin-bottom: 10px;
}

.stat-value {
    font-size: 1.2rem;
    font-weight: 600;
}



.bonus-container {
    background: #121212;
    padding: 20px;
    color: #e0e0e0;
}

/* 탭 버튼 스타일 */
.bonus-tabs {
    display: flex;
    gap: 1px;
    margin: 20px 0;
    background: #1a1a1a;
    padding: 5px;
    border-radius: 8px;
    position: relative;
    border: 1px solid rgba(212, 175, 55, 0.1);
}

.bonus-tab {
    padding: 10px 20px;
    background: transparent;
    border: none;
    color: #888;
    font-size: 0.9rem;
    cursor: pointer;
    position: relative;
    flex: 1;
    transition: all 0.3s ease;
}

.bonus-tab:hover {
    color: #d4af37;
}

.bonus-tab.active {
    color: #d4af37;
    background: rgba(212, 175, 55, 0.1);
    border-radius: 6px;
}

/* 테이블 스타일 */
.table {
    background: #1a1a1a;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid rgba(212, 175, 55, 0.1);
}

.table thead th {
    background: #252525;
    color: #d4af37;
    font-weight: 500;
    padding: 12px 8px;
    font-size: 0.85rem;
    border-bottom: 1px solid rgba(212, 175, 55, 0.1);
    white-space: nowrap;
}

.table tbody td {
    padding: 8px;
    font-size: 0.8rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    vertical-align: middle;
    background: #121212;
    color: #e0e0e0;
}

.table tbody tr:hover {
    background:#b4b4f;
}

/* 뱃지 스타일 업데이트 */
.badge-bonus {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
    text-align: center;
    min-width: 80px;
}

.badge-all {
    background: rgba(158, 158, 158, 0.1);
    color: #9e9e9e;
    border: 1px solid rgba(158, 158, 158, 0.2);
}

.badge-referral {
    background: rgba(76, 175, 80, 0.1);
    color: #4caf50;
    border: 1px solid rgba(76, 175, 80, 0.2);
}

.badge-rank {
    background: rgba(33, 150, 243, 0.1);
    color: #2196f3;
    border: 1px solid rgba(33, 150, 243, 0.2);
}

.badge-center {
    background: rgba(255, 193, 7, 0.1);
    color: #ffc107;
    border: 1px solid rgba(255, 193, 7, 0.2);
}

/* 검색 폼 스타일 업데이트 */
.search-form {
    background: rgba(45, 45, 45, 0.9);
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid rgba(212, 175, 55, 0.1);
}

.search-form .form-control,
.search-form .form-select {
    background: rgba(26, 26, 26, 0.95);
    border: 1px solid rgba(212, 175, 55, 0.2);
    color: #fff;
    font-size: 0.9rem;
}

.search-form .form-control:focus,
.search-form .form-select:focus {
    border-color: #d4af37;
    box-shadow: none;
}

/* 금액 표시 셀 */
.amount-cell {
    text-align: right;
    font-family: 'Roboto Mono', monospace;
    color: #d4af37;
}

/* 페이지네이션 스타일 업데이트 */
.pagination-dark {
    margin-top: 20px;
}

.pagination-dark .page-link {
    background: #1a1a1a;
    border-color: rgba(212, 175, 55, 0.1);
    color: #888;
    padding: 8px 12px;
    font-size: 0.85rem;
}

.pagination-dark .page-link:hover {
    background: rgba(212, 175, 55, 0.1);
    color: #d4af37;
}

.pagination-dark .page-item.active .page-link {
    background: rgba(212, 175, 55, 0.2);
    border-color: #d4af37;
    color: #d4af37;
}
</style>



<div class="bonus-container">
    <!-- 통계 카드는 유지 -->
<!-- 통계 카드 섹션 -->
<div class="stats-container">
    <div class="stat-card">
        <h3>추천수당 총액</h3>
        <div class="stat-value">$<?php echo number_format($total_stats['total_referral'], 2); ?></div>
    </div>
    <div class="stat-card">
        <h3>직급수당 총액</h3>
        <div class="stat-value">$<?php echo number_format($total_stats['total_rank'], 2); ?></div>
    </div>
    <div class="stat-card">
        <h3>센터수당 총액</h3>
        <div class="stat-value">$<?php echo number_format($total_stats['total_center'], 2); ?></div>
    </div>
    <div class="stat-card">
        <h3>수당 총액</h3>
        <div class="stat-value">$<?php echo number_format(
            $total_stats['total_referral'] + 
            $total_stats['total_rank'] + 
            $total_stats['total_center'], 2); ?></div>
    </div>
</div>

    <!-- 수당 종류 탭 버튼 -->
    <div class="bonus-tabs">
        <button type="button" class="bonus-tab <?php echo empty($bonus_type) ? 'active' : ''; ?>" 
                onclick="location.href='?'">전체 수당</button>
        <button type="button" class="bonus-tab <?php echo $bonus_type === 'referral' ? 'active' : ''; ?>" 
                onclick="location.href='?bonus_type=referral'">추천 수당</button>
        <button type="button" class="bonus-tab <?php echo $bonus_type === 'rank' ? 'active' : ''; ?>" 
                onclick="location.href='?bonus_type=rank'">직급 수당</button>
        <button type="button" class="bonus-tab <?php echo $bonus_type === 'center' ? 'active' : ''; ?>" 
                onclick="location.href='?bonus_type=center'">센터멘토 수당</button>
    </div>



<!-- 검색 폼 -->
    <div class="search-form">
        <form method="GET" class="row g-3 align-items-end">
            <input type="hidden" name="bonus_type" value="<?php echo htmlspecialchars($bonus_type); ?>">
            <div class="col-md-3">
                <label class="form-label">회원명/ID</label>
                <input type="text" name="search_name" class="form-control form-control-sm" 
                       value="<?php echo htmlspecialchars($search_name); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">기간 설정</label>
                <div class="input-group input-group-sm">
                    <input type="date" name="start_date" class="form-control" 
                           value="<?php echo htmlspecialchars($start_date); ?>">
                    <span class="input-group-text">~</span>
                    <input type="date" name="end_date" class="form-control"
                           value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
            </div>
            <div class="col-md-3">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-gray">
                        <i class="fas fa-search"></i> 검색
                    </button>
                    <button type="button" class="btn btn-sm btn-success" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i> Excel
                    </button>
                </div>
            </div>
        </form>
    </div>
    

<!-- 수당 내역 테이블 -->
<div class="table-responsive">
    <table class="table" id="bonusTable">
        <thead>
            <tr>
                <th>지급일시</th>
                <th>수당종류</th>
                <th>회원정보</th>
                <?php if ($bonus_type === 'referral'): ?>
                    <th>발생회원</th>
                    <th>매출금액</th>
                    <th>단계</th>
                <?php elseif ($bonus_type === 'rank'): ?>
                    <th>직급</th>
                    <th>수당유형</th>
                    <th>회사매출</th>
                <?php elseif ($bonus_type === 'center'): ?>
                    <th>센터</th>
                    <th>센터매출</th>
                <?php endif; ?>
                <th>수수료율</th>
                <th>수당금액</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></td>
                <td>
                    <?php
                    $badge_class = '';
                    $bonus_name = '';
                    switch($row['bonus_type'] ?? $bonus_type) {
                        case 'referral':
                            $badge_class = 'badge-referral';
                            $bonus_name = '추천수당';
                            break;
                        case 'rank':
                            $badge_class = 'badge-rank';
                            $bonus_name = '직급수당';
                            break;
                        case 'center':
                            $badge_class = 'badge-center';
                            $bonus_name = '센터수당';
                            break;
                    }
                    ?>
                    <span class="badge-bonus <?php echo $badge_class; ?>">
                        <?php echo $bonus_name; ?>
                    </span>
                </td>
                <td>



<?php echo htmlspecialchars($row['user_name']); ?>
                    <br>
                    <small class="text-muted"><?php echo htmlspecialchars($row['login_id']); ?></small>
                </td>
                <?php if ($bonus_type === 'referral'): ?>
                    <td>
                        <?php echo htmlspecialchars($row['source_user_name']); ?>
                        <br>
                        <small class="text-muted"><?php echo htmlspecialchars($row['source_login_id']); ?></small>
                    </td>
                    <td class="text-end">$<?php echo number_format($row['source_amount'], 2); ?></td>
                    <td class="text-center"><?php echo $row['level']; ?>단계</td>
                <?php elseif ($bonus_type === 'rank'): ?>
                    <td><?php echo htmlspecialchars($row['rank']); ?></td>
                    <td><?php echo htmlspecialchars($row['bonus_type']); ?></td>
                    <td class="text-end">$<?php echo number_format($row['total_company_sales'], 2); ?></td>
                <?php elseif ($bonus_type === 'center'): ?>
                    <td><?php echo htmlspecialchars($row['organization_name']); ?></td>
                    <td class="text-end">$<?php echo number_format($row['total_sales'], 2); ?></td>
                <?php endif; ?>
                <td class="text-end"><?php echo number_format($row['commission_rate'], 2); ?>%</td>
                <td class="text-end">$<?php echo number_format($row['amount'], 2); ?></td>
            </tr>
            <?php endwhile; ?>
            <?php if ($result->num_rows === 0): ?>
            <tr>
                <td colspan="8" class="text-center py-3">검색 결과가 없습니다.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- 페이지네이션 -->
<?php if ($total_pages > 1): ?>
<nav>
    <ul class="pagination">
        <?php if ($page > 1): ?>
            <li class="page-item">
                <a class="page-link" href="?page=1<?php echo $bonus_type ? '&bonus_type='.$bonus_type : ''; ?>
                    <?php echo $search_name ? '&search_name='.urlencode($search_name) : ''; ?>
                    <?php echo $start_date ? '&start_date='.$start_date : ''; ?>
                    <?php echo $end_date ? '&end_date='.$end_date : ''; ?>">
                    <i class="fas fa-angle-double-left"></i>
                </a>
            </li>
        <?php endif; ?>

        <?php
        $start = max(1, $page - 2);
        $end = min($start + 4, $total_pages);
        $start = max(1, $end - 4);

        for ($i = $start; $i <= $end; $i++):
        ?>
            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?>
                    <?php echo $bonus_type ? '&bonus_type='.$bonus_type : ''; ?>
                    <?php echo $search_name ? '&search_name='.urlencode($search_name) : ''; ?>
                    <?php echo $start_date ? '&start_date='.$start_date : ''; ?>
                    <?php echo $end_date ? '&end_date='.$end_date : ''; ?>">
                    <?php echo $i; ?>
                </a>
            </li>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?php echo $total_pages; ?>
                    <?php echo $bonus_type ? '&bonus_type='.$bonus_type : ''; ?>
                    <?php echo $search_name ? '&search_name='.urlencode($search_name) : ''; ?>
                    <?php echo $start_date ? '&start_date='.$start_date : ''; ?>
                    <?php echo $end_date ? '&end_date='.$end_date : ''; ?>">
                    <i class="fas fa-angle-double-right"></i>
                </a>
            </li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<script>
// Excel 내보내기 함수
function exportToExcel() {
    const table = document.getElementById('bonusTable');
    const wb = XLSX.utils.table_to_book(table, {
        sheet: "수당내역",
        raw: true,
        dateNF: 'yyyy-mm-dd hh:mm'
    });
    
    // 파일명 생성
    let filename = '수당내역_';
    if ('<?php echo $bonus_type; ?>') {
        switch('<?php echo $bonus_type; ?>') {
            case 'referral': filename += '추천수당_'; break;
            case 'rank': filename += '직급수당_'; break;
            case 'center': filename += '센터수당_'; break;
        }
    }
    filename += new Date().toISOString().split('T')[0] + '.xlsx';
    
    XLSX.writeFile(wb, filename);
}

// 날짜 입력 필드 자동 제한
document.addEventListener('DOMContentLoaded', function() {
    const startDate = document.querySelector('input[name="start_date"]');
    const endDate = document.querySelector('input[name="end_date"]');
    
    if (startDate && endDate) {
        startDate.addEventListener('change', function() {
            endDate.min = this.value;
        });
        
        endDate.addEventListener('change', function() {
            startDate.max = this.value;
        });
    }
});

// 수당 종류 변경 시 자동 submit
document.querySelector('select[name="bonus_type"]').addEventListener('change', function() {
    this.form.submit();
});

// 테이블 정렬 기능
document.querySelectorAll('th[data-sort]').forEach(header => {
    header.addEventListener('click', function() {
        const table = this.closest('table');
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const index = Array.from(this.parentNode.children).indexOf(this);
        const ascending = this.dataset.sortDir === 'asc';
        
        rows.sort((a, b) => {
            const aValue = a.children[index].textContent.trim();
            const bValue = b.children[index].textContent.trim();
            
            if (this.dataset.sortType === 'number') {
                return ascending ? 
                    parseFloat(aValue) - parseFloat(bValue) : 
                    parseFloat(bValue) - parseFloat(aValue);
            }
            
            return ascending ? 
                aValue.localeCompare(bValue) : 
                bValue.localeCompare(aValue);
        });
        
        tbody.innerHTML = '';
        rows.forEach(row => tbody.appendChild(row));
        
        this.dataset.sortDir = ascending ? 'desc' : 'asc';
    });
});

// 필터링 기능
function filterTable() {
    const searchText = document.getElementById('tableSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#bonusTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchText) ? '' : 'none';
    });
}
</script>

<!-- Excel 내보내기용 라이브러리 -->
<script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>

<?php
$conn->close();
include __DIR__ . '/../includes/footer.php'; 
?>