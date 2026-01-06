<?php

declare(strict_types=1);

namespace OlegV\Tests\Components\TestCard;

use OlegV\Brick;
use OlegV\Tests\Components\TestButton\TestButton;

readonly class TestCard extends Brick
{
    public function __construct(
        public string $title,
        public TestButton $button,
        public string $variant = 'default'
    ) {
        parent::__construct();
    }
}