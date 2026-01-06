<?php

namespace OlegV;

use InvalidArgumentException;
use OlegV\ErrorBrick\ErrorBrick;
use ReflectionClass;
use ReflectionException;
use Throwable;

/**
 *  Cement - Фабрика вариантов для Brick компонентов
 *
 *  Позволяет регистрировать прототипы компонентов с разными вариантами
 *  и создавать экземпляры с частичными переопределениями свойств.
 */
class Cement
{
    /**
     * @var array<class-string<Brick>, array<string, Brick>>
     */
    private array $prototypes = [];
    private bool $isProduction;
    /**
     * Режим обработки ошибок: выбрасывать исключения
     *
     * Используется в development для немедленного обнаружения ошибок.
     * При ошибке выбрасывается InvalidArgumentException.
     */
    public const ERROR_STRICT = 'strict';
    /**
     * Режим обработки ошибок: возвращать null
     *
     * Используется в production для предотвращения падения интерфейса.
     * Ошибки логируются, но интерфейс продолжает работу.
     */
    public const ERROR_FALLBACK = 'fallback';

    /**
     * Текущий режим обработки ошибок
     *
     * @var string Одна из констант ERROR_*
     */
    private string $errorMode;

    /**
     * Создаёт фабрику вариантов компонентов
     *
     * @param  string  $errorMode     Режим обработки ошибок. Допустимые значения:
     *                                - Cement::ERROR_STRICT   - выбрасывать исключения
     *                                - Cement::ERROR_FALLBACK - возвращать заглушку (по умолчанию)
     * @param  bool|null  $isProduction Принудительно указать production-режим.
     *                                Если null, определяется автоматически по APP_ENV.
     *                                В production режиме заглушки показывают только комментарии.
     *
     * @throws InvalidArgumentException Если передан недопустимый режим ошибок
     *
     * @example new Cement() // Безопасный режим с автодетектом окружения
     * @example new Cement(Cement::ERROR_STRICT, false) // Development с исключениями
     */
    public function __construct(string $errorMode = self::ERROR_FALLBACK,?bool $isProduction = null)
    {
        if (!in_array($errorMode, [self::ERROR_STRICT, self::ERROR_FALLBACK], true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid error mode: %s. Use Cement::ERROR_* constants', $errorMode)
            );
        }

        $this->errorMode = $errorMode;
        // Определяем production-режим: либо явно передан, либо автоопределение
        $this->isProduction = $isProduction ?? $this->detectProduction();
    }

    private function detectProduction(): bool
    {
        // 1. Явно указано в окружении
        if (isset($_ENV['APP_ENV'])) {
            return $_ENV['APP_ENV'] === 'production';
        }

        // 2. CLI всегда как production для безопасности
        if (PHP_SAPI === 'cli') {
            return true;
        }

        // 3. По умолчанию предполагаем production для безопасности
        return true;
    }

    /**
     * Регистрирует шаблон варианта
     * @param  string  $className
     * @param  Brick  $prototype
     * @param  string  $variant
     */
    public function add(string $className, Brick $prototype, string $variant = 'default'): void
    {
        if (!is_subclass_of($className, Brick::class)) {
            throw new InvalidArgumentException(
                sprintf('Class %s must extend %s', $className, Brick::class)
            );
        }

        if (!$prototype instanceof $className) {
            throw new InvalidArgumentException(
                sprintf('Prototype must be instance of %s, got %s',
                    $className,
                    get_class($prototype)
                )
            );
        }

        $this->prototypes[$className][$variant] = $prototype;
    }

    /**
     * Создаёт компонент на основе шаблона
     * @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection
     * @param  string  $className
     * @param  array<mixed, mixed>  $overrides
     * @param  string  $variant
     * @return Brick
     * @throws Throwable
     */
    public function build(string $className, array $overrides = [], string $variant = 'default'): Brick
    {
        try {
            return $this->doBuild($className, $overrides, $variant);
        } catch (Throwable $e) {
            return $this->handleError($e, $className, $variant);
        }
    }

