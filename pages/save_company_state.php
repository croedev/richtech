function save_company_state($conn, $date) {
    // 필요한 통계 데이터 계산
    // 예시로 몇 가지 데이터만 계산
    $stmt = $conn->prepare("SELECT COUNT(*) as new_members FROM users WHERE DATE(created_at) = ?");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $new_members = $stmt->get_result()->fetch_assoc()['new_members'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT SUM(total_amount) as company_sales FROM orders WHERE DATE(created_at) = ? AND status = 'completed'");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $company_sales = $stmt->get_result()->fetch_assoc()['company_sales'];
    $stmt->close();

    // 기타 필요한 데이터 계산 (여기서는 생략)

    // 회사 일일 통계 저장
    $stmt = $conn->prepare("INSERT INTO company_state (date, new_members, company_sales) VALUES (?, ?, ?)");
    $stmt->bind_param("sid", $date, $new_members, $company_sales);
    $stmt->execute();
    $stmt->close();
}
