<?php
// admin/admin_notice.php
session_start();
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_id'], [1, 2])) {
    header("Location: /login"); exit();
}

$conn = db_connect();
$message = '';

// 페이지네이션 설정
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

$total = $conn->query("SELECT COUNT(*) as count FROM notices")->fetch_assoc()['count'];
$totalPages = ceil($total / $perPage);

// AJAX 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    $notice_id = intval($_POST['notice_id'] ?? 0);
    
    try {
        if ($action === 'add' || $action === 'edit') {
            if (empty($title) || empty($content)) {
                throw new Exception("제목과 내용을 입력하세요.");
            }
            
            if ($action === 'add') {
                $stmt = $conn->prepare("INSERT INTO notices (title, content, author) VALUES (?, ?, ?)");
                $author = $_SESSION['user_name'] ?? 'admin';
                $stmt->bind_param("sss", $title, $content, $author);
            } else {
                $stmt = $conn->prepare("UPDATE notices SET title = ?, content = ? WHERE id = ?");
                $stmt->bind_param("ssi", $title, $content, $notice_id);
            }
            
            if (!$stmt->execute()) {
                throw new Exception($stmt->error);
            }
            
            echo json_encode(['success' => true, 'message' => '저장되었습니다']);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

$notices = $conn->query("SELECT * FROM notices ORDER BY created_at DESC LIMIT $offset, $perPage")->fetch_all(MYSQLI_ASSOC);

$pageTitle = "공지사항 관리";
include __DIR__ . '/admin_header.php';
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>공지사항 관리</title>
    <script src="https://cdn.tiny.cloud/1/7jeq6ekuqb75dcxojwb1ggrlplziwnojpsc89ni4d0u57ic4/tinymce/6/tinymce.min.js"></script>
    <style>
        .container { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .notice-form { margin-bottom: 30px; }
        .notice-list { border-top: 2px solid #333; }
        .notice-item { 
            padding: 15px;
            border-bottom: 1px solid #333;
            cursor: pointer;
        }
        .notice-item:hover { background: #222; }
        .form-group { margin-bottom: 15px; }
        .form-control {
            width: 100%;
            padding: 8px;
            border: 1px solid #444;
            background: #222;
            color: #fff;
        }
        .btn {
            padding: 8px 15px;
            margin: 5px;
            border: none;
            cursor: pointer;
        }
        .btn-primary { background: #d4af37; color: #000; }
        .btn-secondary { background: #444; color: #fff; }
    </style>
</head>
<body>
    <div class="container">
        <h4 class="notosans">공지사항 관리</h4>
        
        <form id="noticeForm" class="notice-form">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="notice_id" value="">
            
            <div class="form-group">
                <label class="my-10">제목</label>
                <input type="text" name="title" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label class="my-10">내용</label>
                <textarea id="content" name="content"></textarea>
            </div>
            
            <div>
                <button type="submit" class="btn btn-primary">저장</button>
                <button type="button" class="btn btn-secondary" onclick="resetForm()">새글</button>
            </div>
        </form>

        <div class="notice-list">
            <?php foreach ($notices as $notice): ?>
            <div class="notice-item" onclick='loadNotice(<?php echo json_encode($notice); ?>)'>
                <h5 class="notosans"><?php echo htmlspecialchars($notice['title']); ?></h5>
                <div>
                    작성일: <?php echo date('Y-m-d H:i', strtotime($notice['created_at'])); ?> |
                    작성자: <?php echo htmlspecialchars($notice['author']); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- 페이지네이션 -->
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?php echo $i; ?>" 
                   class="btn <?php echo $i === $page ? 'btn-primary' : 'btn-secondary'; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>

    <script>
        let editor;
        
        // TinyMCE 초기화
        tinymce.init({
            selector: '#content',
            plugins: 'advlist autolink lists link image charmap preview anchor pagebreak code',
            toolbar: 'undo redo | formatselect | bold italic | alignleft aligncenter alignright | image link | bullist numlist',
            height: 500,
            setup: function(ed) {
                editor = ed;
            },
            init_instance_callback: function(ed) {
                editor = ed;
            }
        });

        // 공지사항 불러오기
        function loadNotice(notice) {
            document.querySelector('[name="action"]').value = 'edit';
            document.querySelector('[name="notice_id"]').value = notice.id;
            document.querySelector('[name="title"]').value = notice.title;
            editor.setContent(notice.content);
        }

        // 폼 초기화
        function resetForm() {
            document.querySelector('[name="action"]').value = 'add';
            document.querySelector('[name="notice_id"]').value = '';
            document.querySelector('[name="title"]').value = '';
            editor.setContent('');
        }

        // 폼 제출
        document.getElementById('noticeForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('content', editor.getContent());
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(result.message);
                    location.reload();
                } else {
                    alert(result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('저장 중 오류가 발생했습니다.');
            }
        });
    </script>
</body>
</html>

<?php include __DIR__ . '/../includes/footer.php'; ?>