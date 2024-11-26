t-muted">미등록</small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex flex-column">
                                <span class="badge <?php echo $member['status'] === 'active' ? 'badge-success' : 'badge-warning'; ?>">
                                    <?php echo $member['status'] === 'active' ? '활성' : '비활성'; ?>
                                </span>
                                <small class="text-muted">
                                    <?php echo date('Y-m-d', strtotime($member['created_at'])); ?>
                                </small>
                            </div>
                        </td>
                        <td>
                            <div class="btn-group-vertical btn-group-sm">
                                <button type="button" class="btn btn-info btn-xs mb-1"
                                        onclick="location.href='member_edit.php?id=<?php echo $member['id']; ?>'">
                                    <i class="fas fa-edit"></i> 수정
                                </button>
                                <button type="button" class="btn btn-warning btn-xs mb-1 status-toggle"
                                        data-user-id="<?php echo $member['id']; ?>"
                                        data-status="<?php echo $member['status']; ?>">
                                    <i class="fas fa-toggle-on"></i> 상태변경
                                </button>
                                <?php if ($member['status'] === 'inactive'): ?>
                                <button type="button" class="btn btn-danger btn-xs"
                                        onclick="AdminMemberManager.deleteMember(<?php echo $member['id']; ?>)">
                                    <i class="fas fa-trash"></i> 삭제
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer">
        <?php if ($total_pages > 1): ?>
        <nav>
            <ul class="pagination justify-content-center mb-0">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=1<?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>">
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
                        <a class="page-link" href="?page=<?php echo $i . (isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''); ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $total_pages . (isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''); ?>">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<!-- BSC 잔액 확인 모달 -->
<div class="modal" id="bscBalanceModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">BSC 잔액 정보</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-column">
                    <p class="mb-2">주소: <span id="bscAddress" class="text-break"></span></p>
                    <p class="mb-0">잔액: <span id="bscBalance"></span> BNB</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">닫기</button>
                <a href="#" id="bscScanLink" target="_blank" class="btn btn-sm btn-primary">
                    BSCScan에서 보기
                </a>
            </div>
        </div>
    </div>
</div>

<!-- 사용자 정의 알림 컨테이너 -->
<div id="notificationContainer" style="position: fixed; top: 20px; right: 20px; z-index: 9999;"></div>

<script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // DataTable 초기화
    const table = $('#membersTable').DataTable({
        pageLength: 25,
        ordering: true,
        searching: true,
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/ko.json'
        },
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip'
    });

    // 검색 입력 필드에 이벤트 리스너 추가
    $('.dataTables_filter input').on('keyup', function() {
        table.search(this.value).draw();
    });

    // 페이지 길이 변경 시 이벤트
    $('.dataTables_length select').on('change', function() {
        table.page.len(this.value).draw();
    });
});

