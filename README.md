# Cement - Фабрика вариантов для Brick компонентов

Фабрика для создания вариантов компонентов Brick. Регистрируйте прототипы, создавайте экземпляры с переопределениями.

## Установка

```bash
composer require olegv/cement
```

## Быстрый старт

```php
use OlegV\Cement;
use OlegV\Components\Button;
use OlegV\Components\Card;

$cement = new Cement();

// Регистрация вариантов
$cement->add(Button::class, new Button('Submit', 'primary'), 'submit');
$cement->add(Button::class, new Button('Cancel', 'secondary'), 'cancel');

// Создание
$button = $cement->build(Button::class, [], 'submit'); // Button('Submit', 'primary')
$custom = $cement->build(Button::class, ['text' => 'Save'], 'submit'); // Button('Save', 'primary')
```

## API

### Конструктор

```php
new Cement(
    string $errorMode = Cement::ERROR_FALLBACK,
    ?bool $isProduction = null
)
```

**Режимы ошибок:**
- `Cement::ERROR_STRICT` - бросает исключения (development)
- `Cement::ERROR_SILENT` - возвращает `null` (production)
- `Cement::ERROR_FALLBACK` - возвращает заглушку (по умолчанию)

**Окружение:**
- Автоопределение из `APP_ENV`
- CLI всегда production
- По умолчанию - production

```php
// Development с заглушками
$cement = new Cement(Cement::ERROR_FALLBACK, false);

// Production с тихими ошибками  
$cement = new Cement(Cement::ERROR_SILENT, true);

// Автоопределение
$cement = new Cement(); // ERROR_FALLBACK + auto-detect
```

### Основные методы

```php
// Регистрация
$cement->add(string $className, Brick $prototype, string $variant = 'default');

// Создание с переопределениями  
$cement->build(string $className, array $overrides = [], string $variant = 'default'): ?Brick;

// Проверка
$cement->has(string $className, string $variant = 'default'): bool;
$cement->variants(string $className): array;
$cement->getPrototype(string $className, string $variant = 'default'): ?Brick;

// Очистка
$cement->clear(): void;
```

## Примеры

### Вложенные компоненты

```php
$cement->add(Button::class, new Button('Action', 'primary'), 'default');
$cement->add(Card::class, new Card('Title', new Button('Action')), 'default');

$card = $cement->build(Card::class, [
    'title' => 'Custom',
    'button' => $cement->build(Button::class, ['text' => 'Nested'], 'default')
], 'default');
//или
$card2 = $cement->build(Card::class, [
    'title' => 'Custom',
    'button' => new Button('Nested', 'primary')
], 'default');
```

### Полный сценарий

```php
$cement = new Cement();

// Регистрация
$cement->add(Button::class, new Button('Submit', 'primary'), 'primary');
$cement->add(Button::class, new Button('Cancel', 'secondary'), 'secondary');

$cement->add(Card::class, new Card(
    title: 'Product',
    button: new Button('Buy', 'primary')
), 'product');

// Использование
$button = $cement->build(Button::class, [], 'primary');
$custom = $cement->build(Button::class, ['text' => 'Save'], 'primary');

$card = $cement->build(Card::class, [
    'title' => 'iPhone',
    'button' => $cement->build(Button::class, ['text' => 'Buy Now'], 'primary')
], 'product');

// Очистка
$cement->clear();
```

## Принципы

- **Иммутабельность** - прототипы readonly, возвращаются как есть без переопределений
- **Типобезопасность** - строгие проверки PHP 8.2+
- **Безопасность по умолчанию** - production-режим, заглушки при ошибках
- **KISS** - минимальный API, одна ответственность

---

**Cement** дополняет Brick, позволяя создавать библиотеки стандартных компонентов и упрощать работу со сложными структурами.