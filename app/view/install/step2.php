<?php /* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */ $h = static fn (string $k, string $d = '') => htmlspecialchars((string) ($old[$k] ?? $d), ENT_QUOTES); ?>
<h1 class="step-title"><?= $t('Keys and ports') ?></h1>
<form method="post" action="/install">
<input type="hidden" name="step" value="2">
<?php foreach (['host','port','database','username','password','prefix','demo_data'] as $k): ?>
<input type="hidden" name="<?= $k ?>" value="<?= htmlspecialchars((string) ($old[$k] ?? ''), ENT_QUOTES) ?>">
<?php endforeach; ?>
<div class="alert-warn" style="margin:0 0 16px;"><?= $t('Leave any key blank and a strong random value will be generated at install time; blank ports use defaults.') ?></div>
<div class="form-row">
  <div class="form-group"><label><?= $t('JWT signing key') ?></label><input name="jwt_secret" value="<?= $h('jwt_secret') ?>" placeholder="<?= htmlspecialchars($t('Leave blank to auto-generate (recommended)'), ENT_QUOTES) ?>"><div class="hint"><?= $t('Signs tokens; leaking it allows forging logins') ?></div></div>
  <div class="form-group"><label><?= $t('API transport key') ?></label><input name="encryption_key" value="<?= $h('encryption_key') ?>" placeholder="<?= htmlspecialchars($t('Leave blank to auto-generate (recommended)'), ENT_QUOTES) ?>"><div class="hint"><?= $t('Written to ENCRYPTION_KEY in .env') ?></div></div>
</div>
<div class="form-row">
  <div class="form-group"><label><?= $t('Storage encryption key') ?></label><input name="encryptable_key" value="<?= $h('encryptable_key') ?>" placeholder="<?= htmlspecialchars($t('Leave blank to auto-generate (recommended)'), ENT_QUOTES) ?>"><div class="hint"><?= $t('Written to ENCRYPTABLE_KEY in .env') ?></div></div>
  <div class="form-group"><label><?= $t('ID obfuscation salt') ?></label><input name="hashids_salt" value="<?= $h('hashids_salt') ?>" placeholder="<?= htmlspecialchars($t('Leave blank to auto-generate (recommended)'), ENT_QUOTES) ?>"><div class="hint"><?= $t('Written to HASHIDS_SALT in .env') ?></div></div>
</div>
<div class="form-group"><label><?= $t('ID obfuscation salt (alternate)') ?></label><input name="hashids_alt_salt" value="<?= $h('hashids_alt_salt') ?>" placeholder="<?= htmlspecialchars($t('Leave blank to auto-generate (recommended)'), ENT_QUOTES) ?>"><div class="hint"><?= $t('Separate salt encoding another set of business IDs; written to HASHIDS_ALT_SALT in .env') ?></div></div>
<div class="form-row">
  <div class="form-group"><label><?= $t('HTTP port') ?></label><input name="http_port" value="<?= $h('http_port', '8788') ?>" placeholder="<?= $t('Default 8788') ?>"><div class="hint"><?= $t('Written to APP_HTTP_PORT in .env') ?></div></div>
  <div class="form-group"><label><?= $t('WebSocket port') ?></label><input name="ws_port" value="<?= $h('ws_port', '8282') ?>" placeholder="<?= $t('Default 8282') ?>"><div class="hint"><?= $t('Written to APP_WS_PORT in .env') ?></div></div>
</div>
<div style="font-weight:700;color:#4338ca;padding:14px 0 6px;border-top:1px dashed #c7d2fe;margin-top:8px;font-size:14px"><?= $t('🔌 Service account passwords') ?></div>
<div class="hint" style="margin:-2px 0 10px;color:#94a3b8;font-size:12px"><?= $t('Must match the passwords in your deployment (docker-compose etc.); if left blank the .env.example values are kept, not auto-generated') ?></div>
<div class="form-group"><label><?= $t('Message queue (RabbitMQ) password') ?></label>
  <div class="pw-wrap"><input type="password" name="rabbitmq_password" id="mq-pass" data-pw value="<?= $h('rabbitmq_password') ?>">
  <button type="button" class="pw-eye" data-eye="mq-pass" aria-label="<?= $t('Show/hide password') ?>">👁</button></div>
</div>

<div class="form-actions">
  <a href="/install?step=1" class="btn btn-secondary"><?= $t('← Back (database configuration)') ?></a>
  <button type="submit" class="btn"><?= $t('Next: Search engine (optional)') ?></button>
</div>
</form>
<script>
(function () {
  var btn = document.querySelector('[data-eye="mq-pass"]');
  var input = document.getElementById('mq-pass');
  btn.addEventListener('click', function () {
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.textContent = show ? '🙈' : '👁';
  });
})();
</script>
