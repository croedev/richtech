<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/richtech.club/pages/error.log');

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/admin_functions.php';

// 관리자 권한 체크
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_id'], [1, 2])) {
    header("Location: /login?redirect=admin/admin_members.php");
    exit;
}

$conn = db_connect();

// CSRF 토큰 생성
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 검색 조건 처리
$search_conditions = [];
$params = [];
$param_types = '';

// 고급 검색 필터 처리
if (!empty($_GET['search_type']) && !empty($_GET['search_value'])) {
    $searchType = $_GET['search_type'];
    $searchValue = $_GET['search_value'];
    
    switch($searchType) {
        case 'id':
            $search_conditions[] = "u.login_id LIKE ?";
            $params[] = "%$searchValue%";
            $param_types .= 's';
            break;
        case 'name':
            $search_conditions[] = "u.name LIKE ?";
            $params[] = "%$searchValue%";
            $param_types .= 's';
            break;
        case 'email':
            $search_conditions[] = "u.email LIKE ?";
            $params[] = "%$searchValue%";
            $param_types .= 's';
            break;
        case 'phone':
            $search_conditions[] = "u.phone LIKE ?";
            $params[] = "%$searchValue%";
            $param_types .= 's';
            break;
        case 'bsc_address':
            $search_conditions[] = "u.bsc_address LIKE ?";
            $params[] = "%$searchValue%";
            $param_types .= 's';
            break;
    }
}

if (!empty($_GET['rank'])) {
    $search_conditions[] = "u.rank = ?";
    $params[] = $_GET['rank'];
    $param_types .= 's';
}

if (!empty($_GET['status'])) {
    $search_conditions[] = "u.status = ?";
    $params[] = $_GET['status'];
    $param_types .= 's';
}

if (!empty($_GET['date_from'])) {
    $search_conditions[] = "DATE(u.created_at) >= ?";
    $params[] = $_GET['date_from'];
    $param_types .= 's';
}

if (!empty($_GET['date_to'])) {
    $search_conditions[] = "DATE(u.created_at) <= ?";
    $params[] = $_GET['date_to'];
    $param_types .= 's';
}

if (!empty($_GET['min_amount'])) {
    $search_conditions[] = "u.myAmount >= ?";
    $params[] = floatval($_GET['min_amount']);
    $param_types .= 'd';
}

if (!empty($_GET['organization'])) {
    $search_conditions[] = "u.organization = ?";
    $params[] = $_GET['organization'];
    $param_types .= 's';
}

// WHERE 절 구성
$where_clause = !empty($search_conditions) ? 'WHERE ' . implode(' AND ', $search_conditions) : '';

// 페이지네이션 설정
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
$offset = ($page - 1) * $limit;

// 전체 레코드 수 조회
$count_query = "
    SELECT COUNT(*) as total 
    FROM users u 
    LEFT JOIN users ref ON u.referred_by = ref.id
    LEFT JOIN users sponsor ON u.sponsored_by = sponsor.id
    $where_clause
";