    /**
     * Создаёт компонент на основе шаблона
     * @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection
     * @param  string  $className
     * @param  array<mixed, mixed>  $overrides
     * @param  string  $variant
     * @return Brick
     * @throws ReflectionException
     */
    private function doBuild(string $className, array $overrides, string $variant): Brick
    {
        if (!isset($this->prototypes[$className][$variant])) {
            $available = array_keys($this->prototypes[$className] ?? []);
            throw new InvalidArgumentException(
                sprintf("Variant '%s' not found for %s. Available: %s",
                    $variant,
                    $className,
                    (count($available)>0) ? implode(', ', $available) : 'none'
                )
            );
        }

        $prototype = $this->prototypes[$className][$variant];

        // Если нет переопределений - возвращаем прототип как есть (readonly безопасно)
        if ($overrides===[]) {
            return $prototype;
        }

        return $this->createFromPrototype($prototype, $overrides);
    }

    /**
     * @throws Throwable
     */
    private function handleError(Throwable $e, string $className, string $variant): ErrorBrick
    {
        if ($this->errorMode === self::ERROR_STRICT) {
            throw $e;
        }
        // Тихое логирование
        if (!$this->isProduction) {
            error_log(sprintf(
                '[Cement] Error building %s:%s - %s',
                $className,
                $variant,
                $e->getMessage()
            ));
        }
        return new ErrorBrick(
            message: $e->getMessage(),
            originalClass: $className,
            context: "variant: $variant",
            isProduction: $this->isProduction
        );
    }

    /**
     * Создаёт компонент
     * @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection
     * @param  string  $className
     * @param  array<mixed, mixed>  $properties
     * @return Brick
     * @throws ReflectionException
     */
    private function make(string $className, array $properties): Brick
    {
        if (!is_subclass_of($className, Brick::class)) {
            throw new InvalidArgumentException(
                sprintf('Class %s must extend %s', $className, Brick::class)
            );
        }

        $reflection = new ReflectionClass($className);
        $constructor = $reflection->getConstructor();

        if ($constructor===null) {
            return new $className();
        }

        // Собираем параметры в правильном порядке
        $params = [];
        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();

            if (!array_key_exists($name, $properties)) {
                if (!$param->isOptional()) {
                    throw new InvalidArgumentException(
                        sprintf("Missing required parameter '%s' for %s", $name, $className)
                    );
                }
                $params[] = $param->getDefaultValue();
            } else {
                $params[] = $properties[$name];
            }
        }

        return new $className(...$params);
    }

    /**
     * Проверяет существование варианта
     * @param  string  $className
     * @param  string  $variant
     * @return bool
     */
    public function has(string $className, string $variant = 'default'): bool
    {
        return isset($this->prototypes[$className][$variant]);
    }

    /**
     * Возвращает список вариантов для класса
     * @param  string  $className
     * @return string[]|int[]
     */
    public function variants(string $className): array
    {
        return array_keys($this->prototypes[$className] ?? []);
    }

    /**
     * Возвращает прототип для проверки/документации
     * @param  string  $className
     * @param  string  $variant
     * @return Brick|null
     */
    public function getPrototype(string $className, string $variant = 'default'): ?Brick
    {
        return $this->prototypes[$className][$variant] ?? null;
    }

    /**
     * Очищает все зарегистрированные прототипы
     */
    public function clear(): void
    {
        $this->prototypes = [];
    }

    /**
     * Создаёт экземпляр с переопределениями из прототипа
     * @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection
     * @param  Brick  $prototype
     * @param  array<mixed, mixed>  $overrides
     * @return Brick
     * @throws ReflectionException
     */
    private function createFromPrototype(Brick $prototype, array $overrides): Brick
    {
        $className = get_class($prototype);
        $prototypeProperties = get_object_vars($prototype);

        // Проверяем, что все переопределяемые свойства существуют
        foreach (array_keys($overrides) as $key) {
            if (!property_exists($prototype, $key)) {
                throw new InvalidArgumentException(
                    sprintf("Property '%s' does not exist in %s", $key, $className)
                );
            }
        }

        // Сливаем свойства (переопределения имеют приоритет)
        $properties = array_merge($prototypeProperties, $overrides);

        // Создаём новый экземпляр
        return $this->make($className, $properties);
    }
}