<?php $t = fn($x) => Glossary::term($x); $groups = Glossary::groups(); $terms = Glossary::terms(); ?>
<?= $this->partial('common/header') ?>
<div class="stack docpage" style="max-width:760px">
  <div class="doctabs" role="tablist"><a class="on" href="/learn" role="tab" aria-selected="true">立法院怎麼運作</a><a href="/about" role="tab" aria-selected="false">關於資料</a></div>
  <div class="stack" style="gap:8px"><h1>立法院怎麼運作</h1>
   <p><?= $t('法案') ?>要經過院會、委員會，有時還有公聽會、黨團協商，最後在院會三讀通過，才成為法律。先認識每種會議，再看法案怎麼走。</p>
   <div class="toclist small"><a class="chip btn" href="#learn-meetings">每種會議在做什麼</a><a class="chip btn" href="#learn-law">法案怎麼變成法律</a><a class="chip btn" href="#learn-terms">會議紀錄裡常見的詞</a></div></div>
  <section class="panel stack" style="gap:12px" id="learn-meetings"><h2>每種會議在做什麼</h2>
   <p style="margin:0">立法院有六種常見的會議。先認識它們，下一段再看一件法案會經過哪些會議。</p>
   <div class="roles"><h3>會議裡會出現的人</h3><div class="rgrid"><div class="rrow"><span class="mpill">立委</span><span>立法委員，人民選出來的代表。</span></div><div class="rrow"><span class="mpill">部會首長</span><span>行政院各部會的首長，例如部長、主委。</span></div><div class="rrow"><span class="mpill">黨團代表</span><span>各黨團派出來談判的立委。</span></div><div class="rrow"><span class="mpill">學者、團體代表</span><span>受邀來表達意見的專家和民間團體。</span></div><div class="rrow"><span class="mpill">官員</span><span>部會或其他機關的公務員。</span></div><div class="rrow"><span class="mpill">被提名人</span><span>總統提名、要經立法院同意才能上任的人選。</span></div><div class="rrow"><span class="mpill maybe">官員<small>有時</small></span><span>虛線框表示有時才會發言。</span></div></div></div>
   <div class="mcards"><div class="mcard"><h3><?= $t('院會') ?></h3><p class="mstepl">在法案流程：<b>一讀、二讀、三讀</b></p><p class="mdo">表決法案、<?= $t('質詢') ?>行政院，每週二、五開。</p><div class="mpills" aria-label="會發言的人"><span class="mpill">全體立委</span><span class="mpill">行政院院長、部會首長</span></div></div><div class="mcard"><h3><?= $t('委員會') ?></h3><p class="mstepl">在法案流程：<b>一讀後審查</b></p><p class="mdo">依領域分成 8 個小組，先審法案、審預算。</p><div class="mpills" aria-label="會發言的人"><span class="mpill">這個委員會的立委</span><span class="mpill"><?= $t('部會') ?>首長</span></div></div><div class="mcard"><h3><?= $t('聯席會議') ?></h3><p class="mstepl">在法案流程：<b>一讀後審查</b></p><p class="mdo">法案牽涉好幾個領域時，幾個委員會一起審。</p><div class="mpills" aria-label="會發言的人"><span class="mpill">幾個委員會的立委</span><span class="mpill">部會首長</span></div></div><div class="mcard"><h3><?= $t('公聽會') ?></h3><p class="mstepl">在法案流程：<b>委員會審查時</b></p><p class="mdo">審法案前，先聽各方意見。</p><div class="mpills" aria-label="會發言的人"><span class="mpill">委員會的立委</span><span class="mpill">學者、團體代表</span><span class="mpill">官員</span></div></div><div class="mcard"><h3><?= $t('黨團協商') ?></h3><p class="mstepl">在法案流程：<b>二讀前</b></p><p class="mdo">談有爭議的條文或院會議程。</p><div class="mpills" aria-label="會發言的人"><span class="mpill">各<?= $t('黨團') ?>的代表</span><span class="mpill maybe">其他立委<small>有時</small></span><span class="mpill maybe">官員<small>有時</small></span></div></div><div class="mcard"><h3><?= $t('全院委員會') ?></h3><p class="mstepl">在法案流程：<b>不在一般法案流程</b></p><p class="mdo">審大法官等的<?= $t('人事同意權') ?>，也審<?= $t('覆議案') ?>、<?= $t('不信任案') ?>。</p><div class="mpills" aria-label="會發言的人"><span class="mpill">全體立委</span><span class="mpill maybe">被提名人<small>有時</small></span></div></div></div>
   <p class="src">出處：在做什麼、誰會發言：立法院職權行使法、立法院議事規則、議事流程研究・詞彙表、本專案的會議切分研究</p></section>
  <section class="panel stack" style="gap:10px" id="learn-law"><h2>法案怎麼變成法律</h2>
   <p style="margin:0">送進立法院的案子叫<?= $t('議案') ?>；要訂立或修改法律的議案，就是法案。法案三讀通過、總統公布後，才成為法律。一部法律常同時有好幾件法案在修改它。</p>
   <figure class="lflow" style="margin:0"><svg viewBox="0 0 680 300" width="100%" role="img" aria-labelledby="lflow-t lflow-d"><title id="lflow-t">法案怎麼變成法律</title><desc id="lflow-d">上排淡紫色是院會，有一讀、二讀、三讀；下排淺灰色是院會以外，有委員會審查和黨團協商。實線路線是最常見的路，虛線是逕付二讀跳過審查、重付審查退回委員會。</desc>
