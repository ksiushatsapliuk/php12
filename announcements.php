<?php require_once __DIR__ . '/lib/bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Дошка сусідських оголошень — FloraCare</title>
    <link rel="stylesheet" href="assets/css/style.css?v=3">
    <link rel="stylesheet" href="assets/css/announcements.css">
</head>
<body>
<main class="app-container">
    <header class="header">
        <div class="header-icon">
            <img src="assets/img/alocasia.png" alt="FloraCare" class="header-icon-img">
        </div>
        <div>
            <h1>FloraCare</h1>
            <p class="subtitle">Дошка сусідських оголошень</p>
        </div>
    </header>

    <nav class="board-nav" aria-label="Розділи застосунку">
        <a class="btn-secondary" href="index.php">Каталог рослин</a>
        <span aria-current="page">Оголошення</span>
    </nav>

    <?php require __DIR__ . '/lib/announcement_board_view.php'; ?>
</main>
<script src="assets/js/announcements.js"></script>
</body>
</html>
