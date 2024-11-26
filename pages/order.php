<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/richtech.club/pages/error.log');
?>


<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

$conn = db_connect();

// 상품 목록 가져오기
$stmt = $conn->prepare("SELECT id, name, price, image_url, stock, token FROM products");
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = '상품구매 신청';
include __DIR__ . '/../includes/header.php';
?>

<style>
body {
    background-color: #1c1c1c;
    color: #fff;
}

.card {
    background-color: #2d2d2d;
    border: none;
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.card-title {
    color: #d4af37;
}

.card-text {
    font-size: 12px;
    color: #ccc;
    font-family: 'Noto Sans KR', sans-serif;
    margin-bottom: 0px;
    margin-top: 0px;
}

.btn-primary {
    background-color: #d5961d;
    border-color: #d4af37;
}

.btn-primary:hover {
    background-color: #c29d36;
    border-color: #c29d36;
}

.product-image {
    width: 50%;
    padding: 10px;
}

    .product-image img {
        width: 100%;
        height: auto;
        object-fit: contain;
        max-width: 300px; /* 이미지를 너무 크게 방지 */
    }

.product-info {
    width: 50%;
    padding: 10px;
}

.product-container {
    display: flex;
    flex-direction: row;
    align-items: stretch;
}

@media (max-width:300px) {
    .product-container {
        flex-direction: column;
        align-items: center;
    }

    .product-image {
        width: 100%;
        text-align: center;
        padding: 0;
    }

    .product-image img {
        width: 100%;
        height: auto;
        object-fit: contain;
        max-width: 300px; /* 이미지를 너무 크게 방지 */
    }

    .product-info {
        width: 100%;
        padding: 10px;
    }
}

.btn-outline {
    background-color: transparent;
    border: 1px solid #d4af37;
    color: #d4af37;
    font-size: 0.8em;
    padding: 5px 10px;
    border-radius: 3px;
    cursor: pointer;
    transition: all 0.3s ease;
    margin: 2px;
}

.btn-outline:hover {
    background-color: #d4af37;
    color: #000000;
}
</style>

<div class="mx-20 mt20 ">
    <div class="flex-x-end ">
        <button class="btn-outline" onclick="location.href='/order_list'"><i class="fas fa-list"></i> 구매내역</button>
                <button class="btn-outline" onclick="location.href='/deposits'"><i class="fas fa-list"></i> USDT충전하기</button>
        <button class="btn-outline" onclick="location.href='/bonus'"><i class="fas fa-chart-line"></i>
            수수료조회</button>
    </div>
</div>

<div class="container mb50">
    <h4 class="mt-1 notosans mb-2 mt30 ">상품 신청</h4>
    <div class="row">
        <?php foreach ($products as $product): ?>
        <div class="col-12 mb-4">
            <div class="card">
                <div class="product-container">
                    <div class="product-image">
                        <a href="/order_apply?id=<?php echo $product['id']; ?>">
                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" class="w-100 h-100"
                            alt="<?php echo htmlspecialchars($product['name']);?>">
                        </a>
                    </div>
                    <div class="product-info">
                        <div class="card-body d-flex flex-column justify-content-between h-100">
                            <div>
                                <h5 class="card-title fs-18"><?php echo htmlspecialchars($product['name']); ?></h5>
                                <p class="card-text mb5">Price</p>
                                <p class="mt10 fw-900 fs-16 text-center text-warning bg-gray50 btn15">
                                    <?php echo number_format($product['price']); ?> <span class="fs-10">USDT</span></p>
                                <p class="card-text mt10">Stock: <span
                                        class="text-warning fs-14 fw-bold"><?php echo number_format($product['stock']); ?>주</span>
                                </p>
                                <p class="card-text mb10">Token: <span
                                        class="text-warning fs-14 fw-bold mr5"><?php echo number_format($product['token'], 0); ?>
                                        rich</span></p>
                            </div>
                            <div class="mt-auto">
                                <a href="/order_apply?id=<?php echo $product['id']; ?>" class="btn btn-primary btn-sm w-100 fs-14 notosans"
                                    onclick="location.href='/order_apply?id=<?php echo $product['id']; ?>'; return false;">결제하기</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>