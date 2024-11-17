<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$mysqli = new mysqli("localhost", "lidyahkc_0", "lidya2016$", "lidyahkc_rich");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$users = [];
$positions = ['left', 'right'];
$names = ['Alice', 'Bob', 'Carol', 'David', 'Eve', 'Frank', 'Grace', 'Heidi', 'Ivan', 'Judy', 'Kim', 'Lee', 'Park', 'Choi', 'Yoon', 'Kang', 'Han', 'Lim', 'Shin', 'Oh', 'Jang', 'Jo', 'Moon', 'Song', 'Yang', 'Kwon', 'Jeong', 'Ryu', 'Nam', 'Baek', 'Hwang', 'An', 'Seo', 'Jeon', 'Goo', 'Na', 'Ha', 'Yoo', 'Sung', 'Bang', 'Jin', 'Hong', 'Cha', 'Byun', 'Ma', 'No', 'Bae', 'Koo', 'Min', 'Kim2', 'Lee2'];

$userId = 3; // 시작 ID

// 루트 회원 설정
$users[1] = [
    'id' => 1,
    'login_id' => 'root',
    'name' => 'Company',
    'sponsored_by' => null,
    'referred_by' => null,
    'position' => null
];

$users[2] = [
    'id' => 2,
    'login_id' => 'user2',
    'name' => 'System',
    'sponsored_by' => 1,
    'referred_by' => 1,
    'position' => 'left'
];

// 회원 생성 (ID 3부터 50까지)
for ($i = 3; $i <= 50; $i++) {
    $id = $userId++;
    $users[$id] = [
        'id' => $id,
        'login_id' => 'user' . $id,
        'name' => $names[$i % count($names)],
        'email' => strtolower($names[$i % count($names)]) . '@example.com',
        'phone' => '010-1234-' . str_pad($id, 4, '0', STR_PAD_LEFT),
        'country' => 'KR',
        'rank' => '회원',
        'organization' => 'Organization ' . chr(65 + ($i % 5)),
        'referred_by' => rand(1, $id - 1),
        'sponsored_by' => rand(1, $id - 1),
        'position' => $positions[$i % 2]
    ];
}

// 데이터베이스에 저장
$stmt = $mysqli->prepare("
    INSERT INTO users (`id`, `login_id`, `name`, `email`, `phone`, `country`, `rank`, `referred_by`, `sponsored_by`, `position`, `organization`, `password`, `is_admin`, `status`, `created_at`, `point`, `usdt`, `stock`, `token`, `myAmount`, `referral_count`, `left_amounts`, `left_members`, `right_amounts`, `right_members`)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'active', NOW(), 0, 0, 0, 0, 0, 0, 0, 0, 0, 0)
");

if (!$stmt) {
    die("SQL preparation failed: " . $mysqli->error);
}

$success_count = 0;
$failures = [];

foreach ($users as $user) {
    if ($user['id'] == 1 || $user['id'] == 2) continue; // 루트 회원 제외

    $password_hash = password_hash('password', PASSWORD_BCRYPT);

    $stmt->bind_param(
        "issssssiiiss",
        $user['id'],
        $user['login_id'],
        $user['name'],
        $user['email'],
        $user['phone'],
        $user['country'],
        $user['rank'],
        $user['referred_by'],
        $user['sponsored_by'],
        $user['position'],
        $user['organization'],
        $password_hash
    );

    if ($stmt->execute()) {
        $success_count++;
    } else {
        $failures[] = [
            'user_id' => $user['id'],
            'error' => $stmt->error
        ];
    }
}

$stmt->close();
$mysqli->close();

// 결과 출력
echo "<h2>데이터 저장 결과</h2>";
echo "<p>성공적으로 저장된 사용자 수: <strong>{$success_count}</strong></p>";

if (!empty($failures)) {
    echo "<h3>실패한 사용자</h3>";
    echo "<ul>";
    foreach ($failures as $failure) {
        echo "<li>User ID: {$failure['user_id']} - Error: {$failure['error']}</li>";
    }
    echo "</ul>";
} else {
    echo "<p>모든 데이터가 성공적으로 저장되었습니다.</p>";
}
?>
