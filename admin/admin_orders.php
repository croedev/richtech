']); ?></td>
                        <td class="text-end">$<?php echo number_format($order['total_amount'], 2); ?></td>
                        <td class="text-end">
                            <?php if ($order['point_used'] > 0): ?>
                                <div class="fs-11">P: <?php echo number_format($order['point_used'], 2); ?></div>
                            <?php endif; ?>
                            <?php if ($order['cash_point_used'] > 0): ?>
                                <div class="fs-11">CP: <?php echo number_format($order['cash_point_used'], 2); ?></div>
                            <?php endif; ?>
                            <?php if ($order['mileage_point_used'] > 0): ?>
                                <div class="fs-11">MP: <?php echo number_format($order['mileage_point_used'], 2); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($order['token'] > 0): ?>
                                <div class="fs-11">토큰: <?php echo number_format($order['token']); ?></div>
                            <?php endif; ?>
                            <?php if ($order['stock'] > 0): ?>
                                <div class="fs-11">주식: <?php echo number_format($order['stock']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php 
                            $badge_class = '';
                            switch($order['payment_method']) {
                                case 'usdp': $badge_class = 'primary'; break;
                                case 'point': $badge_class = 'info'; break;
                                default: $badge_class = 'secondary';
                            }
                            ?>
                            <span class="badge bg-<?php echo $badge_class; ?> fs-10">
                                <?php echo $order['payment_method']; ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <?php 
                            $status_class = '';
                            $status_text = '';
                            switch($order['status']) {
                                case 'pending':
                                    $status_class = 'warning';
                                    $status_text = '대기';
                                    break;
                                case 'completed':
                                    $status_class = 'success';
                                    $status_text = '완료';
                                    break;
                                case 'cancelled':
                                    $status_class = 'danger';
                                    $status_text = '취소';
                                    break;
                                default:
                                    $status_class = 'secondary';
                                    $status_text = $order['status'];
                            }
                            ?>
                            <span class="badge bg-<?php echo $status_class; ?> fs-10">
                                <?php echo $status_text; ?>
                            </span>
                        </td>
                        <td class="text-center fs-10">
                            <?php
                            $settlement_status = [];
                            if ($order['status'] === 'completed') {
                                if ($order['paid_referral'] === 'pending') $settlement_status[] = '추천';
                                if ($order['paid_status'] === 'pending') $settlement_status[] = '직급';
                                if ($order['paid_center'] === 'pending') $settlement_status[] = '센터';
                                
                                if (empty($settlement_status)) {
                                    echo '<span class="text-success">완료</span>';
                                } else {
                                    echo '<span class="text-danger">' . implode(',', $settlement_status) . ' 대기</span>';
                                }
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td>
                            <div class="fs-10"><?php echo date('Y-m-d H:i', strtotime($order['created_at'])); ?></div>
                            <?php if ($order['payment_date']): ?>
                                <div class="fs-10 text-success">
                                    결제: <?php echo date('Y-m-d H:i', strtotime($order['payment_date'])); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <?php if ($order['status'] === 'pending'): ?>
                                    <button type="button" class="btn btn-outline-success btn-xs" 
                                            onclick="confirmPayment(<?php echo $order['id']; ?>)">
                                        입금확인
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-xs" 
                                            onclick="cancelOrder(<?php echo $order['id']; ?>)">
                                        취소
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($order['status'] === 'completed' && !empty($settlement_status)): ?>
                                    <button type="button" class="btn btn-outline-warning btn-xs" 
                                            onclick="processSettlement(<?php echo $order['id']; ?>)">
                                        정산처리
                                    </button>
                                <?php endif; ?>
                                
                                <button type="button" class="btn btn-outline-primary btn-xs" 
                                        onclick="viewOrderDetail(<?php echo $order['id']; ?>)">
                                    상세
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- 페이지네이션 -->
            <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-center mt-3">
                <nav>
                    <ul class="pagination pagination-sm">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&<?php 
                                    echo http_build_query(array_diff_key($_GET, ['page' => ''])); 
                                ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
// 입금 확인 처리
function confirmPayment(orderId) {
    if (!confirm('이 주문의 입금을 확인하시겠습니까?')) return;
    processOrderAction('confirm_payment', orderId);
}

// 주문 취소 처리
function cancelOrder(orderId) {
    if (!confirm('이 주문을 취소하시겠습니까?')) return;
    processOrderAction('cancel_order', orderId);
}

// 정산 처리
function processSettlement(orderId) {
    if (!confirm('이 주문의 정산을 진행하시겠습니까?')) return;
    processOrderAction('process_settlement', orderId);
}

// 주문 액션 처리 함수
function processOrderAction(action, orderId) {
    fetch('order_actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=${action}&order_id=${orderId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert(data.message || '처리 중 오류가 발생했습니다.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('처리 중 오류가 발생했습니다.');
    });
}

// 주문 상세 정보 보기
function viewOrderDetail(orderId) {
    // 이 함수는 주문 상세 모달을 표시합니다.
    // order_detail.php 파일이 필요합니다.
    window.location.href = `order_detail.php?id=${orderId}`;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>