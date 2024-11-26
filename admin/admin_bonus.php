          <input type="hidden" name="bonus_type" value="<?php echo htmlspecialchars($bonus_type); ?>">
            <div class="col-md-3">
                <label class="form-label">회원명/ID</label>
                <input type="text" name="search_name" class="form-control form-control-sm" 
                       value="<?php echo htmlspecialchars($search_name); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">기간 설정</label>
                <div class="input-group input-group-sm">
                    <input type="date" name="start_date" class="form-control" 
                           value="<?php echo htmlspecialchars($start_date); ?>">
                    <span class="input-group-text">~</span>
                    <input type="date" name="end_date" class="form-control"
                           value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
            </div>
            <div class="col-md-3">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-gray">
                        <i class="fas fa-search"></i> 검색
                    </button>
                    <button type="button" class="btn btn-sm btn-success" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i> Excel
                    </button>
                </div>
            </div>
        </form>
    </div>
    

<!-- 수당 내역 테이블 -->
<div class="table-responsive">
    <table class="table" id="bonusTable">
        <thead>
            <tr>
                <th>지급일시</th>
                <th>수당종류</th>
                <th>회원정보</th>
                <?php if ($bonus_type === 'referral'): ?>
                    <th>발생회원</th>
                    <th>매출금액</th>
                    <th>단계</th>
                <?php elseif ($bonus_type === 'rank'): ?>
                    <th>직급</th>
                    <th>수당유형</th>
                    <th>회사매출</th>
                <?php elseif ($bonus_type === 'center'): ?>
                    <th>센터</th>
                    <th>센터매출</th>
                <?php endif; ?>
                <th>수수료율</th>
                <th>수당금액</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></td>
                <td>
                    <?php
                    $badge_class = '';
                    $bonus_name = '';
                    switch($row['bonus_type'] ?? $bonus_type) {
                        case 'referral':
                            $badge_class = 'badge-referral';
                            $bonus_name = '추천수당';
                            break;
                        case 'rank':
                            $badge_class = 'badge-rank';
                            $bonus_name = '직급수당';
                            break;
                        case 'center':
                            $badge_class = 'badge-center';
                            $bonus_name = '센터수당';
                            break;
                    }
                    ?>
                    <span class="badge-bonus <?php echo $badge_class; ?>">
                        <?php echo $bonus_name; ?>
                    </span>
                </td>
                <td>



<?php echo htmlspecialchars($row['user_name']); ?>
                    <br>
                    <small class="text-muted"><?php echo htmlspecialchars($row['login_id']); ?></small>
                </td>
                <?php if ($bonus_type === 'referral'): ?>
                    <td>
                        <?php echo htmlspecialchars($row['source_user_name']); ?>
                        <br>
                        <small class="text-muted"><?php echo htmlspecialchars($row['source_login_id']); ?></small>
                    </td>
                    <td class="text-end">$<?php echo number_format($row['source_amount'], 2); ?></td>
                    <td class="text-center"><?php echo $row['level']; ?>단계</td>
                <?php elseif ($bonus_type === 'rank'): ?>
                    <td><?php echo htmlspecialchars($row['rank']); ?></td>
                    <td><?php echo htmlspecialchars($row['bonus_type']); ?></td>
                    <td class="text-end">$<?php echo number_format($row['total_company_sales'], 2); ?></td>
                <?php elseif ($bonus_type === 'center'): ?>
                    <td><?php echo htmlspecialchars($row['organization_name']); ?></td>
                    <td class="text-end">$<?php echo number_format($row['total_sales'], 2); ?></td>
                <?php endif; ?>
                <td class="text-end"><?php echo number_format($row['commission_rate'], 2); ?>%</td>
                <td class="text-end">$<?php echo number_format($row['amount'], 2); ?></td>
            </tr>
            <?php endwhile; ?>
            <?php if ($result->num_rows === 0): ?>
            <tr>
                <td colspan="8" class="text-center py-3">검색 결과가 없습니다.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- 페이지네이션 -->
