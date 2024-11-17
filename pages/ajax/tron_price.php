<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>트론 및 테더 잔액 및 시세 조회</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script> <!-- Axios for API calls -->
</head>
<body class="container mt-5">
    <h2>트론 및 테더 잔액 및 시세 조회</h2>
    <form id="tronForm">
        <div class="form-group">
            <label for="tronAddress">트론 주소</label>
            <input type="text" class="form-control" id="tronAddress" placeholder="트론 주소를 입력하세요">
        </div>
        <button type="button" class="btn btn-primary" onclick="fetchBalances()">잔액 및 시세 조회</button>
    </form>

    <div id="result" class="mt-5">
        <h4>조회 결과</h4>
        <p><strong>TRX 잔액:</strong> <span id="trxBalance">-</span> TRX</p>
        <p><strong>USDT (TRC20) 잔액:</strong> <span id="usdtBalance">-</span> USDT</p>
        <p><strong>현재 TRX 시세:</strong> <span id="trxPrice">-</span> USD</p>
        <p><strong>현재 USDT 시세:</strong> <span id="usdtPrice">-</span> USD</p>
    </div>

    <script>
        // TronGrid API URL (Tron 주소에 대한 잔액 조회)
        const tronGridAPI = "https://api.trongrid.io";
        
        // CoinGecko API URL (시세 조회)
        const coinGeckoAPI = "https://api.coingecko.com/api/v3/simple/price?ids=tron,tether&vs_currencies=usd";

        // 트론 주소의 TRX 및 USDT 잔액 조회
        async function fetchBalances() {
            const tronAddress = document.getElementById('tronAddress').value;
            
            if (!tronAddress) {
                alert("트론 주소를 입력하세요.");
                return;
            }

            try {
                // TRX 잔액 조회
                const trxResponse = await axios.get(`${tronGridAPI}/v1/accounts/${tronAddress}`);
                const trxBalance = trxResponse.data.data[0]?.balance / 1e6 || 0; // 잔액 (TRX)

                // USDT (TRC20) 잔액 조회
                const usdtResponse = await axios.get(`${tronGridAPI}/v1/accounts/${tronAddress}/trc20`);
                const usdtBalance = usdtResponse.data.data.find(token => token.symbol === "USDT")?.balance / 1e6 || 0; // 잔액 (USDT)

                // 시세 조회 (CoinGecko)
                const priceResponse = await axios.get(coinGeckoAPI);
                const trxPrice = priceResponse.data.tron.usd; // TRX의 USD 시세
                const usdtPrice = priceResponse.data.tether.usd; // USDT의 USD 시세

                // 결과 표시
                document.getElementById('trxBalance').innerText = trxBalance.toFixed(2);
                document.getElementById('usdtBalance').innerText = usdtBalance.toFixed(2);
                document.getElementById('trxPrice').innerText = trxPrice.toFixed(4);
                document.getElementById('usdtPrice').innerText = usdtPrice.toFixed(4);

            } catch (error) {
                console.error("잔액 및 시세 조회 중 오류 발생:", error);
                alert("잔액 및 시세를 조회할 수 없습니다. 주소를 확인하세요.");
            }
        }
    </script>
</body>
</html>
