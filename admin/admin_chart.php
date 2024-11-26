ckground: rgba(212,175,55,0.2);
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
