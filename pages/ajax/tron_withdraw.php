<?php
// 데이터베이스 연결
$conn = new mysqli("localhost", "lidyahkc_0", "lidya2016$", "lidyahkc_rich");

// 에러 핸들링
if ($conn->connect_error) {
    die("데이터베이스 연결 실패: " . $conn->connect_error);
}

// 출금 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'];
    $withdraw_amount = $_POST['amount'];
    $fee = $withdraw_amount * 0.05; // 수수료 5%
    $net_amount = $withdraw_amount - $fee;

    // 사용자 정보 조회
    $result = $conn->query("SELECT * FROM users WHERE id = $user_id");
    if ($user = $result->fetch_assoc()) {
        $tron_address = $user['tron_address'];

        // 출금 기록 삽입
        $stmt = $conn->prepare("INSERT INTO withdrawals (user_id, amount, fee, net_amount) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iddd", $user_id, $withdraw_amount, $fee, $net_amount);
        $stmt->execute();
        $stmt->close();

        echo "출금 요청이 성공적으로 접수되었습니다. 실제 출금 금액: " . $net_amount . " TRX";
    } else {
        echo "사용자 정보를 찾을 수 없습니다.";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>출금 신청</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-5">
    <h2>출금 신청</h2>
    <form method="POST">
        <div class="form-group">
            <label for="user_id">사용자 ID</label>
            <input type="number" class="form-control" id="user_id" name="user_id" required>
        </div>
        <div class="form-group">
            <label for="amount">출금 요청 금액 (TRX)</label>
            <input type="number" class="form-control" id="amount" name="amount" step="0.01" required>
        </div>
        <button type="submit" class="btn btn-primary">출금 신청</button>
    </form>
</body>
</html>
