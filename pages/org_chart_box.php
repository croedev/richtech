<?php
// 오류 보고 설정
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
// 실제 경로로 변경하세요.
ini_set('error_log', '/home/lidyahkc/dir/1626.lidyahk.com/pages/error.log');


session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/organization_functions.php';

// 사용자 인증 확인
if (!isset($_SESSION['user_id'])) {
    header("Location: /login?redirect=org_chart_box");
    exit();
}

$user_id = $_SESSION['user_id'];
$conn = db_connect();

if (!$conn) {
    die("데이터베이스 연결에 실패했습니다.");
}

// 사용자 정보 가져오기
$user = getUserInfo($conn, $user_id);
if ($user === null) {
    error_log("사용자 정보를 가져오는데 실패했습니다. User ID: " . $user_id);
    die("사용자 정보를 가져오는데 실패했습니다. 관리자에게 문의하세요.");
}

// 조직도 데이터 가져오기
$organizationData = getOrganizationData($conn, $user_id);

if (empty($organizationData)) {
    $organizationData = [
        [
            'id' => $user_id,
            'name' => $user['name'],
            'rank' => $user['rank'],
            'myQuantity' => $user['myQuantity'] ?? $user['direct_volume'] ?? 0,
            'myAmount' => $user['myAmount'] ?? 0,
            'myTotal_quantity' => $user['myTotal_quantity'] ?? $user['ref_total_volume'] ?? 0,
            'myTotal_Amount' => $user['myTotal_Amount'] ?? 0,
            'commission_total' => $user['commission_total'] ?? 0,
            'nft_token' => $user['nft_token'] ?? 0,
            'myAgent' => $user['myAgent'] ?? 0,
            'myAgent_referral' => $user['myAgent_referral'] ?? 0,
            'phone' => $user['phone'] ?? '',
            'total_distributor_count' => $user['total_distributor_count'] ?? 0,
            'special_distributor_count' => $user['special_distributor_count'] ?? 0,
            'direct_referrals_count' => $user['direct_referrals_count'] ?? 0,
            'total_referrals_count' => $user['total_referrals_count'] ?? 0,
            'referred_by' => null,
            'depth' => 1
        ]
    ];
}

// D3.js에서 사용하기 위한 데이터 변환
function buildOrgChartData($organizationData) {
    $dataMap = [];
    foreach ($organizationData as $user) {
        $dataMap[$user['id']] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'parentid' => $user['referred_by'],
            'rank' => $user['rank'],
            'phone' => $user['phone'],
            'myQuantity' => $user['myQuantity'],
            'myTotal_quantity' => $user['myTotal_quantity'],
            'myAmount' => $user['myAmount'],
            'myTotal_Amount' => $user['myTotal_Amount'],
            'commission_total' => $user['commission_total'],
            'nft_token' => $user['nft_token'],
            'myAgent' => $user['myAgent'],
            'myAgent_referral' => $user['myAgent_referral'],
            // 기타 필요한 데이터 추가
        ];
    }

    // 트리 구조로 변환
    $treeData = [];
    foreach ($dataMap as $id => &$node) {
        if ($node['parentid'] === null || !isset($dataMap[$node['parentid']])) {
            $treeData[] = &$node;
        } else {
            $parent = &$dataMap[$node['parentid']];
            $parent['children'][] = &$node;
        }
    }

    return $treeData;
}

$orgChartData = buildOrgChartData($organizationData);

// D3.js에 사용할 수 있도록 JSON 인코딩
$orgChartDataJson = json_encode($orgChartData, JSON_UNESCAPED_UNICODE);

$pageTitle = '박스형 조직도';
include __DIR__ . '/../includes/header.php';
?>

<style>
/* 조직도 스타일 정의 */
.node {
    cursor: pointer;
}

.node rect {
    fill: #fff;
    stroke: steelblue;
    stroke-width: 1.5px;
}

.node text {
    font: 12px sans-serif;
}

.link {
    fill: none;
    stroke: #ccc;
    stroke-width: 1.5px;
}

#member-info {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background-color: #f9f9f9;
    color: #000;
    font-size: 0.9em;
    line-height: 1.4;
    padding: 15px;
    box-shadow: 0 -2px 5px rgba(0, 0, 0, 0.1);
    z-index: 1000;
    font-family: 'Noto Sans KR', sans-serif;
}

#member-info h4 {
    font-size: 1.1em;
    margin-bottom: 10px;
    color: blue;
}

#member-info p {
    margin: 5px 0;
}

/* 모바일 화면에서의 스타일 조정 */
@media (max-width: 768px) {
    #member-info {
        padding: 10px;
        font-size: 0.8em;
    }
}
</style>

