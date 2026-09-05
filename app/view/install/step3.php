<?php $h = static fn (string $k, string $d = '') => htmlspecialchars((string) ($old[$k] ?? $d), ENT_QUOTES); ?>
<h1 class="step-title">搜索引擎（可选）</h1>
<form method="post" action="/install" id="engine-form">
<input type="hidden" name="step" value="3">
<?php foreach (['host','port','database','username','password','prefix','jwt_secret','encryption_key','encryptable_key','hashids_salt','hashids_alt_salt','http_port','ws_port','rabbitmq_password'] as $k): ?>
<input type="hidden" name="<?= $k ?>" value="<?= htmlspecialchars((string) ($old[$k] ?? ''), ENT_QUOTES) ?>">
<?php endforeach; ?>
<div class="form-group"><label>搜索引擎（数据同步由 erikwang2013/webman-scout 驱动）</label>
<select name="engine_driver" id="engine-driver">
  <option value="none"<?= $h('engine_driver','none') === 'none' ? ' selected' : '' ?>>不启用（安装后可在 .env 中随时启用）</option>
  <option value="opensearch"<?= $h('engine_driver') === 'opensearch' ? ' selected' : '' ?>>OpenSearch</option>
  <option value="elasticsearch"<?= $h('engine_driver') === 'elasticsearch' ? ' selected' : '' ?>>Elasticsearch</option>
</select>
<div class="hint">「不启用」将写入 SCOUT_DRIVER=null 走空引擎；后续要启用：安装后修改 .env 的 SCOUT_DRIVER 及相关连接变量并重启</div>
</div>
<div id="engine-params"<?= ($h('engine_driver','none') === 'none') ? ' style="display:none"' : '' ?>>
  <div class="form-group"><label>服务地址</label><input name="engine_host" id="engine-host" value="<?= $h('engine_host','https://localhost:9200') ?>" placeholder="https://localhost:9200" autocomplete="off"><div class="hint">需带协议与端口，如 http://127.0.0.1:9200</div></div>
  <div class="form-row">
    <div class="form-group"><label>用户名</label><input name="engine_username" id="engine-user" value="<?= $h('engine_username') ?>" autocomplete="off"></div>
    <div class="form-group"><label>密码</label>
      <div class="pw-wrap"><input type="password" name="engine_password" id="eng-pass" data-pw value="<?= $h('engine_password') ?>" autocomplete="new-password">
      <button type="button" class="pw-eye" data-eye="eng-pass" aria-label="显示/隐藏密码">👁</button></div>
    </div>
  </div>
  <div class="hint" style="color:#94a3b8;font-size:12.5px;margin-top:-4px;">选择 Elasticsearch 写入 SCOUT_HOSTS / ES_USERNAME / ES_PASSWORD；选择 OpenSearch 写入 SCOUT_OPENSEARCH_HOST / USERNAME / PASSWORD（自签证书场景默认 ssl 校验收紧，需在 config/scout.php 调整）</div>
</div>

<div class="form-actions">
  <a href="/install?step=2" class="btn btn-secondary">← 上一步（密钥与端口）</a>
  <button type="submit" class="btn">下一步：管理员账号</button>
</div>
</form>
<script>
(function () {
  var driver = document.getElementById('engine-driver');
  var params = document.getElementById('engine-params');
  var fields = ['engine-host', 'engine-user', 'eng-pass'].map(function (id) {
    return document.getElementById(id);
  });
  function syncRequired() {
    var on = driver.value !== 'none';
    params.style.display = on ? 'block' : 'none';
    fields.forEach(function (el) {
      if (on) { el.setAttribute('required', ''); } else { el.removeAttribute('required'); }
    });
  }
  driver.addEventListener('change', syncRequired);
  syncRequired();
  var btn = document.querySelector('[data-eye="eng-pass"]');
  var input = document.getElementById('eng-pass');
  btn.addEventListener('click', function () {
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.textContent = show ? '🙈' : '👁';
  });
})();
</script>
