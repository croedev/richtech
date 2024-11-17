<?php
// functions_bonus.php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/richtech.club/pages/error.log');

require_once __DIR__ . '/../includes/config.php';


// 추천수당 계산 함수
function calculate_referral_bonus($order_id, $conn) {
    // 주문 정보 가져오기 (created_at 추가)
    $stmt = $conn->prepare("SELECT user_id, total_amount, created_at FROM orders WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $source_user_id = $order['user_id'];
    $source_amount = $order['total_amount'];
    $order_date = $order['created_at']; // 주문일자 저장

    $current_user_id = $source_user_id;
    $level = 1;
    $max_level = 5;
    $commission_rates = [1 => 10, 2 => 3, 3 => 2, 4 => 2, 5 => 2]; // 단계별 수수료 비율

    while ($level <= $max_level) {
        // 추천인 가져오기
        $stmt = $conn->prepare("SELECT referred_by FROM users WHERE id = ?");
        $stmt->bind_param("i", $current_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        if ($result->num_rows == 0) {
            break; // 더 이상 추천인이 없음
        }

        $user = $result->fetch_assoc();
        $referred_by = $user['referred_by'];

        if (is_null($referred_by)) {
            break; // 추천인이 없음
        }

        // 수당 계산
        $commission_rate = $commission_rates[$level];
        $amount = $source_amount * ($commission_rate / 100);

        // 수당 지급 내역 저장 (order_date 컬럼 추가)
        $stmt = $conn->prepare("INSERT INTO bonus_referral (user_id, order_id, source_user_id, source_amount, level, commission_rate, amount, order_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiidids", $referred_by, $order_id, $source_user_id, $source_amount, $level, $commission_rate, $amount, $order_date);
        $stmt->execute();
        $stmt->close();

        // 회원의 추천수당 및 포인트 업데이트
        $stmt = $conn->prepare("UPDATE users SET bonus_referral = bonus_referral + ?, point = point + ? WHERE id = ?");
        $stmt->bind_param("ddi", $amount, $amount, $referred_by);
        $stmt->execute();
        $stmt->close();

        // 다음 레벨로 이동
        $current_user_id = $referred_by;
        $level++;
    }

    // 수당 지급 완료 후 주문의 paid_referral 업데이트
    $stmt = $conn->prepare("UPDATE orders SET paid_referral = 'completed' WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $stmt->close();
}



// 직급수당 계산 함수 (수정)
function calculate_rank_bonus($conn, $date) {
    $conn->begin_transaction();

    try {
        // 1. 회사 전체 매출 집계
        $start_time = $date . ' 00:00:00';
        $end_time = $date . ' 23:59:59';

        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(total_amount), 0) as total_sales
            FROM orders
            WHERE created_at BETWEEN ? AND ?
            AND status = 'completed'
            AND paid_status = 'pending'
        ");
        $stmt->bind_param("ss", $start_time, $end_time);
        $stmt->execute();
        $total_sales = $stmt->get_result()->fetch_assoc()['total_sales'];
        $stmt->close();

        if ($total_sales <= 0) {
            throw new Exception("해당 일자의 매출이 없습니다: $date");
        }

        // 2. 각 직급별 수당 풀 계산 및 지급
        $commission_rates = [
            '1스타' => 9,
            '2스타' => 4,
            '3스타' => 3,
            '4스타' => 3,
            '5스타' => 2,
            '6스타' => 1.5,
            '7스타' => 1.5
        ];

        foreach ($commission_rates as $rank => $rate) {
            // 해당 직급의 수당 대상 직급 구하기
            $eligible_ranks = get_eligible_ranks($rank); // 수당 대상 직급 (applicable_ranks)

            // 대상 회원 목록 조회
            $members = get_rank_members($conn, $eligible_ranks); // 수당 대상 회원

            $total_rank_members = count($members); // 대상 회원 수

            if ($total_rank_members == 0) {
                continue; // 대상자가 없으면 다음으로
            }

            // 수당 풀 계산
            $bonus_pool = $total_sales * ($rate / 100);
            $amount_per_member = $bonus_pool / $total_rank_members;

            // applicable_ranks를 문자열로 변환 (예: '2스타,3스타')
            $applicable_ranks_str = implode(',', $eligible_ranks);

            // 각 회원별 수당 지급
            foreach ($members as $member) {
                // 수당 지급 내역 저장
                $stmt_insert = $conn->prepare("
                    INSERT INTO bonus_rank (
                        user_id, rank, bonus_type, total_company_sales, commission_rate, amount, 
                        applicable_ranks, total_rank_members, calculation_date, created_at
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $bonus_type = $rank . '수당';
                $stmt_insert->bind_param(
                    "issdidsis",
                    $member['id'],
                    $rank,
                    $bonus_type,
                    $total_sales,
                    $rate,
                    $amount_per_member,
                    $applicable_ranks_str,
                    $total_rank_members,
                    $date
                );
                $stmt_insert->execute();
                $stmt_insert->close();

                // 회원의 직급수당 및 포인트 업데이트
                $stmt_update = $conn->prepare("
                    UPDATE users
                    SET bonus_rank = bonus_rank + ?, point = point + ?
                    WHERE id = ?
                ");
                $stmt_update->bind_param("ddi", $amount_per_member, $amount_per_member, $member['id']);
                $stmt_update->execute();
                $stmt_update->close();
            }
        }

        // 수당 지급 완료 후 주문의 paid_status 업데이트
        $stmt = $conn->prepare("
            UPDATE orders
            SET paid_status = 'completed'
            WHERE created_at BETWEEN ? AND ?
            AND status = 'completed'
            AND paid_status = 'pending'
        ");
        $stmt->bind_param("ss", $start_time, $end_time);
        $stmt->execute();
        $stmt->close();

        $conn->commit();

    } catch (Exception $e) {
        $conn->rollback();
        error_log("직급수당 계산 오류 ($date): " . $e->getMessage());
        throw $e;
    }
}

// 수당 대상 직급 구하기 함수
function get_eligible_ranks($rank) {
    switch ($rank) {
        case '1스타':
            return ['1스타'];
        case '2스타':
            return ['2스타', '3스타'];
        case '3스타':
            return ['3스타', '4스타'];
        case '4스타':
            return ['4스타', '5스타'];
        case '5스타':
            return ['5스타', '6스타'];
        case '6스타':
            return ['6스타', '7스타'];
        case '7스타':
            return ['7스타'];
        default:
            return [];
    }
}

// 특정 직급의 회원 목록 조회 함수
function get_rank_members($conn, $ranks) {
    if (empty($ranks)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ranks), '?'));
    $types = str_repeat('s', count($ranks));

    $stmt = $conn->prepare("SELECT id FROM users WHERE rank IN ($placeholders)");
    $stmt->bind_param($types, ...$ranks);
    $stmt->execute();
    $members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $members;
}

// 센터수당 계산 함수 (수정됨)
function calculate_center_bonus($conn, $date) {
    $conn->begin_transaction();

    try {
        // 활성 센터 목록 조회 및 센터장 정보 가져오기
        $stmt = $conn->prepare("
            SELECT o.id as organization_id, o.name as center_name, o.user_id as center_user_id
            FROM organizations o
            WHERE o.user_id IS NOT NULL
        ");
        $stmt->execute();
        $centers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (empty($centers)) {
            throw new Exception("활성 센터가 없습니다.");
        }

        foreach ($centers as $center) {
            $organization_id = $center['organization_id'];
            $center_name = $center['center_name'];
            $center_user_id = $center['center_user_id'];

            // 센터별 매출 집계
            $start_time = $date . ' 00:00:00';
            $end_time = $date . ' 23:59:59';

            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(o.total_amount), 0) as total_sales
                FROM orders o
                JOIN users u ON o.user_id = u.id
                WHERE u.organization = ? AND o.created_at BETWEEN ? AND ? AND o.status = 'completed' AND o.paid_center = 'pending'
            ");
            $stmt->bind_param("sss", $center_name, $start_time, $end_time);
            $stmt->execute();
            $total_sales = $stmt->get_result()->fetch_assoc()['total_sales'];
            $stmt->close();

            if ($total_sales <= 0) {
                continue; // 매출이 없으면 다음 센터로
            }

            // 센터 수당 계산 (2%)
            $commission_rate = 2;
            $amount = $total_sales * ($commission_rate / 100);

            // 수당 지급 내역 저장
            $stmt = $conn->prepare("
                INSERT INTO bonus_center (organization_id, user_id, total_sales, commission_rate, amount, sales_date)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iiddds", $organization_id, $center_user_id, $total_sales, $commission_rate, $amount, $date);
            $stmt->execute();
            $stmt->close();

            // 센터장의 센터수당 및 포인트 업데이트
            $stmt = $conn->prepare("
                UPDATE users
                SET bonus_center = bonus_center + ?, point = point + ?
                WHERE id = ?
            ");
            $stmt->bind_param("ddi", $amount, $amount, $center_user_id);
            $stmt->execute();
            $stmt->close();

            // 수당 지급 완료 후 주문의 paid_center 업데이트
            $stmt = $conn->prepare("
                UPDATE orders o
                JOIN users u ON o.user_id = u.id
                SET o.paid_center = 'completed'
                WHERE u.organization = ? AND o.created_at BETWEEN ? AND ? AND o.status = 'completed' AND o.paid_center = 'pending'
            ");
            $stmt->bind_param("sss", $center_name, $start_time, $end_time);
            $stmt->execute();
            $stmt->close();
        }

        $conn->commit();

    } catch (Exception $e) {
        $conn->rollback();
        error_log("센터수당 계산 오류 ($date): " . $e->getMessage());
        throw $e;
    }
}

// 회원들의 실적 업데이트 및 직급 승급 계산 함수
function update_user_performance_and_rank($conn) {
    // 모든 회원들의 실적 업데이트
    $stmt = $conn->prepare("SELECT id FROM users");
    $stmt->execute();
    $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($users as $user) {
        $user_id = $user['id'];

        // 본인 누적 매출 업데이트
        $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) as myAmount FROM orders WHERE user_id = ? AND status = 'completed'");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        $myAmount = $result->fetch_assoc()['myAmount'];

        // 직추천인 수 업데이트
        $stmt = $conn->prepare("SELECT COUNT(*) as referral_count FROM users WHERE referred_by = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        $referral_count = $result->fetch_assoc()['referral_count'];

        // 좌우 실적 및 회원 수 업데이트
        $left_result = calculate_leg_volume($conn, $user_id, 'left');
        $right_result = calculate_leg_volume($conn, $user_id, 'right');

        $left_amounts = $left_result['amount'];
        $left_members = $left_result['members'];
        $right_amounts = $right_result['amount'];
        $right_members = $right_result['members'];

        // 소실적 계산
        $lesser_leg_amount = min($left_amounts, $right_amounts);

        // users 테이블 업데이트
        $stmt = $conn->prepare("UPDATE users SET myAmount = ?, referral_count = ?, left_amounts = ?, left_members = ?, right_amounts = ?, right_members = ? WHERE id = ?");
        $stmt->bind_param("diddidi", $myAmount, $referral_count, $left_amounts, $left_members, $right_amounts, $right_members, $user_id);
        $stmt->execute();
        $stmt->close();

        // 직급 승급 계산
       try {
            calculate_rank_promotion($conn, $user_id, $myAmount, $referral_count, $lesser_leg_amount, $left_amounts, $right_amounts);
        } catch (Exception $e) {
            error_log("회원 ID {$user_id}의 직급 승급 중 오류 발생: " . $e->getMessage());
            // 필요에 따라 추가적인 처리
        }
    }
}

// 좌우 실적 및 회원 수 계산 함수
function calculate_leg_volume($conn, $user_id, $position) {
    $members = 0;
    $amount = 0.0;

    // 해당 회원의 해당 위치의 직속 후원인 가져오기
    $stmt = $conn->prepare("SELECT id FROM users WHERE sponsored_by = ? AND position = ?");
    $stmt->bind_param("is", $user_id, $position);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows == 0) {
        return ['members' => 0, 'amount' => 0.0];
    }

    $first_member = $result->fetch_assoc();
    $first_member_id = $first_member['id'];

    // 재귀적으로 조직을 탐색하여 회원 수와 매출 합계를 계산
    $visited = [];
    traverse_leg($conn, $first_member_id, $visited, $members, $amount);

    return ['members' => $members, 'amount' => $amount];
}

// 조직 트리를 재귀적으로 탐색하는 함수
function traverse_leg($conn, $user_id, &$visited, &$members, &$amount) {
    if (in_array($user_id, $visited)) {
        return;
    }
    $visited[] = $user_id;

    // 회원 수 증가
    $members += 1;

    // 해당 회원의 본인 누적 매출 가져오기
    $stmt = $conn->prepare("SELECT myAmount FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $user_myAmount = $user['myAmount'];
    $amount += $user_myAmount;

    // 해당 회원의 후원인으로 등록된 회원들을 가져옴
    $stmt = $conn->prepare("SELECT id FROM users WHERE sponsored_by = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $downlines = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($downlines as $downline) {
        traverse_leg($conn, $downline['id'], $visited, $members, $amount);
    }
}

// 직급 승급 계산 함수 (수정)
function calculate_rank_promotion($conn, $user_id, $myAmount, $referral_count, $lesser_leg_amount, $left_amounts, $right_amounts) {
    // 승급 기준 설정
    $rank_requirements = [
        '1스타' => ['myAmount' => 1000, 'referral_count' => 2, 'lesser_leg_amount' => 10000],
        '2스타' => ['myAmount' => 1000, 'referral_count' => 3, 'lesser_leg_amount' => 30000],
        '3스타' => ['myAmount' => 2000, 'referral_count' => 4, 'lesser_leg_amount' => 50000],
        '4스타' => ['myAmount' => 2000, 'referral_count' => 5, 'lesser_leg_amount' => 100000],
        '5스타' => ['myAmount' => 3000, 'referral_count' => 6, 'lesser_leg_amount' => 300000],
        '6스타' => ['myAmount' => 3000, 'referral_count' => 7, 'lesser_leg_amount' => 700000],
        '7스타' => ['myAmount' => 3000, 'referral_count' => 8, 'lesser_leg_amount' => 1000000],
    ];

   // 현재 직급 가져오기
    $stmt = $conn->prepare("SELECT rank FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_rank = $result->fetch_assoc()['rank'];
    $stmt->close();

    // 승급 가능한 직급 찾기
    $new_rank = $current_rank;
    foreach ($rank_requirements as $rank => $requirements) {
        if ($myAmount >= $requirements['myAmount'] && $referral_count >= $requirements['referral_count'] && $lesser_leg_amount >= $requirements['lesser_leg_amount']) {
            $new_rank = $rank;
        } else {
            break;
        }
    }

     if ($new_rank != $current_rank) {
        // 승급일 계산: 승급 기준을 최초로 충족한 날짜 찾기
        $change_date = find_promotion_date($conn, $user_id, $rank_requirements[$new_rank]);

        // 직급 업데이트
        $stmt = $conn->prepare("UPDATE users SET rank = ? WHERE id = ?");
        $stmt->bind_param("si", $new_rank, $user_id);
        if (!$stmt->execute()) {
            throw new Exception("users 테이블 업데이트 중 오류 발생: " . $stmt->error);
        }
        $stmt->close();

        // 직급 변동 내역 저장
        $stmt = $conn->prepare("
            INSERT INTO rank_history (
                user_id, previous_rank, new_rank, change_date, personal_sales, direct_referrals, 
                left_sales, right_sales, lesser_leg_sales, calculation_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $calculation_date = date('Y-m-d');
        $stmt->bind_param(
            "issdidddds",
            $user_id,
            $current_rank,
            $new_rank,
            $change_date,
            $myAmount,
            $referral_count,
            $left_amounts,
            $right_amounts,
            $lesser_leg_amount,
            $calculation_date
        );
        if (!$stmt->execute()) {
            throw new Exception("rank_history 저장 중 오류 발생: " . $stmt->error);
        }
        $stmt->close();
    }
}


// 승급 기준 충족 날짜 찾기 함수 (추가)
function find_promotion_date($conn, $user_id, $requirements) {
    // 개인 매출 누적 합계 추적
    $total_myAmount = 0;
    // 소실적 누적 합계 추적
    $total_lesser_leg_amount = 0;
    // 직추천인 수
    $referral_count = 0;

    // 개인 매출 내역 조회 (날짜별 누적)
    $stmt = $conn->prepare("
        SELECT DATE(payment_date) as date, SUM(total_amount) as daily_total
        FROM orders
        WHERE user_id = ? AND status = 'completed'
        GROUP BY DATE(payment_date)
        ORDER BY DATE(payment_date)
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // 직추천인 가입 날짜 조회
    $stmt = $conn->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as daily_count
        FROM users
        WHERE referred_by = ?
        GROUP BY DATE(created_at)
        ORDER BY DATE(created_at)
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $referrals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // 좌우 소���적 내역은 계산이 복잡하므로, 여기서는 단순히 현재 날짜를 반환합니다.
    // 정확한 승급 날짜를 계산하려면, 좌우 조직의 매출을 날짜별로 누적 합산해야 합니다.

    // TODO: 좌우 소실적의 날짜별 누적 합산을 구현하여 정확한 승급 날짜를 계산

    // 임시로 현재 날짜 반환
    return date('Y-m-d');
}




// 회사 일일 통계 저장 함수
function save_company_state($conn, $date) {
    // 필요한 통계 데이터 계산

    // 1. 신규 가입 회원 수
    $stmt = $conn->prepare("SELECT COUNT(*) as new_members FROM users WHERE DATE(created_at) = ?");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $new_members = $stmt->get_result()->fetch_assoc()['new_members'];
    $stmt->close();

    // 2. 회사 매출 (당일 주문 합계)
    $stmt = $conn->prepare("SELECT SUM(total_amount) as company_sales FROM orders WHERE DATE(created_at) = ? AND status = 'completed'");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $company_sales = $stmt->get_result()->fetch_assoc()['company_sales'];
    $stmt->close();
    $company_sales = $company_sales ?: 0;

    // 3. 회사 입금 금액 (당일 입금 합계)
    $stmt = $conn->prepare("SELECT SUM(amount_usdt) as company_deposits FROM deposits WHERE DATE(created_at) = ? AND status = 'completed'");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $company_deposits = $stmt->get_result()->fetch_assoc()['company_deposits'];
    $stmt->close();
    $company_deposits = $company_deposits ?: 0;

    // 4. 회사 출금 금액 (당일 출금 합계)
    $stmt = $conn->prepare("SELECT SUM(actual_amount_usdt) as company_withdrawals FROM withdrawals WHERE DATE(processed_at) = ? AND status = 'completed'");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $company_withdrawals = $stmt->get_result()->fetch_assoc()['company_withdrawals'];
    $stmt->close();
    $company_withdrawals = $company_withdrawals ?: 0;

    // 5. 추천수당 지급 합계
    $stmt = $conn->prepare("SELECT SUM(amount) as bonus_referral FROM bonus_referral WHERE DATE(created_at) = ?");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $bonus_referral = $stmt->get_result()->fetch_assoc()['bonus_referral'];
    $stmt->close();
    $bonus_referral = $bonus_referral ?: 0;

    // 6. 직급수당 지급 합계
    $stmt = $conn->prepare("SELECT SUM(amount) as bonus_rank FROM bonus_rank WHERE calculation_date = ?");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $bonus_rank = $stmt->get_result()->fetch_assoc()['bonus_rank'];
    $stmt->close();
    $bonus_rank = $bonus_rank ?: 0;

    // 7. 센터수당 지급 합계
    $stmt = $conn->prepare("SELECT SUM(amount) as bonus_center FROM bonus_center WHERE sales_date = ?");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $bonus_center = $stmt->get_result()->fetch_assoc()['bonus_center'];
    $stmt->close();
    $bonus_center = $bonus_center ?: 0;

    // 8. 회사 지급 주식 수 (당일 지급된 주식 수 합계)
    $stmt = $conn->prepare("SELECT SUM(stock) as company_stock_paid FROM orders WHERE DATE(created_at) = ? AND status = 'completed'");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $company_stock_paid = $stmt->get_result()->fetch_assoc()['company_stock_paid'];
    $stmt->close();
    $company_stock_paid = $company_stock_paid ?: 0;

    // 9. 회사 지급 토큰 수 (당일 지급된 토큰 수 합계)
    $stmt = $conn->prepare("SELECT SUM(token) as company_token_paid FROM orders WHERE DATE(created_at) = ? AND status = 'completed'");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $company_token_paid = $stmt->get_result()->fetch_assoc()['company_token_paid'];
    $stmt->close();
    $company_token_paid = $company_token_paid ?: 0;

    // 10. 각 직급별 회원 수
    $ranks = ['1스타', '2스타', '3스타', '4스타', '5스타', '6스타', '7스타'];
    $rank_counts = [];
    foreach ($ranks as $rank) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE rank = ?");
        $stmt->bind_param("s", $rank);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
        $rank_counts[$rank] = $count ?: 0;
    }

    // 11. 센터 수
    $stmt = $conn->prepare("SELECT COUNT(*) as total_centers FROM organizations");
    $stmt->execute();
    $total_centers = $stmt->get_result()->fetch_assoc()['total_centers'];
    $stmt->close();

    // 12. 주식 계좌 및 마스터 계좌 잔액 계산
    $stock_account = $company_sales * 0.37; // 37%를 주식계정으로
    $master_account = $company_sales * 0.63; // 63%를 마스터계정으로

    // 회사 일일 통계 저장
    $stmt = $conn->prepare("
        INSERT INTO company_state (
            date, new_members, company_sales, company_deposits, company_withdrawals,
            bonus_referral, bonus_rank, bonus_center, company_stock_paid, company_token_paid,
            rank_1star, rank_2star, rank_3star, rank_4star, rank_5star, rank_6star, rank_7star,
            total_centers, stock_account, master_account
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
   $stmt->bind_param(
    "siddddddidiiiiiiiidd",
    $date,
    $new_members,
    $company_sales,
    $company_deposits,
    $company_withdrawals,
    $bonus_referral,
    $bonus_rank,
    $bonus_center,
    $company_stock_paid,
    $company_token_paid,
    $rank_counts['1스타'],
    $rank_counts['2스타'],
    $rank_counts['3스타'],
    $rank_counts['4스타'],
    $rank_counts['5스타'],
    $rank_counts['6스타'],
    $rank_counts['7스타'],
    $total_centers,
    $stock_account,
    $master_account
);
    $stmt->execute();
    $stmt->close();
}