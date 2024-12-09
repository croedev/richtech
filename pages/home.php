<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
$pageTitle = '부자들의 모임_리치테크(Rich Tech club)';

// 데이터베이스 연결
$conn = db_connect();

// 상품 목록 가져오기
$stmt = $conn->prepare("SELECT id, name, price, image_url, stock, token FROM products ORDER BY price ASC");
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 최근 공지사항 가져오기
$stmt = $conn->prepare("
    SELECT id, title, content, author, views, created_at,
           CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 3 DAY) THEN 1 ELSE 0 END as is_new
    FROM notices 
    WHERE status = 'active'
    ORDER BY created_at DESC 
    LIMIT 2
");
$stmt->execute();
$recent_notices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo isset($pageTitle) ? $pageTitle : '부자들의 모임_클럽 리치테크'; ?></title>
    <link
        href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@100;200;300;400;500;600;700;800;900&family=Noto+Serif+KR:wght@100;200;300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://yammi.link/css/croe.2.5.0.css">

    <style>
    body {
        font-family: 'Noto Sans KR', sans-serif;
        margin: 0;
        padding: 0;
        background-color: #000;
        color: #d4af37;
        font-size: 14px;
    }

    .content {
        padding: 60px 0;
        padding-bottom: 70px;
        height: 100%;
        overflow-y: auto;

    }

    .slider-container {
        position: relative;
        width: 100%;
        margin: 0 auto 30px;
        overflow: hidden;
    }

    .main-slider {
        position: relative;
        width: 100%;
    }

    .main-slider img {
        width: 100%;
        height: 300px;
        object-fit: cover;
        border-radius: 0px;
    }



    /* 슬라이더 화살표 스타일 */
    .slick-prev,
    .slick-next {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        z-index: 10;
        width: 40px;
        height: 40px;
        background: rgba(255, 255, 255, 0.0);
        border-radius: 50%;
        cursor: pointer;
        border: none;
        font-size: 0;
        /* 텍스트 제거 */
        transition: all 0.3s ease;
    }

    .slick-prev {
        left: 15px;
    }

    .slick-next {
        right: 15px;
    }

    /*
    .slick-prev:before,
    .slick-next:before {
        font-family: 'Font Awesome 5 Free';
        font-weight: 700;
        font-size: 25px;
        color: #ffffff;
        -webkit-font-smoothing: antialiased;
    }
    */


    /* 슬라이더 도트 네비게이션 수정 */
    .slick-dots {
        position: absolute;
        bottom: 15px;
        left: 0;
        right: 0;
        text-align: center;
        padding: 0;
        margin: 0;
        list-style: none;
        z-index: 1;
    }

    .slick-dots li {
        display: inline-block;
        margin: 0 4px;
        padding: 0;
    }

    .slick-dots li button {
        width: 8px;
        height: 8px;
        padding: 0;
        border: none;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.5);
        font-size: 0;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .slick-dots li.slick-active button {
        background: #ffffff;
        width: 24px;
        border-radius: 4px;
    }

    /* 슬라이더 애니메이션 효과 */
    .slick-slide {
        opacity: 0;
        transition: opacity 0.5s ease;
    }

    .slick-current {
        opacity: 1;
    }


    .product-scroll {
        overflow-x: auto;
        padding: 10px 20px;
    }

    .product-container {
        display: flex;
        gap: 15px;
    }

    .product-card {
        flex: 0 0 auto;
        width: 200px;
        background: #222;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .product-image {
        width: 100%;
        height: 200px;
        object-fit: cover;
    }

    .product-info {
        padding: 10px;
    }

    .product-name {
        font-weight: 600;
        color: #d4af37;
    }

    .product-price {
        color: #8BC34A;
        font-size: 0.9em;
    }

    .product-quantity {
        color: #ccc;
        font-size: 0.8em;
    }

    .dashboard-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        padding: 20px;
    }

    .dashboard-card {
        background: #222;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .dashboard-card h6 {
        margin-bottom: 15px;
        color: #d4af37;
    }

    .dashboard-card p {
        margin-bottom: 5px;
        color: #ccc;
        font-size: 12px;
    }

    .referral-link {
        padding: 20px;
        text-align: left;
    }

    .referral-link input {
        width: 100%;
        padding: 10px;
        border: 1px solid #d4af37;
        border-radius: 5px;
        background: #000;
        color: #d4af37;
        text-align: center;
    }

    .product-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .product-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(212, 175, 55, 0.3);
    }

    .product-token {
        color: #d4af37;
        font-size: 0.8em;
        margin-top: 2px;
    }

    .product-name {
        margin-bottom: 5px;
    }

    .product-price {
        font-weight: bold;
        color: #8BC34A;
        margin: 5px 0;
    }

    .product-quantity,
    .product-token {
        font-size: 0.75em;
        color: #aaa;
    }

    .notice-section {
        background: #222;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 30px;
    }

    .notice-header {
        border-bottom: 1px solid rgba(212, 175, 55, 0.2);
        padding-bottom: 10px;
    }

    .notice-header h6 {
        color: #d4af37;
        font-size: 16px;
    }

    .btn-more {
        color: #8a8a8a;
        font-size: 12px;
        text-decoration: none;
        transition: color 0.3s ease;
    }

    .btn-more:hover {
        color: #d4af37;
    }

    .recent-notices {
        margin-top: 15px;
    }

    .notice-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .notice-item:last-child {
        border-bottom: none;
    }

    .notice-item:hover {
        background: rgba(212, 175, 55, 0.1);
        padding-left: 10px;
        padding-right: 10px;
        border-radius: 8px;
    }

    .notice-content h3 {
        color: #fff;
        font-size: 14px;
        margin-bottom: 5px;
    }

    .badge-new {
        background: #d4af37;
        color: #000;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 10px;
        margin-right: 5px;
    }

    .notice-meta {
        display: flex;
        gap: 15px;
        color: #8a8a8a;
        font-size: 12px;
    }

    .notice-meta i {
        margin-right: 4px;
    }

    .notice-arrow {
        color: #8a8a8a;
        font-size: 12px;
    }

    .no-notices {
        text-align: center;
        color: #8a8a8a;
        padding: 20px 0;
    }
    </style>
