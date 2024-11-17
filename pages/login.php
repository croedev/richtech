<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/richtech.club/pages/error.log');

require_once 'includes/config.php';

session_start();
$_SESSION['login_attempt'] = null;
$error = '';

// 리다이렉트 URL과 추가 파라미터 처리
$redirect_url = isset($_GET['redirect']) ? $_GET['redirect'] : '';
$redirect_params = [];

// URL에서 추가 파라미터 수집
foreach ($_GET as $key => $value) {
    if ($key !== 'redirect') {
        $redirect_params[$key] = $value;
    }
}

// XSS 방지를 위한 검증
$redirect_url = filter_var($redirect_url, FILTER_SANITIZE_URL);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login_id = trim($_POST['user_id']);
    $password = trim($_POST['password']);

    if (empty($login_id) || empty($password)) {
        $error = "아이디와 비밀번호를 입력해주세요.";
    } else {
        $conn = db_connect();
        if ($conn->connect_error) {
            $error = "데이터베이스 연결 실패";
        } else {
            try {
                $stmt = $conn->prepare("SELECT id, name, password, email FROM users WHERE login_id = ? AND status = 'active'");
                if (!$stmt) {
                    throw new Exception("쿼리 준비 실패: " . $conn->error);
                }
                
                $stmt->bind_param("s", $login_id);
                if (!$stmt->execute()) {
                    throw new Exception("쿼리 실행 실패: " . $stmt->error);
                }

                $stmt->bind_result($id, $user_name, $hashed_password, $user_email);
                
                if ($stmt->fetch()) {
                    if (password_verify($password, $hashed_password)) {
                        $_SESSION['user_id'] = $id;
                        $_SESSION['user_name'] = $user_name;
                        $_SESSION['user_email'] = $user_email;
                        
                        // 관리자인 경우 항상 관리자 페이지로
                        if ($user_email === 'admin@richtech.club') {
                            header("Location: /admin");
                            exit();
                        }
                        
                        // 리다이렉트 URL이 있으면 해당 페이지로, 없으면 홈으로
                        if (!empty($redirect_url)) {
                            // 추가 파라미터가 있으면 URL에 추가
                            if (!empty($redirect_params)) {
                                $query = http_build_query($redirect_params);
                                header("Location: /{$redirect_url}?{$query}");
                            } else {
                                header("Location: /{$redirect_url}");
                            }
                        } else {
                            header("Location: /");
                        }
                        exit();
                    } else {
                        $error = "비밀번호가 일치하지 않습니다.";
                    }
                } else {
                    $error = "등록되지 않은 회원입니다.";
                }
                
                $stmt->close();
            } catch (Exception $e) {
                error_log("로그인 오류: " . $e->getMessage());
                $error = "로그인 처리 중 오류가 발생했습니다.";
            }
            
            $conn->close();
        }
    }
}

?>

<?php
$pageTitle = '로그인';
include 'includes/header.php';
?>

<style>
        body {
            background-color: #000000;
            color: #d4af37;
            font-family: 'Noto Sans KR', sans-serif;            
        }

        .login-container {
            max-width: 400px;
            width: 90%;
            margin: 0 auto;
            padding: 25px;
            background: rgba(17, 17, 17, 0.95);
            border-radius: 15px;
            border: 1px solid rgba(212, 175, 55, 0.2);
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.1);
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .login-logo {
            text-align: center;
            margin-bottom: 20px;
        }

        .login-logo img {
            width: 160px;
            height: auto;
            margin-bottom: 15px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            color: #d4af37;
            margin-bottom: 6px;
            font-size: 0.9rem;
            font-family: 'Noto Sans KR', sans-serif;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid rgba(212, 175, 55, 0.3);
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.8);
            color: #ffffff;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .form-control:focus {
            border-color: #d4af37;
            outline: none;
            box-shadow: 0 0 0 2px rgba(212, 175, 55, 0.2);
        }

        .btn-login {
            width: 100%;
            padding: 10px;
            background: linear-gradient(135deg, #d4af37, #aa8a2e);
            color: #000000;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1rem;
            margin-top: 8px;
        }

        .btn-login:hover {
            background: linear-gradient(135deg, #aa8a2e, #d4af37);
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.2);
        }

        .links {
            margin-top: 20px;
            text-align: center;
        }

        .links a {
            color: #d4af37;
            text-decoration: none;
            margin: 0 12px;
            font-size: 0.9em;
            transition: color 0.3s ease;
        }

        .links a:hover {
            color: #ffffff;
        }

        .error {
            color: #ff6b6b;
            text-align: center;
            margin: 12px 0;
            padding: 8px;
            border-radius: 5px;
            background: rgba(255, 107, 107, 0.1);
            font-size: 0.9em;
        }

        .title {
            color: #d4af37;
            text-align: center;
            margin-bottom: 20px;
            font-weight: 700;
            font-size: 1.1rem;
            font-family: 'Noto Serif KR', serif;
        }

        ::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        @media (max-width: 768px) {
            .login-container {
                width: 85%;
                padding: 20px;
            }
        }

        @media (max-width: 480px) {
            .login-container {
                width: 95%;
                padding: 15px;
            }
        }
</style>

<div class="login-container mx-20">
    <div class="login-logo">
        <img src="assets/images/logo_rich.png" alt="리치테크">
        <h4 class="title">리치테크 부자클럽</h4>
    </div>
    
    <?php if ($error): ?>
        <p class="error"><?php echo $error; ?></p>
    <?php endif; ?>

    <form method="post" action="" id="login-form">
         <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect_url); ?>">
                 <?php foreach ($redirect_params as $key => $value): ?>
            <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($value); ?>">
        <?php endforeach; ?>
        
        <div class="form-group">
            <label for="user_id" class="form-label">아이디</label>
            <input type="text" id="user_id" name="user_id" 
                   class="form-control" required 
                   placeholder="아이디를 입력하세요">
        </div>
        <div class="form-group">
            <label for="password" class="form-label">비밀번호</label>
            <input type="password" id="password" name="password" 
                   class="form-control" required 
                   placeholder="비밀번호를 입력하세요">
        </div>
        <button type="submit" class="btn-login">로그인</button>
    </form>
    
    <div class="links">
        <a href="/join">회원가입</a>
        <a href="/password_forgot">비밀번호 찾기</a>
    </div>
</div>

<script>
    window.addEventListener('load', function() {
        document.getElementById('login-form').reset();
    });

    if (localStorage.getItem('password')) {
        localStorage.removeItem('password');
    }
    document.getElementById('password').value = '';
</script>

<?php include 'includes/footer.php'; ?>