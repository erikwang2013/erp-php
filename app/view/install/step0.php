<h1>环境检查</h1>
<table class="env-table">
<?php foreach ($envs as $e): ?>
<tr><td><?= $e['icon'] ?></td><td><?= htmlspecialchars((string) $e['name']) ?></td><td style="color:#888;font-size:13px"><?= htmlspecialchars((string) $e['value']) ?></td></tr>
<?php endforeach; ?>
</table>
<?php if ($allOk): ?>
<form method="post"><input type="hidden" name="step" value="0"><button type="submit" class="btn">下一步：数据库配置</button></form>
<?php else: ?>
<div class="alert alert-error">请先解决以上 ❌ 标记的问题，然后刷新本页重新检查。</div>
<?php endif; ?>
