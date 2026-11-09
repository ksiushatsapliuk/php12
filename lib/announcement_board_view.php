<?php
if (!isset($csrfToken)) {
    http_response_code(404);
    return;
}
?>
<section id="announcements" class="announcement-board" aria-labelledby="feedTitle">
    <div class="sort-bar board-feed-heading">
        <h2 id="feedTitle">Оголошення сусідів</h2>
        <div class="board-feed-actions">
            <button id="refreshAnnouncements" class="btn-secondary" type="button">Оновити стрічку</button>
            <button id="openAnnouncementEditor" class="btn-primary" type="button" aria-haspopup="dialog">Додати оголошення</button>
        </div>
    </div>
    <p id="boardMessage" class="board-message" role="status" aria-live="polite">Завантаження оголошень…</p>
    <div id="announcementFeed" class="announcement-feed" aria-busy="true"></div>

    <dialog id="announcementEditor" class="form-card announcement-editor" aria-labelledby="announcementEditorTitle">
        <h2 id="announcementEditorTitle" class="form-title">Додати оголошення</h2>
        <form id="announcementForm" action="api.php?resource=announcements" method="post">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-group">
                <label for="announcementTitle">Заголовок <span class="req">*</span></label>
                <input class="form-control" id="announcementTitle" name="title" autofocus maxlength="160" required placeholder="Наприклад, обмін саджанцями у дворі">
            </div>
            <div class="form-group">
                <label for="announcementBody">Текст оголошення <span class="req">*</span></label>
                <textarea class="form-control" id="announcementBody" name="body" rows="3" maxlength="2000" required placeholder="Що ви пропонуєте сусідам?"></textarea>
            </div>
            <p id="announcementEditorMessage" class="board-message is-error" role="alert" hidden></p>
            <div class="form-actions">
                <button id="cancelAnnouncementEditor" class="btn-secondary" type="button">Скасувати</button>
                <button class="btn-primary" type="submit">Опублікувати оголошення</button>
            </div>
        </form>
    </dialog>
</section>
