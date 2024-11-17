<?php
/**
 * 직급수당 계산 메인 클래스
 * 지급 순서:
 * 1. 어제까지 확정된 직급자 조회
 * 2. 오늘 회사 매출 집계
 * 3. 직급별 수당 풀 계산
 * 4. 직급별 수당 분배
 * 5. 수당 지급 내역 기록
 * 6. 포인트 업데이트
 */
class RankBonusCalculator {
    private $conn;
    private $settlement_date;
    private $total_sales;
    private $rank_counts;
    private $job_id;

    private $commission_rates = [
        '1스타' => 9,
        '2스타' => 4, 
        '3스타' => 3,
        '4스타' => 2,
        '5스타' => 2,
        '6스타' => 2,
        '7스타' => 2
    ];

    public function __construct($conn, $date, $job_id) {
        $this->conn = $conn;
        $this->settlement_date = $date;
        $this->job_id = $job_id;
    }

    /**
     * 직급수당 계산 메인 프로세스
     */
    public function calculate() {
        try {
            $this->conn->begin_transaction();

            // 1. 회사 매출 집계
            $this->total_sales = $this->calculateTotalSales();
            $this->logProgress("회사 매출 집계 완료: $" . number_format($this->total_sales, 2));

            // 2. 직급별 회원 수 집계
            $this->rank_counts = $this->countMembersByRank();
            $this->logProgress("직급별 회원 수 집계 완료");

            // 3. 직급별로 수당 계산 및 지급
            $this->processRankBonuses();
            $this->logProgress("직급별 수당 지급 완료");

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            $this->conn->rollback();
            $this->logError("직급수당 계산 중 오류: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 오늘 발생한 회사 매출 총액 계산
     */
    private function calculateTotalSales(): float {
        $start_time = $this->settlement_date . ' 00:00:00';
        $end_time = $this->settlement_date . ' 23:59:59';

        $sql = "
            SELECT COALESCE(SUM(total_amount), 0) as total_sales 
            FROM orders 
            WHERE created_at BETWEEN ? AND ? 
            AND status = 'completed' 
            AND paid_status = 'pending'
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ss", $start_time, $end_time);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        return floatval($result['total_sales']);
    }

    /**
     * 어제까지 확정된 직급별 회원 수 집계
     */
    private function countMembersByRank(): array {
        $ranks = ['1스타', '2스타', '3스타', '4스타', '5스타', '6스타', '7스타'];
        $counts = [];

        foreach ($ranks as $rank) {
            $sql = "SELECT COUNT(*) as count FROM users WHERE rank = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("s", $rank);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $counts[$rank] = intval($result['count']);
            
            $this->logProgress("$rank 직급 회원 수: {$counts[$rank]}명");
        }

        return $counts;
    }

    /**
     * 직급별 수당 계산 및 지급 처리
     */
    private function processRankBonuses() {
        foreach ($this->commission_rates as $rank => $rate) {
            // 해당 직급의 수당 대상자 구하기
            $eligible_ranks = $this->getEligibleRanks($rank);
            $total_eligible_members = $this->calculateTotalEligibleMembers($eligible_ranks);

            if ($total_eligible_members == 0) {
                $this->logProgress("$rank : 대상자 없음, 다음 직급으로 진행");
                continue;
            }

            // 수당 풀 계산
            $bonus_pool = $this->total_sales * ($rate / 100);
            $amount_per_member = $bonus_pool / $total_eligible_members;

            $this->logProgress("$rank 수당풀: $" . number_format($bonus_pool, 2) . 
                             ", 1인당: $" . number_format($amount_per_member, 2));

            // 대상자별 수당 지급
            $this->distributeBonusToMembers($rank, $eligible_ranks, $amount_per_member);
        }
    }

    /**
     * 각 직급별 수당 대상 직급 목록 반환
     */
    private function getEligibleRanks($rank): array {
        $eligible_ranks = [];
        switch ($rank) {
            case '1스타':
                $eligible_ranks = ['1스타'];
                break;
            case '2스타':
                $eligible_ranks = ['2스타', '3스타'];
                break;
            case '3스타':
                $eligible_ranks = ['3스타', '4스타'];
                break;
            case '4스타':
                $eligible_ranks = ['4스타', '5스타'];
                break;
            case '5스타':
                $eligible_ranks = ['5스타', '6스타'];
                break;
            case '6스타':
                $eligible_ranks = ['6스타', '7스타'];
                break;
            case '7스타':
                $eligible_ranks = ['7스타'];
                break;
        }
        return $eligible_ranks;
    }

    /**
     * 해당 직급의 총 대상자 수 계산
     */
    private function calculateTotalEligibleMembers($eligible_ranks): int {
        $total = 0;
        foreach ($eligible_ranks as $rank) {
            $total += ($this->rank_counts[$rank] ?? 0);
        }
        return $total;
    }

    /**
     * 대상자별 수당 지급 처리
     */
    private function distributeBonusToMembers($rank, $eligible_ranks, $amount_per_member) {
        // IN 절을 위한 플레이스홀더 생성
        $placeholders = str_repeat('?,', count($eligible_ranks) - 1) . '?';
        
        $sql = "SELECT id, rank FROM users WHERE rank IN ($placeholders)";
        $stmt = $this->conn->prepare($sql);
        
        // bind_param을 위한 타입 문자열과 값 배열 생성
        $types = str_repeat('s', count($eligible_ranks));
        $stmt->bind_param($types, ...$eligible_ranks);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($member = $result->fetch_assoc()) {
            // 수당 지급 내역 저장
            $this->saveBonusRecord($member['id'], $rank, $amount_per_member);
            
            // 회원 포인트 업데이트
            $this->updateMemberPoints($member['id'], $amount_per_member);
        }
    }

    /**
     * 수당 지급 내역 저장
     */
    private function saveBonusRecord($user_id, $rank, $amount) {
        $sql = "
            INSERT INTO bonus_rank (
                user_id, rank, bonus_type, total_company_sales, 
                commission_rate, amount, calculation_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ";
        
        $bonus_type = $rank . '수당';
        $rate = $this->commission_rates[$rank];
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param(
            "issdids",
            $user_id,
            $rank,
            $bonus_type,
            $this->total_sales,
            $rate,
            $amount,
            $this->settlement_date
        );
        $stmt->execute();
    }

    /**
     * 회원 포인트 업데이트
     */
    private function updateMemberPoints($user_id, $amount) {
        $sql = "
            UPDATE users 
            SET bonus_rank = bonus_rank + ?,
                point = point + ?
            WHERE id = ?
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ddi", $amount, $amount, $user_id);
        $stmt->execute();
    }

    /**
     * 진행상황 로깅
     */
    private function logProgress($message) {
        $sql = "
            INSERT INTO settlement_step_logs 
            (job_id, step, message, start_time) 
            VALUES (?, 'rank_bonus', ?, NOW())
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("is", $this->job_id, $message);
        $stmt->execute();
    }

    /**
     * 에러 로깅
     */
    private function logError($message) {
        $sql = "
            UPDATE settlement_jobs 
            SET error_message = ?, 
                status = 'failed'
            WHERE id = ?
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("si", $message, $this->job_id);
        $stmt->execute();
    }
}


?>




<?php
/**
 * 직급 승급 계산 클래스
 * 프로세스:
 * 1. 회원별 본인 누적 매출 계산
 * 2. 회원별 직추천 회원수 계산
 * 3. 회원별 좌우 실적 계산
 * 4. 소실적 계산
 * 5. 승급 조건 검사
 * 6. 직급 변경 및 이력 저장
 */
class RankPromotionCalculator {
    private $conn;
    private $settlement_date;
    private $job_id;

    // 직급별 승급 요구사항
    private $rank_requirements = [
        '1스타' => ['myAmount' => 1000, 'referral_count' => 2, 'lesser_leg_amount' => 10000],
        '2스타' => ['myAmount' => 1000, 'referral_count' => 3, 'lesser_leg_amount' => 30000],
        '3스타' => ['myAmount' => 2000, 'referral_count' => 4, 'lesser_leg_amount' => 50000],
        '4스타' => ['myAmount' => 2000, 'referral_count' => 5, 'lesser_leg_amount' => 100000],
        '5스타' => ['myAmount' => 3000, 'referral_count' => 6, 'lesser_leg_amount' => 300000],
        '6스타' => ['myAmount' => 3000, 'referral_count' => 7, 'lesser_leg_amount' => 700000],
        '7스타' => ['myAmount' => 3000, 'referral_count' => 8, 'lesser_leg_amount' => 1000000],
    ];

    public function __construct($conn, $date, $job_id) {
        $this->conn = $conn;
        $this->settlement_date = $date;
        $this->job_id = $job_id;
    }

    /**
     * 직급 승급 계산 메인 프로세스
     */
    public function calculate() {
        try {
            $this->conn->begin_transaction();

            // 1. 변경이 필요한 회원 목록 조회
            $this->logProgress("회원별 실적 계산 시작");
            $members = $this->getTargetMembers();
            $this->logProgress("대상 회원 수: " . count($members));

            // 2. 회원별로 승급 조건 검사
            foreach ($members as $member) {
                $this->processMemberPromotion($member['id']);
            }

            $this->conn->commit();
            $this->logProgress("직급 승급 처리 완료");
            return true;

        } catch (Exception $e) {
            $this->conn->rollback();
            $this->logError("직급 승급 계산 중 오류: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 실적 변동이 있는 회원 목록 조회
     * - 오늘 주문이 있는 회원의 상위라인
     * - 오늘 추천한 회원이 있는 회원
     */
    private function getTargetMembers(): array {
        $start_time = $this->settlement_date . ' 00:00:00';
        $end_time = $this->settlement_date . ' 23:59:59';

        $sql = "
            WITH RECURSIVE upline AS (
                -- 오늘 주문이 있는 회원
                SELECT DISTINCT u.id, u.sponsored_by
                FROM users u
                JOIN orders o ON u.id = o.user_id
                WHERE o.created_at BETWEEN ? AND ?
                
                UNION
                
                -- 상위라인 추적
                SELECT u.id, u.sponsored_by
                FROM users u
                JOIN upline up ON u.id = up.sponsored_by
            )
            SELECT DISTINCT u.id, u.login_id, u.rank
            FROM users u
            LEFT JOIN upline up ON u.id = up.id
            WHERE up.id IS NOT NULL
            OR u.id IN (
                -- 오늘 추천한 회원이 있는 회원
                SELECT referred_by 
                FROM users 
                WHERE created_at BETWEEN ? AND ?
                AND referred_by IS NOT NULL
            )
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ssss", $start_time, $end_time, $start_time, $end_time);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * 개별 회원의 승급 조건 처리
     */
    private function processMemberPromotion($user_id) {
        // 1. 실적 계산
        $stats = $this->calculateMemberStats($user_id);
        
        // 2. 현재 직급 조회
        $current_rank = $this->getCurrentRank($user_id);
        
        // 3. 승급 가능한 최고 직급 찾기
        $new_rank = $this->determineNewRank($stats, $current_rank);
        
        // 4. 직급 변경이 필요한 경우 처리
        if ($new_rank !== $current_rank) {
            $this->updateMemberRank($user_id, $current_rank, $new_rank, $stats);
        }
    }

    /**
     * 회원의 모든 실적 통계 계산
     */
    private function calculateMemberStats($user_id): array {
        // 1. 본인 누적 매출
        $myAmount = $this->calculatePersonalSales($user_id);
        
        // 2. 직추천 회원수
        $referral_count = $this->calculateReferralCount($user_id);
        
        // 3. 좌우 실적
        $leg_volumes = $this->calculateLegVolumes($user_id);
        
        return array_merge(
            ['myAmount' => $myAmount, 'referral_count' => $referral_count],
            $leg_volumes
        );
    }

    /**
     * 본인 누적 매출 계산
     */
    private function calculatePersonalSales($user_id): float {
        $sql = "
            SELECT COALESCE(SUM(total_amount), 0) as total
            FROM orders
            WHERE user_id = ? 
            AND status = 'completed'
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc()['total'];
    }

    /**
     * 직추천 회원수 계산
     */
    private function calculateReferralCount($user_id): int {
        $sql = "
            SELECT COUNT(*) as count
            FROM users
            WHERE referred_by = ?
            AND status = 'active'
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc()['count'];
    }

    /**
     * 좌우 실적 계산 (WITH RECURSIVE 사용)
     */
    private function calculateLegVolumes($user_id): array {
        $volumes = [
            'left_amounts' => 0,
            'right_amounts' => 0,
            'left_members' => 0,
            'right_members' => 0,
            'lesser_leg_amount' => 0
        ];

        // 좌측 실적 계산
        $left_result = $this->calculateLegVolume($user_id, 'left');
        $volumes['left_amounts'] = $left_result['amount'];
        $volumes['left_members'] = $left_result['members'];

        // 우측 실적 계산
        $right_result = $this->calculateLegVolume($user_id, 'right');
        $volumes['right_amounts'] = $right_result['amount'];
        $volumes['right_members'] = $right_result['members'];

        // 소실적 계산
        $volumes['lesser_leg_amount'] = min(
            $volumes['left_amounts'],
            $volumes['right_amounts']
        );

        return $volumes;
    }

    /**
     * 특정 방향(좌/우)의 실적 계산
     */
    private function calculateLegVolume($user_id, $position): array {
        $sql = "
            WITH RECURSIVE downline AS (
                -- 초기 회원 선택
                SELECT id, myAmount
                FROM users 
                WHERE sponsored_by = ? 
                AND position = ?
                AND status = 'active'
                
                UNION ALL
                
                -- 하위 회원 재귀적 선택
                SELECT u.id, u.myAmount
                FROM users u
                INNER JOIN downline d ON u.sponsored_by = d.id
                WHERE u.status = 'active'
            )
            SELECT 
                COUNT(*) as members,
                COALESCE(SUM(myAmount), 0) as amount
            FROM downline
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("is", $user_id, $position);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * 회원의 현재 직급 조회
     */
    private function getCurrentRank($user_id): string {
        $sql = "SELECT rank FROM users WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc()['rank'];
    }

    /**
     * 승급 가능한 새로운 직급 결정
     */
    private function determineNewRank(array $stats, string $current_rank): string {
        $new_rank = $current_rank;

        foreach ($this->rank_requirements as $rank => $requirements) {
            if ($stats['myAmount'] >= $requirements['myAmount'] &&
                $stats['referral_count'] >= $requirements['referral_count'] &&
                $stats['lesser_leg_amount'] >= $requirements['lesser_leg_amount']) {
                $new_rank = $rank;
            } else {
                break;
            }
        }

        return $new_rank;
    }

    /**
     * 회원 직급 업데이트 및 이력 저장
     */
    private function updateMemberRank($user_id, $current_rank, $new_rank, $stats) {
        // 1. 직급 업데이트
        $sql = "UPDATE users SET rank = ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("si", $new_rank, $user_id);
        $stmt->execute();

        // 2. 직급 변경 이력 저장
        $sql = "
            INSERT INTO rank_history (
                user_id, previous_rank, new_rank, personal_sales,
                direct_referrals, left_sales, right_sales,
                lesser_leg_sales, calculation_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param(
            "issdiddds",
            $user_id,
            $current_rank,
            $new_rank,
            $stats['myAmount'],
            $stats['referral_count'],
            $stats['left_amounts'],
            $stats['right_amounts'],
            $stats['lesser_leg_amount'],
            $this->settlement_date
        );
        $stmt->execute();

        $this->logProgress(
            sprintf(
                "회원 승급 완료 - ID: %d, %s → %s",
                $user_id,
                $current_rank,
                $new_rank
            )
        );
    }

    /**
     * 진행상황 로깅
     */
    private function logProgress($message) {
        $sql = "
            INSERT INTO settlement_step_logs 
            (job_id, step, message, start_time) 
            VALUES (?, 'rank_promotion', ?, NOW())
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("is", $this->job_id, $message);
        $stmt->execute();
    }

    /**
     * 에러 로깅
     */
    private function logError($message) {
        $sql = "
            UPDATE settlement_jobs 
            SET error_message = ?, 
                status = 'failed'
            WHERE id = ?
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("si", $message, $this->job_id);
        $stmt->execute();
    }
}