</head>

<body>
    <div class="content pt10 mb50">
        <div class="fs-12 text-yellow6 text-left height50">
            <img src="/assets/images/logo_rich0.png" alt="부자들의모임_리치테크(Rich Tech club) 로고"
                style="vertical-align: bottom; width: 200px; height: auto;"> 부자들의모임_리치테크
        </div>

        <!-- 메인 슬라이더 -->
        <div class="slider-container mt30">
            <div class="main-slider">
                <div><img
                        src="https://png.pngtree.com/background/20230520/original/pngtree-many-gold-coins-scattered-on-a-red-background-picture-image_2680338.jpg"
                        alt="부자 이미지 1"></div>
                <div><img
                        src="https://mblogthumb-phinf.pstatic.net/MjAxODA2MDVfMTMg/MDAxNTI4MTY3OTQ3MjM2.EwZkTD70V0Vm3xBjMYIBmrGg934LHAL7ZkjgYgsRCHwg.LZs7zpdlwsMzUGRGrjpyRZlxI2vmyeV59V-QfTsVyqgg.JPEG.sandhya/DSC07693.JPG?type=w420"
                        alt="부자 이미지 2"></div>
                <div><img src="https://pds.skyedaily.com/top_image/201903/82498_p.jpg" alt="부자 이미지 3"></div>
                <div><img src="https://i.pinimg.com/736x/c0/9a/7a/c09a7a0d39e12ff281ef5635e69c24f3.jpg" alt="부자 이미지 4">
                </div>
            </div>
        </div>


        <div class="mx-20 mt50 notoserif">
            <span class="fs-18">리치테크클럽(Rich Tech Club)은 <br>검증된 투자 전문가들과 함께 최고의 수익을 추구합니다.</span><br><br>
            <div class="text-white notosans fw-100">
                <span class="fs-13">글로벌 자산시장의 엄선된 투자 기회를 발굴하여 <br>
                    <span class="fs-13">체계적인 투자 시스템과 <strong>차별화된</strong> 수익 구조로 부자가 되는 지름길을 제시합니다.</span><br>
                    <span class="fs-16 lh-12"><strong>지금 바로 리치테크와 함께 부자의 꿈을 현실로 만들어보세요.</strong></span><br>
                    <span class="fs-12 fw-100">당신의 <strong>성공적인</strong> 투자를 위해 리치테크가 함께 하겠습니다.</span>
            </div>
        </div>




        <!-- 상품 슬라이드 -->
        <h6 class="ml20 mt50 notosans">
            <i class="fas fa-shopping-cart"></i> 상품안내
        </h6>
        <div class="product-scroll">
            <div class="product-container">
                <?php if (!empty($products)): ?>
                <?php foreach ($products as $product): ?>
                <div class="product-card" onclick="location.href='/order_apply?id=<?php echo $product['id']; ?>';"
                    style="cursor: pointer;">
                    <img src="<?php echo htmlspecialchars($product['image_url']); ?>" class="product-image"
                        alt="<?php echo htmlspecialchars($product['name']); ?>">
                    <div class="product-info">
                        <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                        <div class="product-price"><?php echo number_format($product['price']); ?> USDT</div>
                        <div class="product-quantity">주식수: <?php echo number_format($product['stock']); ?>주</div>
                        <div class="product-token">토큰: <?php echo number_format($product['token']); ?> rich</div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <div class="text-center w-100 p-3">
                    <p>현재 등록된 상품이 없습니다.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>


        <!-- 나의 추천링크 -->