// 알림 표시 함수
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show`;
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.getElementById('notificationContainer').appendChild(notification);
    
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}
</script>

<?php
$conn->close();
include __DIR__ . '/admin_footer.php';
?>






<style>
  /* 기본 설정 */
:root {
    --primary-gold: #d4af37;
    --dark-bg: #121212;
    --card-bg: rgba(26, 26, 26, 0.95);
    --border-color: rgba(212, 175, 55, 0.2);
}

body {
    background-color: var(--dark-bg);
    color: #ffffff;
    font-family: 'Noto Sans KR', sans-serif;
}

/* 대시보드 통계 카드 */
.summary-stats {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin-bottom: 20px;
    padding: 0 15px;
}

.stat-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.stat-card h5 {
    color: var(--primary-gold);
    font-size: 0.9rem;
    margin-bottom: 8px;
}

.stat-value {
    font-size: 1.2rem;
    font-weight: bold;
    color: #fff;
}

/* 모달 수정 */
.modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 1040;
}

.modal {
    z-index: 1050;
}

.modal-dialog {
    margin: 1.75rem auto;
}

.modal-content {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
}

/* 테이블 스타일링 */
.table-responsive {
    margin-top: 20px;
}

.table {
    background: transparent;
}

.table th {
    background: rgba(45, 45, 45, 0.9);
    color: var(--primary-gold);
    font-size: 0.85rem;
    border-bottom: 1px solid var(--border-color);
}

.table td {
    color: #00fff;
    border-color: rgba(255,255,255,0.1);
}

/* 반응형 조정 */
@media (min-width: 768px) {
    .summary-stats {
        grid-template-columns: repeat(4, 1fr);
    }
}

@media (max-width: 767px) {
    .summary-stats {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
    
    .stat-card {
        padding: 10px;
    }
    
    .stat-card h5 {
        font-size: 0.8rem;
    }
    
    .stat-value {
        font-size: 1rem;
    }
}

/* 폼 요소 */
.form-control, .form-select {
    background: rgba(0,0,0,0.2);
    border: 1px solid var(--border-color);
    color: #fff;
}

.form-control:focus, .form-select:focus {
    background: rgba(0,0,0,0.3);
    border-color: var(--primary-gold);
    color: #fff;
    box-shadow: none;
}

/* 버튼 스타일 */
.btn-gold {
    background: linear-gradient(135deg, var(--primary-gold), #f2d06b);
    color: #000;
    border: none;
    transition: all 0.3s ease;
}

.btn-gold:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}
</style>

<script>
        // 회원관리 관련 JavaScript 함수들
        const AdminMemberManager = {
            // 상태 업데이트 함수
            updateStatus: async function(userId, currentStatus) {
                const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
                
                try {
                    const response = await fetch('/admin/update_status', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ userId, status: newStatus })
                    });
                    
                    const data = await response.json();
                    if (data.success) {
                        showNotification('회원 상태가 성공적으로 변경되었습니다.', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        throw new Error(data.message);
                    }
                } catch (error) {
                    showNotification(error.message || '상태 변경 중 오류가 발생했습니다.', 'error');
                }
            },

            // BSC 주소 검증 함수
            validateBscAddress: function(address) {
                return /^0x[0-9a-fA-F]{40}$/.test(address);
            },

            // BSC 잔액 조회 함수
            checkBscBalance: async function(address) {
                try {
                    const response = await fetch(`https://api.bscscan.com/api`, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                        },
                        params: {
                            module: 'account',
                            action: 'balance',
                            address: address,
                            apikey: BSC_API_KEY
                        }
                    });

                    const data = await response.json();
                    return data.status === '1' ? data.result : null;
                } catch (error) {
                    console.error('BSC 잔액 조회 실패:', error);
                    return null;
                }
            },

            // 회원 수정 데이터 검증
            validateMemberData: function(formData) {
                const errors = [];
                
                // 이메일 검증
                if (formData.get('email') && !this.validateEmail(formData.get('email'))) {
                    errors.push('유효하지 않은 이메일 주소입니다.');
                }

                // BSC 주소 검증
                if (formData.get('bsc_address') && !this.validateBscAddress(formData.get('bsc_address'))) {
                    errors.push('유효하지 않은 BSC 주소입니다.');
                }

                // 전화번호 검증
                if (formData.get('phone') && !this.validatePhone(formData.get('phone'))) {
                    errors.push('유효하지 않은 전화번호 형식입니다.');
                }

                return errors;
            },

            // 회원 삭제 처리
            deleteMember: async function(userId) {
                if (!confirm('정말 이 회원을 삭제하시겠습니까? 이 작업은 되돌릴 수 없습니다.')) {
                    return;
                }

                try {
                    const response = await fetch('/admin/delete_member', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ userId })
                    });

                    const data = await response.json();
                    if (data.success) {
                        showNotification('회원이 성공적으로 삭제되었습니다.', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        throw new Error(data.message);
                    }
                } catch (error) {
                    showNotification(error.message || '회원 삭제 중 오류가 발생했습니다.', 'error');
                }
            },

            // 실시간 검증 함수들
            validateEmail: function(email) {
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
            },

            validatePhone: function(phone) {
                return /^[0-9-+()]{10,}$/.test(phone);
            },

            // Excel 내보내기
            exportToExcel: function() {
                const table = document.querySelector('table');
                const wb = XLSX.utils.table_to_book(table, {
                    sheet: "Members",
                    raw: true,
                    dateNF: 'yyyy-mm-dd'
                });
                
                XLSX.writeFile(wb, `회원목록_${new Date().toISOString().split('T')[0]}.xlsx`);
            },

            // 데이터 테이블 초기화
            initializeDataTable: function() {
                return new DataTable('#membersTable', {
                    pageLength: 25,
                    order: [[0, 'desc']],
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/ko.json'
                    },
                    columnDefs: [
                        { targets: 'no-sort', orderable: false }
                    ],
                    drawCallback: function() {
                        this.api().columns().every(function() {
                            const column = this;
                            const header = $(column.header());
                            
                            if (header.hasClass('searchable')) {
                                const input = $('<input type="text" class="form-control form-control-sm mt-1" placeholder="검색...">')
                                    .appendTo(header)
                                    .on('keyup change', function() {
                                        if (column.search() !== this.value) {
                                            column.search(this.value).draw();
                                        }
                                    });
                            }
                        });
                    }
                });
            },

            // 알림 표시
            showNotification: function(message, type = 'info') {
                const notification = document.createElement('div');
                notification.className = `notification notification-${type}`;
                notification.textContent = message;
                
                document.body.appendChild(notification);
                setTimeout(() => notification.remove(), 3000);
            },

            // 초기화 함수
            init: function() {
                // 데이터테이블 초기화
                const table = this.initializeDataTable();

                // 이벤트 리스너 등록
                document.querySelectorAll('.status-toggle').forEach(btn => {
                    btn.addEventListener('click', e => {
                        const userId = e.target.dataset.userId;
                        const currentStatus = e.target.dataset.status;
                        this.updateStatus(userId, currentStatus);
                    });
                });

                // BSC 주소 유효성 검사 이벤트
                document.querySelectorAll('.bsc-address').forEach(input => {
                    input.addEventListener('blur', e => {
                        const address = e.target.value;
                        if (address && !this.validateBscAddress(address)) {
                            this.showNotification('유효하지 않은 BSC 주소입니다.', 'error');
                        }
                    });
                });

                // 실시간 검색 기능
                document.querySelector('#searchInput').addEventListener('input', e => {
                    table.search(e.target.value).draw();
                });

                // Excel 내보내기 버튼
                document.querySelector('#exportExcel').addEventListener('click', () => {
                    this.exportToExcel();
                });
            }
        };

        // DOM 로드 완료 시 초기화
        document.addEventListener('DOMContentLoaded', () => {
            AdminMemberManager.init();
        });

</script>



<?php include __DIR__ . '/../includes/footer.php'; ?>