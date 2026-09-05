<?php $h = static fn (string $k, string $d = '') => htmlspecialchars((string) ($old[$k] ?? $d), ENT_QUOTES); ?>
<h1>数据库配置</h1>
<form method="post" action="/install" id="db-form">
<input type="hidden" name="step" value="1">
<div class="form-row">
  <div class="form-group" style="flex:2"><label>主机地址</label><input type="text" name="host" required value="<?= $h('host','127.0.0.1') ?>"></div>
  <div class="form-group" style="flex:1"><label>端口</label><input type="number" name="port" required value="<?= $h('port','3306') ?>"></div>
</div>
<div class="form-group"><label>数据库名</label><input type="text" name="database" required value="<?= $h('database','erp') ?>" placeholder="不存在将自动创建"></div>
<div class="form-group"><label>用户名</label><input type="text" name="username" required value="<?= $h('username','root') ?>"></div>
<div class="form-group"><label>密码</label>
  <div class="pw-wrap"><input type="password" name="password" id="db-pass" data-pw value="<?= $h('password') ?>">
  <button type="button" class="pw-eye" data-eye="db-pass" aria-label="显示/隐藏密码">👁</button></div>
</div>
<div class="form-group"><label>表前缀</label><input type="text" name="prefix" value="<?= $h('prefix','erp_') ?>" required></div>
<div class="hint" style="color:#94a3b8;font-size:12.5px;margin-top:-4px;">密钥、启动端口与搜索引擎等高级配置将在后续步骤中单独完成</div>

<div class="form-actions">
  <a href="/install" class="btn btn-secondary">← 上一步（环境检查）</a>
  <button type="button" id="test-db-btn" class="btn btn-secondary">测试连接</button>
  <button type="submit" class="btn">下一步：密钥与端口</button>
</div>
<div id="test-result" style="margin-top:12px;"></div>
</form>
<script>
(function () {
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
    if (!host.value.trim()) { ev.preventDefault(); return block(host, '请填写数据库主机地址'); }
    if (!port.value.trim() || !/^\d{1,5}$/.test(port.value)) { ev.preventDefault(); return block(port, '请填写正确的数据库端口'); }
    if (!db.value.trim()) { ev.preventDefault(); return block(db, '请填写数据库名'); }
    if (!user.value.trim()) { ev.preventDefault(); return block(user, '请填写数据库用户名'); }
  });
  document.getElementById('test-db-btn')?.addEventListener('click', testDb);
  async function testDb() {
    var r = document.getElementById('test-result');
    var host = document.querySelector('[name="host"]'), port = document.querySelector('[name="port"]');
    var db = document.querySelector('[name="database"]'), user = document.querySelector('[name="username"]');
    if (!host.value.trim() || !db.value.trim() || !user.value.trim() || !port.value.trim()) {
      r.innerHTML = '<span style="color:#c62828;">❌ 请先完整填写主机/端口/数据库名/用户名再测试</span>';
      return;
    }
    r.innerHTML = '<span style="color:#999;">⏳ 测试中...</span>';
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
      r.innerHTML = '<span style="color:#c62828;">❌ 请求失败: ' + e.message + '</span>';
    }
  }
})();
</script>
