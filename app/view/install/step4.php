<?php $h = static fn (string $k, string $d = '') => htmlspecialchars((string) ($old[$k] ?? $d), ENT_QUOTES); ?>
<h1>管理员账号</h1>
<form method="post" action="/install">
<input type="hidden" name="step" value="4">
<?php foreach (['host','port','database','username','password','prefix','jwt_secret','encryption_key','encryptable_key','hashids_salt','hashids_alt_salt','http_port','ws_port','rabbitmq_password','engine_driver','engine_host','engine_username','engine_password'] as $k): ?>
<input type="hidden" name="<?= $k ?>" value="<?= htmlspecialchars((string) ($old[$k] ?? ''), ENT_QUOTES) ?>">
<?php endforeach; ?>
<div class="form-group"><label>管理员用户名</label><input type="text" name="admin_username" value="<?= $h('admin_username','admin') ?>" required minlength="3"></div>
<div class="form-group"><label>管理员密码</label>
  <div class="pw-wrap"><input type="password" name="admin_password" id="ap-pass" data-pw required minlength="6" placeholder="至少6位">
  <button type="button" class="pw-eye" data-eye="ap-pass" aria-label="显示/隐藏密码">👁</button></div>
</div>
<div class="form-group"><label>确认密码</label>
  <div class="pw-wrap"><input type="password" name="admin_password_confirm" id="ap-confirm" data-pw required minlength="6" placeholder="再次输入密码">
  <button type="button" class="pw-eye" data-eye="ap-confirm" aria-label="显示/隐藏密码">👁</button></div>
</div>
<a href="/install?step=3" class="btn btn-secondary">← 上一步（搜索引擎）</a>
<button type="submit" class="btn">下一步：确认安装</button>
</form>
<script>
(function () {
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
