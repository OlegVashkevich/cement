<?php
declare(strict_types=1);

namespace OlegV\Tests;

use InvalidArgumentException;
use OlegV\Cement;
use OlegV\Tests\Components\TestButton\TestButton;
use OlegV\Tests\Components\TestCard\TestCard;
use OlegV\Tests\Components\TestEmpty\TestEmpty;
use OlegV\Tests\Components\TestWithDefaults\TestWithDefaults;
use PHPUnit\Framework\TestCase;
use ReflectionException;

final class CementTest extends TestCase
{
    private Cement $cement;
protected function setUp(): void
{
    $this->cement = new Cement();
}

public function testItCanBeInstantiated(): void
{
    $this->assertInstanceOf(Cement::class, $this->cement);
}

public function testItAddsAndRetrievesPrototypes(): void
{
    $button = new TestButton('Submit');
    $this->cement->add(TestButton::class, $button, 'submit');

    $this->assertTrue($this->cement->has(TestButton::class, 'submit'));
    $this->assertSame($button, $this->cement->getPrototype(TestButton::class, 'submit'));
}

public function testItThrowsExceptionWhenAddingNonBrickClass(): void
{
    $this->expectException(\TypeError::class);
    $this->expectExceptionMessage('must be of type OlegV\Brick');

    $fake = new \stdClass();
    /** @phpstan-ignore argument.type */
    $this->cement->add(\stdClass::class, $fake);
}

public function testItThrowsExceptionWhenPrototypeNotInstanceOfClass(): void
{
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Prototype must be instance of');

    $button = new TestButton('Submit');
    // Пытаемся добавить TestButton как прототип для TestCard
    $this->cement->add(TestCard::class, $button);
}

public function testItReturnsEmptyArrayWhenNoVariants(): void
{
    $this->assertSame([], $this->cement->variants(TestButton::class));
}

public function testItReturnsListOfVariants(): void
{
    $this->cement->add(TestButton::class, new TestButton('A'), 'primary');
    $this->cement->add(TestButton::class, new TestButton('B'), 'secondary');
    $this->cement->add(TestButton::class, new TestButton('C'), 'danger');

    $variants = $this->cement->variants(TestButton::class);
    $this->assertSame(['primary', 'secondary', 'danger'], $variants);
}

public function testItReturnsNullForNonExistentPrototype(): void
{
    $this->assertNull($this->cement->getPrototype(TestButton::class, 'nonexistent'));
}

public function testItBuildsComponentWithoutOverrides(): void
{
    $prototype = new TestButton('Submit', 'primary');
    $this->cement->add(TestButton::class, $prototype, 'default');

    $result = $this->cement->build(TestButton::class, [], 'default');

    $this->assertInstanceOf(TestButton::class, $result);
    $this->assertSame('Submit', $result->text);
    $this->assertSame('primary', $result->variant);
    // Должен вернуть тот же объект (readonly безопасно)
    $this->assertSame($prototype, $result);
}

public function testItBuildsComponentWithOverrides(): void
{
    $prototype = new TestButton('Submit', 'primary');
    $this->cement->add(TestButton::class, $prototype, 'default');

    $result = $this->cement->build(TestButton::class, [
        'text' => 'Custom Text',
        'variant' => 'danger'
    ], 'default');

    $this->assertInstanceOf(TestButton::class, $result);
    $this->assertSame('Custom Text', $result->text);
    $this->assertSame('danger', $result->variant);
    // Это должен быть НОВЫЙ объект
    $this->assertNotSame($prototype, $result);
}

public function testItThrowsExceptionWhenBuildingNonExistentVariant(): void
{
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage("Variant 'nonexistent' not found for");

    $this->cement->build(TestButton::class, [], 'nonexistent');
}

public function testItThrowsExceptionWhenOverridingNonExistentProperty(): void
{
    $this->cement->add(TestButton::class, new TestButton('Submit'), 'default');

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage("Property 'nonexistent' does not exist in");

    $this->cement->build(TestButton::class, [
        'nonexistent' => 'value'
    ], 'default');
}

public function testItCreatesEmptyComponent(): void
{
    $prototype = new TestEmpty();
    $this->cement->add(TestEmpty::class, $prototype, 'default');

    $result = $this->cement->build(TestEmpty::class, [], 'default');

    $this->assertInstanceOf(TestEmpty::class, $result);
    $this->assertSame($prototype, $result);
}

public function testItBuildsComponentWithDefaults(): void
{
    $prototype = new TestWithDefaults();
    $this->cement->add(TestWithDefaults::class, $prototype, 'default');

    $result = $this->cement->build(TestWithDefaults::class, [
        'name' => 'custom'
    ], 'default');

    $this->assertInstanceOf(TestWithDefaults::class, $result);
    $this->assertSame('custom', $result->name);
    $this->assertSame(10, $result->count); // Дефолтное значение
    $this->assertTrue($result->active);    // Дефолтное значение
}

public function testItWorksWithNestedComponents(): void
{
    // Регистрируем варианты кнопок
    $primaryButton = new TestButton('Submit', 'primary');
    $dangerButton = new TestButton('Delete', 'danger');

    $this->cement->add(TestButton::class, $primaryButton, 'primary');
    $this->cement->add(TestButton::class, $dangerButton, 'danger');

    // Создаём карточку с кнопкой из Cement
    $cardPrototype = new TestCard(
        'Default Card',
        $this->cement->build(TestButton::class, [], 'primary'),
        'default'
    );

    $this->cement->add(TestCard::class, $cardPrototype, 'default');

    // Создаём кастомную карточку с переопределённой кнопкой
    $customCard = $this->cement->build(TestCard::class, [
        'title' => 'Custom Card',
        'button' => $this->cement->build(TestButton::class, [
            'text' => 'Custom Action',
            'variant' => 'success'
        ], 'primary'), // Используем primary как основу
    ], 'default');

    $this->assertInstanceOf(TestCard::class, $customCard);
    $this->assertSame('Custom Card', $customCard->title);
    $this->assertSame('Custom Action', $customCard->button->text);
    $this->assertSame('success', $customCard->button->variant);
}

public function testItClearsAllPrototypes(): void
{
    $this->cement->add(TestButton::class, new TestButton('A'), 'variant1');
    $this->cement->add(TestButton::class, new TestButton('B'), 'variant2');

    $this->assertTrue($this->cement->has(TestButton::class, 'variant1'));
    $this->assertTrue($this->cement->has(TestButton::class, 'variant2'));

    $this->cement->clear();

    $this->assertFalse($this->cement->has(TestButton::class, 'variant1'));
    $this->assertFalse($this->cement->has(TestButton::class, 'variant2'));
    $this->assertSame([], $this->cement->variants(TestButton::class));
}

public function testErrorMessageShowsAvailableVariants(): void
{
    $this->cement->add(TestButton::class, new TestButton('A'), 'primary');
    $this->cement->add(TestButton::class, new TestButton('B'), 'secondary');

    try {
        $this->cement->build(TestButton::class, [], 'nonexistent');
        $this->fail('Should have thrown exception');
    } catch (InvalidArgumentException $e) {
        $this->assertStringContainsString("Variant 'nonexistent' not found for", $e->getMessage());
        $this->assertStringContainsString('Available: primary, secondary', $e->getMessage());
    }
}

public function testErrorMessageShowsNoneWhenNoVariants(): void
{
    try {
        $this->cement->build(TestButton::class, [], 'nonexistent');
        $this->fail('Should have thrown exception');
    } catch (InvalidArgumentException $e) {
        $this->assertStringContainsString('Available: none', $e->getMessage());
    }
}

public function testItHandlesPartialOverridesCorrectly(): void
{
    $prototype = new TestButton('Original', 'primary');
    $this->cement->add(TestButton::class, $prototype, 'default');

    // Переопределяем только text, variant остаётся из прототипа
    $result = $this->cement->build(TestButton::class, [
        'text' => 'Modified'
    ], 'default');

    $this->assertSame('Modified', $result->text);
    $this->assertSame('primary', $result->variant);
}

public function testItPreservesObjectIdentityForEmptyOverrides(): void
{
    $prototype = new TestButton('Test', 'variant');
    $this->cement->add(TestButton::class, $prototype, 'default');

    $result1 = $this->cement->build(TestButton::class, [], 'default');
    $result2 = $this->cement->build(TestButton::class, [], 'default');

    $this->assertSame($prototype, $result1);
    $this->assertSame($prototype, $result2);
    $this->assertSame($result1, $result2);
}
}