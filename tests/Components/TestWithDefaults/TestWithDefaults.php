<?php


declare(strict_types=1);

namespace OlegV\Tests\Components\TestWithDefaults;

use OlegV\Brick;

readonly class TestWithDefaults extends Brick
{
    public function __construct(
        public string $name = 'default',
        public int $count = 10,
        public bool $active = true
    ) {
        parent::__construct();
    }
}