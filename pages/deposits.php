<?php
// deposits_new.php
// BSCscan API를 통한 실시간 입금 확인 및 포인트 자동 지급 시스템

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/richtech.club/pages/error.log');

session_start();
require_once __DIR__ . '/../includes/config.php';

// 로그인 체크 
if (!isset($_SESSION['user_id'])) {
   header("Location: login?redirect=deposits_new");
   exit();
}

$conn = db_connect();
$user_id = $_SESSION['user_id'];

/**
* BSC 트랜잭션 검증 함수
* @param string $transaction_hash 트랜잭션 해시값
* @param mysqli $conn DB 연결 객체
* @return array 검증 결과 및 트랜잭션 정보
*/
function verify_deposit($transaction_hash, $conn) {
   try {
       // BSCscan API 호출 URL 구성
       $url = sprintf(
           'https://api.bscscan.com/api?module=account&action=tokentx&contractaddress=%s&address=%s&page=1&offset=100&sort=desc&apikey=%s',
           USDT_CONTRACT_ADDRESS, 
           COMPANY_ADDRESS,
           BSC_API_KEY
       );

       // API 응답 받기
       $response = file_get_contents($url);
       if (!$response) {
           throw new Exception('BSC API 응답 실패');
       }

       $data = json_decode($response, true);
       if (!isset($data['result'])) {
           throw new Exception('잘못된 API 응답 형식');
       }

       // 트랜잭션 검색 및 검증
       foreach ($data['result'] as $tx) {
           if ($tx['hash'] === $transaction_hash) {
               // USDT 금액 계산 (토큰 데시멀 적용)
               $tokenDecimal = intval($tx['tokenDecimal']);
               $actualAmount = bcdiv($tx['value'], bcpow('10', $tokenDecimal, 0), 6);
               $scanLink = "https://bscscan.com/tx/" . $transaction_hash;

               return [
                   'success' => true,
                   'amount' => $actualAmount,
                   'from' => $tx['from'],
                   'to' => $tx['to'], 
                   'scan_link' => $scanLink
               ];
           }
       }
       throw new Exception('트랜잭션을 찾을 수 없습니다');

   } catch (Exception $e) {
       error_log("BSC 검증 오류: " . $e->getMessage());
       return ['success' => false, 'error' => $e->getMessage()];
   }
}




