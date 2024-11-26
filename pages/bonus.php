                <span class="detail-label">매출액</span>
                    <span class="detail-value fs-13">
                        $<?php echo number_format($bonus['source_amount'], 2); ?>
                    </span>
                </div>
                <div class="detail-row d-flex justify-content-between align-items-center">
                    <span class="detail-label">수당율</span>
                    <span class="detail-value text-gold fs-13">
                        <?php echo $bonus['level']; ?>대 (<?php echo $bonus['commission_rate']; ?>%)
                    </span>
                </div>
            </div>
        </div>
        <?php endforeach;
            else: ?>
        <div class="no-data">
            <i class="fas fa-info-circle"></i>
            해당 기간에 발생한 추천수당이 없습니다.
        </div>
        <?php endif;
        }
        ?>



        <?php
        // 직급수당 조회 및 표시
        if ($bonus_type == 'rank' || $bonus_type == 'all') {
            // 전체 레코드 수 조회
            $stmt = $conn->prepare("
                SELECT COUNT(*) as total 
                FROM bonus_rank 
                WHERE user_id = ? AND calculation_date BETWEEN ? AND ?
            ");
            $stmt->bind_param("iss", $user_id, $start_date, $end_date);
            $stmt->execute();
            $total = $stmt->get_result()->fetch_assoc()['total'];
            $total_pages = ceil($total / $limit);
            $stmt->close();

            // 해당 페이지의 데이터 조회
            $stmt = $conn->prepare("
                SELECT *
                FROM bonus_rank
                WHERE user_id = ? AND calculation_date BETWEEN ? AND ?
                ORDER BY calculation_date DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->bind_param("issii", $user_id, $start_date, $end_date, $limit, $offset);
            $stmt->execute();
            $rank_bonuses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            if (!empty($rank_bonuses)): ?>
        <h5 class="text-gold mb-3">
            <i class="fas fa-crown"></i> 직급수당 내역
        </h5>
        <?php foreach ($rank_bonuses as $bonus): ?>
        <div class="bonus-card">
            <div class="bonus-header">
                <div>
                    <span class="text-white">
                        <i class="far fa-calendar-alt"></i>
                        <?php echo date('Y-m-d', strtotime($bonus['calculation_date'])); ?>
                    </span>
                </div>
                <div class="amount-value">
                    $<?php echo number_format($bonus['amount'], 2); ?>
                </div>
            </div>
            <div class="bonus-body">
                <div class="detail-row">
                    <div class="detail-label">수당 종류</div>
                    <div class="detail-value">
                        <?php echo htmlspecialchars($bonus['bonus_type']); ?>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">본인 직급</div>
                    <div class="detail-value">
                        <span class="badge bg-gold">
                            <?php echo htmlspecialchars($bonus['rank']); ?>
                        </span>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">적용 대상 직급</div>
                    <div class="detail-value">
                        <?php echo htmlspecialchars($bonus['applicable_ranks']); ?>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">공유 비율</div>
                    <div class="detail-value text-gold">
                        <?php echo $bonus['commission_rate']; ?>%
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach;
            elseif ($bonus_type == 'rank'): ?>
        <div class="no-data">
            <i class="fas fa-info-circle"></i>
            해당 기간에 발생한 직급수당이 없습니다.
        </div>
        <?php endif;
        }

        // 센터수당 조회 및 표시
        if ($bonus_type == 'center' || $bonus_type == 'all') {
            // 전체 레코드 수 조회
            $stmt = $conn->prepare("
                SELECT COUNT(*) as total 
                FROM bonus_center 
                WHERE user_id = ? AND sales_date BETWEEN ? AND ?
            ");
            $stmt->bind_param("iss", $user_id, $start_date, $end_date);
            $stmt->execute();
            $total = $stmt->get_result()->fetch_assoc()['total'];
            $total_pages = ceil($total / $limit);
            $stmt->close();

            // 해당 페이지의 데이터 조회
            $stmt = $conn->prepare("
                SELECT bc.*, o.name as organization_name
                FROM bonus_center bc
                LEFT JOIN organizations o ON bc.organization_id = o.id
                WHERE bc.user_id = ? AND bc.sales_date BETWEEN ? AND ?
                ORDER BY bc.sales_date DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->bind_param("issii", $user_id, $start_date, $end_date, $limit, $offset);
            $stmt->execute();
            $center_bonuses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            if (!empty($center_bonuses)): ?>
        <h5 class="text-gold mb-3">
            <i class="fas fa-building"></i> 센터수당 내역
        </h5>
        <?php foreach ($center_bonuses as $bonus): ?>
        <div class="bonus-card">
            <div class="bonus-header">
                <div>
                    <span class="text-white">
                        <i class="far fa-calendar-alt"></i>
                        <?php echo date('Y-m-d', strtotime($bonus['sales_date'])); ?>
                    </span>
                </div>
                <div class="amount-value">
                    $<?php echo number_format($bonus['amount'], 2); ?>
                </div>
            </div>
            <div class="bonus-body">
                <div class="detail-row">
                    <div class="detail-label">센터명</div>
                    <div class="detail-value">
                        <?php echo htmlspecialchars($bonus['organization_name']); ?>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">센터 매출</div>
                    <div class="detail-value">
                        $<?php echo number_format($bonus['total_sales'], 2); ?>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">수당율</div>
                    <div class="detail-value text-gold">
                        <?php echo $bonus['commission_rate']; ?>%
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach;
            elseif ($bonus_type == 'center'): ?>
        <div class="no-data">
            <i class="fas fa-info-circle"></i>
            해당 기간에 발생한 센터수당이 없습니다.
        </div>
        <?php endif;
        }
        ?>

        <!-- 페이지네이션 -->
        <?php if (isset($total_pages) && $total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link"
                        href="?type=<?php echo $bonus_type; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&page=1">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                </li>
                <li class="page-item">
                    <a class="page-link"
                        href="?type=<?php echo $bonus_type; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&page=<?php echo ($page - 1); ?>">
                        <i class="fas fa-angle-left"></i>
                    </a>
                </li>
                <?php endif; ?>

                <?php
                    // 표시할 페이지 번호 범위 계산
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);

                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                    <a class="page-link"
                        href="?type=<?php echo $bonus_type; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&page=<?php echo $i; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link"
                        href="?type=<?php echo $bonus_type; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&page=<?php echo ($page + 1); ?>">
                        <i class="fas fa-angle-right"></i>
                    </a>
                </li>
                <li class="page-item">
                    <a class="page-link"
                        href="?type=<?php echo $bonus_type; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&page=<?php echo $total_pages; ?>">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 날짜 선택 시 자동 서브밋
    const dateInputs = document.querySelectorAll('.date-input');
    dateInputs.forEach(input => {
        input.addEventListener('change', function() {
            const otherDateInput = input.getAttribute('name') === 'start_date' ?
                document.querySelector('input[name="end_date"]') :
                document.querySelector('input[name="start_date"]');

            if (otherDateInput.value) {
                input.form.submit();
            }
        });
    });

    // 탭 전환 시 스무스 스크롤
    const tabs = document.querySelectorAll('.nav-link');
    tabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            const targetHref = this.getAttribute('href');

            // 현재 active 클래스 제거
            tabs.forEach(t => t.classList.remove('active'));
            // 클릭된 탭에 active 클래스 추가
            this.classList.add('active');

            // 스무스 스크롤로 해당 섹션으로 이동
            const targetSection = document.querySelector(targetHref);
            if (targetSection) {
                targetSection.scrollIntoView({
                    behavior: 'smooth'
                });
            } else {
                window.location.href = targetHref;
            }
        });
    });

    // 금액에 천단위 콤마 적용
    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    // 모바일에서 탭 스크롤 위치 조정
    const tabContainer = document.querySelector('.nav-tabs');
    const activeTab = tabContainer.querySelector('.active');
    if (activeTab) {
        tabContainer.scrollLeft = activeTab.offsetLeft - tabContainer.offsetWidth / 2 + activeTab.offsetWidth /
            2;
    }
});
</script>

<?php
// 데이터베이스 연결 종료
$conn->close();

// 푸터 인클루드
include __DIR__ . '/../includes/footer.php';
?>