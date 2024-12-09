<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/richtech.club/pages/error.log');

// 필요한 파일 포함
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/config.php';

use Web3\Web3;
use Web3\Contract;
use Web3\Utils;
use Web3p\EthereumTx\Transaction;
use Web3\Providers\HttpProvider;
use Web3\RequestManagers\HttpRequestManager;

// 관리자 권한 체크
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_id'], [1, 2])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '권한이 없습니다.']);
    exit();
}

header('Content-Type: application/json');

// POST 요청 확인
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
    exit();
}

// 입력 데이터 확인
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => '유효하지 않은 입력입니다.']);
    exit();
}

$conn = db_connect();

try {
    $withdrawal_ids = [];

    if (isset($input['withdrawal_id'])) {
        $withdrawal_ids[] = intval($input['withdrawal_id']);
    } elseif (isset($input['withdrawal_ids'])) {
        $withdrawal_ids = array_map('intval', $input['withdrawal_ids']);
    } else {
        throw new Exception('출금 ID가 지정되지 않았습니다.');
    }

    $success_count = 0;
    $fail_count = 0;
    $error_messages = [];

    foreach ($withdrawal_ids as $withdrawal_id) {
        // 트랜잭션 시작
        $conn->begin_transaction();

        // 출금 정보 조회 및 잠금
        $stmt = $conn->prepare("SELECT * FROM withdrawals WHERE id = ? AND status = 'pending' FOR UPDATE");
        if ($stmt === false) {
            throw new Exception('쿼리 준비 중 오류 발생: ' . $conn->error);
        }
        $stmt->bind_param("i", $withdrawal_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $withdrawal = $result->fetch_assoc();
        $stmt->close();

        if (!$withdrawal) {
            $conn->rollback();
            $fail_count++;
            $error_messages[] = "출금 요청을 찾을 수 없거나 이미 처리되었습니다 (ID: $withdrawal_id).";
            continue;
        }

        try {
            // BSC USDT 전송
            $txHash = sendBscUsdtTransaction(
                $withdrawal['to_address'],
                $withdrawal['actual_amount_usdt']
            );

            // DB 업데이트
    $scan_link = "https://bscscan.com/tx/" . $txHash;
$stmt = $conn->prepare("UPDATE withdrawals SET transaction_id = ?, scan_link = ?, status = 'completed', processed_at = NOW() WHERE id = ?");
if ($stmt === false) {
    throw new Exception('출금 업데이트 쿼리 준비 중 오류 발생: ' . $conn->error);
}
$stmt->bind_param("ssi", $txHash, $scan_link, $withdrawal_id);
$stmt->execute();
$stmt->close();

            // 트랜잭션 커밋
            $conn->commit();
            $success_count++;
        } catch (Exception $e) {
            $conn->rollback();
            error_log("출금 처리 실패 (ID: $withdrawal_id): " . $e->getMessage());
            $fail_count++;
            $error_messages[] = "출금 처리 실패 (ID: $withdrawal_id): " . $e->getMessage();
        }
    }

    // 결과에 따른 응답 구성
    if ($success_count > 0 && $fail_count == 0) {
        $message = "총 " . count($withdrawal_ids) . "건의 출금이 성공적으로 처리되었습니다.";
        echo json_encode(['success' => true, 'message' => $message]);
    } elseif ($success_count > 0 && $fail_count > 0) {
        $message = "총 " . count($withdrawal_ids) . "건 중 $success_count 건 성공, $fail_count 건 실패했습니다.";
        echo json_encode(['success' => true, 'message' => $message, 'errors' => $error_messages]);
    } else {
        $message = "출금 처리에 실패했습니다.";
        echo json_encode(['success' => false, 'message' => $message, 'errors' => $error_messages]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * BSC USDT 전송 함수
 */
function sendBscUsdtTransaction($toAddress, $amount) {
    // 설정 정보 가져오기
    $bscNodeUrl = BSC_NODE_URL;
    $privateKey = getenv('BSC_PRIVATE_KEY') ?: $_ENV['BSC_PRIVATE_KEY'] ?? null;   //개인키를 환경변수에서 가져온다.
    $companyAddress = COMPANY_ADDRESS;
    $usdtContractAddress = USDT_CONTRACT_ADDRESS;

    // 개인키 앞의 '0x' 제거
    if (strpos($privateKey, '0x') === 0) {
        $privateKey = substr($privateKey, 2);
    }

    // 개인키와 주소 유효성 검사
    if (empty($privateKey) || empty($companyAddress)) {
        throw new Exception('회사 지갑 정보가 설정되지 않았습니다.');
    }

    // Web3 초기화
    $provider = new HttpProvider(new HttpRequestManager($bscNodeUrl, 5)); // 타임아웃 5초
    $web3 = new Web3($provider);

    // 가스비용 잔액 확인
    $balanceWei = null;
    $web3->eth->getBalance($companyAddress, function ($err, $result) use (&$balanceWei) {
        if ($err !== null) {
            throw new Exception('가스비 잔액 확인 실패: ' . $err->getMessage());
        }
        $balanceWei = $result;
    });

    if ($balanceWei instanceof \phpseclib\Math\BigInteger) {
        $minGasBalanceWei = Utils::toWei('0.005', 'ether'); // 최소 0.005 BNB
        if ($balanceWei->compare(new \phpseclib\Math\BigInteger($minGasBalanceWei)) < 0) {
            throw new Exception('가스비용이 부족합니다.');
        }
    } else {
        throw new Exception('getBalance 결과가 예상치 않은 형식입니다.');
    }

    // USDT 잔액 확인
    $usdtBalance = null;
    $contract = new Contract($provider, '[]');
    $data = '0x70a08231' . str_pad(substr($companyAddress, 2), 64, '0', STR_PAD_LEFT);

    $web3->eth->call([
        'to' => $usdtContractAddress,
        'data' => $data
    ], function ($err, $result) use (&$usdtBalance) {
        if ($err !== null) {
            throw new Exception('USDT 잔액 조회 실패: ' . $err->getMessage());
        }
        $usdtBalance = Utils::toBn($result);
    });

    // 전송하려는 금액을 Wei 단위로 변환
    $decimals = 18; // USDT의 소수점 자리수 (BEP20 USDT는 18자리)
    $amountInWei = bcmul($amount, bcpow('10', $decimals, 0), 0);

    if ($usdtBalance->compare(new \phpseclib\Math\BigInteger($amountInWei)) < 0) {
        throw new Exception('회사 지갑의 USDT 잔액이 부족합니다.');
    }

    // nonce 값 가져오기
    $nonce = null;
    $web3->eth->getTransactionCount($companyAddress, 'pending', function ($err, $result) use (&$nonce) {
        if ($err !== null) {
            throw new Exception('Nonce 가져오기 실패: ' . $err->getMessage());
        }
        $nonce = $result;
    });

    // 가스 가격 설정 (네트워크 상황에 맞게 조정)
    $gasPrice = Utils::toWei('5', 'gwei');

    // 가스 리미트 설정 (200,000)
    $gasLimit = '200000';

    // 트랜잭션 데이터 수동 생성
    $methodId = substr(Utils::sha3('transfer(address,uint256)'), 0, 10); // '0xa9059cbb'
    $toAddressHex = '0x' . str_pad(substr($toAddress, 2), 64, '0', STR_PAD_LEFT);
    $amountHex = '0x' . str_pad(gmp_strval(gmp_init($amountInWei, 10), 16), 64, '0', STR_PAD_LEFT);
    $data = $methodId . substr($toAddressHex, 2) . substr($amountHex, 2);

    // 트랜잭션 구성
    $transaction = [
        'nonce' => Utils::toHex($nonce, true),
        'from' => $companyAddress,
        'to' => $usdtContractAddress,
        'gas' => Utils::toHex($gasLimit, true),
        'gasPrice' => Utils::toHex($gasPrice, true),
        'value' => '0x0',
        'data' => $data,
        'chainId' => 56 // BSC 메인넷 체인 ID
    ];

    // 트랜잭션 서명
    $tx = new Transaction($transaction);
    $signedTx = '0x' . $tx->sign($privateKey);

    // 트랜잭션 전송
    $txHash = null;
    $web3->eth->sendRawTransaction($signedTx, function ($err, $result) use (&$txHash) {
        if ($err !== null) {
            throw new Exception('트랜잭션 전송 실패: ' . $err->getMessage());
        }
        $txHash = $result;
    });

    if (!$txHash) {
        throw new Exception('트랜잭션 해시를 가져오지 못했습니다.');
    }

    return $txHash;
}