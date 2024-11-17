<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('잘못된 요청 방식입니다.');
    }

    if (!isset($_POST['name']) || empty($_POST['name'])) {
        throw new Exception('검색할 이름을 입력해주세요.');
    }

    $conn = db_connect();
    if (!$conn) {
        throw new Exception('데이터베이스 연결에 실패했습니다.');
    }

    $searchName = '%' . trim($_POST['name']) . '%';

    $stmt = $conn->prepare("SELECT name, phone, referral_code FROM users WHERE name LIKE ? AND referral_code IS NOT NULL LIMIT 10");
    if (!$stmt) {
        throw new Exception('쿼리 준비 실패: ' . $conn->error);
    }

    $stmt->bind_param("s", $searchName);

    if (!$stmt->execute()) {
        throw new Exception('쿼리 실행 실패: ' . $stmt->error);
    }

    // get_result() 대신 bind_result()와 fetch() 사용
    $stmt->bind_result($name, $phone, $referral_code);
    $users = [];

    while ($stmt->fetch()) {
        // 전화번호 마스킹 처리
        $phone_clean = preg_replace("/[^0-9]/", "", $phone);
        if (strlen($phone_clean) === 11) {
            $phone_masked = substr($phone_clean, 0, 3) . '-****-' . substr($phone_clean, 7);
        } else {
            $phone_masked = $phone;
        }

        $users[] = [
            'name' => $name,
            'phone' => $phone_masked,
            'referral_code' => $referral_code
        ];
    }

    echo json_encode(['success' => true, 'data' => $users], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Search referral error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

if (isset($stmt)) {
    $stmt->close();
}
if (isset($conn)) {
    $conn->close();
}
?>
