<?php
// check_deposits.php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/richtech.club/admin/error.log');

require_once __DIR__ . '/../includes/config.php';

class DepositChecker {
    private $conn;
    private $bscApiKey;
    private $processedCount = 0;
    private $errors = [];
    private $companyAddress;
    private $usdtContract;

    public function __construct() {
        $this->bscApiKey = BSC_API_KEY;
        $this->companyAddress = COMPANY_ADDRESS;
        $this->usdtContract = USDT_CONTRACT_ADDRESS;
        $this->initDatabase();
    }

    private function initDatabase() {
        try {
            $this->conn = db_connect();
            $this->conn->set_charset('utf8mb4');
        } catch (Exception $e) {
            $this->logError("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }

    public function processDeposits() {
        try {
            // 처리되지 않은 입금 내역 조회
            $pendingDeposits = $this->getPendingDeposits();
            if (empty($pendingDeposits)) {
                $this->logInfo("No pending deposits found");
                return 0;
            }

            foreach ($pendingDeposits as $deposit) {
                $this->processDeposit($deposit);
            }

            return $this->processedCount;

        } catch (Exception $e) {
            $this->logError("Error processing deposits: " . $e->getMessage());
            throw $e;
        }
    }

    private function getPendingDeposits() {
        $stmt = $this->conn->prepare("
            SELECT d.*, u.point 
            FROM deposits d
            JOIN users u ON d.user_id = u.id 
            WHERE d.status = 'pending'
        ");
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    private function processDeposit($deposit) {
        try {
            // BSCScan API 호출하여 트랜잭션 정보 조회
            $txInfo = $this->getTransactionInfo($deposit['transaction_id']);
            if (!$txInfo) {
                return;
            }

            // USDT 전송 이벤트 확인
            if (strtolower($txInfo['to']) === strtolower($this->companyAddress)) {
                $this->validateAndUpdateDeposit($deposit, $txInfo);
            }

        } catch (Exception $e) {
            $this->logError("Error processing deposit ID {$deposit['id']}: " . $e->getMessage());
        }
    }

    
private function getTransactionInfo($txHash) {
    $url = sprintf(
        'https://api.bscscan.com/api?module=account&action=tokentx&contractaddress=%s&address=%s&page=1&offset=100&sort=desc&apikey=%s',
        $this->usdtContract,
        $this->companyAddress,
        $this->bscApiKey
    );

    // API URL 로깅
    error_log("BSCScan API URL: " . $url);

    $response = file_get_contents($url);
    if ($response === false) {
        error_log("Failed to get response from BSCScan");
        return null;
    }

    // 전체 응답 로깅
    error_log("BSCScan API Response: " . $response);

    $data = json_decode($response, true);
    if (!isset($data['result']) || !is_array($data['result'])) {
        error_log("Invalid response format: " . print_r($data, true));
        return null;
    }

    foreach ($data['result'] as $tx) {
        if ($tx['hash'] === $txHash) {
            // 트랜잭션 정보 로깅
            error_log("Found matching transaction: " . print_r($tx, true));
            error_log("Raw value from transaction: " . $tx['value']);
            
            // 값 변환 과정 로깅
            $rawValue = $tx['value'];
            $divider = bcpow('10', '6', 0);
            error_log("Divider (10^6): " . $divider);
            
            $convertedAmount = bcdiv($rawValue, $divider, 6);
            error_log("Converted amount: " . $convertedAmount);
            
            return $tx;
        }
    }

    error_log("No matching transaction found for hash: " . $txHash);
    return null;
}



private function validateAndUpdateDeposit($deposit, $txInfo) {
    try {
        $this->conn->begin_transaction();

        // 원본 값 로깅
        error_log("Original value from txInfo: " . $txInfo['value']);
        
        // 변환 과정 상세 로깅
        $rawValue = $txInfo['value'];
        $divider = bcpow('10', '6', 0);
        $actualAmount = bcdiv($rawValue, $divider, 6);
        
        error_log("Raw value: " . $rawValue);
        error_log("Divider: " . $divider);
        error_log("Calculated actual amount: " . $actualAmount);

        // BSCScan 링크 생성
        $scanLink = "https://bscscan.com/tx/" . $deposit['transaction_id'];

        // SQL 쿼리 로깅
        $updateQuery = "
            UPDATE deposits 
            SET status = 'completed',
                confirm_usdt = {$actualAmount},
                amount_usdp = {$actualAmount},
                from_address = '{$txInfo['from']}',
                to_address = '{$txInfo['to']}',
                scan_link = '{$scanLink}',
                processed_at = NOW()
            WHERE id = {$deposit['id']} AND status = 'pending'
        ";
        error_log("Update Query: " . $updateQuery);

        // deposits 테이블 업데이트
        $stmt = $this->conn->prepare("
            UPDATE deposits 
            SET status = 'completed',
                confirm_usdt = ?,
                amount_usdp = ?,
                from_address = ?,
                to_address = ?,
                scan_link = ?,
                processed_at = NOW()
            WHERE id = ? AND status = 'pending'
        ");

        $stmt->bind_param(
            "ddsssi",
            $actualAmount,
            $actualAmount,
            $txInfo['from'],
            $txInfo['to'],
            $scanLink,
            $deposit['id']
        );

        $result = $stmt->execute();
        error_log("Update result: " . ($result ? "success" : "failed"));
        error_log("SQL error (if any): " . $stmt->error);

        // 포인트 업데이트 로깅
        error_log("Updating user points - Amount: {$actualAmount}, User ID: {$deposit['user_id']}");

        $stmt = $this->conn->prepare("
            UPDATE users 
            SET point = point + ? 
            WHERE id = ?
        ");

        $stmt->bind_param("di", $actualAmount, $deposit['user_id']);
        $result = $stmt->execute();
        error_log("Point update result: " . ($result ? "success" : "failed"));
        error_log("SQL error (if any): " . $stmt->error);

        $this->conn->commit();
        $this->processedCount++;

    } catch (Exception $e) {
        $this->conn->rollback();
        error_log("Error in validateAndUpdateDeposit: " . $e->getMessage());
        throw $e;
    }
}




    private function logError($message) {
        error_log("[DepositChecker Error] " . $message);
        $this->errors[] = $message;
    }

    private function logInfo($message) {
        error_log("[DepositChecker Info] " . $message);
    }

    public function getProcessedCount() {
        return $this->processedCount;
    }

    public function getErrors() {
        return $this->errors;
    }

    public function __destruct() {
        if ($this->conn) {
            $this->conn->close();
        }
    }



}





// 실행 컨텍스트에 따른 처리
try {
    $checker = new DepositChecker();
    $processedCount = $checker->processDeposits();

    // CLI에서 실행된 경우 (크론잡)
    if (php_sapi_name() === 'cli') {
        echo "Processed deposits: $processedCount\n";
        if (!empty($checker->getErrors())) {
            echo "Errors occurred:\n";
            foreach ($checker->getErrors() as $error) {
                echo "- $error\n";
            }
        }
    } 
    // AJAX 요청인 경우
    else if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => empty($checker->getErrors()),
            'processed' => $processedCount,
            'errors' => $checker->getErrors()
        ]);
    }
    // 일반 HTTP 요청인 경우
    else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'processed' => $processedCount
        ]);
    }

} catch (Exception $e) {
    $error = "Critical error: " . $e->getMessage();
    error_log($error);

    if (php_sapi_name() === 'cli') {
        echo $error . "\n";
        exit(1);
    } else {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $error]);
    }
}