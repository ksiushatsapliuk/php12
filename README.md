# Практична робота 12 — варіант 18

## Мета
Додати до застосунку елемент інтерактивності через CSS-анімацію чи Canvas API

## Що реалізовано
Варіант 18 у PDF — **дошка сусідських оголошень**. 

## Запуск

```powershell
& C:/php/php.exe -d extension_dir=C:/php/ext -d extension=pdo_mysql tools/install.php
& C:/php/php.exe -d extension_dir=C:/php/ext -d extension=pdo_mysql -d extension=mbstring -S localhost:8000
```

## Структура
- index.php, edit.php, delete.php, classes/Plant.php, classes/Succulent.php, classes/Garden.php — каталог рослин.
- announcements.php — форма та стрічка оголошень.
- classes/AnnouncementBoard.php — PDO-запити та валідація оголошень.
- api.php і lib/announcements_api.php — маршрути нового ресурсу.
- assets/css/announcements.css і assets/js/announcements.js — анімація та відображення масиву announcements. Початкові CSS/JS каталогу не змінено.
- sql/schema.sql — рослини; sql/02_announcements.sql — оголошення; решта SQL і міграцій успадковані з 08.
- tools/ — встановлення, перевірки API та збережений benchmark 07.
- benchmarks/ — потрібні контрольні алгоритми benchmark; не зайві сторінки застосунку.
- results/verification.json — фактичні перевірки роботи 12. results/php08/ — незмінені історичні результати роботи 08, не нові вимірювання 12.
- docs/ — PDF завдання і заповнений звіт Word.