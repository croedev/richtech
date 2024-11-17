<?php
// order_process.php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/richtech.club/pages/error.log');

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions_bonus.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = '로그인이 필요합니다.';
    header("Location: /order_apply?id=" . (int)$_POST['product_id']);
    exit();
}

$conn = db_connect();

try {
    // 입력값 검증
    if (!isset($_POST['product_id'], $_POST['quantity'])) {
        throw new Exception('잘못된 요청입니다.');
    }

    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);
    $user_id = intval($_SESSION['user_id']);

    if ($product_id <= 0 || $quantity <= 0) {
        throw new Exception('잘못된 상품 정보나 수량입니다.');
    }

    // 상품 정보 조회
    $stmt = $conn->prepare("SELECT price, stock, token FROM products WHERE id = ?");
    if (!$stmt) {
        throw new Exception("상품 조회 오류: " . $conn->error);
    }
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $product_result = $stmt->get_result();
    if ($product_result->num_rows === 0) {
        throw new Exception('상품을 찾을 수 없습니다.');
    }
    $product = $product_result->fetch_assoc();
    $stmt->close();

    // 사용자 포인트 조회
    $stmt = $conn->prepare("SELECT point FROM users WHERE id = ?");
    if (!$stmt) {
        throw new Exception("사용자 정보 조회 오류: " . $conn->error);
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();
    if ($user_result->num_rows === 0) {
        throw new Exception('사용자 정보를 찾을 수 없습니다.');
    }
    $user = $user_result->fetch_assoc();
    $user_point = $user['point'];
    $stmt->close();

    // 총 결제 금액 계산
    $total_amount = $product['price'] * $quantity;
    $total_stock = $product['stock'] * $quantity;
    $total_token = $product['token'] * $quantity;

    // 포인트 잔액 확인
    if ($user_point < $total_amount) {
        throw new Exception('포인트가 부족합니다.');
    }

    // 트랜잭션 시작
    $conn->begin_transaction();

    // 주문 생성
    $stmt = $conn->prepare("INSERT INTO orders (user_id, product_id, price_unit, quantity, total_amount, point_used, stock, token, payment_method, status, paid_status, payment_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'point', 'completed', 'pending', NOW())");
    if (!$stmt) {
        throw new Exception("주문 생성 오류: " . $conn->error);
    }
    $stmt->bind_param("iidiiddd", $user_id, $product_id, $product['price'], $quantity, $total_amount, $total_amount, $total_stock, $total_token);
    $stmt->execute();
    $order_id = $stmt->insert_id;
    $stmt->close();

    // 사용자 정보 업데이트 (포인트 차감, 주식 및 토큰 추가)
    $stmt = $conn->prepare("UPDATE users SET point = point - ?, stock = COALESCE(stock, 0) + ?, token = COALESCE(token, 0) + ?, myAmount = COALESCE(myAmount, 0) + ? WHERE id = ?");
    if (!$stmt) {
        throw new Exception("사용자 업데이트 오류: " . $conn->error);
    }
    $stmt->bind_param("diddi", $total_amount, $total_stock, $total_token, $total_amount, $user_id);
    $stmt->execute();
    $stmt->close();

    // 주식 거래 내역 생성
    $stmt = $conn->prepare("INSERT INTO stock_history (to_user_id, amount, transaction_type, order_id) VALUES (?, ?, 'purchase', ?)");
    if (!$stmt) {
        throw new Exception("주식 내역 생성 오류: " . $conn->error);
    }
    $stmt->bind_param("iii", $user_id, $total_stock, $order_id);
    $stmt->execute();
    $stmt->close();

    // 토큰 거래 내역 생성
    $stmt = $conn->prepare("INSERT INTO token_history (to_user_id, amount, transaction_type, order_id) VALUES (?, ?, 'purchase', ?)");
    if (!$stmt) {
        throw new Exception("토큰 내역 생성 오류: " . $conn->error);
    }
    $stmt->bind_param("idi", $user_id, $total_token, $order_id);
    $stmt->execute();
    $stmt->close();


    // 트랜잭션 커밋
    $conn->commit();

    // 주문 완료 페이지로 이동
    header("Location: /order_complete?order_id=" . $order_id);
    exit();
} catch (Exception $e) {
    if ($conn->in_transaction()) {
        $conn->rollback();
    }
    error_log("order_process.php 오류: " . $e->getMessage());
    $_SESSION['error_message'] = $e->getMessage();
    header("Location: /order_apply?id=" . $product_id);
    exit();
}

$conn->close();
