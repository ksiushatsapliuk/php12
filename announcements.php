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

    <section class="form-card">
        <h2 class="form-title">Додати оголошення</h2>
        <form id="announcementForm" action="api.php?resource=announcements" method="post">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-group">
                <label for="title">Заголовок <span class="req">*</span></label>
                <input class="form-control" id="title" name="title" maxlength="160" required placeholder="Наприклад, обмін саджанцями у дворі">
            </div>
            <div class="form-group">
                <label for="announcementBody">Текст оголошення <span class="req">*</span></label>
                <textarea class="form-control" id="announcementBody" name="body" rows="3" maxlength="2000" required placeholder="Що ви пропонуєте сусідам?"></textarea>
            </div>
            <div class="form-actions">
                <button class="btn-primary" type="submit">Опублікувати оголошення</button>
            </div>
        </form>
    </section>

    <section aria-labelledby="feedTitle">
        <div class="sort-bar board-feed-heading">
            <h2 id="feedTitle">Оголошення сусідів</h2>
            <button id="refreshAnnouncements" class="btn-secondary" type="button">Оновити стрічку</button>
        </div>
        <p id="boardMessage" class="board-message" role="status" aria-live="polite">Завантаження оголошень…</p>
        <div id="announcementFeed" class="announcement-feed" aria-busy="true"></div>
    </section>
</main>
<script src="assets/js/announcements.js"></script>
</body>
</html>
