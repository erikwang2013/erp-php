<?php /* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */ $h = static fn (string $k, string $d = '') => htmlspecialchars((string) ($old[$k] ?? $d), ENT_QUOTES); ?>
<h1><?= $t('Administrator account') ?></h1>
<form method="post" action="/install" id="admin-form">
<input type="hidden" name="step" value="4">
<?php foreach (['host','port','database','username','password','prefix','jwt_secret','encryption_key','encryptable_key','hashids_salt','hashids_alt_salt','http_port','ws_port','rabbitmq_password','engine_driver','engine_host','engine_username','engine_password','demo_data'] as $k): ?>
<input type="hidden" name="<?= $k ?>" value="<?= htmlspecialchars((string) ($old[$k] ?? ''), ENT_QUOTES) ?>">
<?php endforeach; ?>
<div class="form-group"><label><?= $t('Administrator user name') ?></label><input type="text" name="admin_username" value="<?= $h('admin_username', 'admin') ?>" required minlength="3" maxlength="50"></div>
<div class="form-group"><label><?= $t('Administrator password') ?></label>
  <div class="pw-wrap"><input type="password" name="admin_password" id="ap-pass" data-pw required minlength="6" maxlength="32" placeholder="<?= $t('6-32 characters') ?>">
  <button type="button" class="pw-eye" data-eye="ap-pass" aria-label="<?= $t('Show/hide password') ?>">👁</button></div>
</div>
<div class="form-group"><label><?= $t('Confirm password') ?></label>
  <div class="pw-wrap"><input type="password" name="admin_password_confirm" id="ap-confirm" data-pw required minlength="6" maxlength="32" placeholder="<?= $t('Enter the password again') ?>">
  <button type="button" class="pw-eye" data-eye="ap-confirm" aria-label="<?= $t('Show/hide password') ?>">👁</button></div>
</div>
<a href="/install?step=3" class="btn btn-secondary"><?= $t('← Back (search engine)') ?></a>
<button type="submit" class="btn"><?= $t('Next: Confirm installation') ?></button>
</form>
<script>
(function () {
  var L = <?= json_encode(['mismatch' => $t('The two passwords do not match')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var form = document.getElementById('admin-form');
  var pass = document.getElementById('ap-pass');
  var confirm = document.getElementById('ap-confirm');
  function check() {
    confirm.setCustomValidity(pass.value !== confirm.value ? L.mismatch : '');
  }
  form.addEventListener('submit', check);
  [pass, confirm].forEach(function (el) {
    el.addEventListener('input', function () { confirm.setCustomValidity(''); });
  });
  function bindEye(id) {
    var input = document.getElementById(id);
    var btn = document.querySelector('[data-eye="'+id+'"]');
    btn.addEventListener('click', function () {
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.textContent = show ? '🙈' : '👁';
    });
  }
  bindEye('ap-pass'); bindEye('ap-confirm');
})();
</script>
