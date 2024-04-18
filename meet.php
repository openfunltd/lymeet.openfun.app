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
跳到：<a href="#section-meet-data">會議資料</a>、<a href="#section-speech">發言紀錄</a>、<a href="#section-gazette">公報發言紀錄</a>、<a href="#section-ivod">iVOD記錄</a>
<hr>
<h2 id="section-meet-data">會議資料</h2>
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
<h2 id="section-speech">發言紀錄</h2>
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
<h2 id="section-gazette">公報發言紀錄</h2>
<h4>以下資料是從公報的「本期發言目錄」中利用文字處理抓取</h4>
<table border="1">
    <?php foreach ($data->{'公報發言紀錄'} ?? [] as $meet) { ?>
    <tr>
        <td>
            <ul>
                <?php foreach ($meet as $k => $v) { ?>
                <li>
                <?= htmlspecialchars($k) ?>:
                <?php if (in_array($k, ['ppg_gazette_url'])) { ?>
                <a href="<?= htmlspecialchars($v) ?>"><?= htmlspecialchars($v) ?></a>
                <?php } elseif (is_scalar($v)) { ?>
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
<hr>
<h2 id="section-ivod">iVOD記錄</h2>
<h4>以下資料是從<a href="https://ivod.ly.gov.tw">立法院iVOD</a>抓取</h4>
<?php
$url = "https://{$domain}/meet/" . urlencode($id) . "/ivod";
$data = json_decode(file_get_contents($url));
?>
<code>API: <?= htmlspecialchars($url) ?></code>
<table border="1">
    <thead>
        <tr>
            <th>ID</th>
            <th>日期</th>
            <th>委員名稱</th>
            <th>委員發言時間</th>
            <th>影片長度</th>
            <th>連結</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($data->ivods as $ivod) { ?>
    <tr>
        <td><?= htmlspecialchars($ivod->id) ?></td>
        <td><?= htmlspecialchars($ivod->date) ?></td>
        <td><?= htmlspecialchars($ivod->{'委員名稱'}) ?></td>
        <td><?= htmlspecialchars($ivod->{'委員發言時間'}) ?></td>
        <td><?= htmlspecialchars($ivod->{'影片長度'}) ?></td>
        <td><a href="<?= htmlspecialchars($ivod->url) ?>">Link</a></td>


    </tr>
    <?php } ?>
    </tbody>
</table>


</body>
</html>

