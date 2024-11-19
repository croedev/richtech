<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/richtech.club/pages/error.log');

// 필수 설정 및 PHPMailer 포함
require_once __DIR__ . '/../includes/config.php';

// PHPMailer 클래스 파일들 직접 include
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

$pageTitle = '비밀번호 재설정';
date_default_timezone_set('Asia/Seoul');

// 메시지 및 오류 변수 초기화
$message = '';
$error = '';

// 폼이 제출되었을 때 처리
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
   $login_id = trim($_POST['login_id'] ?? ''); // 아이디
   $email = trim($_POST['email'] ?? ''); // 이메일

   if (empty($login_id) || empty($email)) {
       $error = "아이디와 이메일을 모두 입력하세요.";
   } else {
       $conn = db_connect();
       if ($conn->connect_error) {
           $error = "데이터베이스 연결 실패: " . $conn->connect_error;
       } else {
           try {
               // 아이디와 이메일로 사용자 검색
               $stmt = $conn->prepare("SELECT id, login_id, email FROM users WHERE login_id = ? AND email = ?");
               $stmt->bind_param("ss", $login_id, $email);
               $stmt->execute();
               $stmt->store_result();
               $stmt->bind_result($userId, $userLoginId, $userEmail);

               if ($stmt->fetch()) {
                   // 토큰 생성
                   $token = bin2hex(random_bytes(50));
                   $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                   // 토큰 저장
                   $updateStmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
                   $updateStmt->bind_param("ssi", $token, $expires, $userId);
                   
                   if ($updateStmt->execute()) {
                       $reset_link = SITE_URL . "/password_reset?token=" . $token;

                       // 이메일 발송
                       try {
                           $mail = new PHPMailer(true);
                           
                           // SMTP 디버그 모드 설정
                           $mail->SMTPDebug = SMTP::DEBUG_SERVER;
                           $mail->Debugoutput = function($str, $level) {
                               error_log("PHPMailer debug: $str");
                           };

                           $mail->isSMTP();
                           $mail->Host = 'mail.richtech.club';
                           $mail->SMTPAuth = true;
                           $mail->Username = 'info@richtech.club';
                           $mail->Password = 'lidya2016$';
                           $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                           $mail->Port = 465;
                           $mail->CharSet = 'UTF-8';

                           $mail->setFrom('info@richtech.club', '리치테크 클럽');
                           $mail->addAddress($userEmail);
                           $mail->isHTML(true);
                           $mail->Subject = '[리치테크 클럽] 비밀번호 재설정';
                           $mail->Body = "
                               안녕하세요.<br><br>
                               비밀번호 재설정을 위한 링크입니다.<br>
                               아래 링크를 클릭하여 새로운 비밀번호를 설정해주세요.<br><br>
                               <a href='{$reset_link}'>{$reset_link}</a><br><br>
                               이 링크는 1시간 동안만 유효합니다.<br>
                               리치테크 클럽 고객지원팀
                           ";

                           $mail->send();
                           $message = "비밀번호 재설정 링크를 이메일로 발송했습니다.";
                           error_log("Password reset email sent successfully to $userEmail");
                       } catch (Exception $e) {
                           error_log("Email sending failed: " . $mail->ErrorInfo);
                           $error = "이메일 발송 실패: " . $mail->ErrorInfo;
                       }
                   } else {
                       error_log("Token update failed: " . $updateStmt->error);
                       $error = "토큰 업데이트 실패";
                   }
                   $updateStmt->close();
               } else {
                   $error = "입력하신 아이디와 이메일이 일치하지 않습니다.";
               }
               $stmt->close();
           } catch (Exception $e) {
               error_log("General error: " . $e->getMessage());
               $error = "처리 중 오류가 발생했습니다.";
           } finally {
               $conn->close();
           }
       }
   }
}

include __DIR__ . '/../includes/header.php';
?>

<style>
   body {
       background-color: #000;
       color: #d4af37;
       font-family: 'Noto serif KR', serif;
   }
   .forgot-password-container {
       max-width: 400px;
       margin: 50px auto;
       padding: 20px;
       background: rgba(17, 17, 17, 0.95);
       border-radius: 15px;
       border: 1px solid rgba(212, 175, 55, 0.2);
       box-shadow: 0 4px 15px rgba(212, 175, 55, 0.1);
   }
   .form-group {
       margin-bottom: 20px;
       text-align: left;
   }
   .form-label {
       display: block;
       color: #d4af37;
       margin-bottom: 8px;
       font-size: 0.9rem;
   }
   .form-control {
       width: 100%;
       padding: 12px;
       border: 1px solid rgba(212, 175, 55, 0.3);
       background-color: rgba(0, 0, 0, 0.8);
       color: #fff;
       border-radius: 8px;
   }
   .btn-gold {
       background: linear-gradient(to right, #d4af37, #f2d06b);
       border: none;
       color: #000;
       padding: 12px 20px;
       border-radius: 8px;
       cursor: pointer;
       width: 100%;
       font-weight: bold;
       margin-top: 20px;
   }
   .error {
       color: #ff6b6b;
       text-align: center;
       margin: 10px 0;
       padding: 10px;
       background: rgba(255, 107, 107, 0.1);
       border-radius: 5px;
   }
   .message {
       color: #4cd137;
       text-align: center;
       margin: 10px 0;
       padding: 10px;
       background: rgba(76, 209, 55, 0.1);
       border-radius: 5px;
   }
   .title {
       text-align: center;
       margin-bottom: 30px;
   }
   .info-text {
       font-size: 0.85rem;
       color: #888;
       margin-top: 5px;
   }
</style>

<div class="forgot-password-container">
   <div class="title">
       <img src="assets/images/goldkey.png" width="150" alt="로고">
       <h3 class="text-yellow9 mt-3">비밀번호 재설정</h3>
   </div>

   <?php if ($message): ?>
       <div class="message">
           <p><?php echo $message; ?></p>
           <p class="info-text">이메일을 확인하여 비밀번호를 재설정해주세요.</p>
           <a href="/" class="btn-gold mt-3">홈으로</a>
       </div>
   <?php else: ?>
       <form method="post" action="">
           <div class="form-group">
               <label for="login_id" class="form-label">아이디</label>
               <input type="text" id="login_id" name="login_id" class="form-control" 
                      required placeholder="가입시 등록한 아이디">
           </div>
           <div class="form-group">
               <label for="email" class="form-label">이메일</label>
               <input type="email" id="email" name="email" class="form-control" 
                      required placeholder="가입시 등록한 이메일">
           </div>
           <p class="info-text">* 입력하신 이메일로 비밀번호 재설정 링크가 발송됩니다.</p>
           <button type="submit" class="btn-gold">비밀번호 재설정 링크 받기</button>
       </form>
       <?php if ($error): ?>
           <p class="error"><?php echo $error; ?></p>
       <?php endif; ?>
   <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>