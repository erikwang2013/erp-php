<h1>管理员账号</h1>
<form method="post">
<input type="hidden" name="step" value="2">
<?php foreach (['host','port','database','username','password','prefix'] as $k): ?>
<input type="hidden" name="<?= $k ?>" value="<?= htmlspecialchars((string) ($old[$k] ?? ''), ENT_QUOTES) ?>">
<?php endforeach; ?>
<div class="form-group"><label>管理员用户名</label><input type="text" name="admin_username" value="<?= htmlspecialchars((string) ($old['admin_username'] ?? 'admin'), ENT_QUOTES) ?>" required minlength="3"></div>
<div class="form-group"><label>管理员密码</label><input type="password" name="admin_password" required minlength="6" placeholder="至少6位"></div>
<div class="form-group"><label>确认密码</label><input type="password" name="admin_password_confirm" required minlength="6" placeholder="再次输入密码"></div>
<a href="/install?step=1" class="btn btn-secondary">← 上一步（数据库配置）</a>
<button type="submit" class="btn">下一步：确认安装</button>
</form>
