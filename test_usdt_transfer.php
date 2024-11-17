<?php
// UTF-8 설정
ini_set('default_charset', 'UTF-8');
header('Content-Type: text/html; charset=UTF-8');

require 'vendor/autoload.php';

use IEXBase\TronAPI\Provider\HttpProvider;
use IEXBase\TronAPI\Tron;
use IEXBase\TronAPI\Exception\TronException;

// 오류 보고 설정
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    // Tron 노드 설정
    $fullNode = new HttpProvider('https://api.trongrid.io');
    $solidityNode = new HttpProvider('https://api.trongrid.io');
    $eventServer = new HttpProvider('https://api.trongrid.io');

    // Tron 객체 생성
    $tron = new Tron($fullNode, $solidityNode, $eventServer);

    // 마스터 지갑 개인 키 설정
    $privateKey = '마스터 지갑의 개인 키를 입력하세요'; // 개인 키
    $tron->setPrivateKey($privateKey);

    // 마스터 지갑 주소 설정
    $masterAddress = '마스터 지갑 주소를 입력하세요'; // 지갑 주소
    if (!$tron->validateAddress($masterAddress)) {
        throw new Exception('마스터 지갑 주소가 유효하지 않습니다.');
    }
    $tron->setAddress($masterAddress);

    // USDT 컨트랙트 주소
    $usdtContractAddress = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';

    // 수신자 주소
    $recipientAddress = '수신자 지갑 주소를 입력하세요';
    if (!$tron->validateAddress($recipientAddress)) {
        throw new Exception('수신자 지갑 주소가 유효하지 않습니다.');
    }

    // 전송할 금액 (예: 10 USDT)
    $amount = 10 * pow(10, 6); // USDT는 소수점 6자리

    // 주소를 16진수 형식으로 변환
    $ownerAddressHex = $tron->address2HexString($masterAddress);
    $toAddressHex = $tron->address2HexString($recipientAddress);

    if ($ownerAddressHex === false || $toAddressHex === false) {
        throw new Exception('주소 변환에 실패했습니다.');
    }

    // 트랜잭션 생성
    $transaction = $tron->getTransactionBuilder()->sendTrx($toAddressHex, $amount, $ownerAddressHex);

    // 트랜잭션 서명
    $signedTransaction = $tron->signTransaction($transaction);

    // 트랜잭션 브로드캐스트
    $response = $tron->sendRawTransaction($signedTransaction);

    // 결과 확인
    if (isset($response['result']) && $response['result'] === true) {
        echo "트랜잭션 성공! 트랜잭션 ID: " . $response['txid'] . "\n";
    } else {
        throw new Exception('트랜잭션 실패.');
    }
} catch (TronException $e) {
    echo "Tron API 오류: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "오류 발생: " . $e->getMessage() . "\n";
}
