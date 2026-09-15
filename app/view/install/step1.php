<?php /* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */ $h = static fn (string $k, string $d = '') => htmlspecialchars((string) ($old[$k] ?? $d), ENT_QUOTES); ?>
<h1><?= $t('Database configuration') ?></h1>
<form method="post" action="/install" id="db-form">
<input type="hidden" name="step" value="1">
<div class="form-row">
  <div class="form-group" style="flex:2"><label><?= $t('Host address') ?></label><input type="text" name="host" required value="<?= $h('host', '127.0.0.1') ?>"></div>
  <div class="form-group" style="flex:1"><label><?= $t('Port') ?></label><input type="number" name="port" required value="<?= $h('port', '3306') ?>"></div>
</div>
<div class="form-group"><label><?= $t('Database name') ?></label><input type="text" name="database" required value="<?= $h('database', 'erp') ?>" placeholder="<?= $t('Will be created automatically if missing') ?>"></div>
<div class="form-group"><label><?= $t('User name') ?></label><input type="text" name="username" required value="<?= $h('username', 'root') ?>"></div>
<div class="form-group"><label><?= $t('Password') ?></label>
  <div class="pw-wrap"><input type="password" name="password" id="db-pass" data-pw value="<?= $h('password') ?>">
  <button type="button" class="pw-eye" data-eye="db-pass" aria-label="<?= $t('Show/hide password') ?>">👁</button></div>
</div>
<div class="form-group"><label><?= $t('Table prefix') ?></label><input type="text" name="prefix" value="<?= $h('prefix', 'erp_') ?>" required></div>
<div class="hint" style="color:#94a3b8;font-size:12.5px;margin-top:-4px;"><?= $t('Keys, ports and the search engine are configured in the following steps') ?></div>

<div class="form-group" style="border-top:1px dashed #c7d2fe;padding-top:12px;margin-top:4px">
  <label style="cursor:pointer;display:flex;align-items:flex-start;gap:8px">
    <input type="checkbox" name="demo_data" value="1" style="margin-top:3px"<?= ($old['demo_data'] ?? '') === '1' ? ' checked' : '' ?>>
    <span style="font-weight:600"><?= $t('Also import <b>demo data</b>') ?><br>
      <small style="color:#94a3b8;font-weight:400"><?= $t('Products/specs/SKUs/customers/suppliers, ID range 41…, removable by range. Off by default — leave it unchecked in production.') ?></small></span>
  </label>
</div>

<div class="form-actions">
  <a href="/install" class="btn btn-secondary"><?= $t('← Back (environment check)') ?></a>
  <button type="button" id="test-db-btn" class="btn btn-secondary"><?= $t('Test connection') ?></button>
  <button type="submit" class="btn"><?= $t('Next: Keys and ports') ?></button>
</div>
<div id="test-result" style="margin-top:12px;"></div>
</form>
<script>
(function () {
  var L = <?= json_encode([
      'host' => $t('Please enter the database host'),
      'port' => $t('Please enter a valid database port'),
      'database' => $t('Please enter the database name (it will be created if missing)'),
      'user' => $t('Please enter the database user name'),
      'incomplete' => $t('Please fill in host/port/database/user before testing'),
      'testing' => $t('Testing...'),
      'failed' => $t('Request failed: :msg'),
  ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var form = document.getElementById('db-form');
  function bindEye(id) {
    var input = document.getElementById(id), btn = document.querySelector('[data-eye="'+id+'"]');
    btn.addEventListener('click', function () {
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.textContent = show ? '🙈' : '👁';
    });
  }
  bindEye('db-pass');
  function block(elm, msg) {
    elm.focus();
    document.getElementById('test-result').innerHTML = '<span style="color:#c62828;">❌ ' + msg + '</span>';
  }
  form.addEventListener('submit', function (ev) {
    var host = form.querySelector('[name="host"]');
    var port = form.querySelector('[name="port"]');
    var db   = form.querySelector('[name="database"]');
    var user = form.querySelector('[name="username"]');
    if (!host.value.trim()) { ev.preventDefault(); return block(host, L.host); }
    if (!port.value.trim() || !/^\d{1,5}$/.test(port.value)) { ev.preventDefault(); return block(port, L.port); }
    if (!db.value.trim()) { ev.preventDefault(); return block(db, L.database); }
    if (!user.value.trim()) { ev.preventDefault(); return block(user, L.user); }
  });
  document.getElementById('test-db-btn')?.addEventListener('click', testDb);
  async function testDb() {
    var r = document.getElementById('test-result');
    var host = document.querySelector('[name="host"]'), port = document.querySelector('[name="port"]');
    var db = document.querySelector('[name="database"]'), user = document.querySelector('[name="username"]');
    if (!host.value.trim() || !db.value.trim() || !user.value.trim() || !port.value.trim()) {
      r.innerHTML = '<span style="color:#c62828;">❌ ' + L.incomplete + '</span>';
      return;
    }
    r.innerHTML = '<span style="color:#999;">⏳ ' + L.testing + '</span>';
    var fd = new FormData(form);
    var p = new URLSearchParams();
    for (var e of fd) { if (e[0] !== 'step' && e[0] !== 'prefix') p.append(e[0], e[1]); }
    try {
      var resp = await fetch('/install/test-db?' + p.toString());
      var json = await resp.json();
      r.innerHTML = json.code === 0
        ? '<span style="color:#2e7d32;">✅ ' + json.message + '</span>'
        : '<span style="color:#c62828;">❌ ' + json.message + '</span>';
    } catch (e) {
      r.innerHTML = '<span style="color:#c62828;">❌ ' + L.failed.replace(':msg', e.message) + '</span>';
    }
  }
})();
</script>
