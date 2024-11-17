<?php
// UTF-8 ����
ini_set('default_charset', 'UTF-8');
header('Content-Type: text/html; charset=UTF-8');

require 'vendor/autoload.php';

use IEXBase\TronAPI\Provider\HttpProvider;
use IEXBase\TronAPI\Tron;
use IEXBase\TronAPI\Exception\TronException;

// ���� ���� ����
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    // Tron ��� ����
    $fullNode = new HttpProvider('https://api.trongrid.io');
    $solidityNode = new HttpProvider('https://api.trongrid.io');
    $eventServer = new HttpProvider('https://api.trongrid.io');

    // Tron ��ü ����
    $tron = new Tron($fullNode, $solidityNode, $eventServer);

    // ������ ���� ���� Ű ����
    $privateKey = '������ ������ ���� Ű�� �Է��ϼ���'; // ���� Ű
    $tron->setPrivateKey($privateKey);

    // ������ ���� �ּ� ����
    $masterAddress = '������ ���� �ּҸ� �Է��ϼ���'; // ���� �ּ�
    if (!$tron->validateAddress($masterAddress)) {
        throw new Exception('������ ���� �ּҰ� ��ȿ���� �ʽ��ϴ�.');
    }
    $tron->setAddress($masterAddress);

    // USDT ��Ʈ��Ʈ �ּ�
    $usdtContractAddress = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';

    // ������ �ּ�
    $recipientAddress = '������ ���� �ּҸ� �Է��ϼ���';
    if (!$tron->validateAddress($recipientAddress)) {
        throw new Exception('������ ���� �ּҰ� ��ȿ���� �ʽ��ϴ�.');
    }

    // ������ �ݾ� (��: 10 USDT)
    $amount = 10 * pow(10, 6); // USDT�� �Ҽ��� 6�ڸ�

    // �ּҸ� 16���� �������� ��ȯ
    $ownerAddressHex = $tron->address2HexString($masterAddress);
    $toAddressHex = $tron->address2HexString($recipientAddress);

    if ($ownerAddressHex === false || $toAddressHex === false) {
        throw new Exception('�ּ� ��ȯ�� �����߽��ϴ�.');
    }

    // Ʈ����� ����
    $transaction = $tron->getTransactionBuilder()->sendTrx($toAddressHex, $amount, $ownerAddressHex);

    // Ʈ����� ����
    $signedTransaction = $tron->signTransaction($transaction);

    // Ʈ����� ��ε�ĳ��Ʈ
    $response = $tron->sendRawTransaction($signedTransaction);

    // ��� Ȯ��
    if (isset($response['result']) && $response['result'] === true) {
        echo "Ʈ����� ����! Ʈ����� ID: " . $response['txid'] . "\n";
    } else {
        throw new Exception('Ʈ����� ����.');
    }
} catch (TronException $e) {
    echo "Tron API ����: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "���� �߻�: " . $e->getMessage() . "\n";
}
