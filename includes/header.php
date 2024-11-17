<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo isset($pageTitle) ? $pageTitle : '부자들의모임_리치테크(Rich Tech club)'; ?></title>
    <link
        href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@100;200;300;400;500;600;700;800;900&family=Noto+Serif+KR:wght@100;200;300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://yammi.link/css/croe.2.5.0.css">


    <style>
            body {
                font-family: 'Noto Serif KR', serif;
                margin: 0;
                padding: 0;
                background-color: #000000;
                color: #ffffff;
            }

            .header {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                height: 50px;
                background: linear-gradient(to right, #846300, #f2d06b);
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 0 15px;
                z-index: 1000;
            }

            .back-button {
                position: absolute;
                left: 15px;
                color: #ffffff;
                font-size: 20px;
                text-decoration: none;
                cursor: pointer;
            }

            .header h1 {
                font-family: 'Noto Serif KR', serif;
                color: #000000;
                font-size: 18px;
                font-weight: bold;
                margin: 0;
            }

            .header.transparent {
                background: transparent;
                color: transparent;
            }

            .header.transparent .back-button,
            .header.transparent h1 {
                opacity: 0;
            }

            .content {
                padding: 0;
                padding-top: 40px;
                padding-bottom: 40px;
                max-height: calc(100vh - 40px);
                overflow-y: auto;
            }


            .button-group {
                display: flex;
                justify-content: end;
                margin-top: 0px;
                padding-top: 0px;
            }

            .btn-outline {
                background-color: transparent;
                border: 1px solid #d4af37;
                color: #d4af37;
                font-size: 0.75em;
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

            .btn-gold {
                background: linear-gradient(to right, #d4af37, #f2d06b);
                border: none;
                color: #000000;
                font-weight: bold;
                padding: 5px 10px;
                border-radius: 5px;
                cursor: pointer;
                transition: all 0.3s ease;
                font-size: 0.9em;
            }

            .btn-gold-sm {
                background: linear-gradient(to right, #d4af37, #f2d06b);
                border: none;
                color: #000000;
                font-weight: bold;
                padding: 3px 10px;
                border-radius: 5px;
                cursor: pointer;
                transition: all 0.3s ease;
                font-size: 0.8em;
            }

            .btn-gold-md {
                background: linear-gradient(135deg, #d4af37, #aa8a2e);
                color: #000;
                border: none;
                padding: 10px 20px;
                border-radius: 5px;
                cursor: pointer;
                font-weight: 600;
                width: 100%;
                margin-top: 15px;
                transition: all 0.3s ease;
            }

            .btn-gold:hover {
                background: linear-gradient(135deg, #f2d06b, #d4af37);
                transform: translateY(-1px);
            }


            .form-group {
                margin-bottom: 10px;
            }

            .form-label {
                display: block;
                font-family: 'Noto Sans KR', sans-serif;
                color: #d4af37;
                font-weight: bold;
                margin-bottom: 2px;
                font-size: 0.9em;
            }

            .form-control {
                width: 100%;
                padding: 5px;
                background-color: #333333;
                border: 1px solid #d4af37;
                color: #ffffff;
                border-radius: 5px;
                font-size: 0.9em;
            }
    </style>

</head>

<body>
    <div class="header <?php echo isset($hideHeader) && $hideHeader ? 'hidden-header' : ''; ?>">
        <?php if (!isset($hideHeader) || !$hideHeader): ?>
        <span class="back-button" onclick="history.back();"><i class="fas fa-chevron-left"></i></span>
        <h1><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : '부자들의모임_리치테크(Rich Tech club)'; ?></h1>
        <?php endif; ?>
    </div>
    <div class="content">