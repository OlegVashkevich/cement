# Cement - Фабрика вариантов для Brick компонентов

Фабрика для создания вариантов компонентов Brick. Позволяет регистрировать прототипы компонентов с разными вариантами и создавать их экземпляры с переопределениями.

## Особенности

- **Для Brick компонентов** - работает только с классами, расширяющими `Brick`
- **Прототипный подход** - регистрируете базовые варианты, создаёте с кастомными параметрами
- **Строгая типизация** - PHP 8.2+ с проверками типов
- **Простота** - минималистичный API без лишней сложности

## Установка

```bash
composer require olegv/cement
```

> Cement является частью пакета Brick и устанавливается вместе с ним

## Быстрый старт

```php
use OlegV\Cement;
use OlegV\Components\Button;
use OlegV\Components\Card;

// Создаём фабрику
$cement = new Cement();

// Регистрируем варианты кнопок
$cement->add(Button::class, new Button('Submit', 'primary'), 'submit');
$cement->add(Button::class, new Button('Cancel', 'secondary'), 'cancel');
$cement->add(Button::class, new Button('Delete', 'danger'), 'delete');

// Создаём кнопку на основе варианта
$submitButton = $cement->build(Button::class, [], 'submit');
echo $submitButton; // Button с текстом "Submit", вариантом "primary"

// Создаём с переопределениями
$customButton = $cement->build(Button::class, [
    'text' => 'Custom Text',
    'variant' => 'warning'
], 'submit');
echo $customButton; // Button с текстом "Custom Text", вариантом "warning"
```

## Работа с вложенными компонентами

```php
use OlegV\Cement;
use OlegV\Components\Button;
use OlegV\Components\Card;

$cement = new Cement();

// Регистрируем варианты
$cement->add(Button::class, new Button('Default Action', 'primary'), 'default');
$cement->add(Card::class, new Card('Default Title', new Button('Action')), 'default');

// Создаём сложную структуру
$customCard = $cement->build(Card::class, [
    'title' => 'Custom Card',
    'button' => $cement->build(Button::class, [
        'text' => 'Custom Action',
        'variant' => 'success'
    ], 'default') // Используем вариант 'default' как основу
], 'default');

echo $customCard;
```

## API

### `Cement::add()`
Регистрирует прототип варианта компонента

```php
$cement->add(string $className, Brick $prototype, string $variant = 'default'): void
```

**Пример:**
```php
// Регистрация варианта кнопки
$cement->add(Button::class, new Button('Submit', 'primary'), 'submit');

// Регистрация варианта карточки
$cardPrototype = new Card('Title', new Button('Action'));
$cement->add(Card::class, $cardPrototype, 'compact');
```

### `Cement::build()`
Создаёт компонент на основе зарегистрированного прототипа

```php
$cement->build(string $className, array $overrides = [], string $variant = 'default'): Brick
```

**Пример:**
```php
// Без переопределений
$button = $cement->build(Button::class, [], 'submit');

// С переопределениями
$customButton = $cement->build(Button::class, [
    'text' => 'Custom',
    'variant' => 'warning'
], 'submit');

// С вложенными компонентами
$card = $cement->build(Card::class, [
    'title' => 'New Title',
    'button' => $cement->build(Button::class, ['text' => 'Nested'], 'submit')
], 'compact');
```

### `Cement::has()`
Проверяет существование варианта

```php
$cement->has(string $className, string $variant = 'default'): bool
```

**Пример:**
```php
if ($cement->has(Button::class, 'primary')) {
    // Вариант существует
}
```

### `Cement::variants()`
Возвращает список зарегистрированных вариантов для класса

```php
$cement->variants(string $className): array
```

**Пример:**
```php
$variants = $cement->variants(Button::class);
// ['submit', 'cancel', 'delete']
```

### `Cement::getPrototype()`
Возвращает зарегистрированный прототип для проверки или документации

```php
$cement->getPrototype(string $className, string $variant = 'default'): ?Brick
```

**Пример:**
```php
$prototype = $cement->getPrototype(Button::class, 'submit');
// Button('Submit', 'primary')
```

### `Cement::clear()`
Очищает все зарегистрированные прототипы

```php
$cement->clear(): void
```

**Пример:**
```php
$cement->clear(); // Удаляет все варианты всех компонентов
```

## Полный пример

```php
use OlegV\Cement;
use OlegV\Components\Button;
use OlegV\Components\Card;
use OlegV\Components\Modal;

$cement = new Cement();

// 1. Регистрируем варианты компонентов
$cement->add(Button::class, new Button('Submit', 'primary'), 'primary');
$cement->add(Button::class, new Button('Cancel', 'secondary'), 'secondary');
$cement->add(Button::class, new Button('Delete', 'danger'), 'danger');

$cement->add(Card::class, new Card(
    title: 'Product Card',
    description: 'Default description',
    price: 99.99,
    button: new Button('Add to Cart', 'primary')
), 'product');

$cement->add(Modal::class, new Modal(
    title: 'Confirm Action',
    content: 'Are you sure?',
    buttons: [
        new Button('Yes', 'primary'),
        new Button('No', 'secondary')
    ]
), 'confirm');

// 2. Проверяем доступные варианты
echo "Available button variants: ";
print_r($cement->variants(Button::class)); // ['primary', 'secondary', 'danger']

// 3. Создаём компоненты
$primaryButton = $cement->build(Button::class, [], 'primary');
$customButton = $cement->build(Button::class, ['text' => 'Custom'], 'primary');

$productCard = $cement->build(Card::class, [
    'title' => 'iPhone 15',
    'price' => 120000,
    'button' => $cement->build(Button::class, [
        'text' => 'Buy Now',
        'variant' => 'success'
    ], 'primary')
], 'product');

$confirmModal = $cement->build(Modal::class, [
    'title' => 'Delete Product',
    'content' => 'This action cannot be undone',
    'buttons' => [
        $cement->build(Button::class, ['text' => 'Delete', 'variant' => 'danger'], 'danger'),
        $cement->build(Button::class, ['text' => 'Cancel'], 'secondary')
    ]
], 'confirm');

// 4. Рендеринг
echo $productCard;
echo $confirmModal;

// 5. Очистка (например, для тестов)
$cement->clear();
```

## Обработка ошибок

Cement выбрасывает информативные исключения при ошибках:

```php
try {
    // Попытка создать несуществующий вариант
    $cement->build(Button::class, [], 'nonexistent');
} catch (InvalidArgumentException $e) {
    // Сообщение: "Variant 'nonexistent' not found for OlegV\Components\Button. Available: primary, secondary"
    echo $e->getMessage();
}

try {
    // Попытка переопределить несуществующее свойство
    $cement->build(Button::class, ['nonexistent' => 'value'], 'primary');
} catch (InvalidArgumentException $e) {
    // Сообщение: "Property 'nonexistent' does not exist in OlegV\Components\Button"
    echo $e->getMessage();
}
```

## Принципы работы

1. **Иммутабельность** - прототипы и созданные компоненты являются readonly
2. **Безопасность** - при отсутствии переопределений возвращается оригинальный прототип
3. **Типобезопасность** - строгие проверки типов на всех этапах
4. **Простота** - минимальный API, понятная логика работы

## Интеграция с Brick

Cement идеально дополняет Brick-компоненты:
- Создавайте библиотеки стандартных компонентов (primary button, danger button и т.д.)
- Реализуйте тему/стиль через варианты компонентов
- Упростите создание сложных компонентных структур
- Используйте в сочетании с другими фичами Brick (кэширование, наследование)
