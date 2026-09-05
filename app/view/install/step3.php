<h1 class="step-title">确认安装</h1>
<div class="summary-card">
  <div class="sum-head">📋 安装配置总览</div>
  <?php foreach ($summary as $s): ?>
  <div class="sum-item"><span class="sum-label"><?= htmlspecialchars((string) $s[0]) ?></span><span class="sum-value"><?= htmlspecialchars((string) $s[1], ENT_QUOTES) ?></span></div>
  <?php endforeach; ?>
</div>
<div class="notice-box">
  <div class="notice-title">⚠️ 点击「开始安装」后将依次执行：</div>
  <ol class="notice-list">
    <li>写入 <code>.env</code> 配置文件（密钥自动生成）</li>
    <li>自动创建数据库并导入 226 张表结构与种子数据</li>
    <li>创建管理员账号并关联超级管理员角色</li>
  </ol>
  <div class="notice-tip">全过程约需数秒，请勿关闭页面。安装后 .env 将标记 APP_INSTALLED=true，重复访问 /install 将跳转完成页。</div>
</div>
<form method="post" class="install-actions" id="install-form">
<input type="hidden" name="step" value="3">
<?php foreach (['host','port','database','username','password','prefix','admin_username','admin_password','jwt_secret','encryption_key','encryptable_key','hashids_salt','http_port','ws_port'] as $k): ?>
<input type="hidden" name="<?= $k ?>" value="<?= htmlspecialchars((string) ($old[$k] ?? ''), ENT_QUOTES) ?>">
<?php endforeach; ?>
<a href="/install?step=2" class="btn btn-secondary">← 上一步</a>
<button type="submit" id="install-btn" class="btn btn-install">🚀 开始安装</button>
</form>

<div id="progress-mask" style="display:none">
  <div class="pm-card"><div class="pm-title" id="pm-title">正在安装 open-erp…</div>
  <div class="pm-track"><div class="pm-bar" id="pm-bar"></div></div>
  <div class="pm-step" id="pm-step">准备中…</div>
  <div class="pm-err" id="pm-err" style="display:none"></div>
  <button type="button" id="pm-retry" class="btn btn-install" style="display:none">🔄 重试</button>
  </div>
</div>
<script>
(function () {
  var form = document.getElementById('install-form');
  var mask = document.getElementById('progress-mask');
  var bar = document.getElementById('pm-bar');
  var stepEl = document.getElementById('pm-step');
  var errEl = document.getElementById('pm-err');
  var retryBtn = document.getElementById('pm-retry');
  var phases = ['写入配置文件…', '创建数据库…', '导入表结构与种子数据…', '创建管理员账号…', '即将完成…'];
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
        if (html.indexOf('系统已成功安装') !== -1 || html.indexOf('安装完成') !== -1) {
          stepEl.textContent = '✅ 安装成功，正在跳转…';
          setTimeout(function () { location.href = '/install'; }, 900);
        } else {
          var doc = new DOMParser().parseFromString(html, 'text/html');
          var alert = doc.querySelector('.alert-error');
          bar.style.width = '0%';
          errEl.style.display = 'block';
          errEl.textContent = '安装未完成：' + (alert ? alert.textContent.trim() : '未知错误，请查看服务端日志');
          retryBtn.style.display = 'inline-flex'; stepEl.textContent = '';
        }
      })
      .catch(function (e) {
        clearInterval(timer); bar.style.width = '0%';
        errEl.style.display = 'block';
        errEl.textContent = '请求失败：' + e.message;
        retryBtn.style.display = 'inline-flex'; stepEl.textContent = '';
      });
  }
  form.addEventListener('submit', function (ev) { ev.preventDefault(); run(new FormData(form)); });
  retryBtn.addEventListener('click', function () { if (lastFd) run(lastFd); });
})();
</script>
