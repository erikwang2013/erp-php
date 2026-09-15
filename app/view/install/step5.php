<?php /* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */ $h = static fn (string $k, string $d = '') => htmlspecialchars((string) ($old[$k] ?? $d), ENT_QUOTES); ?>
<h1 class="step-title"><?= $t('Confirm installation') ?></h1>
<div class="summary-card">
  <div class="sum-head"><?= $t('📋 Installation summary') ?></div>
  <?php foreach ($summary as $s): ?>
  <div class="sum-item"><span class="sum-label"><?= htmlspecialchars((string) $s[0]) ?></span><span class="sum-value"><?= htmlspecialchars((string) $s[1], ENT_QUOTES) ?></span></div>
  <?php endforeach; ?>
</div>
<div class="notice-box">
  <div class="notice-title"><?= $t('⚠️ After you click "Start installation", the following will run in order:') ?></div>
  <ol class="notice-list">
    <li><?= $t('Write the <code>.env</code> configuration file (keys and search engine settings included; blank items auto-generated)') ?></li>
    <li><?= $t('Create the database and import 227 table definitions plus seed data') ?></li>
    <li><?= $t('Import <b>demo data</b> (only when checked in step 1 "Database configuration"; products/specs/SKUs/customers/suppliers, ID range 41…)') ?></li>
    <li><?= $t('Create the administrator account and attach the super-admin role') ?></li>
  </ol>
  <div class="notice-tip"><?= $t('This takes a few seconds — please keep this page open. After installation the .env file will be marked APP_INSTALLED=true and revisiting /install will show the completion page.') ?></div>
</div>
<form method="post" action="/install" class="install-actions" id="install-form">
<input type="hidden" name="step" value="5">
<?php foreach (['host','port','database','username','password','prefix','jwt_secret','encryption_key','encryptable_key','hashids_salt','hashids_alt_salt','http_port','ws_port','rabbitmq_password','engine_driver','engine_host','engine_username','engine_password','admin_username','admin_password','demo_data'] as $k): ?>
<input type="hidden" name="<?= $k ?>" value="<?= htmlspecialchars((string) ($old[$k] ?? ''), ENT_QUOTES) ?>">
<?php endforeach; ?>
<a href="/install?step=4" class="btn btn-secondary"><?= $t('← Back') ?></a>
<button type="submit" id="install-btn" class="btn btn-install"><?= $t('🚀 Start installation') ?></button>
</form>

<div id="progress-mask" style="display:none">
  <div class="pm-card"><div class="pm-title" id="pm-title"><?= $t('Installing open-erp…') ?></div>
  <div class="pm-track"><div class="pm-bar" id="pm-bar"></div></div>
  <div class="pm-step" id="pm-step"><?= $t('Preparing…') ?></div>
  <div class="pm-err" id="pm-err" style="display:none"></div>
  <button type="button" id="pm-retry" class="btn btn-install" style="display:none"><?= $t('🔄 Retry') ?></button>
  </div>
</div>
<script>
(function () {
  var L = <?= json_encode([
      'phases' => [
          $t('Writing configuration file…'), $t('Creating database…'), $t('Importing tables and seed data…'),
          $t('Creating administrator account…'), $t('Almost done…'),
      ],
      'ok' => $t('✅ Installed successfully, redirecting…'),
      'incomplete' => $t('Installation incomplete: :msg'),
      'unknown' => $t('Unknown error, please check the server log'),
      'failed' => $t('Request failed: :msg'),
  ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var form = document.getElementById('install-form');
  var mask = document.getElementById('progress-mask');
  var bar = document.getElementById('pm-bar');
  var stepEl = document.getElementById('pm-step');
  var errEl = document.getElementById('pm-err');
  var retryBtn = document.getElementById('pm-retry');
  var phases = L.phases;
  var lastFd = null;
  function run(fd) {
    lastFd = fd; mask.style.display = 'flex';
    errEl.style.display = 'none'; retryBtn.style.display = 'none';
    bar.style.width = '8%'; var i = 0;
    var timer = setInterval(function () {
      i = Math.min(i + 1, phases.length - 1);
      stepEl.textContent = phases[i];
      bar.style.width = Math.min(8 + i * 17 + 6, 88) + '%';
    }, 700);
    fetch(form.action, { method: 'POST', body: fd })
      .then(function (r) { return r.text(); })
      .then(function (html) {
        clearInterval(timer); bar.style.width = '100%';
        // 认标记不认文案：成功页带 id="install-success"，与界面语种无关
        if (html.indexOf('id="install-success"') !== -1) {
          stepEl.textContent = L.ok;
          setTimeout(function () { location.href = '/install'; }, 900);
        } else {
          var doc = new DOMParser().parseFromString(html, 'text/html');
          var alert = doc.querySelector('.alert-error');
          bar.style.width = '0%';
          errEl.style.display = 'block';
          errEl.textContent = L.incomplete.replace(':msg', alert ? alert.textContent.trim() : L.unknown);
          retryBtn.style.display = 'inline-flex'; stepEl.textContent = '';
        }
      })
      .catch(function (e) {
        clearInterval(timer); bar.style.width = '0%';
        errEl.style.display = 'block';
        errEl.textContent = L.failed.replace(':msg', e.message);
        retryBtn.style.display = 'inline-flex'; stepEl.textContent = '';
      });
  }
  form.addEventListener('submit', function (ev) { ev.preventDefault(); run(new FormData(form)); });
  retryBtn.addEventListener('click', function () { if (lastFd) run(lastFd); });
})();
</script>
