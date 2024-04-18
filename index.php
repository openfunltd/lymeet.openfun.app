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
$committees->committees = array_filter($committees->committees, function($c) {
    if ($c->comtType == 1) {
        return true;
    }
    if ($c->comtType == 2) {
        return true;
    }
    return false;
});
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
if ($_GET['meet_type'] ?? false) {
    $params['meet_type'] = $_GET['meet_type'];
}
// 取得會議資料
$api_url = "https://" . $domain . "/meet?" . $build_query($params);
$meet_obj = json_decode(file_get_contents($api_url));
$meet_params = [];
$gazette_params = [];
foreach ($meet_obj->meets as $meet) {
    $meet_params[] = 'meet_id=' . urlencode($meet->id);
    foreach ($meet->{'公報發言紀錄'} as $gazette) {
        $gazette_params[$gazette->gazette_id] = 'gazette_id=' . urlencode($gazette->gazette_id);
    }
}
// 取得ivod數量
$url = sprintf("https://%s/ivod/?%s&aggs=meet_id,date&size=0", $domain, implode('&', $meet_params));
$ivod_count = json_decode(file_get_contents($url));
$meet_ivod_count = [];
foreach ($ivod_count->aggs->{'meet_id,date'} as $obj) {
    $meet_id = $obj->meet_id;
    $date = date('Y-m-d', $obj->date / 1000);
    if (!isset($meet_ivod_count[$meet_id])) {
        $meet_ivod_count[$meet_id] = [];
    }
    $meet_ivod_count[$meet_id][$date] = $obj->count;
}

// 取得質詢數量
$url = sprintf("https://%s/interpellation/?%s&aggs=meet_id&size=0", $domain, implode('&', $meet_params));
$interpellation_count = json_decode(file_get_contents($url));
$meet_interpellation_count = [];
foreach ($interpellation_count->aggs->meet_id as $obj) {
    $meet_id = $obj->value;
    $meet_interpellation_count[$meet_id] = $obj->count;
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
<?php $new_params = $params; unset($new_params['committee_id']); unset($new_params['meet_type']); ?>
<a href="?<?= $build_query($new_params) ?>">不篩選</a>
<?php if ($params['meet_type'] == '院會') { ?>
<strong>院會</strong>
<?php } else { ?>
<?php $new_params['meet_type'] = '院會'; ?>
<?php } ?>

<a href="?<?= $build_query($new_params) ?>">院會</a>
<?php unset($new_params['meet_type']); ?>
<?php foreach ($committees->committees as $committee) { ?>
    <?php if ($committee->comtCd == $params['committee_id']) { ?>
    <strong><?= htmlspecialchars($committee->comtName) ?></strong>
    <?php } else { ?>
        <?php $new_params['committee_id'] = intval($committee->comtCd); ?>
        <a href="?<?= $build_query($new_params) ?>"><?= htmlspecialchars($committee->comtName) ?></a>
    <?php } ?>
<?php } ?>
</p>

<hr>
API: <code><?= $api_url ?></code>
<hr>

<table class="table table-striped" border="1">
    <thead>
        <tr>
            <th>日期</th>
            <th>會議名稱</th>
            <th>會議頁面</th>
            <th>議事錄</th>
            <th>opendata發言紀錄</th>
            <th>公報紀錄</th>
            <th>iVod數量</th>
            <th>書面質詢</th>
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
        <td>
            <?php foreach ($meet->meet_data ?? [] as $meet_data) { ?>
            <a href="<?= htmlspecialchars($meet_data->ppg_url) ?>"><?= htmlspecialchars($meet_data->date) ?></a><br>
            <?php } ?>
        </td>
        <td title="<?= htmlspecialchars(json_encode($meet->{'議事錄'}, JSON_UNESCAPED_UNICODE)) ?>">
            <?php if ($meet->{'議事錄'} ?? false) { ?>
            <a href="<?= htmlspecialchars($meet->{'議事錄'}->ppg_url) ?>">議事錄</a> 
            <?php } ?>
        </td>
        <td>
            <?php if ($meet->{'發言紀錄'} ?? false) { ?>
                <?php foreach ($meet->{'發言紀錄'} as $speak) { ?>
                <div title="<?= htmlspecialchars(json_encode($speak, JSON_UNESCAPED_UNICODE)) ?>">
                    <span title="<?= htmlspecialchars(json_encode($speak, JSON_UNESCAPED_UNICODE)) ?>">
                        <?= htmlspecialchars($speak->smeetingDate) ?>
                    </span>:
                    <span title="<?= htmlspecialchars(implode(', ', $speak->legislatorNameList)) ?>">
                        <?= count($speak->legislatorNameList) ?> 人
                    </span>
                </div>
                <?php } ?>
            <?php } ?>
        </td>
        <td>
            <?php if ($meet->{'公報發言紀錄'} ?? false) { ?>
                <?php $dates = []; ?>
                <?php foreach ($meet->{'公報發言紀錄'} as $idx => $gazette) { ?>
                <?php $dates[$meet_gazettes[$gazette->gazette_id]->published_at] = 1; ?>
                <?php if (count($meet->{'公報發言紀錄'}) > 4 and $idx > 1) { continue; } ?>
                <div>
                    <span title="<?= htmlspecialchars(json_encode($gazette, JSON_UNESCAPED_UNICODE)) ?>">
                        <?= $gazette->gazette_id ?>:<?= $gazette->page_start ?>
                    </span>：
                    <span title="<?= htmlspecialchars(implode(', ', $gazette->speakers)) ?>">
                        <?= count($gazette->speakers) ?> 人
                    </span>
                </div>
                <?php } ?>

                <?php if (count($meet->{'公報發言紀錄'}) > 4) { ?>
                <div> ... 共 <?= count($meet->{'公報發言紀錄'}) ?> 章</div>
                <?php } ?>
                公報出版日：<?= implode('<br>', array_keys($dates)) ?>
            <?php } ?>
        </td>
        <td>
            <?php foreach ($meet_ivod_count[$meet->id] ?? [] as $date => $count) { ?>
            <div><?= $date ?>: <?= $count ?></div>
            <?php } ?>
        </td>
        <td>
            <?= $meet_interpellation_count[$meet->id] ?? '' ?>
        </td>
    </tr>
    <?php } ?>
    </tbody>
</table>
</body>
</html>
