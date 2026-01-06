<?php

namespace OlegV;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;

/**
 * Cement - Фабрика вариантов для Brick компонентов
 */
class Cement
{
    /**
     * @var array<class-string<Brick>, array<string, Brick>>
     */
    private array $prototypes = [];

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
     * @throws ReflectionException
     */
    public function build(string $className, array $overrides = [], string $variant = 'default'): Brick
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