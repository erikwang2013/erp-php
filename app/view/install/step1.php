<?php $h = static fn (string $k, string $d = '') => htmlspecialchars((string) ($old[$k] ?? $d), ENT_QUOTES); ?>
<h1>数据库配置</h1>
<form method="post" id="db-form">
<input type="hidden" name="step" value="1">
<div class="form-row">
  <div class="form-group" style="flex:2"><label>主机地址</label><input type="text" name="host" required value="<?= $h('host','127.0.0.1') ?>"></div>
  <div class="form-group" style="flex:1"><label>端口</label><input type="number" name="port" required value="<?= $h('port','3306') ?>"></div>
</div>
<div class="form-group"><label>数据库名</label><input type="text" name="database" required value="<?= $h('database','erp') ?>" placeholder="不存在将自动创建"></div>
<div class="form-group"><label>用户名</label><input type="text" name="username" required value="<?= $h('username','root') ?>"></div>
<div class="form-group"><label>密码</label><input type="password" name="password" value="<?= $h('password') ?>"></div>
<div class="form-group"><label>表前缀</label><input type="text" name="prefix" value="<?= $h('prefix','erp_') ?>" required></div>

<div class="adv-box" id="adv-box" style="display:block;margin:18px 0;border:1px solid #c7d2fe;border-radius:12px;background:#fafaff;padding:12px 18px 16px">
  <div class="adv-head" style="font-weight:700;color:#4338ca;padding:6px 0 10px">🔐 密钥与启动端口（高级）—— 留空则安装时自动生成</div>
  <div class="form-row" style="display:flex;gap:14px">
    <div class="form-group" style="flex:1"><label>JWT 签名密钥 JWT_SECRET_KEY</label><input name="jwt_secret" value="<?= $h('jwt_secret') ?>" placeholder="留空自动生成（推荐）"><div class="hint">令牌签名，泄露可伪造登录态</div></div>
    <div class="form-group" style="flex:1"><label>接口传输密钥 ENCRYPTION_KEY</label><input name="encryption_key" value="<?= $h('encryption_key') ?>" placeholder="留空自动生成（推荐）"></div>
  </div>
  <div class="form-row" style="display:flex;gap:14px">
    <div class="form-group" style="flex:1"><label>存储加密密钥 ENCRYPTABLE_KEY</label><input name="encryptable_key" value="<?= $h('encryptable_key') ?>" placeholder="留空自动生成（推荐）"></div>
    <div class="form-group" style="flex:1"><label>ID 混淆盐 HASHIDS_SALT</label><input name="hashids_salt" value="<?= $h('hashids_salt') ?>" placeholder="留空自动生成（推荐）"></div>
  </div>
  <div class="form-row" style="display:flex;gap:14px">
    <div class="form-group" style="flex:1"><label>启动端口（HTTP）</label><input name="http_port" value="<?= $h('http_port','8788') ?>" placeholder="默认 8788"><div class="hint">写入 .env 的 APP_HTTP_PORT</div></div>
    <div class="form-group" style="flex:1"><label>WebSocket 端口</label><input name="ws_port" value="<?= $h('ws_port','8282') ?>" placeholder="默认 8282"><div class="hint">写入 .env 的 APP_WS_PORT</div></div>
  </div>
</div>

<div class="form-actions">
  <a href="/install" class="btn btn-secondary">← 上一步（环境检查）</a>
  <button type="button" id="test-db-btn" class="btn btn-secondary">测试连接</button>
  <button type="submit" class="btn">下一步：管理员账号</button>
</div>
<div id="test-result" style="margin-top:12px;"></div>
</form>
<script>
(function () {
  document.getElementById('test-db-btn')?.addEventListener('click', testDb);
  async function testDb() {
    const r = document.getElementById('test-result');
    r.innerHTML = '<span style="color:#999;">⏳ 测试中...</span>';
    const fd = new FormData(document.getElementById('db-form'));
    const p = new URLSearchParams();
    for (const [k, v] of fd) { if (k !== 'step' && k !== 'prefix') p.append(k, v); }
    try {
      const resp = await fetch('/install/test-db?' + p.toString());
      const json = await resp.json();
      r.innerHTML = json.code === 0
        ? '<span style="color:#2e7d32;">✅ ' + json.message + '</span>'
        : '<span style="color:#c62828;">❌ ' + json.message + '</span>';
    } catch (e) {
      r.innerHTML = '<span style="color:#c62828;">❌ 请求失败: ' + e.message + '</span>';
    }
  }
})();
</script>
