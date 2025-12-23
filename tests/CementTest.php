<?php

namespace OlegV\Tests;

use OlegV\Cement;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CementTest extends TestCase
{
    private Cement $cement;

    protected function setUp(): void
    {
        $this->cement = new Cement();
    }

    public function testAddSingleClosure(): void
    {
        $this->cement->add(TestClass::class, fn() => new TestClass('test'));

        $instance = $this->cement->get(TestClass::class);

        $this->assertInstanceOf(TestClass::class, $instance);
        $this->assertEquals('test', $instance->value);
    }

    public function testAddVariants(): void
    {
        $this->cement->add(TestClass::class, [
            'default' => fn() => new TestClass('default'),
            'special' => fn() => new TestClass('special'),
        ]);

        $default = $this->cement->get(TestClass::class);
        $special = $this->cement->get(TestClass::class, ['variant' => 'special']);

        $this->assertEquals('default', $default->value);
        $this->assertEquals('special', $special->value);
    }

    public function testAddAll(): void
    {
        $config = [
            TestClass::class => [
                'default' => fn() => new TestClass('test1'),
            ],
            AnotherClass::class => fn() => new AnotherClass('test2'),
        ];

        $this->cement->addAll($config);

        $this->assertTrue($this->cement->has(TestClass::class));
        $this->assertTrue($this->cement->has(AnotherClass::class));

        $test1 = $this->cement->get(TestClass::class);
        $test2 = $this->cement->get(AnotherClass::class);

        $this->assertEquals('test1', $test1->value);
        $this->assertEquals('test2', $test2->value);
    }

    public function testGetWithParameters(): void
    {
        $this->cement->add(TestClass::class,
            fn($c, $params) => new TestClass($params['value'] ?? 'default')
        );

        $instance = $this->cement->get(TestClass::class, ['value' => 'custom']);

        $this->assertEquals('custom', $instance->value);
    }

    public function testMakeAlwaysCreatesNewInstance(): void
    {
        $counter = 0;
        $this->cement->add(TestClass::class,
            function() use (&$counter) {
                return new TestClass('test' . ++$counter);
            }
        );

        $first = $this->cement->get(TestClass::class);
        $second = $this->cement->get(TestClass::class);
        $third = $this->cement->make(TestClass::class);

        $this->assertSame($first, $second); // Из кэша
        $this->assertNotSame($first, $third); // Новый экземпляр
        $this->assertEquals('test1', $first->value);
        $this->assertEquals('test2', $third->value);
    }

    public function testHas(): void
    {
        $this->cement->add(TestClass::class, [
            'default' => fn() => new TestClass('default'),
            'special' => fn() => new TestClass('special'),
        ]);

        $this->assertTrue($this->cement->has(TestClass::class));
        $this->assertTrue($this->cement->has(TestClass::class, 'default'));
        $this->assertTrue($this->cement->has(TestClass::class, 'special'));
        $this->assertFalse($this->cement->has(TestClass::class, 'nonexistent'));
        $this->assertFalse($this->cement->has('NonexistentClass'));
    }

    public function testClear(): void
    {
        $counter = 0;
        $this->cement->add(TestClass::class,
            function() use (&$counter) {
                return new TestClass('test' . ++$counter);
            }
        );

        $first = $this->cement->get(TestClass::class);
        $this->cement->clear();
        $second = $this->cement->get(TestClass::class);

        $this->assertNotSame($first, $second);
        $this->assertEquals('test1', $first->value);
        $this->assertEquals('test2', $second->value);
    }

    public function testAutowireWithoutConstructor(): void
    {
        $instance = $this->cement->get(ClassWithoutConstructor::class);

        $this->assertInstanceOf(ClassWithoutConstructor::class, $instance);
    }

    public function testAutowireWithDependencies(): void
    {
        $this->cement->add(TestClass::class, fn() => new TestClass('dependency'));
        $this->cement->add(AnotherClass::class, fn() => new AnotherClass('another'));

        $instance = $this->cement->get(ClassWithDependencies::class);

        $this->assertInstanceOf(ClassWithDependencies::class, $instance);
        $this->assertEquals('dependency', $instance->testClass->value);
        $this->assertEquals('another', $instance->anotherClass->value);
    }

    public function testAutowireWithParametersOverride(): void
    {
        $this->cement->add(TestClass::class, fn() => new TestClass('default'));
        $this->cement->add(AnotherClass::class, fn() => new AnotherClass('default_another'));

        $instance = $this->cement->get(ClassWithDependencies::class, [
            'testClass' => new TestClass('custom'),
            'optional' => 'optional_value',
        ]);

        $this->assertEquals('custom', $instance->testClass->value);
        $this->assertEquals('default_another', $instance->anotherClass->value);
        $this->assertEquals('optional_value', $instance->optional);
    }

    public function testAutowireWithOptionalParameter(): void
    {
        $this->cement->add(TestClass::class, fn() => new TestClass('dependency'));

        $instance = $this->cement->get(ClassWithOptionalDependency::class);

        $this->assertInstanceOf(ClassWithOptionalDependency::class, $instance);
        $this->assertNotNull($instance->required);
        $this->assertNull($instance->optional);
    }

    public function testAutowireWithBuiltinTypes(): void
    {
        $instance = $this->cement->get(ClassWithBuiltinTypes::class, [
            'name' => 'TestName',
            'count' => 42,
        ]);

        $this->assertEquals('TestName', $instance->name);
        $this->assertEquals(42, $instance->count);
        $this->assertTrue($instance->active);
    }

    public function testThrowsExceptionWhenClassNotFound(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Класс NonExistentClass не найден');

        $this->cement->get('NonExistentClass');
    }

    public function testThrowsExceptionWhenVariantNotFound(): void
    {
        $this->cement->add(TestClass::class, [
            'default' => fn() => new TestClass('default'),
        ]);

        $this->expectException(RuntimeException::class);

        $this->cement->get(TestClass::class, ['variant' => 'nonexistent']);
    }

    public function testThrowsExceptionForUnresolvableDependency(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Не удалось разрешить зависимость');

        $this->cement->get(ClassWithUnresolvableDependency::class);
    }

    public function testThrowsExceptionForUnresolvableBuiltinParameter(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Не удалось разрешить обязательный параметр');

        $this->cement->get(ClassWithRequiredBuiltinParameter::class);
    }

    public function testFactoryReturnsObject(): void
    {
        $object = new TestClass('premade');
        $this->cement->add(TestClass::class, ['default' => $object]);

        $instance = $this->cement->get(TestClass::class);

        $this->assertSame($object, $instance);
    }

    public function testFactoryWithArrayParameters(): void
    {
        $this->cement->add(TestClass::class, [
            'default' => ['array_value'],
        ]);

        $instance = $this->cement->get(TestClass::class);

        $this->assertEquals('array_value', $instance->value);
    }

    public function testCacheKeyIncludesParameters(): void
    {
        $this->cement->add(TestClass::class,
            fn($c, $params) => new TestClass($params['value'] ?? 'default')
        );

        $instance1 = $this->cement->get(TestClass::class, ['value' => 'first']);
        $instance2 = $this->cement->get(TestClass::class, ['value' => 'second']);
        $instance3 = $this->cement->get(TestClass::class, ['value' => 'first']);

        $this->assertNotSame($instance1, $instance2);
        $this->assertSame($instance1, $instance3);
        $this->assertEquals('first', $instance1->value);
        $this->assertEquals('second', $instance2->value);
    }

    public function testCircularDependencyDetection(): void
    {
        $this->cement->add(CircularA::class,
            fn($c) => new CircularA($c->get(CircularB::class))
        );

        $this->cement->add(CircularB::class,
            fn($c) => new CircularB($c->get(CircularA::class))
        );

        $this->expectException(RuntimeException::class);

        $this->cement->get(CircularA::class);
    }
}

// Вспомогательные классы для тестирования

class TestClass
{
    public function __construct(public string $value) {}
}

class AnotherClass
{
    public function __construct(public string $value) {}
}

class ClassWithoutConstructor {}

class ClassWithDependencies
{
    public function __construct(
        public TestClass $testClass,
        public AnotherClass $anotherClass,
        public string $optional = 'default'
    ) {}
}

class ClassWithOptionalDependency
{
    public function __construct(
        public TestClass $required,
        public ?AnotherClass $optional = null
    ) {}
}

class ClassWithBuiltinTypes
{
    public function __construct(
        public string $name,
        public int $count,
        public bool $active = true
    ) {}
}

class ClassWithUnresolvableDependency
{
    public function __construct(TestClass $required) {}
}

class CircularA
{
    public function __construct(public CircularB $b) {}
}

class CircularB
{
    public function __construct(public CircularA $a) {}
}
class ClassWithRequiredBuiltinParameter
{
    public function __construct(
        public string $requiredString // Нет значения по умолчанию
    ) {}
}