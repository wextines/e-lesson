<?php if (!isset($extraScripts)) $extraScripts = ''; ?>
</main>
</div>
<?php if ($extraScripts): ?>
<script><?= $extraScripts ?></script>
<?php endif; ?>
<script>lucide.createIcons();</script>
</body>
</html>