// AJAX 입금 신청 처리 부분
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   ob_clean();
   header('Content-Type: application/json');
   
   try {
       // 입력값 검증
       if (!isset($_POST['amount'], $_POST['transaction_hash'])) {
           throw new Exception('필수 입력값이 누락되었습니다');
       }

       $amount = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT);
       $transaction_hash = trim($_POST['transaction_hash']);

       if ($amount < 10) {
           throw new Exception('최소 충전금액은 10 USDT입니다');
       }

       if (!preg_match('/^0x[a-fA-F0-9]{64}$/', $transaction_hash)) {
           throw new Exception('유효하지 않은 트랜잭션 해시입니다');
       }

       // 트랜잭션 해시 존재 여부 체크
       $stmt = $conn->prepare("SELECT COUNT(*) as count FROM deposits WHERE transaction_id = ?");
       $stmt->bind_param("s", $transaction_hash);
       $stmt->execute();
       $result = $stmt->get_result();
       $row = $result->fetch_assoc();
       
       if ($row['count'] > 0) {
           throw new Exception('이미 등록된 트랜잭션입니다. 다른 트랜잭션 코드를 입력하세요.');
       }
       $stmt->close();

       // 트랜잭션 검증
       $verify_result = verify_deposit($transaction_hash, $conn);
       if (!$verify_result['success']) {
           throw new Exception($verify_result['error']);
       }

       // 수동으로 트랜잭션 제어
       $conn->autocommit(FALSE);

       try {
           // 입금 기록 저장
           $stmt = $conn->prepare("
               INSERT INTO deposits (
                   user_id, amount_usdt, confirm_usdt, amount_usdp,
                   from_address, to_address, transaction_id, scan_link,
                   status, created_at, processed_at
               ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed', NOW(), NOW())
           ");

           $stmt->bind_param(
               "idddssss",
               $user_id,
               $amount,
               $verify_result['amount'],
               $verify_result['amount'],
               $verify_result['from'],
               $verify_result['to'],
               $transaction_hash,
               $verify_result['scan_link']
           );

           if (!$stmt->execute()) {
               throw new Exception('입금 기록 저장에 실패했습니다');
           }

           // 사용자 포인트 업데이트
           $stmt = $conn->prepare("
               UPDATE users 
               SET point = point + ? 
               WHERE id = ?
           ");
           
           $stmt->bind_param("di", $verify_result['amount'], $user_id);
           if (!$stmt->execute()) {
               throw new Exception('포인트 업데이트에 실패했습니다');
           }

           $conn->commit();
           echo json_encode(['success' => true, 'message' => '입금이 확인되었습니다']);
       } catch (Exception $e) {
           $conn->rollback();
           throw $e;
       } finally {
           $conn->autocommit(TRUE);
       }

   } catch (Exception $e) {
       echo json_encode(['success' => false, 'message' => $e->getMessage()]);
   }
   exit;
}


// 사용자 정보 조회
$stmt = $conn->prepare("
   SELECT name, login_id, point, bsc_address as wallet_address 
   FROM users WHERE id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// 최근 입금 내역 조회
$stmt = $conn->prepare("
   SELECT * FROM deposits 
   WHERE user_id = ? 
   ORDER BY created_at DESC 
   LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_deposits = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = '충전하기';
include __DIR__ . '/../includes/header.php';

// HTML 템플릿과 JavaScript 코드는 기존 deposits.php와 동일하게 유지
// 단, form 필드명과 이벤트 핸들러만 새로운 방식에 맞게 수정
?>



<!-- HTML/CSS 부분 -->
<style>
        .deposit-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
        }

        .info-card {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            border: 1px solid rgba(212, 175, 55, 0.2);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .qr-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
            padding: 30px 0;
        }

        .qr-code {
            background: white;
            padding: 15px;
            border-radius: 10px;
            width: 200px;
            height: 200px;
        }

        .address-box {
            width: 100%;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(212, 175, 55, 0.2);
            border-radius: 8px;
            padding: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 5px;
        }

        .address-text {
            font-family: monospace;
            color: #fff;
            font-size: 0.9rem;
            word-break: break-all;
        }

        .copy-btn {
            background: none;
            border: 1px solid #d4af37;
            color: #d4af37;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .copy-btn:hover {
            background: #d4af37;
            color: #000;
        }

        .deposit-form {
            background: rgba(26, 26, 26, 0.95);
            border: 1px solid rgba(212, 175, 55, 0.1);
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            color: #d4af37;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(212, 175, 55, 0.2);
            border-radius: 8px;
            padding: 12px;
            color: #fff;
            font-size: 1rem;
        }

        .form-control:focus {
            border-color: #d4af37;
            outline: none;
        }

        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, #d4af37 0%, #f2d06b 100%);
            color: #000;
            border: none;
            padding: 10px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(212, 175, 55, 0.2);
        }

        .notice-section {
            margin-top: 20px;
            padding: 15px;
            background: rgba(212, 175, 55, 0.1);
            border-radius: 8px;
        }

        .notice-title {
            color: #d4af37;
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 1.0rem;
        }

        .notice-list {
            list-style: none;
            padding: 0;
            margin: 0;
            font-size: 0.8rem;
            font-family: notosans;
        }

        .notice-list li {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.8rem;
            margin-bottom: 5px;
            padding-left: 15px;
            position: relative;
            font-weight: 200;
        }

        .notice-list li:before {
            content: "•";
            color: #d4af37;
            position: absolute;
            left: 0;
        }

        .history-section {
            margin-top: 30px;
        }

        .history-card {
            background: rgba(26, 26, 26, 0.95);
            border: 1px solid rgba(212, 175, 55, 0.1);
            border-radius: 10px;
            margin-bottom: 15px;
            overflow: hidden;
        }

        .history-header {
            background: rgba(212, 175, 55, 0.1);
            padding: 12px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .history-amount {
            color: #d4af37;
            font-weight: bold;
        }

        .history-body {
            padding: 15px;
        }

        .check-button {
            background: linear-gradient(135deg, #d4af37 0%, #f2d06b 100%);
            border: none;
            border-radius: 4px;
            color: #000;
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 500;
            padding: 4px 8px;
            transition: all 0.3s ease;
            opacity: 0.9;
            margin-left: 8px;
        }

        .check-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(212, 175, 55, 0.3);
            opacity: 1;
        }

        .check-button:active {
            transform: translateY(0);
        }

        .check-button.checking {
            position: relative;
            color: transparent;
        }

        .check-button.checking::after {
            content: "";
            position: absolute;
            width: 12px;
            height: 12px;
            top: 50%;
            left: 50%;
            margin: -6px 0 0 -6px;
            border: 2px solid #000;
            border-top-color: transparent;
            border-radius: 50%;
            animation: button-loading-spinner 0.8s linear infinite;
        }

        @keyframes button-loading-spinner {
            from { transform: rotate(0turn); }
            to { transform: rotate(1turn); }
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            font-size: 0.7rem;
            padding: 3px 8px;
            border-radius: 12px;
            font-weight: 500;
        }

        .status-badge.pending {
            background: rgba(255, 193, 7, 0.15);
            color: #ffc107;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }

        .status-badge.completed {
            background: rgba(76, 175, 80, 0.15);
            color: #4caf50;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }

        .status-badge.failed {
            background: rgba(244, 67, 54, 0.15);
            color: #f44336;
            border: 1px solid rgba(244, 67, 54, 0.3);
        }

        .verification-status {
            display: none;
            padding: 10px;
            border-radius: 8px;
            margin: 10px 0;
            font-size: 0.9rem;
        }

        .verification-status.checking {
            display: block;
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
            border: 1px solid rgba(255, 193, 7, 0.2);
        }

        .verification-status.success {
            display: block;
            background: rgba(76, 175, 80, 0.1);
            color: #4caf50;
            border: 1px solid rgba(76, 175, 80, 0.2);
        }

        .verification-status.error {
            display: block;
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
            border: 1px solid rgba(244, 67, 54, 0.2);
        }
</style>







<div class="deposit-container">
   <!-- 상단 정보 카드 -->
   <div class="info-card">
       <div class="flex justify-between items-center mb-4">
           <h2 class="fs-18 font-bold text-orange notosans">포인트(USDP) 충전</h2>
           <span class="fs-14">
               현재 포인트: <span class="btn14 bg-gray60 text-orange font-bold">
                   <?php echo number_format($user['point'], 1); ?> <small>USDP</small>
               </span>
           </span>
       </div>

       <div class="qr-section mp-0">
           <img src="https://api.qrserver.com/v1/create-qr-code/?data=<?php echo COMPANY_ADDRESS; ?>&size=200x200"
               alt="Company BSC Address QR" class="qr-code">
           <div class="notosans" style="text-align: center; font-size: 12px;">리치클럽 입금주소(BSC BEP20 Address)</div>
           <div class="mx-20">
               <div class="notosans fs-14 text-red mt20-" id="companyAddress"><?php echo COMPANY_ADDRESS; ?></div>
               <div class="fs-14 text-center text-orange" onclick="copyAddress('companyAddress')" class="copy-btn">
                   <i class="fas fa-copy mr-2">복사</i>
               </div>
           </div>
       </div>
   </div>

   <!-- 입금 신청 폼 -->
   <form id="depositForm" class="deposit-form">
       <div class="form-group">
           <label class="form-label">송금한 USDT 금액</label>
           <input type="number" name="amount" class="form-control" 
                  step="0.000001" min="10" placeholder="송금하신 USDT 금액을 입력하세요" required>
       </div>

       <div class="form-group">
           <label class="form-label">트랜잭션 해시 (Transaction Hash)</label>
           <input type="text" name="transaction_hash" class="form-control" required 
                  placeholder="0x로 시작하는 64자리 해시값">
       </div>

       <div id="verificationStatus" class="verification-status"></div>

       <button type="submit" class="btn-submit" id="submitBtn">
           입금 확인 신청
       </button>
   </form>

   <!-- 안내사항 섹션 -->
   <div class="notice-section bg-gray90">
       <div class="notice-title notosans" onclick="toggleNotice()" style="cursor: pointer;">
           충전 안내사항 <span id="toggle-icon">▼</span>
       </div>
       <ul class="notice-list" id="notice-list" style="display: none;">
           <li>바이낸스 스마트 체인(BSC) USDT로만 입금 가능합니다.</li>
           <li>트랜잭션이 확인되면 즉시 USDP로 자동 충전됩니다.</li>
           <li>최소 충전 금액은 10 USDT입니다.</li>
           <li>트랜잭션 해시는 지갑에서 송금 후 확인할 수 있습니다.</li>
           <li>실제 송금액과 입력한 금액이 일치해야 합니다.</li>
       </ul>
   </div>

   <!-- 입금 내역 섹션 -->
   <div class="history-section mt60 mb100">
       <h6 class="fs-18 font-bold text-orange notosans">
           <i class="fas fa-list-ul mr10"></i> 최근 입금 내역
       </h6>

       <hr class="mb-4">
       <?php if (empty($recent_deposits)): ?>
           <div class="text-center text-gray-500 py-8">
               최근 입금 내역이 없습니다.
           </div>
       <?php else: ?>
           <?php foreach ($recent_deposits as $deposit): ?>
               <div class="history-card">
                   <div class="history-header">
                       <span class="text-sm">
                           <?php echo date('Y-m-d H:i', strtotime($deposit['created_at'])); ?>
                       </span>
                       <span class="history-amount">
                           <?php echo number_format($deposit['confirm_usdt'], 2); ?> USDT
                       </span>
                   </div>
                   <div class="history-body notosans">
                       <div class="flex justify-between items-center mb-2">
                           <span class="fs-13 text-gray-400">트랜잭션:</span>
                           <a href="<?php echo $deposit['scan_link']; ?>" 
                              target="_blank" 
                              class="fs-13 text-blue-400 hover:text-blue-500">
                               <?php echo substr($deposit['transaction_id'], 0, 16); ?>...
                           </a>
                       </div>
                       <div class="flex justify-between items-center mb-2">
                           <span class="fs-13 text-gray-400">확인금액:</span>
                           <span class="fs-13 btn14 bg-gray90 text-orange font-mono">
                               <?php echo number_format($deposit['confirm_usdt'], 2); ?> USDT
                           </span>
                       </div>
                       <div class="flex justify-between items-center">
                           <span class="fs-13 text-gray-400">상태:</span>
                           <span class="status-badge <?php echo $deposit['status']; ?>">
                               <?php
                               switch($deposit['status']) {
                                   case 'completed': 
                                       echo '<i class="fas fa-check-circle mr-1"></i> 완료';
                                       break;
                                   case 'pending': 
                                       echo '<i class="fas fa-clock mr-1"></i> 처리중';
                                       break;
                                   case 'failed': 
                                       echo '<i class="fas fa-times-circle mr-1"></i> 실패';
                                       break;
                               }
                               ?>
                           </span>
                       </div>
                   </div>
               </div>
           <?php endforeach; ?>
       <?php endif; ?>
   </div>
</div>





<script>
// 주소 복사 함수
function copyAddress(elementId) {
   const text = document.getElementById(elementId).textContent;
   navigator.clipboard.writeText(text).then(() => {
       showNotification('주소가 복사되었습니다.', 'success');
   }).catch(() => {
       showNotification('복사에 실패했습니다. 수동으로 복사해주세요.', 'error');
   });
}

// 안내사항 토글
function toggleNotice() {
   const noticeList = document.getElementById('notice-list');
   const toggleIcon = document.getElementById('toggle-icon');
   if (noticeList.style.display === 'none') {
       noticeList.style.display = 'block';
       toggleIcon.textContent = '▲';
   } else {
       noticeList.style.display = 'none';
       toggleIcon.textContent = '▼';
   }
}

// 입금 폼 제출 처리
document.getElementById('depositForm').addEventListener('submit', async function(e) {
   e.preventDefault();

   const form = this;
   const submitBtn = form.querySelector('button[type="submit"]');
   const verificationStatus = document.getElementById('verificationStatus');
   const amount = form.amount.value.trim();
   const transactionHash = form.transaction_hash.value.trim();

   // 입력값 검증
   if (!amount || parseFloat(amount) < 10) {
       showNotification('최소 충전 금액은 10 USDT입니다.', 'error');
       return;
   }

   if (!transactionHash.match(/^0x[a-fA-F0-9]{64}$/)) {
       showNotification('올바른 트랜잭션 해시를 입력해주세요.', 'error');
       return;
   }

   try {
       submitBtn.disabled = true;
       submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>검증중...';
       verificationStatus.className = 'verification-status checking';
       verificationStatus.innerHTML = '<i class="fas fa-sync fa-spin mr-2"></i>트랜잭션을 검증하는 중입니다...';

       const formData = new URLSearchParams();
       formData.append('amount', amount);
       formData.append('transaction_hash', transactionHash);

       const response = await fetch('', {
           method: 'POST',
           headers: {
               'Content-Type': 'application/x-www-form-urlencoded',
               'X-Requested-With': 'XMLHttpRequest'
           },
           body: formData
       });

       const result = await response.json();
       
       if (result.success) {
           verificationStatus.className = 'verification-status success';
           verificationStatus.innerHTML = '<i class="fas fa-check-circle mr-2"></i>' + result.message;
           showNotification(result.message, 'success');
           setTimeout(() => location.reload(), 2000);
       } else {
           verificationStatus.className = 'verification-status error';
           verificationStatus.innerHTML = '<i class="fas fa-exclamation-circle mr-2"></i>' + result.message;
           showNotification(result.message, 'error');
           
           // 트랜잭션 해시 입력 필드에 포커스
           if(result.message.includes('이미 등록된 트랜잭션')) {
               form.transaction_hash.focus();
               form.transaction_hash.select();
           }
       }

   } catch (error) {
       verificationStatus.className = 'verification-status error';
       verificationStatus.innerHTML = '<i class="fas fa-exclamation-circle mr-2"></i>처리 중 오류가 발생했습니다';
       showNotification('처리 중 오류가 발생했습니다', 'error');
   } finally {
       submitBtn.disabled = false;
       submitBtn.innerHTML = '입금 확인 신청';
   }
});


// 페이지 로드 시 초기화
document.addEventListener('DOMContentLoaded', function() {
   document.getElementById('depositForm').reset();
});
</script>

<?php
$conn->close();
include __DIR__ . '/../includes/footer.php';
?>