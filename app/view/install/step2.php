<?php $h = static fn (string $k, string $d = '') => htmlspecialchars((string) ($old[$k] ?? $d), ENT_QUOTES); ?>
<h1 class="step-title">密钥与启动端口</h1>
<form method="post" action="/install">
<input type="hidden" name="step" value="2">
<?php foreach (['host','port','database','username','password','prefix'] as $k): ?>
<input type="hidden" name="<?= $k ?>" value="<?= htmlspecialchars((string) ($old[$k] ?? ''), ENT_QUOTES) ?>">
<?php endforeach; ?>
<div class="alert-warn" style="margin:0 0 16px;">以下密钥均可留空，安装时将自动生成强随机值；端口留空取默认。</div>
<div class="form-row">
  <div class="form-group"><label>JWT 签名密钥</label><input name="jwt_secret" value="<?= $h('jwt_secret') ?>" placeholder="留空自动生成（推荐）"><div class="hint">令牌签名，泄露可伪造登录态</div></div>
  <div class="form-group"><label>接口传输密钥</label><input name="encryption_key" value="<?= $h('encryption_key') ?>" placeholder="留空自动生成（推荐）"><div class="hint">写入 .env 的 ENCRYPTION_KEY</div></div>
</div>
<div class="form-row">
  <div class="form-group"><label>存储加密密钥</label><input name="encryptable_key" value="<?= $h('encryptable_key') ?>" placeholder="留空自动生成（推荐）"><div class="hint">写入 .env 的 ENCRYPTABLE_KEY</div></div>
  <div class="form-group"><label>ID 混淆盐</label><input name="hashids_salt" value="<?= $h('hashids_salt') ?>" placeholder="留空自动生成（推荐）"><div class="hint">写入 .env 的 HASHIDS_SALT</div></div>
</div>
<div class="form-group"><label>ID 混淆盐（备用）</label><input name="hashids_alt_salt" value="<?= $h('hashids_alt_salt') ?>" placeholder="留空自动生成（推荐）"><div class="hint">独立于主盐的备用盐，编码另一组业务 ID；写入 .env 的 HASHIDS_ALT_SALT</div></div>
<div class="form-row">
  <div class="form-group"><label>启动端口（HTTP）</label><input name="http_port" value="<?= $h('http_port','8788') ?>" placeholder="默认 8788"><div class="hint">写入 .env 的 APP_HTTP_PORT</div></div>
  <div class="form-group"><label>WebSocket 端口</label><input name="ws_port" value="<?= $h('ws_port','8282') ?>" placeholder="默认 8282"><div class="hint">写入 .env 的 APP_WS_PORT</div></div>
</div>
<div style="font-weight:700;color:#4338ca;padding:14px 0 6px;border-top:1px dashed #c7d2fe;margin-top:8px;font-size:14px">🔌 服务账号密码</div>
<div class="hint" style="margin:-2px 0 10px;color:#94a3b8;font-size:12px">需与部署环境（docker-compose 等）中的口令一致；留空则沿用 .env.example 原值，不会自动生成</div>
<div class="form-group"><label>消息队列（RabbitMQ）密码</label>
  <div class="pw-wrap"><input type="password" name="rabbitmq_password" id="mq-pass" data-pw value="<?= $h('rabbitmq_password') ?>">
  <button type="button" class="pw-eye" data-eye="mq-pass" aria-label="显示/隐藏密码">👁</button></div>
</div>

<div class="form-actions">
  <a href="/install?step=1" class="btn btn-secondary">← 上一步（数据库配置）</a>
  <button type="submit" class="btn">下一步：搜索引擎（可选）</button>
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
