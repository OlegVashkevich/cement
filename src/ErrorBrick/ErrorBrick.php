<?php

namespace OlegV\ErrorBrick;

use OlegV\Brick;

/**
 * Компонент для отображения ошибок
 */
readonly class ErrorBrick extends Brick
{
    public function __construct(
        public string $message,
        public string $originalClass,
        public string $context = '',
        public bool $isProduction = false
    ) {
        parent::__construct();
    }
}