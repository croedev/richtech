<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/richtech.club/pages/error.log');

session_start();
require_once __DIR__ . '/../includes/config.php';

// 로그인 체크
if (!isset($_SESSION['user_id'])) {
    header("Location: /login?redirect=chart");
    exit();
}

$conn = db_connect();

// 차트 타입 확인 (기본값: referral)
$chart_type = isset($_GET['type']) && $_GET['type'] === 'sponsor' ? 'sponsor' : 'referral';

// AJAX 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = [];

    if (isset($_POST['action']) && $_POST['action'] === 'search_member') {
        $search_term = trim($_POST['search_term']);
        $chart_type = $_POST['chart_type'] ?? 'referral';
        $relationship_column = $chart_type === 'sponsor' ? 'sponsored_by' : 'referred_by';

        $stmt = $conn->prepare("
            WITH RECURSIVE member_tree AS (
                SELECT id, login_id, name, $relationship_column as parent_id, 0 as level,
                       CAST(id AS CHAR(50)) as path
                FROM users 
                WHERE id = ?
                UNION ALL
                SELECT u.id, u.login_id, u.name, u.$relationship_column, mt.level + 1,
                       CONCAT(mt.path, ',', u.id)
                FROM users u
                INNER JOIN member_tree mt ON u.$relationship_column = mt.id
                WHERE mt.level < 20
            )
            SELECT id, login_id, name, level, path
            FROM member_tree
            WHERE login_id LIKE ? OR name LIKE ?
            ORDER BY level ASC
            LIMIT 1
        ");

        $search_param = "%$search_term%";
        $stmt->bind_param('iss', $_SESSION['user_id'], $search_param, $search_param);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $found_member = $result->fetch_assoc();
            $response = [
                'success' => true, 
                'member' => $found_member,
                'path' => array_map('intval', explode(',', $found_member['path']))
            ];
        } else {
            // 전체 회원에서 검색
            $stmt = $conn->prepare("
                SELECT id, login_id, name 
                FROM users 
                WHERE (login_id LIKE ? OR name LIKE ?) 
                AND $relationship_column IS NOT NULL
                LIMIT 1
            ");
            $stmt->bind_param('ss', $search_param, $search_param);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $response = [
                    'success' => false,
                    'message' => '해당 회원은 내 하위 조직에 없습니다.'
                ];
            } else {
                $response = [
                    'success' => false,
                    'message' => '존재하지 않는 회원입니다.'
                ];
            }
        }

        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    // 조직도 데이터 요청 처리
    $level = isset($_POST['level']) ? min((int)$_POST['level'], 20) : 5;
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : $_SESSION['user_id'];
    $expand_path = isset($_POST['expand_path']) ? $_POST['expand_path'] : null;
    $chart_type = isset($_POST['chart_type']) ? $_POST['chart_type'] : 'referral';

    $orgData = $chart_type === 'sponsor' ? 
        buildSponsorOrgData($conn, $user_id, $level, $expand_path) :
        buildReferralOrgData($conn, $user_id, $level, $expand_path);

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $orgData]);
    exit;
}

/**
 * 추천 조직도 데이터 생성 함수
 */
