<?php
require 'vendor/autoload.php';

use IEXBase\TronAPI\Tron;
use IEXBase\TronAPI\Provider\HttpProvider;

$fullNode = new HttpProvider('https://api.trongrid.io');
$solidityNode = new HttpProvider('https://api.trongrid.io');
$eventServer = new HttpProvider('https://api.trongrid.io');

$tron = new Tron($fullNode, $solidityNode, $eventServer);

$walletAddress = 'YOUR_WALLET_ADDRESS';
$senderAddress = 'SENDER_WALLET_ADDRESS';

try {
    $transactions = $tron->getTransactionFromAddress($senderAddress, 0, 200);

    foreach ($transactions as $transaction) {
        if ($transaction['to'] === $walletAddress) {
            $amount = $transaction['value'] / 1e6; // USDT는 소수점 6자리
            $txID = $transaction['txID'];
            $tronScanUrl = "https://tronscan.org/#/transaction/{$txID}";

            // 데이터베이스에 저장하는 로직을 여기에 추가하세요.
            // 예: saveTransaction($senderAddress, $amount, $txID, $tronScanUrl);

            echo "송신자: {$senderAddress}\n";
            echo "입금 금액: {$amount} USDT\n";
            echo "트랜잭션 ID: {$txID}\n";
            echo "트론스캔 링크: {$tronScanUrl}\n\n";
        }
    }
} catch (Exception $e) {
    echo "오류 발생: " . $e->getMessage() . "\n";
}
?>
