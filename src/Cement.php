<?php
/**
 * Cement - DI-контейнер для PHP-компонентов
 * Легковесный контейнер внедрения зависимостей с поддержкой вариантов и автосвязывания.
 * Идеально подходит для компонентных систем вроде Brick.
 *
 * @package OlegV\Cement
 * @version 1.0.0
 * @license MIT
 */

declare(strict_types=1);

namespace OlegV;

use Closure;
use Exception;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

/**
 * Cement - DI-контейнер для Brick-компонентов
 *
 * @example
 * $cement = new Cement();
 * $cement->mix(Button::class, [
 *     'default' => fn($c) => new Button('Кнопка', 'primary'),
 *     'search'  => fn($c) => new Button('Поиск', 'outline'),
 * ]);
 *
 * $button = $cement->get(Button::class, ['variant' => 'search']);
 */
class Cement
{
    private array $definitions = [];
    private array $instances = [];

    /**
     * Используем компонент с вариантами
     * @param  string  $className  Класс компонента
     * @param  array|Closure  $factory  Массив вариантов или фабричная функция
     * */
    public function add(string $className, array|Closure $factory): void
    {
        // Если передана просто фабрика - оборачиваем в массив с ключом 'default'
        if ($factory instanceof Closure) {
            $this->definitions[$className] = ['default' => $factory];
        } else {
            $this->definitions[$className] = $factory;
        }
    }

    /**
     * Используем несколько компонентов сразу
     */
    public function addAll(array $config): void
    {
        foreach ($config as $className => $variants) {
            $this->add($className, $variants);
        }
    }

    /**
     * Получить компонент
     *
     * @param string $className Класс компонента
     * @param array $params Параметры для конструктора или фабрики
     *                      Можно указать 'variant' для выбора варианта
     * @return object Готовый компонент
     */
    public function get(string $className, array $params = []): object
    {
        // Определяем вариант (если не указан - 'default')
        $variant = $params['variant'] ?? 'default';
        unset($params['variant']);

        // Ключ для кэша
        $key = $className . ':' . $variant;
        if ($params) {
            $key .= ':' . md5(serialize($params));
        }

        // Возвращаем из кэша если есть
        if (isset($this->instances[$key])) {
            return $this->instances[$key];
        }

        // Создаём компонент
        $instance = $this->create($className, $variant, $params);

        // Сохраняем в кэш
        $this->instances[$key] = $instance;

        return $instance;
    }

    /**
     * Создаёт новый экземпляр (игнорирует кэш)
     */
    public function make(string $className, array $params = []): object
    {
        $variant = $params['variant'] ?? 'default';
        unset($params['variant']);

        return $this->create($className, $variant, $params);
    }

    /**
     * Проверяет, зарегистрирован ли компонент
     */
    public function has(string $className, string $variant = 'default'): bool
    {
        return isset($this->definitions[$className][$variant]);
    }

    /**
     * Очищает кэш (полезно для тестов)
     */
    public function clear(): void
    {
        $this->instances = [];
    }

    /**
     * Основная логика создания компонента
     */
    private function create(string $className, string $variant, array $params): object
    {
        // Если есть определение - используем его
        if (isset($this->definitions[$className][$variant])) {
            $factory = $this->definitions[$className][$variant];

            if ($factory instanceof Closure) {
                return $factory($this, $params);
            }

            if (is_object($factory)) {
                return $factory;
            }

            if (is_array($factory)) {
                return new $className(...$factory);
            }

            throw new RuntimeException("Некорректная фабрика для $className:$variant");
        }

        // Если нет определения - создаём автоматически
        return $this->autowire($className, $params);
    }

    /**
     * Автоматическое создание компонента через рефлексию
     */
    private function autowire(string $className, array $params): object
    {
        if (!class_exists($className)) {
            throw new RuntimeException("Класс $className не найден");
        }

        $reflection = new ReflectionClass($className);

        // Если нет конструктора
        if (!$reflection->getConstructor()) {
            return new $className();
        }

        // Собираем аргументы для конструктора
        $args = $this->resolveConstructorArgs($reflection, $params);

        return new $className(...$args);
    }

    /**
     * Разрешает аргументы конструктора
     */
    private function resolveConstructorArgs(ReflectionClass $reflection, array $params): array
    {
        $constructor = $reflection->getConstructor();
        $args = [];

        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();

            // Если параметр передан явно
            if (array_key_exists($name, $params)) {
                $args[] = $params[$name];
                continue;
            }

            // Если параметр - это другой Brick компонент
            $type = $param->getType();
            if (!$type->isBuiltin() && $type instanceof ReflectionNamedType) {
                $typeName = $type->getName();

                // Пробуем создать через контейнер
                try {
                    $args[] = $this->get($typeName);
                    continue;
                } catch (Exception) {
                    // Не получилось - продолжаем
                }
            }

            // Значение по умолчанию
            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            // Для публичных свойств Brick - null
            $args[] = null;
        }

        return $args;
    }
}