function buildReferralOrgData($conn, $user_id, $level, $expand_path = null) {
    if ($level < 0) return null;

    $stmt = $conn->prepare("
        SELECT id, login_id, name, rank, myAmount,
               (SELECT COUNT(*) FROM users WHERE referred_by = u.id) as child_count
        FROM users u
        WHERE id = ?
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) return null;

    $node = [
        'id' => $user['id'],
        'login_id' => $user['login_id'],
        'name' => $user['name'],
        'rank' => $user['rank'],
        'myAmount' => number_format((float)$user['myAmount'], 2),
        'has_more' => $user['child_count'] > 0 && $level === 0,
        'children' => []
    ];

    if ($level > 0 || ($expand_path && in_array($user['id'], $expand_path))) {
        $stmt = $conn->prepare("
            SELECT id 
            FROM users 
            WHERE referred_by = ?
            ORDER BY created_at ASC
        ");
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($child = $result->fetch_assoc()) {
            $next_level = $level - 1;
            if ($expand_path && in_array($child['id'], $expand_path)) {
                $next_level = max($next_level, 0);
            }
            $childNode = buildReferralOrgData($conn, $child['id'], $next_level, $expand_path);
            if ($childNode) {
                $node['children'][] = $childNode;
            }
        }
    }

    return $node;
}

/**
 * 후원 조직도 데이터 생성 함수
 */
function buildSponsorOrgData($conn, $user_id, $level, $expand_path = null) {
    if ($level < 0) return null;

    $stmt = $conn->prepare("
        SELECT id, login_id, name, rank, myAmount, position,
               left_amounts, right_amounts, left_members, right_members,
               (SELECT COUNT(*) FROM users WHERE sponsored_by = u.id) as child_count
        FROM users u
        WHERE id = ?
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) return null;

    $node = [
        'id' => $user['id'],
        'login_id' => $user['login_id'],
        'name' => $user['name'],
        'rank' => $user['rank'],
        'myAmount' => number_format((float)$user['myAmount'], 2),
        'has_more' => $user['child_count'] > 0 && $level === 0,
        'position' => $user['position'],
        'left_amounts' => number_format((float)$user['left_amounts'], 2),
        'right_amounts' => number_format((float)$user['right_amounts'], 2),
        'left_members' => $user['left_members'],
        'right_members' => $user['right_members'],
        'children' => []
    ];

    if ($level > 0 || ($expand_path && in_array($user['id'], $expand_path))) {
        $stmt = $conn->prepare("
            SELECT id 
            FROM users 
            WHERE sponsored_by = ?
            ORDER BY FIELD(position, 'left', 'right'), created_at ASC
        ");
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($child = $result->fetch_assoc()) {
            $next_level = $level - 1;
            if ($expand_path && in_array($child['id'], $expand_path)) {
                $next_level = max($next_level, 0);
            }
            $childNode = buildSponsorOrgData($conn, $child['id'], $next_level, $expand_path);
            if ($childNode) {
                $node['children'][] = $childNode;
            }
        }
    }

    return $node;
}

// 페이지 타이틀 설정
$chart_type = isset($_GET['type']) ? $_GET['type'] : 'referral';
$pageTitle = $chart_type === 'sponsor' ? '후원 조직도' : '추천 조직도';

include __DIR__ . '/../includes/header.php';
?>



<style>
    body {
        background-color: #121212;
        color: #ffffff;
        font-family: 'Noto Sans KR', sans-serif;
        margin: 0;
        padding: 0;
    }

    .content {
        padding: 0;
        margin-top: -50px !important;
        padding-bottom: 0px;
        max-height: calc(100vh - 0px);
        overflow-y: auto;
    }

    .control-box {
        padding: 10px;
        background: linear-gradient(145deg, #1a1a1a, #2a2a2a);
        border-bottom: 1px solid #333;
        position: fixed;
        top: 50px;
        left: 0;
        right: 0;
        z-index: 100;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    }

.control-box input {
            flex-grow: 1; /* 화면 크기에 따라 검색 input 크기 조정 */
        }

    .search-container {
        max-width: 600px;
        min-width: 320px;
        margin: 0 auto;
        display: flex;
        gap: 5px;
        align-items: center;
        flex-wrap: nowrap;
    }

    .search-group {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .search-input {
        background: #2d2d2d;
        border: 1px solid #d4af37;
        color: #fff;
        padding: 4px 10px;
        border-radius: 5px;
        font-size: 13px;
    }

    #level {
        width: 50px;
        min-width: 80px;
    }

    #search_term {
        flex: 1;
        min-width: 60px;
    }

    .search-input:focus {
        outline: none;
        border-color: #f2d06b;
        box-shadow: 0 0 0 2px rgba(212,175,55,0.2);
    }

    .search-btn {
        background: linear-gradient(145deg, #aba9a3, #3b3a39);
        color: #000;
        border: none;
        padding: 4px 10px;
        border-radius: 5px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        white-space: nowrap;
        min-width: 40px;
    }

    .search-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(212,175,55,0.3);
    }

    /* 차트 타입 토글 버튼 스타일 */
    .chart-type-toggle {
        position: fixed;
        top: 110px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 101;
        display: flex;
        gap: 1px;
        background: linear-gradient(145deg, #2d2d2d, #404040);
        padding: 3px;
        border-radius: 5px;
        border: 1px solid #333;
    }

    .chart-type-btn {
        background: transparent;
        border: 1px solid #d4af37;
        color: #d4af37;
        padding: 4px 12px;
        border-radius: 3px;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.3s ease;
        white-space: nowrap;
    }

 .chart-type-btn.active {
    background: linear-gradient(145deg, #590202, #e73b11);
    color: #fff;
}

    .legend {
        position: fixed;
        top: 110px;
        left: 10px;
        background: linear-gradient(145deg, #2d2d2d, #404040);
        padding: 10px;
        border-radius: 8px;
        border: 1px solid #333;
        z-index: 100;
        box-shadow: 2px 2px 5px rgba(0,0,0,0.2);
    }

    .legend-item {
        display: flex;
        align-items: center;
        margin: 3px 0;
        font-size: 11px;
    }

    .legend-color {
        width: 14px;
        height: 14px;
        margin-right: 8px;
        border-radius: 3px;
        border: 1px solid #444;
    }

    .nav-controls {
        position: fixed;
        top: 110px;
        right: 10px;
        background: linear-gradient(145deg, #2d2d2d, #404040, 0.5);
        padding: 5px;
        border-radius: 8px;
        border: 0px solid #333;
        z-index: 100;
        display: flex;
        flex-direction: column;
        gap: 5px;
        box-shadow: 2px 2px 5px rgba(0,0,0,0.2);
    }

    .nav-btn {
        background: linear-gradient(145deg, #3a3a3a, #2a2a2a);
        border: 1px solid #d4af37;
        color: #d4af37;
        padding: 5px;
        border-radius: 5px;
        cursor: pointer;
        transition: all 0.3s ease;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .nav-btn:hover {
        background: linear-gradient(145deg, #d4af37, #aa8a2e);
        color: #000;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(212,175,55,0.3);
    }

    #org_chart {
        margin-top: 130px;
        width: 100%;
        height: calc(100vh - 130px);
        overflow: hidden;
    }

    .node {
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .node rect {
        fill-opacity: 0.9;
        stroke: #333;
        stroke-width: 1px;
    }

    .node.highlighted rect {
        fill: #ff4444 !important;
        stroke: #ff8888;
        stroke-width: 2px;
    }

    .node.highlighted text {
        fill: #ffffff !important;
    }

    .node text.name {
        font-size: 14px;
        font-weight: 600;
    }

    .node text.id {
        font-size: 12px;
        fill: #666;
    }

    .node text.amount {
        font-size: 11px;
        fill: #888;
    }

    .link {
        fill: none;
        stroke: #555;
        stroke-width: 1.5px;
    }

    /* 후원조직도 전용 스타일 */
    .node.sponsor {
        position: relative;
    }

    .node.sponsor .amounts-info text {
        font-size: 10px;
        fill: #888;
    }

    .node.sponsor.left-position .link {
        stroke: #96e399;
    }

    .node.sponsor.right-position .link {
        stroke: #e3885b;
    }

    .more-button {
        cursor: pointer;
    }

    .more-button:hover .more-icon,
    .more-button:hover .more-text {
        fill: #f2d06b;
    }

    .context-menu {
        position: absolute;
        background: linear-gradient(145deg, #2d2d2d, #404040);
        border: 1px solid #d4af37;
        border-radius: 5px;
        padding: 5px 0;
        z-index: 1000;
        box-shadow: 0 2px 5px rgba(0,0,0,0.3);
    }

    .menu-item {
        padding: 5px 15px;
        cursor: pointer;
        color: #d4af37;
        font-size: 12px;
        transition: all 0.3s ease;
    }

    .menu-item:hover {
        background: rgba(212,175,55,0.2);
    }

    .tooltip {
        position: absolute;
        background: rgba(0,0,0,0.9);
        color: #fff;
        padding: 8px 12px;
        border-radius: 4px;
        font-size: 12px;
        pointer-events: none;
        z-index: 1000;
        border: 1px solid #d4af37;
    }

    /* 버튼 활성화 상태 스타일 */
.switch-buttons button.active {
    background-color: #b38e30; /* 활성화된 버튼 색상 */
    color: #ffffff; /* 활성화된 버튼 텍스트 색상 */
}

</style>

<!-- 기존 타이틀 표시 부분을 다음과 같이 수정 -->
<h1 id="page_header_title" class="page-title"><?php echo $pageTitle; ?></h1>
<div class="control-box">
    <div class="search-container">
        <div class="search-group">
            <input type="number" id="level" class="search-input w-20" placeholder="단계 (1-20)" min="1" max="20" value="5">
            <button id="level_search_btn" class="search-btn">
                <i class="fas fa-search"></i>
            </button>
        </div>
        <div class="search-group">
            <input type="text" id="search_term" class="search-input w-30" placeholder="회원 아이디/이름 검색">
            <button id="member_search_btn" class="search-btn">
                <i class="fas fa-search"></i>
            </button>
        </div>
    </div>
</div>

<div class="swithch-buttons chart-type-toggle">
    <button id="recommendation_btn" class="chart-type-btn <?php echo $chart_type === 'referral' ? 'active' : ''; ?>" onclick="switchChartType('referral')">추천조직도</button>
    <button id="sponsorship_btn" class="chart-type-btn <?php echo $chart_type === 'sponsor' ? 'active' : ''; ?>" onclick="switchChartType('sponsor')">후원조직도</button>
</div>

<div class="legend">
    <div class="legend-item">
        <div class="legend-color" style="background: #ffffff;"></div>회원
    </div>
    <div class="legend-item">
        <div class="legend-color" style="background: #FFBF00;"></div>1스타
    </div>
    <div class="legend-item">
        <div class="legend-color" style="background: #96e399;"></div>2스타
    </div>
    <div class="legend-item">
        <div class="legend-color" style="background: #cfb144;"></div>3스타
    </div>
    <div class="legend-item">
        <div class="legend-color" style="background: #e3885b;"></div>4스타
    </div>
    <div class="legend-item">
        <div class="legend-color" style="background: #f04141;"></div>5스타
    </div>
    <div class="legend-item">
        <div class="legend-color" style="background: #a84bd6;"></div>6스타
    </div>
    <div class="legend-item">
        <div class="legend-color" style="background: #ed8ae5;"></div>7스타
    </div>
</div>

<div class="nav-controls">
    <button class="nav-btn" id="reset_view" title="원래상태로">
        <i class="fas fa-sync-alt"></i>
    </button>
    <button class="nav-btn" id="zoom_in" title="확대">
        <i class="fas fa-plus"></i>
    </button>
    <button class="nav-btn" id="zoom_out" title="축소">
        <i class="fas fa-minus"></i>
    </button>
    <button class="nav-btn" id="fit_view" title="전체보기">
        <i class="fas fa-expand"></i>
    </button>
</div>

<div id="org_chart"></div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://d3js.org/d3.v7.min.js"></script>
<script>
    $(document).ready(function () {
        let currentZoom;
        let svg, mainGroup;
        let currentHighlightedNode = null;
        let isInitialLoad = true;
        let currentChartType = '<?php echo $chart_type; ?>';

        // 윈도우 리사이즈 이벤트 처리
        $(window).on('resize', debounce(function() {
            if (svg) {
                const currentLevel = parseInt($('#level').val()) || 5;
                loadOrgData(currentLevel, <?php echo $_SESSION['user_id']; ?>);
            }
        }, 250));

        // 디바운스 함수
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // 차트 타입 전환 함수
        function switchChartType(type) {
            if (type === currentChartType) return;

            // URL 업데이트 후 페이지 새로고침
            const url = new URL(window.location);
            url.searchParams.set('type', type);
            window.location.href = url.toString(); // 페이지 새로고침
        }

        // 조직도 데이터 로드 함수
        function loadOrgData(level, user_id, highlightId = null, expandPath = null) {
            // 로딩 표시 추가
            showLoading();

            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: { 
                    level: level, 
                    user_id: user_id,
                    expand_path: expandPath,
                    chart_type: currentChartType
                },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        renderOrgChart(response.data, highlightId);
                        isInitialLoad = false;
                    } else {
                        alert(response.message || "데이터 로드 실패");
                    }
                },
                error: function () {
                    alert("데이터 로드 실패");
                },
                complete: function() {
                    hideLoading();
                }
            });
        }

        // 로딩 표시/숨김 함수
        function showLoading() {
            if (!$('#loading-indicator').length) {
                $('body').append(`
                    <div id="loading-indicator" style="
                        position: fixed;
                        top: 50%;
                        left: 50%;
                        transform: translate(-50%, -50%);
                        background: rgba(0,0,0,0.8);
                        padding: 20px;
                        border-radius: 10px;
                        z-index: 1000;
                        color: #d4af37;
                    ">
                        <i class="fas fa-spinner fa-spin"></i> 로딩중...
                    </div>
                `);
            }
        }

        function hideLoading() {
            $('#loading-indicator').remove();
        }

        // 조직도 렌더링 함수
        function renderOrgChart(data, highlightId = null) {
            $('#org_chart').empty();

            const margin = {
                top: 20,
                right: 50,
                bottom: 50,
                left: 50
            };
            const width = $('#org_chart').width() - margin.left - margin.right;
            const height = $('#org_chart').height() - margin.top - margin.bottom;

            svg = d3.select('#org_chart')
                .append('svg')
                .attr('width', width + margin.left + margin.right)
                .attr('height', height + margin.top + margin.bottom);

            mainGroup = svg.append('g');

            // 줌 기능 설정
            currentZoom = d3.zoom()
                .scaleExtent([0.1, 3])
                .on('zoom', (event) => {
                    mainGroup.attr('transform', event.transform);
                });

            svg.call(currentZoom)
               .call(currentZoom.transform, d3.zoomIdentity);

            // 트리 레이아웃 설정
            const tree = d3.tree()
                .nodeSize(currentChartType === 'sponsor' ? [100, 150] : [85, 95])
                .separation((a, b) => {
                    if (currentChartType === 'sponsor') {
                        return a.parent === b.parent ? 2 : 2.5;
                    }
                    return a.parent === b.parent ? 1.5 : 2;
                });

            const root = d3.hierarchy(data);
            tree(root);

            // 수직 직선 연결선 그리기 함수
            function verticalLink(d) {
                return `M${d.source.x},${d.source.y}
                        L${d.source.x},${(d.source.y + d.target.y) / 2}
                        L${d.target.x},${(d.source.y + d.target.y) / 2}
                        L${d.target.x},${d.target.y}`;
            }

            // 링크(선) 그리기
            const links = mainGroup.selectAll('.link')
                .data(root.links())
                .enter()
                .append('path')
                .attr('class', 'link')
                .attr('d', verticalLink)
                .style('stroke', d => {
                    // 후원조직도의 경우 좌우 구분을 위한 색상 적용
                    if (currentChartType === 'sponsor' && d.target.data.position) {
                        return d.target.data.position === 'left' ? '#96e399' : '#e3885b';
                    }
                    return '#555';
                });

            // 노드 그룹 생성
            const nodes = mainGroup.selectAll('.node')
                .data(root.descendants())
                .enter()
                .append('g')
                .attr('class', d => {
                    let classes = ['node'];
                    if (d.data.id === highlightId) classes.push('highlighted');
                    if (currentChartType === 'sponsor') {
                        classes.push('sponsor');
                        if (d.data.position) classes.push(`${d.data.position}-position`);
                    }
                    return classes.join(' ');
                })
                .attr('transform', d => `translate(${d.x},${d.y})`);

            // 노드 배경 사각형
            nodes.append('rect')
                .attr('width', 120)
                .attr('height', 70)
                .attr('x', -60)
                .attr('y', -35)
                .attr('rx', 5)
                .attr('ry', 5)
                .style('fill', d => {
                    const rankColors = {
                        '회원': '#ffffff',
                        '1스타': '#FFBF00',
                        '2스타': '#96e399',
                        '3스타': '#cfb144',
                        '4스타': '#e3885b',
                        '5스타': '#f04141',
                        '6스타': '#a84bd6',
                        '7스타': '#ed8ae5'
                    };
                    return rankColors[d.data.rank] || '#f5f5f5';
                });

            // 후원조직도 추가 정보
            if (currentChartType === 'sponsor') {
                nodes.each(function(d) {
                    if (d.data.left_amounts !== undefined) {
                        const amounts = d3.select(this).append('g')
                            .attr('class', 'amounts-info')
                            .attr('transform', 'translate(0, 45)');

                        amounts.append('text')
                            .attr('x', -50)
                            .attr('text-anchor', 'middle')
                            .text(d => `L:$${d.data.left_amounts}(${d.data.left_members})`);

                        amounts.append('text')
                            .attr('x', 50)
                            .attr('text-anchor', 'middle')
                            .text(d => `R:$${d.data.right_amounts}(${d.data.right_members})`);
                    }
                });
            }

            // 기본 노드 정보
            nodes.append('text')
                .attr('class', 'id')
                .attr('dy', -15)
                .attr('text-anchor', 'middle')
                .text(d => `${d.data.login_id} (${d.data.id})`)
                .style('fill', d => d.data.id === highlightId ? '#ffffff' : '#333');

            nodes.append('text')
                .attr('class', 'name')
                .attr('dy', 5)
                .attr('text-anchor', 'middle')
                .text(d => d.data.name)
                .style('fill', d => d.data.id === highlightId ? '#ffffff' : '#333');

            nodes.append('text')
                .attr('class', 'amount')
                .attr('dy', 25)
                .attr('text-anchor', 'middle')
                .text(d => `$${d.data.myAmount}`)
                .style('fill', d => {
                    // 매출액이 0보다 크면 파란색, 아니면 기존 색상 유지
                    if (parseFloat(d.data.myAmount) > 0) {
                        return '#0066ff'; // 파란색
                    }
                    return d.data.id === highlightId ? '#ffffff' : '#666';
                });

            // 더보기 버튼과 호버 효과
            addMoreButtons(nodes);

            // 노드 툴팁과 컨텍스트 메뉴
            setupNodeInteractions(nodes);

            // 위치 설정
            setInitialPosition(nodes, highlightId, width, height, margin);
        }

        // 노드 인터랙션 설정
        function setupNodeInteractions(nodes) {
            const tooltip = d3.select('body')
                .append('div')
                .attr('class', 'tooltip')
                .style('opacity', 0);

            nodes.on('mouseover', function(event, d) {
                    tooltip.transition()
                        .duration(200)
                        .style('opacity', .9);

                    let tooltipContent = `
                        <strong>${d.data.name}</strong><br/>
                        ID: ${d.data.login_id} (${d.data.id})<br/>
                        직급: ${d.data.rank}<br/>
                        실적: $${d.data.myAmount}
                    `;

                    if (currentChartType === 'sponsor') {
                        tooltipContent += `<br/>
                            좌측: $${d.data.left_amounts} (${d.data.left_members}명)<br/>
                            우측: $${d.data.right_amounts} (${d.data.right_members}명)
                        `;
                    }

                    tooltip.html(tooltipContent)
                        .style('left', (event.pageX + 10) + 'px')
                        .style('top', (event.pageY - 28) + 'px');
                })
                .on('mouseout', function() {
                    tooltip.transition()
                        .duration(500)
                        .style('opacity', 0);
                })
                .on('contextmenu', function(event, d) {
                    event.preventDefault();
                    showContextMenu(event, d);
                });
        }

        // 컨텍스트 메뉴
        function showContextMenu(event, d) {
            d3.selectAll('.context-menu').remove();

            const menu = d3.select('body')
                .append('div')
                .attr('class', 'context-menu')
                .style('left', `${event.pageX}px`)
                .style('top', `${event.pageY}px`);

            menu.append('div')
                .attr('class', 'menu-item')
                .text('중심노드로 보기')
                .on('click', () => {
                    isInitialLoad = true;
                    loadOrgData(5, d.data.id);
                    menu.remove();
                });
        }

        // 더보기 버튼 추가
        function addMoreButtons(nodes) {
            nodes.each(function(d) {
                if (d.data.has_more) {
                    const moreButton = d3.select(this)
                        .append('g')
                        .attr('class', 'more-button')
                        .attr('transform', 'translate(0, 35)');

                    moreButton.append('path')
                        .attr('class', 'more-icon')
                        .attr('d', 'M-5,0 L5,0 L0,8 Z')
                        .style('fill', '#d4af37');

                    moreButton.append('text')
                        .attr('class', 'more-text')
                        .attr('text-anchor', 'middle')
                        .attr('dy', 20)
                        .text('더보기')
                        .style('fill', '#d4af37');

                    moreButton.on('click', function(event) {
                        event.stopPropagation();
                        expandNode(d);
                    });
                }
            });
        }

        // 초기 위치 설정
        function setInitialPosition(nodes, highlightId, width, height, margin) {
            if (highlightId) {
                // 검색된 노드가 있으면 중앙에 배치
                const highlightedNode = nodes.filter(d => d.data.id === highlightId);
                if (!highlightedNode.empty()) {
                    const nodeData = highlightedNode.datum();
                    const x = width / 2 - nodeData.x;
                    const y = height / 2 - nodeData.y;
                    const t = d3.zoomIdentity.translate(x, y).scale(0.8);

                    svg.transition()
                       .duration(750)
                       .call(currentZoom.transform, t);
                }
            } else if (isInitialLoad) {
                // 초기 로드시 중앙에 배치
                const t = d3.zoomIdentity
                    .translate(width/2 + 50, 120) // 가로절반에서+50, 세로는 위에서 아래로 120이동
                    .scale(0.6);
                svg.call(currentZoom.transform, t);
            }
        }

        // 노드 확장 함수
        function expandNode(node) {
            const currentLevel = parseInt($('#level').val()) || 5;
            const newLevel = currentLevel + 5;
            $('#level').val(newLevel);
            loadOrgData(newLevel, <?php echo $_SESSION['user_id']; ?>);
        }

        // 네비게이션 컨트롤 이벤트 핸들러
        $('#reset_view').click(() => {
            isInitialLoad = true;
            loadOrgData(5, <?php echo $_SESSION['user_id']; ?>);
        });

        $('#zoom_in').click(() => {
            svg.transition().duration(750).call(currentZoom.scaleBy, 1.2);
        });

        $('#zoom_out').click(() => {
            svg.transition().duration(750).call(currentZoom.scaleBy, 0.8);
        });

        // 전체보기 - 전체 조직도를 화면에 맞게 조정
        $('#fit_view').click(() => {
            const bounds = mainGroup.node().getBBox();
            const parent = svg.node().parentElement;
            const fullWidth = parent.clientWidth;
            const fullHeight = parent.clientHeight;

            const width = bounds.width;
            const height = bounds.height;
            const midX = (bounds.x + bounds.x + bounds.width) / 2;
            const midY = (bounds.y + bounds.y + bounds.height) / 2;

            const scale = 0.95 / Math.max(width / fullWidth, height / fullHeight);
            const translate = [fullWidth / 2 - scale * midX, fullHeight / 2 - scale * midY];

            svg.transition()
               .duration(750)
               .call(currentZoom.transform, d3.zoomIdentity.translate(translate[0], translate[1]).scale(scale));
        });

        // 검색 기능
        function setupSearch() {
            // 단계 검색 버튼
            $('#level_search_btn').click(function() {
                const level = parseInt($('#level').val()) || 5;
                loadOrgData(level, <?php echo $_SESSION['user_id']; ?>);
            });

            // 회원 검색 버튼
            $('#member_search_btn').click(function() {
                const searchTerm = $('#search_term').val().trim();
                if (!searchTerm) {
                    alert('검색어를 입력해주세요.');
                    return;
                }

                const level = parseInt($('#level').val()) || 5;

                $.ajax({
                    url: window.location.href,
                    method: 'POST',
                    data: {
                        action: 'search_member',
                        search_term: searchTerm,
                        chart_type: currentChartType
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            loadOrgData(level, <?php echo $_SESSION['user_id']; ?>,
                                response.member.id, response.path);
                        } else {
                            alert(response.message);
                        }
                    },
                    error: function() {
                        alert("검색 중 오류가 발생했습니다.");
                    }
                });
            });

            $('#search_term').keypress(function(e) {
                if (e.which == 13) {
                    $('#member_search_btn').click();
                }
            });

            // 단계 입력값 검증
            $('#level').on('input', function() {
                let value = parseInt($(this).val());
                if (value < 1) $(this).val(1);
                if (value > 20) $(this).val(20);
            });
        }

        // 초기화 및 이벤트 바인딩
        function initialize() {
            setupSearch();

            // 차트 타입 버튼 이벤트 핸들러
            $('#recommendation_btn').click(function() {
                switchChartType('referral');
            });

            $('#sponsorship_btn').click(function() {
                switchChartType('sponsor');
            });

            // 초기 로드
            loadOrgData(5, <?php echo $_SESSION['user_id']; ?>);

            // 클릭 이벤트로 컨텍스트 메뉴 닫기
            $(document).click(() => {
                d3.selectAll('.context-menu').remove();
            });

            // ESC 키로 컨텍스트 메뉴 닫기
            $(document).keyup(function(e) {
                if (e.key === "Escape") {
                    d3.selectAll('.context-menu').remove();
                }
            });
        }

        // 초기화 실행
        initialize();

        // 차트 타입 전환 함수를 글로벌 스코프로 노출
        window.switchChartType = switchChartType;



   // 조직도 전환 버튼 클릭 이벤트
         // 추천/후원 조직도 버튼 클릭 이벤트
$('#recommendation_btn').click(function () {
    document.title = '추천 조직도'; // 브라우저 타이틀 변경
    $('#page_title').text('추천 조직도'); // 상단 타이틀 변경
    $('#recommendation_btn').addClass('active'); // 추천 조직도 버튼 활성화
    $('#sponsorship_btn').removeClass('active'); // 후원 조직도 버튼 비활성화
});

$('#sponsorship_btn').click(function () {
    document.title = '후원 조직도'; // 브라우저 타이틀 변경
    $('#page_title').text('후원 조직도'); // 상단 타이틀 변경
    $('#sponsorship_btn').addClass('active'); // 후원 조직도 버튼 활성화
    $('#recommendation_btn').removeClass('active'); // 추천 조직도 버튼 비활성화
});


    });


 
</script>


<?php include __DIR__ . '/../includes/footer.php'; ?>
