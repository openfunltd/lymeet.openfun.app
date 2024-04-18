<?php
$id = $_GET['id'];
$domain = 'ly.govapi.tw';
if (file_exists(__DIR__ . '/config.php')) {
    include(__DIR__ . '/config.php');
    if (getenv('API_URL')) {
        $domain = getenv('API_URL');
    }
}
$url = "https://{$domain}/meet/" . urlencode($id);
$data = json_decode(file_get_contents($url));
?>
<html>
<head>
<title><?= htmlspecialchars($data->name) ?></title>
</head>
<body>
<h1><?= htmlspecialchars($data->name) ?></h1>
<code>API: <?= htmlspecialchars($url) ?></code>
<hr>
<h2>會議資料</h2>
<h4>本區資料來自 <a href="https://data.ly.gov.tw/getds.action?id=42">立法院資料開放平台</a></h4>
<table border="1">
    <?php foreach ($data->meet_data ?? [] as $meet) { ?>
    <tr>
        <td>
            <ul>
                <?php foreach ($meet as $k => $v) { ?>
                <li>
                <?= htmlspecialchars($k) ?>:
                <?php if (is_scalar($v)) { ?>
                <?= htmlspecialchars($v) ?>
                <?php } else { ?>
                <?= json_encode($v, JSON_UNESCAPED_UNICODE) ?>
                <?php } ?>
                </li>
                <?php } ?>
            </ul>
        </td>
    </tr>
    <?php } ?>
</table>
<hr>
<h1>發言紀錄</h1>
<h4>以下資料來自 <a href="https://data.ly.gov.tw/getds.action?id=221">立法院資料開放平台:院會發言名單</a> 和 <a href="https://data.ly.gov.tw/getds.action?id=223">立法院資料開放平台:委員會登記發言名單</a></h4>
<table border="1">
    <?php foreach ($data->{'發言紀錄'} ?? [] as $meet) { ?>
    <tr>
        <td>
            <ul>
                <?php foreach ($meet as $k => $v) { ?>
                <li>
                <?= htmlspecialchars($k) ?>:
                <?php if (is_scalar($v)) { ?>
                <?= htmlspecialchars($v) ?>
                <?php } else { ?>
                <?= json_encode($v, JSON_UNESCAPED_UNICODE) ?>
                <?php } ?>
                </li>
                <?php } ?>
            </ul>
        </td>
    </tr>
    <?php } ?>
</table>
<hr>
<h1>公報發言紀錄</h1>
<h4>以下資料是從公報的「本期發言目錄」中利用文字處理抓取</h4>
<table border="1">
    <?php foreach ($data->{'公報發言紀錄'} ?? [] as $meet) { ?>
    <tr>
        <td>
            <ul>
                <?php foreach ($meet as $k => $v) { ?>
                <li>
                <?= htmlspecialchars($k) ?>:
                <?php if (is_scalar($v)) { ?>
                <?= htmlspecialchars($v) ?>
                <?php } elseif (in_array($k, ['html_files', 'txt_files'])) { ?>
                <?php foreach ($v as $url) { ?>
                <a href="<?= htmlspecialchars($url) ?>"><?= htmlspecialchars($url) ?></a><br>
                <?php } ?>
                <?php } else { ?>
                <?= json_encode($v, JSON_UNESCAPED_UNICODE) ?>
                <?php } ?>
                </li>
                <?php } ?>
            </ul>
        </td>
    </tr>
    <?php } ?>
</table>

</body>
</html>