<rect x="20" y="24" width="640" height="104" rx="12" class="lf-lane1"/><text x="36" y="48" class="lf-lab">院會</text><text x="36" y="66" class="lf-s">讀會都在這裡</text>
<rect x="20" y="148" width="640" height="104" rx="12" class="lf-lane2"/><text x="36" y="172" class="lf-lab">院會以外</text>
<path d="M170 92 L170 186 Q170 200 184 200 L420 200 Q434 200 434 186 L434 92 L590 92" fill="none" class="lf-line"/>
<path d="M182 80 Q300 40 422 80" fill="none" class="lf-skip"/><text x="302" y="50" text-anchor="middle" class="lf-s">逕付二讀：跳過審查</text>
<path d="M422 104 Q330 150 240 188" fill="none" class="lf-back"/><text x="300" y="126" text-anchor="middle" class="lf-s">重付審查：退回</text>
<circle cx="170" cy="92" r="10" class="lf-st1"/><text x="156" y="122" text-anchor="end" class="lf-b">一讀</text>
<circle cx="434" cy="92" r="10" class="lf-st1"/><text x="448" y="122" text-anchor="start" class="lf-b">二讀</text>
<circle cx="590" cy="92" r="10" class="lf-st1"/><text x="590" y="120" text-anchor="middle" class="lf-b">三讀</text>
<circle cx="240" cy="200" r="10" class="lf-st2"/><text x="240" y="230" text-anchor="middle" class="lf-b">委員會審查</text><text x="240" y="246" text-anchor="middle" class="lf-s">需要時開公聽會</text>
<circle cx="360" cy="200" r="10" class="lf-st2"/><text x="360" y="230" text-anchor="middle" class="lf-b">黨團協商</text><text x="360" y="246" text-anchor="middle" class="lf-s">有爭議才有</text>
<line x1="140" y1="280" x2="170" y2="280" class="lf-line"/><text x="178" y="284" class="lf-s">最常見的路</text>
<line x1="270" y1="280" x2="300" y2="280" class="lf-skip"/><text x="308" y="284" class="lf-s">跳過</text>
<line x1="360" y1="280" x2="390" y2="280" class="lf-back"/><text x="398" y="284" class="lf-s">退回</text>
<circle cx="470" cy="280" r="6" class="lf-st1"/><text x="482" y="284" class="lf-s">院會</text><circle cx="540" cy="280" r="6" class="lf-st2"/><text x="552" y="284" class="lf-s">院會以外</text>
</svg></figure>
   <ul class="tips" style="margin:0"><li>一讀、二讀、三讀合稱<?= $t('讀會') ?>，都在院會進行；委員會審查、公聽會、黨團協商在院會以外，發生在讀會之間。</li><li><?= $t('逕付二讀') ?>：一讀後不經委員會審查，直接進入二讀。</li><li><?= $t('重付審查') ?>：二讀時，院會決定把法案送回委員會再審。</li><li>同一件法案也可能排進院會好幾次，才真的討論、表決。每部法律的法案實際走過哪些步驟，看法律頁的「修法進度」。</li></ul>
   <p class="src">出處：立法院職權行使法第 7–11 條、第 68–74-1 條；本專案的議事流程研究（findings/stage-terms/meeting-stages.md、census-data.md）</p></section>
  <section class="panel stack" style="gap:12px" id="learn-terms"><h2>會議紀錄裡常見的詞</h2>
   <p style="margin:0">會議頁上點這些詞，也會跳出同樣的說明。</p>
   <?php foreach ($groups as $gi => [$gname, $keys]) { $k0 = array_values(array_filter($keys, fn($k) => isset($terms[$k]))); ?>
   <div class="glgrp"><h3><?= $this->escape($gname) ?></h3><div class="glwall" role="group" aria-label="<?= $this->escape($gname) ?>"><?php foreach ($k0 as $i => $k) { ?><button type="button" class="glchip" data-gl="<?= $this->escape($k) ?>" data-glg="<?= $gi ?>" aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>" aria-controls="gldef-<?= $gi ?>"><?= $this->escape($k) ?></button><?php } ?></div><div class="gldef" id="gldef-<?= $gi ?>" aria-live="polite"><b><?= $this->escape($k0[0]) ?></b><p><?= $this->escape($terms[$k0[0]]['d']) ?></p><span class="src">出處：<?= $this->escape($terms[$k0[0]]['s']) ?></span></div></div>
   <?php } ?>
  </section>
</div>
<?= $this->partial('common/footer') ?>
