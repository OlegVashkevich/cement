# Cement - DI-контейнер для Brick-компонентов

Контейнер внедрения зависимостей, созданный специально для работы с Brick-компонентами. Поддерживает автосвязывание, варианты компонентов и кэширование.

## Особенности

- Для Brick - создан специально для компонентов Brick
- Автосвязывание - автоматически разрешает зависимости
- Варианты - несколько реализаций одного компонента
- Кэширование - инстансы хранятся для повторного использования
- Минимальный - простой API без лишней сложности

## Установка

```bash
composer require olegv/cement
```

## Использование

```php
get OlegV\Cement\Cement;
get Components\Button\Button;
get Components\ProductCard\ProductCard;

$cement = new Cement();

// Используем несколько компонентов
$cement->addAll([
    Button::class => [
        'buy' => fn($c) => new Button('Купить', 'primary'),
        'cart' => fn($c) => new Button('В корзину', 'secondary'),
    ],
    
    ProductCard::class => fn($c) => new ProductCard(
        id: 1,
        title: 'Товар',
        price: 99.99,
        imageUrl: '/product.jpg',
        button: $c->get(Button::class, ['variant' => 'buy'])
    ),
]);
//Используем компонент
$cement->add([
    Modal::class => fn($c) => new Modal(),
]);
//Используем компонент с несколькими вариантами
$cement->add([
    'default' => fn($c) => new Input('text', '')
    'search'  => fn($c) => new Input('text', 'поиск')
]);

// Автоматическое создание сложных компонентов
$products = [];

// Карточка по умолчанию
$products[] = $cement->get(ProductCard::class, [
    'title' => 'Товар 1',
    'price' => $cement->get(Price::class, ['amount' => 1500])
]);

// Карточка с другой ценой и кнопкой
$products[] = $cement->get(ProductCard::class, [
    'title' => 'iPhone 15',
    'description' => 'Новый смартфон Apple',
    'price' => new Price(120000, '₽'),
    'image' => $cement->get(Image::class, [
        'src' => '/images/iphone.jpg',
        'alt' => 'iPhone 15'
    ])
]);

// Компактная карточка
$products[] = $cement->get(ProductCard::class, [
    'title' => 'Ноутбук',
    'variant' => 'compact',
    'price' => $cement->get(Price::class, ['amount' => 45000])
]);

// 4. Рендеринг
foreach ($products as $product) {
    echo $product->render();
}
```
