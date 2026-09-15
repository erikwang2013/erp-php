<?php /* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */ ?>
<h1><?= $t('Environment check') ?></h1>
<table class="env-table">
<?php foreach ($envs as $e): ?>
<tr><td><?= $e['icon'] ?></td><td><?= htmlspecialchars((string) $e['name']) ?></td><td style="color:#888;font-size:13px"><?= htmlspecialchars((string) $e['value']) ?></td></tr>
<?php endforeach; ?>
</table>
<?php if ($allOk): ?>
<form method="post"><input type="hidden" name="step" value="0"><button type="submit" class="btn"><?= $t('Next: Database configuration') ?></button></form>
<?php else: ?>
<div class="alert alert-error"><?= $t('Please fix the issues marked ❌ above, then refresh this page to check again.') ?></div>
<?php endif; ?>
