<?php
require 'vendor/autoload.php';

use Web3\Web3;

// BSC 노드 연결
$web3 = new Web3('https://bsc-dataseed.binance.org/');

// 회사 주소
$companyAddress = '0xFAF0ab8dcBcDc1806A65119C8f59187499E4B910';

// 잔액 조회
$web3->eth->getBalance($companyAddress, function ($err, $balance) {
    if ($err !== null) {
        echo "오류: " . $err->getMessage();
        return;
    }
    echo "회사 지갑 잔액: " . hexdec($balance) . " wei\n";
});
