<?php

declare(strict_types=1);

/** @var OlegV\ErrorBrick\ErrorBrick $this */
?>

<?php if ($this->isProduction): ?>
    <!-- Brick error: <?= $this->e($this->originalClass) ?> -->
<?php else: ?>
    <div class="brick-error" style="border: 1px solid #dc2626; background: #fef2f2; padding: 1rem; margin: 0.5rem; border-radius: 0.375rem;">
        <div style="font-weight: 600; color: #dc2626; margin-bottom: 0.5rem;">
            🧱 Brick Error
        </div>
        <div style="margin-bottom: 0.25rem;">
            <?= $this->e($this->message) ?>
        </div>
        <?php if ($this->context !== ''): ?>
            <div style="font-size: 0.875rem; color: #6b7280; margin-bottom: 0.25rem;">
                Context: <?= $this->e($this->context) ?>
            </div>
        <?php endif; ?>
        <div style="font-size: 0.75rem; color: #9ca3af;">
            Component: <?= $this->e($this->originalClass) ?>
        </div>
    </div>
<?php endif; ?>