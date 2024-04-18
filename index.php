<?php

$build_query = function($params) {
    return implode('&', array_map(function($k, $v) {
        return urlencode($k) . '=' . urlencode($v);
    }, array_keys($params), $params));
};

$domain = 'ly.govapi.tw';
if (file_exists(__DIR__ . '/config.php')) {
    include(__DIR__ . '/config.php');
    if (getenv('API_URL')) {
        $domain = getenv('API_URL');
    }
}
$stat = json_decode(file_get_contents(sprintf("https://%s/stat", $domain)));
$session_periods = [];
foreach ($stat->meet->terms as $term_data) {
    $term = $term_data->term;
    if (!isset($session_periods[$term])) {
        $session_periods[$term] = [];
    }
    foreach ($term_data->sessionPeriod_count as $session_data) {
        $session_periods[$term][] = $session_data;
    }
}
$committees = json_decode(file_get_contents(sprintf("https://%s/committee", $domain)));
$params = [];
if ($_GET['term'] ?? false) {
    $params['term'] = intval($_GET['term']);
}
if ($_GET['sessionPeriod'] ?? false) {
    $params['sessionPeriod'] = intval($_GET['sessionPeriod']);
}
if ($_GET['committee_id'] ?? false) {
    $params['committee_id'] = intval($_GET['committee_id']);
}
// 取得會議資料
$url = "https://" . $domain . "/meet?" . $build_query($params);
$meet_obj = json_decode(file_get_contents($url));
$meet_params = [];
$gazette_params = [];
foreach ($meet_obj->meets as $meet) {
    $meet_params[] = 'meet_id=' . urlencode($meet->id);
    foreach ($meet->{'公報發言紀錄'} as $gazette) {
        $gazette_params[$gazette->gazette_id] = 'gazette_id=' . urlencode($gazette->gazette_id);
    }
}
// 取得ivod數量
$url = sprintf("https://%s/ivod/?%s&aggs=meet_id&size=0", $domain, implode('&', $meet_params));
$ivod_count = json_decode(file_get_contents($url));
$meet_ivod_count = [];
foreach ($ivod_count->aggs->meet_id as $obj) {
    $meet_ivod_count[$obj->value] = $obj->count;
}

$meet_gazettes = [];
// 取得公報資料
$url = sprintf("https://%s/gazette/?%s", $domain, implode('&', $gazette_params));
$gazette_obj = json_decode(file_get_contents($url));
foreach ($gazette_obj->gazettes as $gazette) {
    $meet_gazettes[$gazette->gazette_id] = $gazette;
}

