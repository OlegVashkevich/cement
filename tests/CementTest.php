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

final class CementTest extends TestCase
{
    private Cement $cement;

    protected function setUp(): void
    {
        // По умолчанию используем ERROR_STRICT для тестов, чтобы ловить исключения
        $this->cement = new Cement(Cement::ERROR_STRICT, false);
    }

    public function testItCanBeInstantiated(): void
    {
        $this->assertInstanceOf(Cement::class, $this->cement);
    }

    public function testItThrowsExceptionOnInvalidErrorMode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid error mode');

        new Cement('invalid_mode');
    }

    public function testConstructorAcceptsValidErrorModes(): void
    {
        $this->assertInstanceOf(Cement::class, new Cement(Cement::ERROR_STRICT));
        $this->assertInstanceOf(Cement::class, new Cement(Cement::ERROR_SILENT));
        $this->assertInstanceOf(Cement::class, new Cement(Cement::ERROR_FALLBACK));
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

    public function testItThrowsExceptionWhenAddingNonBrickClassName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class stdClass must extend OlegV\Brick');

        $validBrick = new TestButton('test');
        $this->cement->add(\stdClass::class, $validBrick);
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

    public function testErrorModeStrictThrowsException(): void
    {
        $strictCement = new Cement(Cement::ERROR_STRICT, false);
        $strictCement->add(TestButton::class, new TestButton('Test'), 'default');

        $this->expectException(InvalidArgumentException::class);
        $strictCement->build(TestButton::class, [], 'nonexistent');
    }

    public function testErrorModeSilentReturnsNull(): void
    {
        $silentCement = new Cement(Cement::ERROR_SILENT, false);
        $silentCement->add(TestButton::class, new TestButton('Test'), 'default');

        $result = $silentCement->build(TestButton::class, [], 'nonexistent');
        $this->assertNull($result);
    }

    public function testErrorModeFallbackReturnsFallbackComponent(): void
    {
        $fallbackCement = new Cement(Cement::ERROR_FALLBACK, false);
        $fallbackCement->add(TestButton::class, new TestButton('Test'), 'default');

        $result = $fallbackCement->build(TestButton::class, [], 'nonexistent');
        $this->assertNotNull($result);
        $this->assertStringContainsString('Brick Error', (string)$result);
    }

    public function testFallbackComponentIsProductionSafe(): void
    {
        $productionCement = new Cement(Cement::ERROR_FALLBACK, true);
        $productionCement->add(TestButton::class, new TestButton('Test'), 'default');

        $result = $productionCement->build(TestButton::class, [], 'nonexistent');
        $this->assertNotNull($result);
        $this->assertStringContainsString('<!--', (string)$result);
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

    public function testProductionModeAffectsFallbackOutput(): void
    {
        // Сохраняем текущее окружение
        $originalEnv = $_ENV['APP_ENV'] ?? null;

        try {
            // Тест 1: В production режиме fallback должен быть комментарием
            $_ENV['APP_ENV'] = 'production';
            $productionCement = new Cement(Cement::ERROR_FALLBACK);

            $productionCement->add(TestButton::class, new TestButton('Test'), 'default');
            $result = $productionCement->build(TestButton::class, [], 'nonexistent');

            $this->assertNotNull($result, 'Fallback should return a component in production');
            $this->assertStringContainsString('<!--', (string)$result,
                'Production fallback should be an HTML comment');
            $this->assertStringNotContainsString('Brick Error', (string)$result,
                'Production should not show error details');

            // Тест 2: В development режиме fallback должен показывать ошибку
            $_ENV['APP_ENV'] = 'development';
            $developmentCement = new Cement(Cement::ERROR_FALLBACK);

            $developmentCement->add(TestButton::class, new TestButton('Test'), 'default');
            $result = $developmentCement->build(TestButton::class, [], 'nonexistent');

            $this->assertNotNull($result, 'Fallback should return a component in development');
            $this->assertStringContainsString('Brick Error', (string)$result,
                'Development fallback should show error message');
            $this->assertStringContainsString('div', (string)$result,
                'Development fallback should be an HTML element');

            // Тест 3: Явное указание production режима
            $explicitProductionCement = new Cement(Cement::ERROR_FALLBACK, true);
            $explicitProductionCement->add(TestButton::class, new TestButton('Test'), 'default');
            $result = $explicitProductionCement->build(TestButton::class, [], 'nonexistent');

            $this->assertStringContainsString('<!--', (string)$result,
                'Explicit production mode should produce HTML comment');

            // Тест 4: Явное указание development режима
            $explicitDevCement = new Cement(Cement::ERROR_FALLBACK, false);
            $explicitDevCement->add(TestButton::class, new TestButton('Test'), 'default');
            $result = $explicitDevCement->build(TestButton::class, [], 'nonexistent');

            $this->assertStringContainsString('Brick Error', (string)$result,
                'Explicit development mode should show error details');

        } finally {
            // Восстанавливаем окружение
            if ($originalEnv !== null) {
                $_ENV['APP_ENV'] = $originalEnv;
            } else {
                unset($_ENV['APP_ENV']);
            }
        }
    }

    public function testCliModeIsTreatedAsProduction(): void
    {
        // Не можем изменить PHP_SAPI во время выполнения,
        // но можем протестировать логику через явное указание

        // Тест через явное указание production (как бы в CLI)
        $cement = new Cement(Cement::ERROR_FALLBACK, true);
        $cement->add(TestButton::class, new TestButton('Test'), 'default');

        $result = $cement->build(TestButton::class, [], 'nonexistent');

        // В "CLI/production" режиме должен быть комментарий
        $this->assertStringContainsString('<!--', (string)$result,
            'CLI/production mode should produce HTML comment');
    }
}