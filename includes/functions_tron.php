<?php
// functions_tron.php

function getTronTransaction($transaction_id) {
    $url = "https://apilist.tronscan.org/api/transaction-info?hash={$transaction_id}";

    $response = file_get_contents($url);
    if ($response === FALSE) {
        return null;
    }

    $data = json_decode($response, true);
    return $data;
}

// 회사의 트론 주소로 입금된 트랜잭션 목록을 가져오는 함수
function getIncomingTransactions($address) {
    $url = "https://api.trongrid.io/v1/accounts/{$address}/transactions/trc20?limit=200&only_confirmed=true";

    $response = file_get_contents($url);
    if ($response === FALSE) {
        return [];
    }

    $data = json_decode($response, true);
    return $data['data'] ?? [];
}
