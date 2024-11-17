<?php
// 데이터베이스 연결
$conn = new mysqli("localhost", "username", "password", "database");

// 에러 핸들링
if ($conn->connect_error) {
    die("데이터베이스 연결 실패: " . $conn->connect_error);
}

// 폼 제출 시 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $tron_address = $_POST['tron_address'];

    // 트론 주소가 입력되지 않은 경우 자동 생성
    if (empty($tron_address)) {
        // 사용되지 않은 트론 주소 가져오기
        $result = $conn->query("SELECT * FROM tronwallet WHERE is_used = FALSE LIMIT 1");
        if ($row = $result->fetch_assoc()) {
            $tron_address = $row['address'];
            $private_key = $row['private_key'];
            
            // 트론 주소 사용 처리
            $conn->query("UPDATE tronwallet SET is_used = TRUE WHERE id = " . $row['id']);
        } else {
            echo "사용 가능한 트론 주소가 없습니다.";
            exit;
        }
    }

    // 사용자 정보 삽입
    $stmt = $conn->prepare("INSERT INTO users (username, email, tron_address) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $email, $tron_address);
    $stmt->execute();
    $stmt->close();

    echo "회원가입이 완료되었습니다!";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>회원등록 페이지</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-5">
    <h2>회원 등록</h2>
    <form method="POST">
        <div class="form-group">
            <label for="username">사용자 이름</label>
            <input type="text" class="form-control" id="username" name="username" required>
        </div>
        <div class="form-group">
            <label for="email">이메일</label>
            <input type="email" class="form-control" id="email" name="email" required>
        </div>
        <div class="form-group">
            <label for="tron_address">트론 주소</label>
            <input type="text" class="form-control" id="tron_address" name="tron_address" placeholder="기존 주소가 있다면 입력하세요">
        </div>
        <button type="submit" class="btn btn-primary">등록</button>
    </form>
</body>
</html>