<div class="container mt-4 mb-5">
    <h5><?php echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?> (ID:<?php echo htmlspecialchars($user['id'], ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars($user['phone'], ENT_QUOTES, 'UTF-8'); ?>)</h5>
    <hr>

    <div class="row mb-3">
        <div class="col-md-12 text-right">
            <a href="/org_tree" class="btn btn-sm <?php echo ($_SERVER['REQUEST_URI'] == '/org_tree') ? 'btn-primary' : 'btn-secondary'; ?> ml-2">
                <i class="fas fa-sitemap"></i> 트리형 조직도보기
            </a>
            <a href="/organization" class="btn btn-sm <?php echo ($_SERVER['REQUEST_URI'] == '/organization') ? 'btn-primary' : 'btn-secondary'; ?> ml-2">
                <i class="fas fa-th-large"></i> 박스형 조직도보기
            </a>
        </div>
    </div>

    <!-- 검색 폼 -->
    <form id="search-form" class="form-inline mb-3">
        <div class="form-group mr-3">
            <label for="max-depth" class="mr-2">레벨:</label>
            <input type="number" class="form-control" id="max-depth" name="max_depth" min="1" value="5">
        </div>
        <div class="form-group mr-3">
            <label for="search-name" class="mr-2">회원명:</label>
            <input type="text" class="form-control" id="search-name" name="search_name" placeholder="회원명 입력">
        </div>
        <button type="submit" class="btn btn-primary">검색</button>
    </form>

    <!-- 조직도 컨테이너 -->
    <div id="chart"></div>
    <div id="member-info" style="display: none;" class="bg-yellow-b p-3 mt-3"></div>
</div>

<!-- D3.js 및 필요한 스크립트 -->
<script src="https://d3js.org/d3.v5.min.js"></script>

<script>
    var orgChartData = <?php echo $orgChartDataJson; ?>;

    // 초기 변수 설정
    var margin = { top: 20, right: 120, bottom: 20, left: 120 },
        width = window.innerWidth - margin.right - margin.left,
        height = window.innerHeight - margin.top - margin.bottom;

    var i = 0,
        duration = 750,
        root;

    var treeLayout = d3.tree().size([height, width]);

    var svg = d3.select("#chart").append("svg")
        .attr("width", width + margin.right + margin.left)
        .attr("height", height + margin.top + margin.bottom)
        .call(d3.zoom().scaleExtent([0.1, 3]).on("zoom", zoomed))
        .append("g")
        .attr("transform", "translate(" + margin.left + "," + margin.top + ")");

    function zoomed() {
        svg.attr("transform", d3.event.transform);
    }

    root = d3.hierarchy(orgChartData[0], function(d) { return d.children; });
    root.x0 = height / 2;
    root.y0 = 0;

    // 초기 depth 설정 (검색 폼에서 가져올 수 있음)
    var initialDepth = 5; // 기본값 5

    // collapse 함수 정의
    function collapse(d) {
        if (d.children) {
            d._children = d.children;
            d._children.forEach(collapse);
            d.children = null;
        }
    }

    // expand 함수 정의
    function expand(d) {
        if (d._children) {
            d.children = d._children;
            d.children.forEach(expand);
            d._children = null;
        }
    }

    // 초기 트리 설정
    root.children.forEach(function(d) {
        if (d.depth >= initialDepth) {
            collapse(d);
        } else {
            expand(d);
        }
    });

    update(root);

    function update(source) {
        var treeData = treeLayout(root);

        var nodes = treeData.descendants(),
            links = treeData.links();

        nodes.forEach(function(d) { d.y = d.depth * 180; });

        var node = svg.selectAll('g.node')
            .data(nodes, function(d) { return d.id || (d.id = ++i); });

        var nodeEnter = node.enter().append('g')
            .attr('class', 'node')
            .attr("transform", function(d) {
                return "translate(" + source.y0 + "," + source.x0 + ")";
            })
            .on('click', click);

        nodeEnter.append('rect')
            .attr('width', 160)
            .attr('height', 60)
            .attr('x', -80)
            .attr('y', -30)
            .attr('rx', 10)
            .attr('ry', 10)
            .attr('fill', '#fff')
            .attr('stroke', 'steelblue')
            .attr('stroke-width', '1.5px');

        nodeEnter.append('text')
            .attr('dy', '-0.6em')
            .attr('text-anchor', 'middle')
            .text(function(d) { return d.data.name; });

        nodeEnter.append('text')
            .attr('dy', '0.6em')
            .attr('text-anchor', 'middle')
            .text(function(d) { return "직급: " + d.data.rank; });

        nodeEnter.append('text')
            .attr('dy', '1.8em')
            .attr('text-anchor', 'middle')
            .text(function(d) { return "ID: " + d.data.id; });

        var nodeUpdate = nodeEnter.merge(node);

        nodeUpdate.transition()
            .duration(duration)
            .attr("transform", function(d) {
                return "translate(" + d.y + "," + d.x + ")";
            });

        nodeUpdate.select('rect')
            .attr('fill', function(d) {
                return d._children ? "#fff" : "#fff";
            });

        var nodeExit = node.exit().transition()
            .duration(duration)
            .attr("transform", function(d) {
                return "translate(" + source.y + "," + source.x + ")";
            })
            .remove();

        nodeExit.select('rect')
            .attr('width', 160)
            .attr('height', 60);

        var link = svg.selectAll('path.link')
            .data(links, function(d) { return d.target.id; });

        var linkEnter = link.enter().insert('path', "g")
            .attr("class", "link")
            .attr('d', function(d) {
                var o = { x: source.x0, y: source.y0 };
                return diagonal(o, o);
            });

        var linkUpdate = linkEnter.merge(link);

        linkUpdate.transition()
            .duration(duration)
            .attr('d', function(d) { return diagonal(d.source, d.target); });

        var linkExit = link.exit().transition()
            .duration(duration)
            .attr('d', function(d) {
                var o = { x: source.x, y: source.y };
                return diagonal(o, o);
            })
            .remove();

        nodes.forEach(function(d) {
            d.x0 = d.x;
            d.y0 = d.y;
        });

        // 노드 클릭 시 상세 정보 표시
        function click(event, d) {
            var content = "<h4>" + d.data.name + " (ID: " + d.data.id + ", " + d.data.phone + ")</h4>";
            content += "<p>직급: " + d.data.rank + "</p>";
            content += "<p>NFT 보유수량: " + numberWithCommas(d.data.nft_token || 0) + "</p>";
            content += "<p>개인구매: (수량: " + numberWithCommas(d.data.myQuantity || 0) + "개, 금액: " + numberWithCommas(d.data.myAmount || 0) + "원)</p>";
            content += "<p>본인하위전체: (수량: " + numberWithCommas(d.data.myTotal_quantity || 0) + "개, 금액: " + numberWithCommas(d.data.myTotal_Amount || 0) + "원)</p>";
            content += "<p>수수료 총액: " + numberWithCommas(d.data.commission_total || 0) + "원</p>";
            content += "<p>하위총판수: " + numberWithCommas(d.data.myAgent || 0) + "명, 직접추천한총판수: " + numberWithCommas(d.data.myAgent_referral || 0) + "명</p>";

            var infoDiv = document.getElementById('member-info');
            infoDiv.innerHTML = content;
            infoDiv.style.display = 'block';

            if (d.children) {
                d._children = d.children;
                d.children = null;
            } else {
                if (d._children) {
                    d.children = d._children;
                    d._children = null;
                }
            }
            update(d);
        }

        function diagonal(s, d) {
            path = `M ${s.y} ${s.x}
                    C ${(s.y + d.y) / 2} ${s.x},
                      ${(s.y + d.y) / 2} ${d.x},
                      ${d.y} ${d.x}`;
            return path;
        }
    }

    // 숫자 콤마 추가 함수
    function numberWithCommas(x) {
        if (x == null) return '0';
        return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    // 검색 폼 제출 처리
    document.getElementById('search-form').addEventListener('submit', function(e) {
        e.preventDefault();
        var maxDepthInput = document.getElementById('max-depth').value;
        var searchName = document.getElementById('search-name').value.trim().toLowerCase();

        initialDepth = parseInt(maxDepthInput) || 5;

        function searchTree(d) {
            var nameMatch = d.data.name.toLowerCase().includes(searchName);
            var match = nameMatch;

            if (d.children)
                d.children.forEach(function(child) {
                    if (searchTree(child)) match = true;
                });
            else if (d._children)
                d._children.forEach(function(child) {
                    if (searchTree(child)) match = true;
                });

            if (match) {
                if (d._children) {
                    d.children = d._children;
                    d._children = null;
                }
            } else {
                if (d.children) {
                    d._children = d.children;
                    d.children = null;
                }
            }
            return match;
        }

        if (searchName !== "") {
            searchTree(root);
        } else {
            root.children.forEach(function(d) {
                if (d.depth >= initialDepth) {
                    collapse(d);
                } else {
                    expand(d);
                }
            });
        }

        update(root);
    });

    // 윈도우 리사이즈 시 SVG 크기 조정
    window.addEventListener('resize', function() {
        width = window.innerWidth - margin.right - margin.left;
        height = window.innerHeight - margin.top - margin.bottom;

        d3.select("#chart svg")
            .attr("width", width + margin.right + margin.left)
            .attr("height", height + margin.top + margin.bottom);

        treeLayout.size([height, width]);
        update(root);
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
