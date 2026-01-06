<?php

declare(strict_types=1);

namespace OlegV\Tests\Components\TestButton;

use OlegV\Brick;

readonly class TestButton extends Brick
{
    public function __construct(
        public string $text,
        public string $variant = 'primary'
    ) {
        parent::__construct();
    }
}