$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$total_records = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// 회원 통계 데이터 조회
$stats = $conn->query("
    SELECT 
        COUNT(*) as total_members,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_members,
        COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_members,
        SUM(myAmount) as total_amount,
        SUM(point) as total_points,
        SUM(commission_total) as total_commission
    FROM users
")->fetch_assoc();

// 회원 목록 조회
$query = "
    SELECT 
        u.*,
        ref.name as referrer_name,
        ref.login_id as referrer_login_id,
        sponsor.name as sponsor_name,
        sponsor.login_id as sponsor_login_id,
        (SELECT COUNT(*) FROM users WHERE referred_by = u.id) as direct_referrals
    FROM users u
    LEFT JOIN users ref ON u.referred_by = ref.id
    LEFT JOIN users sponsor ON u.sponsored_by = sponsor.id
    $where_clause
    ORDER BY u.created_at DESC
    LIMIT ?, ?
";

$stmt = $conn->prepare($query);
$params[] = $offset;
$params[] = $limit;
$param_types .= 'ii';
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$members = $stmt->get_result();

// 소속 센터 목록 조회
$organizations = $conn->query("SELECT DISTINCT organization FROM users WHERE organization IS NOT NULL ORDER BY organization");

require_once __DIR__ . '/admin_header.php';
?>

<!-- 통계 카드 섹션 -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="stat-card">
            <h5>전체 회원</h5>
            <div class="stat-value"><?php echo number_format($stats['total_members']); ?>명</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="stat-card">
            <h5>활성 회원</h5>
            <div class="stat-value"><?php echo number_format($stats['active_members']); ?>명</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="stat-card">
            <h5>구매 총액</h5>
            <div class="stat-value">$<?php echo number_format($stats['total_amount'], 2); ?></div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="stat-card">
            <h5>포인트 총액</h5>
            <div class="stat-value"><?php echo number_format($stats['total_points'], 4); ?></div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="stat-card">
            <h5>수당 총액</h5>
            <div class="stat-value">$<?php echo number_format($stats['total_commission'], 2); ?></div>
        </div>
    </div>
</div>

<!-- 검색 필터 -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-filter"></i> 검색 필터</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-md-2">
                <select name="search_type" class="form-select form-select-sm">
                    <option value="">검색 유형</option>
                    <option value="id" <?php echo $_GET['search_type'] == 'id' ? 'selected' : ''; ?>>아이디</option>
                    <option value="name" <?php echo $_GET['search_type'] == 'name' ? 'selected' : ''; ?>>이름</option>
                    <option value="email" <?php echo $_GET['search_type'] == 'email' ? 'selected' : ''; ?>>이메일</option>
                    <option value="phone" <?php echo $_GET['search_type'] == 'phone' ? 'selected' : ''; ?>>전화번호</option>
                    <option value="bsc_address" <?php echo $_GET['search_type'] == 'bsc_address' ? 'selected' : ''; ?>>BSC 주소</option>
                </select>
            </div>
            <div class="col-md-3">
                <input type="text" name="search_value" class="form-control form-control-sm" 
                       value="<?php echo htmlspecialchars($_GET['search_value'] ?? ''); ?>" 
                       placeholder="검색어 입력">
            </div>
            <div class="col-md-2">
                <select name="rank" class="form-select form-select-sm">
                    <option value="">전체 직급</option>
                    <?php
                    $ranks = ['회원', '1스타', '2스타', '3스타', '4스타', '5스타', '6스타', '7스타'];
                    foreach ($ranks as $rank) {
                        echo '<option value="' . $rank . '"' . 
                             (isset($_GET['rank']) && $_GET['rank'] === $rank ? ' selected' : '') . 
                             '>' . $rank . '</option>';
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="organization" class="form-select form-select-sm">
                    <option value="">전체 소속</option>
                    <?php while ($org = $organizations->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($org['organization']); ?>"
                                <?php echo isset($_GET['organization']) && $_GET['organization'] === $org['organization'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($org['organization']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <input type="date" name="date_from" class="form-control" 
                           value="<?php echo htmlspecialchars($_GET['date_from'] ?? ''); ?>">
                    <span class="input-group-text">~</span>
                    <input type="date" name="date_to" class="form-control"
                           value="<?php echo htmlspecialchars($_GET['date_to'] ?? ''); ?>">
                </div>
            </div>
            <div class="col-md-12">
                <div class="btn-group">
                    <button type="submit" class="btn btn-sm btn-gold">
                        <i class="fas fa-search"></i> 검색
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="location.href='?'">
                        <i class="fas fa-redo"></i> 초기화
                    </button>
                    <button type="button" class="btn btn-sm btn-success" onclick="AdminMemberManager.exportToExcel()">
                        <i class="fas fa-file-excel"></i> Excel
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- 회원 목록 테이블 -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-users"></i> 회원 목록</h5>
        <div>
            <span class="text-muted">총 <?php echo number_format($total_records); ?>명</span>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover" id="membersTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>회원정보</th>
                        <th>연락처</th>
                        <th>추천/후원</th>
                        <th>실적정보</th>
                        <th>BSC주소</th>
                        <th>상태/가입일</th>
                        <th class="no-sort">관리</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($member = $members->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $member['id']; ?></td>
                        <td>
                            <div class="d-flex flex-column">
                                <strong><?php echo htmlspecialchars($member['name']); ?></strong>
                                <small class="text-muted"><?php echo htmlspecialchars($member['login_id']); ?></small>
                                <span class="badge bg-info"><?php echo htmlspecialchars($member['rank']); ?></span>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex flex-column">
                                <small><?php echo htmlspecialchars($member['email']); ?></small>
                                <small><?php echo htmlspecialchars($member['phone']); ?></small>
                                <small class="text-muted"><?php echo htmlspecialchars($member['country']); ?></small>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex flex-column">
                                <small>추천: <?php echo $member['referrer_login_id'] ? htmlspecialchars($member['referrer_login_id']) : '-'; ?></small>
                                <small>후원: <?php echo $member['sponsor_login_id'] ? htmlspecialchars($member['sponsor_login_id']) : '-'; ?></small>
                                <small>직추천: <?php echo number_format($member['direct_referrals']); ?>명</small>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex flex-column">
                                <small>구매: $<?php echo number_format($member['myAmount'], 2); ?></small>
                                <small>수당: $<?php echo number_format($member['commission_total'], 2); ?></small>
                                <small>포인트: <?php echo number_format($member['point'], 4); ?></small>
                            </div>
                        </td>
                        <td>
                            
                        Admin Members Management Page (Continued)

<div class="d-flex flex-column">
                                <?php if ($member['bsc_address']): ?>
                                    <small class="text-truncate" style="max-width: 150px;" title="<?php echo htmlspecialchars($member['bsc_address']); ?>">
                                        <?php echo substr($member['bsc_address'], 0, 10) . '...' . substr($member['bsc_address'], -8); ?>
                                    </small>
                                    <button class="btn btn-xs btn-outline-secondary mt-1" 
                                            onclick="AdminMemberManager.checkBscBalance('<?php echo $member['bsc_address']; ?>')">
                                        잔액확인
                                    </button>
                                <?php else: ?>
                                    <small class="text-muted">미등록</small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex flex-column">
                                <span class="badge <?php echo $member['status'] === 'active' ? 'badge-success' : 'badge-warning'; ?>">
                                    <?php echo $member['status'] === 'active' ? '활성' : '비활성'; ?>
                                </span>
                                <small class="text-muted">
                                    <?php echo date('Y-m-d', strtotime($member['created_at'])); ?>
                                </small>
                            </div>
                        </td>
                        <td>
                            <div class="btn-group-vertical btn-group-sm">
                                <button type="button" class="btn btn-info btn-xs mb-1"
                                        onclick="location.href='member_edit.php?id=<?php echo $member['id']; ?>'">
                                    <i class="fas fa-edit"></i> 수정
                                </button>
                                <button type="button" class="btn btn-warning btn-xs mb-1 status-toggle"
                                        data-user-id="<?php echo $member['id']; ?>"
                                        data-status="<?php echo $member['status']; ?>">
                                    <i class="fas fa-toggle-on"></i> 상태변경
                                </button>
                                <?php if ($member['status'] === 'inactive'): ?>
                                <button type="button" class="btn btn-danger btn-xs"
                                        onclick="AdminMemberManager.deleteMember(<?php echo $member['id']; ?>)">
                                    <i class="fas fa-trash"></i> 삭제
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer">
        <?php if ($total_pages > 1): ?>
        <nav>
            <ul class="pagination justify-content-center mb-0">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=1<?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>">
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
                        <a class="page-link" href="?page=<?php echo $i . (isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''); ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $total_pages . (isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''); ?>">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<!-- BSC 잔액 확인 모달 -->
<div class="modal fade" id="bscBalanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">BSC 잔액 정보</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-column">
                    <p class="mb-2">주소: <span id="bscAddress" class="text-break"></span></p>
                    <p class="mb-0">잔액: <span id="bscBalance"></span> BNB</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">닫기</button>
                <a href="#" id="bscScanLink" target="_blank" class="btn btn-sm btn-primary">
                    BSCScan에서 보기
                </a>
            </div>
        </div>
    </div>
</div>

<!-- 사용자 정의 알림 컨테이너 -->
<div id="notificationContainer" style="position: fixed; top: 20px; right: 20px; z-index: 9999;"></div>

<script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // DataTable 초기화
    const table = $('#membersTable').DataTable({
        pageLength: 25,
        ordering: true,
        searching: true,
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/ko.json'
        },
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip'
    });

    // 검색 입력 필드에 이벤트 리스너 추가
    $('.dataTables_filter input').on('keyup', function() {
        table.search(this.value).draw();
    });

    // 페이지 길이 변경 시 이벤트
    $('.dataTables_length select').on('change', function() {
        table.page.len(this.value).draw();
    });
});

// 알림 표시 함수
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show`;
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.getElementById('notificationContainer').appendChild(notification);
    
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}
</script>

<?php
$conn->close();
include __DIR__ . '/admin_footer.php';
?>






<style>
        /* 관리자 페이지 공통 스타일 */
        :root {
            --primary-gold: #d4af37;
            --primary-dark: #1a1a1a;
            --secondary-dark: #2d2d2d;
            --text-primary: #ffffff;
            --text-secondary: rgba(255, 255, 255, 0.7);
            --border-color: rgba(212, 175, 55, 0.2);
        }

        body {
            background-color: #121212;
            color: var(--text-primary);
            font-family: 'Noto Sans KR', sans-serif;
        }

        /* 카드 스타일 */
        .card {
            background: rgba(26, 26, 26, 0.95);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .card-header {
            background: rgba(45, 45, 45, 0.9);
            border-bottom: 1px solid var(--border-color);
            padding: 0.8rem 1rem;
            font-size: 0.9rem;
            color: var(--primary-gold);
        }

        .card-body {
            padding: 1rem;
        }

        /* 테이블 스타일 */
        .table {
            width: 100%;
            margin-bottom: 0;
            color: var(--text-primary);
            font-size: 0.8rem;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table th {
            background: rgba(45, 45, 45, 0.9);
            color: var(--primary-gold);
            font-weight: 500;
            padding: 0.5rem;
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap;
        }

        .table td {
            padding: 0.4rem 0.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background: rgba(212, 175, 55, 0.05);
        }

        /* 폼 컨트롤 */
        .form-control {
            background-color: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            font-size: 0.85rem;
            padding: 0.4rem 0.8rem;
        }

        .form-control:focus {
            background-color: rgba(0, 0, 0, 0.3);
            border-color: var(--primary-gold);
            color: var(--text-primary);
            box-shadow: none;
        }

        /* 버튼 스타일 */
        .btn {
            font-size: 0.8rem;
            padding: 0.3rem 0.8rem;
            border-radius: 4px;
        }

        .btn-gold {
            background: linear-gradient(135deg, var(--primary-gold) 0%, #f2d06b 100%);
            border: none;
            color: #000;
        }

        .btn-gold:hover {
            background: linear-gradient(135deg, #f2d06b 0%, var(--primary-gold) 100%);
            transform: translateY(-1px);
        }

        /* 상태 배지 */
        .badge {
            padding: 0.3em 0.6em;
            font-size: 0.75rem;
            font-weight: 500;
            border-radius: 12px;
        }

        .badge-success {
            background: rgba(76, 175, 80, 0.1);
            color: #4caf50;
            border: 1px solid rgba(76, 175, 80, 0.2);
        }

        .badge-warning {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
            border: 1px solid rgba(255, 193, 7, 0.2);
        }

        /* 페이지네이션 */
        .pagination {
            margin-top: 1rem;
            justify-content: center;
        }

        .page-link {
            background: rgba(26, 26, 26, 0.95);
            border: 1px solid var(--border-color);
            color: var(--primary-gold);
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
        }

        .page-link:hover {
            background: rgba(212, 175, 55, 0.1);
            color: var(--primary-gold);
        }

        .page-item.active .page-link {
            background: var(--primary-gold);
            border-color: var(--primary-gold);
            color: #000;
        }

        /* 검색 필터 */
        .filter-section {
            background: rgba(45, 45, 45, 0.9);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .filter-section .form-group {
            margin-bottom: 0.5rem;
        }

        /* 입력 그룹 */
        .input-group-text {
            background: rgba(45, 45, 45, 0.9);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
        }

        /* 모달 스타일 */
        .modal-content {
            background: var(--primary-dark);
            border: 1px solid var(--border-color);
        }

        .modal-header {
            border-bottom: 1px solid var(--border-color);
            padding: 1rem;
        }

        .modal-footer {
            border-top: 1px solid var(--border-color);
            padding: 1rem;
        }

        /* 유틸리티 클래스 */
        .text-gold {
            color: var(--primary-gold) !important;
        }

        .border-gold {
            border-color: var(--border-color) !important;
        }

        .bg-dark-custom {
            background: var(--primary-dark) !important;
        }

        /* 반응형 조정 */
        @media (max-width: 768px) {
            .table-responsive {
                font-size: 0.75rem;
            }
            
            .btn {
                font-size: 0.75rem;
                padding: 0.25rem 0.6rem;
            }
        }
</style>

<script>
        // 회원관리 관련 JavaScript 함수들
        const AdminMemberManager = {
            // 상태 업데이트 함수
            updateStatus: async function(userId, currentStatus) {
                const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
                
                try {
                    const response = await fetch('/admin/update_status', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ userId, status: newStatus })
                    });
                    
                    const data = await response.json();
                    if (data.success) {
                        showNotification('회원 상태가 성공적으로 변경되었습니다.', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        throw new Error(data.message);
                    }
                } catch (error) {
                    showNotification(error.message || '상태 변경 중 오류가 발생했습니다.', 'error');
                }
            },

            // BSC 주소 검증 함수
            validateBscAddress: function(address) {
                return /^0x[0-9a-fA-F]{40}$/.test(address);
            },

            // BSC 잔액 조회 함수
            checkBscBalance: async function(address) {
                try {
                    const response = await fetch(`https://api.bscscan.com/api`, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                        },
                        params: {
                            module: 'account',
                            action: 'balance',
                            address: address,
                            apikey: BSC_API_KEY
                        }
                    });

                    const data = await response.json();
                    return data.status === '1' ? data.result : null;
                } catch (error) {
                    console.error('BSC 잔액 조회 실패:', error);
                    return null;
                }
            },

            // 회원 수정 데이터 검증
            validateMemberData: function(formData) {
                const errors = [];
                
                // 이메일 검증
                if (formData.get('email') && !this.validateEmail(formData.get('email'))) {
                    errors.push('유효하지 않은 이메일 주소입니다.');
                }

                // BSC 주소 검증
                if (formData.get('bsc_address') && !this.validateBscAddress(formData.get('bsc_address'))) {
                    errors.push('유효하지 않은 BSC 주소입니다.');
                }

                // 전화번호 검증
                if (formData.get('phone') && !this.validatePhone(formData.get('phone'))) {
                    errors.push('유효하지 않은 전화번호 형식입니다.');
                }

                return errors;
            },

            // 회원 삭제 처리
            deleteMember: async function(userId) {
                if (!confirm('정말 이 회원을 삭제하시겠습니까? 이 작업은 되돌릴 수 없습니다.')) {
                    return;
                }

                try {
                    const response = await fetch('/admin/delete_member', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ userId })
                    });

                    const data = await response.json();
                    if (data.success) {
                        showNotification('회원이 성공적으로 삭제되었습니다.', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        throw new Error(data.message);
                    }
                } catch (error) {
                    showNotification(error.message || '회원 삭제 중 오류가 발생했습니다.', 'error');
                }
            },

            // 실시간 검증 함수들
            validateEmail: function(email) {
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
            },

            validatePhone: function(phone) {
                return /^[0-9-+()]{10,}$/.test(phone);
            },

            // Excel 내보내기
            exportToExcel: function() {
                const table = document.querySelector('table');
                const wb = XLSX.utils.table_to_book(table, {
                    sheet: "Members",
                    raw: true,
                    dateNF: 'yyyy-mm-dd'
                });
                
                XLSX.writeFile(wb, `회원목록_${new Date().toISOString().split('T')[0]}.xlsx`);
            },

            // 데이터 테이블 초기화
            initializeDataTable: function() {
                return new DataTable('#membersTable', {
                    pageLength: 25,
                    order: [[0, 'desc']],
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/ko.json'
                    },
                    columnDefs: [
                        { targets: 'no-sort', orderable: false }
                    ],
                    drawCallback: function() {
                        this.api().columns().every(function() {
                            const column = this;
                            const header = $(column.header());
                            
                            if (header.hasClass('searchable')) {
                                const input = $('<input type="text" class="form-control form-control-sm mt-1" placeholder="검색...">')
                                    .appendTo(header)
                                    .on('keyup change', function() {
                                        if (column.search() !== this.value) {
                                            column.search(this.value).draw();
                                        }
                                    });
                            }
                        });
                    }
                });
            },

            // 알림 표시
            showNotification: function(message, type = 'info') {
                const notification = document.createElement('div');
                notification.className = `notification notification-${type}`;
                notification.textContent = message;
                
                document.body.appendChild(notification);
                setTimeout(() => notification.remove(), 3000);
            },

            // 초기화 함수
            init: function() {
                // 데이터테이블 초기화
                const table = this.initializeDataTable();

                // 이벤트 리스너 등록
                document.querySelectorAll('.status-toggle').forEach(btn => {
                    btn.addEventListener('click', e => {
                        const userId = e.target.dataset.userId;
                        const currentStatus = e.target.dataset.status;
                        this.updateStatus(userId, currentStatus);
                    });
                });

                // BSC 주소 유효성 검사 이벤트
                document.querySelectorAll('.bsc-address').forEach(input => {
                    input.addEventListener('blur', e => {
                        const address = e.target.value;
                        if (address && !this.validateBscAddress(address)) {
                            this.showNotification('유효하지 않은 BSC 주소입니다.', 'error');
                        }
                    });
                });

                // 실시간 검색 기능
                document.querySelector('#searchInput').addEventListener('input', e => {
                    table.search(e.target.value).draw();
                });

                // Excel 내보내기 버튼
                document.querySelector('#exportExcel').addEventListener('click', () => {
                    this.exportToExcel();
                });
            }
        };

        // DOM 로드 완료 시 초기화
        document.addEventListener('DOMContentLoaded', () => {
            AdminMemberManager.init();
        });

</script>