<?php if ($total_pages > 1): ?>
<nav>
    <ul class="pagination">
        <?php if ($page > 1): ?>
            <li class="page-item">
                <a class="page-link" href="?page=1<?php echo $bonus_type ? '&bonus_type='.$bonus_type : ''; ?>
                    <?php echo $search_name ? '&search_name='.urlencode($search_name) : ''; ?>
                    <?php echo $start_date ? '&start_date='.$start_date : ''; ?>
                    <?php echo $end_date ? '&end_date='.$end_date : ''; ?>">
                    <i class="fas fa-angle-double-left"></i>
                </a>
            </li>
        <?php endif; ?>

        <?php
        $start = max(1, $page - 2);
        $end = min($start + 4, $total_pages);
        $start = max(1, $end - 4);

        for ($i = $start; $i <= $end; $i++):
        ?>
            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?>
                    <?php echo $bonus_type ? '&bonus_type='.$bonus_type : ''; ?>
                    <?php echo $search_name ? '&search_name='.urlencode($search_name) : ''; ?>
                    <?php echo $start_date ? '&start_date='.$start_date : ''; ?>
                    <?php echo $end_date ? '&end_date='.$end_date : ''; ?>">
                    <?php echo $i; ?>
                </a>
            </li>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?php echo $total_pages; ?>
                    <?php echo $bonus_type ? '&bonus_type='.$bonus_type : ''; ?>
                    <?php echo $search_name ? '&search_name='.urlencode($search_name) : ''; ?>
                    <?php echo $start_date ? '&start_date='.$start_date : ''; ?>
                    <?php echo $end_date ? '&end_date='.$end_date : ''; ?>">
                    <i class="fas fa-angle-double-right"></i>
                </a>
            </li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<script>
// Excel 내보내기 함수
function exportToExcel() {
    const table = document.getElementById('bonusTable');
    const wb = XLSX.utils.table_to_book(table, {
        sheet: "수당내역",
        raw: true,
        dateNF: 'yyyy-mm-dd hh:mm'
    });
    
    // 파일명 생성
    let filename = '수당내역_';
    if ('<?php echo $bonus_type; ?>') {
        switch('<?php echo $bonus_type; ?>') {
            case 'referral': filename += '추천수당_'; break;
            case 'rank': filename += '직급수당_'; break;
            case 'center': filename += '센터수당_'; break;
        }
    }
    filename += new Date().toISOString().split('T')[0] + '.xlsx';
    
    XLSX.writeFile(wb, filename);
}

// 날짜 입력 필드 자동 제한
document.addEventListener('DOMContentLoaded', function() {
    const startDate = document.querySelector('input[name="start_date"]');
    const endDate = document.querySelector('input[name="end_date"]');
    
    if (startDate && endDate) {
        startDate.addEventListener('change', function() {
            endDate.min = this.value;
        });
        
        endDate.addEventListener('change', function() {
            startDate.max = this.value;
        });
    }
});

// 수당 종류 변경 시 자동 submit
document.querySelector('select[name="bonus_type"]').addEventListener('change', function() {
    this.form.submit();
});

// 테이블 정렬 기능
document.querySelectorAll('th[data-sort]').forEach(header => {
    header.addEventListener('click', function() {
        const table = this.closest('table');
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const index = Array.from(this.parentNode.children).indexOf(this);
        const ascending = this.dataset.sortDir === 'asc';
        
        rows.sort((a, b) => {
            const aValue = a.children[index].textContent.trim();
            const bValue = b.children[index].textContent.trim();
            
            if (this.dataset.sortType === 'number') {
                return ascending ? 
                    parseFloat(aValue) - parseFloat(bValue) : 
                    parseFloat(bValue) - parseFloat(aValue);
            }
            
            return ascending ? 
                aValue.localeCompare(bValue) : 
                bValue.localeCompare(aValue);
        });
        
        tbody.innerHTML = '';
        rows.forEach(row => tbody.appendChild(row));
        
        this.dataset.sortDir = ascending ? 'desc' : 'asc';
    });
});

// 필터링 기능
function filterTable() {
    const searchText = document.getElementById('tableSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#bonusTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchText) ? '' : 'none';
    });
}
</script>

<!-- Excel 내보내기용 라이브러리 -->
<script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>

<?php
$conn->close();
include __DIR__ . '/../includes/footer.php'; 
?>