<?php
require 'vendor/autoload.php';

use IEXBase\TronAPI\Provider\HttpProvider;
use IEXBase\TronAPI\Tron;

$privateKey = '00ec30ba0e6aee95990e9cc28d2bee8a6c3322ac291f45cbace2ab5caeac1b58';
$masterAddress = 'TECXxuTdgMNETyYndRkKYnM6KpH2DtwBty';
$usdtContractAddress = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';

// Tron API 설정
$fullNode = new HttpProvider('https://api.trongrid.io');
$solidityNode = new HttpProvider('https://api.trongrid.io');
$eventServer = new HttpProvider('https://api.trongrid.io');

$tron = new Tron($fullNode, $solidityNode, $eventServer);
$tron->setPrivateKey($privateKey);

try {
    // USDT 컨트랙트 초기화
    $usdtContract = $tron->contract($usdtContractAddress);

    // USDT 잔고 가져오기 (Sun 단위)
    $rawUsdtBalance = $usdtContract->call('balanceOf', [$masterAddress], $masterAddress);
    $hexBalance = $rawUsdtBalance[0]->toHex(); // 잔고 값(16진수)
    $usdtBalance = hexdec($hexBalance) / pow(10, 6); // Sun 단위를 USDT로 변환

    // TRX 잔고 가져오기
    $trxBalance = $tron->getBalance($masterAddress) / pow(10, 6);

    echo "💰 USDT 잔액: $usdtBalance USDT<br>";
    echo "💰 TRX 잔액: $trxBalance TRX<br>";
} catch (Exception $e) {
    echo "오류 발생: " . $e->getMessage();
}