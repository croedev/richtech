<?php
// notice.php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/richtech.club/pages/error.log');

session_start();
require_once __DIR__ . '/../includes/config.php';

$conn = db_connect();

// 페이지네이션 설정
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 15; // 한 페이지당 표시할 게시물 수 증가
$offset = ($page - 1) * $perPage;

// 선택된 공지사항 ID (없으면 최신 글)
$selected_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// 현재 공지사항 가져오기
if ($selected_id) {
    // 조회수 증가
    $stmt = $conn->prepare("UPDATE notices SET views = views + 1 WHERE id = ? AND status = 'active'");
    $stmt->bind_param("i", $selected_id);
    $stmt->execute();
    
    // 선택된 공지사항 가져오기
    $stmt = $conn->prepare("
        SELECT id, title, content, author, views, created_at 
        FROM notices 
        WHERE id = ? AND status = 'active'
    ");
    $stmt->bind_param("i", $selected_id);
} else {
    // 최신 공지사항 가져오기
    $stmt = $conn->prepare("
        SELECT id, title, content, author, views, created_at 
        FROM notices 
        WHERE status = 'active' 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
}

$stmt->execute();
$current_notice = $stmt->get_result()->fetch_assoc();

// 공지사항 목록 가져오기
$stmt = $conn->prepare("
    SELECT id, title, author, views, created_at,
           CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 3 DAY) THEN 1 ELSE 0 END as is_new
    FROM notices 
    WHERE status = 'active'
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->bind_param("ii", $perPage, $offset);
$stmt->execute();
$notices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 전체 게시물 수 계산
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM notices WHERE status = 'active'");
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($total / $perPage);

$pageTitle = "공지사항";
include __DIR__ . '/../includes/header.php';
?>




<div class="notice-container mb50">
    <!-- 현재 공지사항 표시 영역 -->
    <?php if ($current_notice): ?>
    <div class="notice-content">
        <div class="notice-header">
            <h1 class="notice-title notosans fw-900"><?php echo htmlspecialchars($current_notice['title']); ?></h1>
            <div class="notice-meta notosans">
                <span><i class="fas fa-user "></i> <?php echo htmlspecialchars($current_notice['author']); ?></span>
                <span><i class="fas fa-clock"></i> <?php echo date('Y.m.d H:i', strtotime($current_notice['created_at'])); ?></span>
                <span><i class="fas fa-eye"></i> <?php echo number_format($current_notice['views']); ?></span>
            </div>
        </div>
        <div class="notice-body">
            <?php echo $current_notice['content']; ?>
        </div>
    </div>
    <?php endif; ?>


 
    <!-- 공지사항 목록 -->
    <div class="notice-list mt30">
        <h2 class="notosans fw-900">공지사항</h2>
           <hr>

        <div class="list-container">
            <?php foreach ($notices as $notice): ?>
            <a href="?id=<?php echo $notice['id']; ?>" class="notice-item <?php echo ($current_notice['id'] ?? '') == $notice['id'] ? 'active' : ''; ?>">
                <div class="item-main">
                    <h3>
                        <?php if ($notice['is_new']): ?>
                        <span class="badge-new">NEW</span>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($notice['title']); ?>
                    </h3>
                    <div class="item-meta">
                        <span><?php echo date('Y.m.d', strtotime($notice['created_at'])); ?></span>
                        <span>조회 <?php echo number_format($notice['views']); ?></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- 페이지네이션 -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
            <a href="?page=1" class="page-link first"><i class="fas fa-angle-double-left"></i></a>
            <a href="?page=<?php echo $page-1; ?>" class="page-link"><i class="fas fa-angle-left"></i></a>
            <?php endif; ?>

            <?php
            $start = max(1, min($page - 2, $totalPages - 4));
            $end = min($start + 4, $totalPages);
            
            for ($i = $start; $i <= $end; $i++):
            ?>
            <a href="?page=<?php echo $i; ?>" 
               class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
            <a href="?page=<?php echo $page+1; ?>" class="page-link"><i class="fas fa-angle-right"></i></a>
            <a href="?page=<?php echo $totalPages; ?>" class="page-link last"><i class="fas fa-angle-double-right"></i></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
:root {
    --gold: #d4af37;
    --gold-light: rgba(212, 175, 55, 0.1);
    --gold-border: rgba(212, 175, 55, 0.3);
    --dark-bg: #1a1a1a;
    --darker-bg: #121212;
    --text-light: #ffffff;
    --text-muted: rgba(255, 255, 255, 0.7);
}

.notice-container {
    max-width: 1200px;
    margin: 2rem auto;
    padding: 0 1rem;
}

.notice-content {
    background: var(--dark-bg);
    border: 1px solid var(--gold-border);
    border-radius: 8px;
    padding: 2rem;
    margin-bottom: 2rem;
}

.notice-header {
    border-bottom: 1px solid var(--gold-border);
    margin-bottom: 2rem;
    padding-bottom: 1rem;
}

.notice-title {
    color: var(--gold);
    font-size: 1.4rem;
    margin-bottom: 1rem;
    font-weight: 500;
}

.notice-meta {
    display: flex;
    gap: 1.5rem;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.notice-meta i {
    color: var(--gold);
    margin-right: 0.5rem;
}

.notice-body {
    color: var(--text-light);
    line-height: 1.8;
    font-size: 0.95rem;
}

.notice-list {
    background: ;
    border: 0px solid var(--gold-border);
    border-radius: 8px;
    padding: 0rem;
}

.notice-list h2 {
    color: var(--gold);
    font-size: 1.2rem;
    margin-bottom: 1.5rem;
    font-weight: 500;
}

.list-container {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.notice-item {
    display: block;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid transparent;
    border-radius: 4px;
    text-decoration: none;
    transition: all 0.3s ease;
}

.notice-item:hover,
.notice-item.active {
    background: var(--gold-light);
    border-color: var(--gold-border);
}

.notice-item h3 {
    color: var(--text-light);
    font-size: 1rem;
    margin-bottom: 0.5rem;
    font-weight: normal;
}

.badge-new {
    background: var(--gold);
    color: var(--darker-bg);
    padding: 0.2rem 0.5rem;
    border-radius: 3px;
    font-size: 0.7rem;
    margin-right: 0.5rem;
    font-weight: 600;
}

.item-meta {
    display: flex;
    gap: 1rem;
    color: var(--text-muted);
    font-size: 0.8rem;
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 2rem;
}

.page-link {
    color: var(--text-muted);
    text-decoration: none;
    padding: 0.5rem 0.8rem;
    border: 1px solid var(--gold-border);
    border-radius: 4px;
    transition: all 0.3s ease;
}

.page-link:hover,
.page-link.active {
    background: var(--gold);
    color: var(--darker-bg);
}

@media (max-width: 768px) {
    .notice-container {
        margin: 1rem auto;
    }

    .notice-content,
    .notice-list {
        padding: 1rem;
    }

    .notice-meta {
        flex-direction: column;
        gap: 0.5rem;
    }
}
</style>


<script>
// 현재 선택된 공지사항이 있을 경우 해당 위치로 부드럽게 스크롤
document.addEventListener('DOMContentLoaded', function() {
    // 현재 URL에서 공지사항 ID 파라미터 확인
    const urlParams = new URLSearchParams(window.location.search);
    const noticeId = urlParams.get('id');
    
    if (noticeId) {
        const noticeContent = document.querySelector('.notice-content');
        if (noticeContent) {
            // 부드러운 스크롤 적용
            window.scrollTo({
                top: noticeContent.offsetTop - 20,
                behavior: 'smooth'
            });
        }
    }
});

// 긴 컨텐츠의 경우 이미지 크기 조정
document.querySelectorAll('.notice-body img').forEach(function(img) {
    img.style.maxWidth = '100%';
    img.style.height = 'auto';
});
</script>

<!-- 추가 반응형 스타일 -->
<style>
/* 글자 크기 미세 조정 */
@media (max-width: 1200px) {
    .notice-title {
        font-size: 1.3rem;
    }
    
    .notice-body {
        font-size: 0.9rem;
    }
}

@media (max-width: 992px) {
    .notice-container {
        max-width: 900px;
    }
}

@media (max-width: 768px) {
    .notice-container {
        max-width: 100%;
        padding: 0 0.8rem;
    }
    
    .notice-content,
    .notice-list {
        padding: 1rem;
    }
    
    .notice-title {
        font-size: 1.2rem;
    }
    
    .notice-item h3 {
        font-size: 0.95rem;
    }
}

/* 프린트 스타일 */
@media print {
    .notice-container {
        margin: 0;
        padding: 0;
    }
    
    .notice-list,
    .pagination {
        display: none;
    }
    
    .notice-content {
        border: none;
        padding: 0;
    }
    
    .notice-body {
        font-size: 12pt;
    }
}

/* 접근성 향상 */
.notice-item:focus {
    outline: 2px solid var(--gold);
    outline-offset: 2px;
}

/* 부드러운 효과 */
.notice-content {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.notice-item {
    position: relative;
    overflow: hidden;
}

.notice-item::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 0;
    height: 2px;
    background: var(--gold);
    transition: width 0.3s ease;
}

.notice-item:hover::after {
    width: 100%;
}
</style>

<?php 
// 마지막으로 데이터베이스 연결 종료
if (isset($conn)) {
    $conn->close();
}

include __DIR__ . '/../includes/footer.php'; 
?>
