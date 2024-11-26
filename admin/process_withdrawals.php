<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/richtech.club/pages/error.log');

// admin/process_withdrawals.php

// 세션 시작
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/config.php';

use IEXBase\TronAPI\Tron;
use IEXBase\TronAPI\Exception\TronException;

// 데이터베이스 연결
$conn = db_connect();

// Tron 설정
$fullNode = new \IEXBase\TronAPI\Provider\HttpProvider('https://api.trongrid.io');
$solidityNode = new \IEXBase\TronAPI\Provider\HttpProvider('https://api.trongrid.io');
$eventServer = new \IEXBase\TronAPI\Provider\HttpProvider('https://api.trongrid.io');

// Tron 객체 생성
$tron = new Tron($fullNode, $solidityNode, $eventServer);

// 마스터 계좌 개인키 설정 (보안에 주의하세요)
$masterPrivateKey = MASTER_PRIVATE_KEY; // config.php에서 정의됨
$tron->setPrivateKey($masterPrivateKey);

// USDT 계약 주소 (메인넷)
$usdtContractAddress = 'TXLAQ63Xg1NAzckPwKHvzw7CSEmLMEqcdj';

// BCMath 확장 확인
if (!function_exists('bcmul')) {
    die('BCMath 확장이 설치되어 있지 않습니다. 서버 관리자에게 문의하세요.');
}

try {
    // 선택된 출금 요청 ID 가져오기
    $selectedIds = isset($_SESSION['selected_withdrawal_ids']) ? $_SESSION['selected_withdrawal_ids'] : [];

    if (!empty($selectedIds)) {
        // 선택된 출금 요청만 가져오기
        $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
        $types = str_repeat('i', count($selectedIds));

        $sql = "SELECT w.*, u.point as points, u.name FROM withdrawals w JOIN users u ON w.user_id = u.id WHERE w.status = 'pending' AND w.id IN ($placeholders)";

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            die('쿼리 준비 실패: ' . $conn->error);
        }

        $params = array_merge([$types], $selectedIds);
        $tmp = [];
        foreach ($params as $key => $value) {
            $tmp[$key] = &$params[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $tmp);
    } else {
        exit('처리할 출금 요청이 없습니다.');
    }

    $stmt->execute();
    $withdrawals = $stmt->get_result();

    if ($withdrawals === false) {
        die('결과 가져오기 실패: ' . $stmt->error);
    }

    while ($withdrawal = $withdrawals->fetch_assoc()) {
        $withdrawalId = $withdrawal['id'];
        $userId = $withdrawal['user_id'];
        $tronAddress = $withdrawal['tron_address'];
        $actualAmountUsdt = $withdrawal['actual_amount_usdt'];
        $requestAmountUsdp = $withdrawal['request_amount_usdp'];
        $userPoints = $withdrawal['points'];
        $userName = $withdrawal['name'];

        // 회원의 포인트가 충분한지 확인
        if ($userPoints < $requestAmountUsdp) {
            $updateStmt = $conn->prepare("UPDATE withdrawals SET status = 'failed', processed_at = NOW() WHERE id = ?");
            $updateStmt->bind_param("i", $withdrawalId);
            $updateStmt->execute();
            continue;
        }

        // Tron 주소 유효성 검사
        if (!$tron->isAddress($tronAddress)) {
            $updateStmt = $conn->prepare("UPDATE withdrawals SET status = 'failed', processed_at = NOW() WHERE id = ?");
            $updateStmt->bind_param("i", $withdrawalId);
            $updateStmt->execute();
            continue;
        }

        $amountSun = bcmul($actualAmountUsdt, '1000000', 0);

        // 마스터 계좌의 TRX 잔액 확인
        $masterAddress = $tron->getAddress();
        if (is_array($masterAddress)) {
            $masterAddress = $masterAddress['base58'] ?? null;
        }

        if (!is_string($masterAddress)) {
            throw new Exception("유효하지 않은 Tron 주소: " . json_encode($masterAddress));
        }

        $trxBalance = $tron->getBalance($masterAddress);
        $estimatedFee = 50;

        if ($trxBalance < $estimatedFee) {
            $updateStmt = $conn->prepare("UPDATE withdrawals SET status = 'failed', processed_at = NOW() WHERE id = ?");
            $updateStmt->bind_param("i", $withdrawalId);
            $updateStmt->execute();
            continue;
        }

        $conn->begin_transaction();

        try {
            $transaction = $tron->getTransactionBuilder()->triggerSmartContract(
                $usdtContractAddress,
                'transfer(address,uint256)',
                0,
                [$tronAddress, $amountSun],
                $masterAddress
            );

            $signedTransaction = $tron->signTransaction($transaction);
            $response = $tron->sendRawTransaction($signedTransaction);

            if (isset($response['result']) && $response['result'] === true) {
                $txId = $response['txid'];
                $tronscanLink = 'https://tronscan.io/#/transaction/' . $txId;

                $updateStmt = $conn->prepare("UPDATE withdrawals SET transaction_id = ?, tronscan_link = ?, status = 'completed', processed_at = NOW() WHERE id = ?");
                $updateStmt->bind_param("ssi", $txId, $tronscanLink, $withdrawalId);
                $updateStmt->execute();

                $pointStmt = $conn->prepare("UPDATE users SET point = point - ? WHERE id = ?");
                $pointStmt->bind_param("di", $requestAmountUsdp, $userId);
                $pointStmt->execute();

                $conn->commit();
            } else {
                $conn->rollback();
                $updateStmt = $conn->prepare("UPDATE withdrawals SET status = 'failed', processed_at = NOW() WHERE id = ?");
                $updateStmt->bind_param("i", $withdrawalId);
                $updateStmt->execute();
            }
        } catch (Exception $e) {
            $conn->rollback();
            $updateStmt = $conn->prepare("UPDATE withdrawals SET status = 'failed', processed_at = NOW() WHERE id = ?");
            $updateStmt->bind_param("i", $withdrawalId);
            $updateStmt->execute();
        }
    }
} catch (TronException $e) {
    error_log('TronException: ' . $e->getMessage());
} catch (\Exception $e) {
    error_log('Exception: ' . $e->getMessage());
}

$conn->close();