<div class="mt50 notoserif referral-link">
    <h4 class="mb-3 fs-18">부자글럽을 추천하세요! <small class="fs-14"> (나의 추천링크)</small></h4>
    <div class="input-group">
        <?php if (isset($_SESSION['user_id'])): 
            // 로그인된 사용자의 추천 링크 가져오기
            $stmt = $conn->prepare("SELECT referral_link FROM users WHERE id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $referralLink = $user['referral_link'] ?? 'https://richtech.club/join?ref=fef76d80';
        ?>
            <input type="text" value="<?php echo htmlspecialchars($referralLink); ?>" readonly class="form-control" id="referralLink">
            <button class="btn fs-11 btn-gray notosans outline" onclick="copyToClipboard()">복사</button>
        <?php else: ?>
            <input type="text" value="https://richtech.club/join?ref=fef76d80" readonly class="form-control" id="referralLink">
            <button class="btn fs-11 btn-gray notosans outline" onclick="showLoginMessage()">복사</button>
        <?php endif; ?>
    </div>
</div>

        <hr>

     

<div class="mx-20 mt20 mb30 ">
    <div class="">
        <button class="btn-outline text-orange" onclick="location.href='/join'"><i class="fas fa-user-plus"></i> 부자되기 가입</button>
                 <button class="btn-outline text-orange" onclick="location.href='/profile'"><i class="fas fa-user"></i> 나의정보</button>
          <button class="btn-outline text-orange" onclick="location.href='/order'"><i class="fas fa-shopping-cart"></i> 구매하기</button>
                   <button class="btn-outline text-orange" onclick="location.href='/order_list'"><i class="fas fa-clipboard-list"></i> 구매내역보기</button>
                  <button class="btn-outline text-orange" onclick="location.href='/deposits'"><i class="fas fa-wallet"></i> 충전하기</button>
                           <button class="btn-outline text-orange" onclick="location.href='/bonus'"><i class="fas fa-coins"></i> 수수료보기</button>
                  <button class="btn-outline text-orange" onclick="location.href='/withdrawals'"><i class="fas fa-money-bill-wave"></i> 출금하기</button>
         <button class="btn-outline text-orange" onclick="location.href='/chart'"><i class="fas fa-sitemap"></i> 조직도보기</button>
           <button class="btn-outline text-orange" onclick="location.href='/certificate'"><i class="fas fa-certificate"></i> 주주인증서</button>

           <?php if (isset($_SESSION['user_id']) && in_array($_SESSION['user_id'], [1, 2])): ?>
               <button class="btn-outline bg-orange text-black" onclick="location.href='/admin/'"><i class="fas fa-cog"></i> 관리자</button>
           <?php endif; ?>
    </div>
</div>


        <!-- 최근공지사항 섹션 추가 -->
        <div class="notice-section mx-20 mt00 mb70">
            <div class="notice-header d-flex justify-content-between align-items-center mb-3">
                <h6 class="notosans mb-0">
                    <i class="fas fa-bullhorn"></i> 최근 공지사항
                </h6>
                <a href="/notice" class="btn-more">
                    더보기 <i class="fas fa-chevron-right"></i>
                </a>
            </div>
            
            <div class="recent-notices">
                <?php if (!empty($recent_notices)): ?>
                    <?php foreach ($recent_notices as $notice): ?>
                        <a href="/notice?id=<?php echo $notice['id']; ?>" class="notice-item">
                            <div class="notice-content">
                                <h3>
                                    <?php if ($notice['is_new']): ?>
                                        <span class="badge-new">NEW</span>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($notice['title']); ?>
                                </h3>
                                <div class="notice-meta">
                                    <span><i class="far fa-clock"></i> <?php echo date('Y.m.d', strtotime($notice['created_at'])); ?></span>
                                    <span><i class="far fa-eye"></i> <?php echo number_format($notice['views']); ?></span>
                                </div>
                            </div>
                            <i class="fas fa-chevron-right notice-arrow"></i>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-notices">
                        <p>등록된 공지사항이 없습니다.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>



        <?php include 'includes/footer.php'; ?>

        <!-- Slick Slider 스크립트 -->
        <script type="text/javascript" src="//code.jquery.com/jquery-1.11.0.min.js"></script>
        <script type="text/javascript" src="//code.jquery.com/jquery-migrate-1.2.1.min.js"></script>
        <script type="text/javascript" src="//cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js"></script>
        <link rel="stylesheet" type="text/css" href="//cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css" />

        <script>
        $(document).ready(function() {
            $('.main-slider').slick({
                dots: true,
                infinite: true,
                speed: 500,
                fade: true,
                cssEase: 'linear',
                autoplay: true,
                autoplaySpeed: 3000,
                prevArrow: '<button type="button" class="slick-prev"></button>',
                nextArrow: '<button type="button" class="slick-next"></button>',
                customPaging: function(slider, i) {
                    return '<button type="button"></button>';
                }
            });
        });
        </script>

        <script>
function copyToClipboard() {
    const input = document.getElementById('referralLink');
    input.select();
    document.execCommand('copy');
    showNotification("추천링크가 복사되었습니다.", "success");
}

function showLoginMessage() {
    showNotification("로그인 후 이용하실 수 있습니다.", "error");
    setTimeout(() => {
        window.location.href = '/login';
    }, 1500);
}
</script>

</body>

</html>