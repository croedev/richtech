<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TronWeb CDN Example</title>
  <!-- TronWeb CDN 추가 -->
  <script src="https://cdn.jsdelivr.net/npm/tronweb@latest/dist/TronWeb.min.js"></script>
</head>
<body>
  <h1>TronWeb CDN Example</h1>
  <script>
    // TronWeb 초기화
    const tronWeb = new TronWeb({
      fullHost: 'https://api.trongrid.io',
      privateKey: 'c830d5ad-b752-4a4f-9247-30826e45a2c2'
    });

    // 트론 지갑 생성 예제
    async function createWallet() {
      const account = await tronWeb.createAccount();
      console.log('Address:', account.address.base58);
      console.log('Private Key:', account.privateKey);
    }

    createWallet();
  </script>
</body>
</html>