?>
<html>
<head>
<meta charset="utf-8">
</head>
<body>
<p>屆期:
<?php $new_params = $params; unset($new_params['term']); unset($new_params['sessionPeriod']); ?>
<a href="?<?= $build_query($new_params) ?>">不篩選</a>
<?php foreach ($stat->meet->terms as $term_data) { ?>
    <?php if ($term_data->term == $params['term']) { ?>
    <strong>第<?= intval($term_data->term) ?>屆(<?= intval($term_data->count) ?>)</strong>
    <?php } else { ?>
        <?php $new_params['term'] = intval($term_data->term); ?>
        <a href="?<?= $build_query($new_params) ?>">第<?= intval($term_data->term) ?>屆(<?= intval($term_data->count) ?>)</a>
    <?php } ?>
<?php } ?>
</p>
<?php if ($params['term']) { ?>
<p>會期：
<?php $new_params = $params; unset($new_params['sessionPeriod']); ?>
<a href="?<?= $build_query($new_params) ?>">不篩選</a>
<?php foreach ($session_periods[$params['term']] as $pdata) { ?>
<?php if ($pdata->sessionPeriod == $params['sessionPeriod'] and $pdata->sessionPeriod) { ?>
    <strong>第<?= intval($pdata->sessionPeriod) ?>會期(<?= intval($pdata->count) ?>)</strong>
    <?php } else { ?>
        <?php $new_params['sessionPeriod'] = intval($pdata->sessionPeriod); ?>
        <a href="?<?= $build_query($new_params) ?>">第<?= intval($pdata->sessionPeriod) ?>會期(<?= intval($pdata->count) ?>)</a>
    <?php } ?>
<?php } ?>
<?php } ?>
<p>委員會:
<?php $new_params = $params; unset($new_params['committee_id']); ?>
<a href="?<?= $build_query($new_params) ?>">不篩選</a>
<?php foreach ($committees->committees as $committee) { ?>
    <?php if ($committee->comtCd == $params['committee_id']) { ?>
    <strong><?= htmlspecialchars($committee->comtName) ?></strong>
    <?php } else { ?>
        <?php if ($committee->comtType == 1) { ?>
        <?php $new_params['committee_id'] = intval($committee->comtCd); ?>
        <a href="?<?= $build_query($new_params) ?>"><?= htmlspecialchars($committee->comtName) ?></a>
        <?php } ?>
    <?php } ?>
<?php } ?>
</p>
<table class="table table-striped" border="1">
    <thead>
        <tr>
            <th>日期</th>
            <th>會議名稱</th>
            <th>議事錄</th>
            <th>opendata發言紀錄</th>
            <th>公報紀錄</th>
            <th>iVod數量</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($meet_obj->meets as $meet) { ?>
    <tr>
        <td>
            <?= implode('<br>', $meet->dates) ?>
        </td>
        <td>
            <?= htmlspecialchars($meet->id) ?><br>
            <a href="meet.php?id=<?= urlencode($meet->id) ?>"><?= htmlspecialchars($meet->name) ?></a>
        </td>
        <td title="<?= htmlspecialchars(json_encode($meet->{'議事錄'}, JSON_UNESCAPED_UNICODE)) ?>">
            <?php if ($meet->{'議事錄'} ?? false) { ?>
            <a href="<?= htmlspecialchars($meet->{'議事錄'}->ppg_url) ?>">議事錄</a> 
            <?php } ?>
        </td>
        <td>
            <?php if ($meet->{'發言紀錄'} ?? false) { ?>
            <table border="1" style="width: 100%">
                <?php foreach ($meet->{'發言紀錄'} as $speak) { ?>
                <tr title="<?= htmlspecialchars(json_encode($speak, JSON_UNESCAPED_UNICODE)) ?>">
                    <td title="<?= htmlspecialchars(json_encode($speak, JSON_UNESCAPED_UNICODE)) ?>">
                        <?= htmlspecialchars(mb_strimwidth($speak->meetingContent, 0, 30, '...')) ?>
                    </td>
                    <td title="<?= htmlspecialchars(implode(', ', $speak->legislatorNameList)) ?>">
                        <?= count($speak->legislatorNameList) ?> 人
                    </td>
                </tr>
                <?php } ?>
            </table>
            <?php } ?>
        </td>
        <td>
            <?php if ($meet->{'公報發言紀錄'} ?? false) { ?>
            <table border="1" style="width: 100%">
                <?php $dates = []; ?>
                <?php foreach ($meet->{'公報發言紀錄'} as $gazette) { ?>
                <?php $dates[$meet_gazettes[$gazette->gazette_id]->published_at] = 1; ?>
                <tr>
                    <td title="<?= htmlspecialchars(json_encode($gazette, JSON_UNESCAPED_UNICODE)) ?>">
                        <?= htmlspecialchars(mb_strimwidth($gazette->content, 0, 30, '...')) ?>
                    </td>
                    <td title="<?= htmlspecialchars(implode(', ', $gazette->speakers)) ?>">
                        <?= count($gazette->speakers) ?> 人
                    </td>
                    <td>
                        <?php foreach ($gazette->html_files as $f) { ?>
                        <a href="<?= htmlspecialchars($f) ?>">HTML</a><br>
                        <?php } ?>
                    </td>
                </tr>
                <?php } ?>
            </table>
            公報出版日：<?= implode(', ', array_keys($dates)) ?>
            <?php } ?>
        </td>
        <td>
            <?= $meet_ivod_count[$meet->id] ?? '' ?>
        </td>
    </tr>
    <?php } ?>
    </tbody>
</table>
</body>
</html>