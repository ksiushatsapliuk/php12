document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('announcementForm');
    const feed = document.getElementById('announcementFeed');
    const message = document.getElementById('boardMessage');
    const refresh = document.getElementById('refreshAnnouncements');
    const editor = document.getElementById('announcementEditor');
    const openEditor = document.getElementById('openAnnouncementEditor');
    const cancelEditor = document.getElementById('cancelAnnouncementEditor');
    const editorMessage = document.getElementById('announcementEditorMessage');
    const publish = form.querySelector('button[type="submit"]');
    const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
    const token = form.elements.csrf.value;
    let announcements = [];
    let initialized = false;
    let busy = false;
    let publishing = false;

    function setBusy(value) {
        busy = value;
        refresh.disabled = value;
        publish.disabled = value;
        feed.setAttribute('aria-busy', String(value));
    }

    openEditor.addEventListener('click', () => {
        editorMessage.hidden = true;
        editor.showModal();
    });

    cancelEditor.addEventListener('click', () => editor.close());
    editor.addEventListener('cancel', event => {
        if (publishing) event.preventDefault();
    });

    function status(text, error = false) {
        message.textContent = text;
        message.classList.toggle('is-error', error);
    }

    async function request(action = '', values = null) {
        const options = values === null ? {} : {
            method: 'POST', body: values, headers: {'X-CSRF-Token': token}
        };
        let response;

        try {
            response = await fetch('api.php?resource=announcements' + action, options);
        } catch {
            throw new Error('Немає зв’язку із сервером. Повторіть дію.');
        }

        let result;

        try {
            result = await response.json();
        } catch {
            throw new Error('Сервер повернув некоректну відповідь.');
        }

        if (!response.ok || result.success !== true) throw new Error(result.error || 'Не вдалося виконати дію.');

        return result.data;
    }

    function card(post, isNew) {
        const article = document.createElement('article');
        article.className = 'form-card announcement-card';
        article.dataset.id = post.id;
        const title = document.createElement('h3');
        title.textContent = post.title;
        const text = document.createElement('p');
        text.textContent = post.body;
        const meta = document.createElement('div');
        meta.className = 'announcement-meta';
        const time = document.createElement('time');
        time.dateTime = post.created_at;
        time.textContent = new Date(post.created_at).toLocaleString('uk-UA');
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'announcement-remove';
        remove.textContent = 'Видалити';
        meta.append(time, remove);

        if (isNew && !reducedMotion.matches) {
            article.classList.add('announcement-enter');
            const badge = document.createElement('span');
            badge.className = 'announcement-new';
            badge.textContent = 'Нове';
            meta.prepend(badge);
            // Кінцевий стан прибирає позначку та анімаційний клас без таймерів.
            badge.addEventListener('animationend', () => badge.remove(), {once: true});
            article.addEventListener('animationend', event => {
                if (event.target === article) article.classList.remove('announcement-enter');
            });
        }

        article.append(title, text, meta);

        return article;
    }

    function render(newIds = new Set()) {
        const ids = new Set(announcements.map(post => String(post.id)));

        for (const item of [...feed.children]) {
            if (!ids.has(item.dataset.id)) item.remove();
        }

        announcements.forEach((post, index) => {
            let item = feed.querySelector('[data-id="' + post.id + '"]');

            if (!item) item = card(post, newIds.has(post.id));

            // Наявні картки не створюються заново і не повторюють анімацію.
            if (feed.children[index] !== item) feed.insertBefore(item, feed.children[index] || null);
        });
    }

    async function load() {
        if (busy) return;
        setBusy(true);

        try {
            const data = await request();

            if (!Array.isArray(data)) throw new Error('Сервер повернув некоректний список.');

            const previousIds = new Set(announcements.map(post => post.id));
            const newIds = new Set(initialized ? data.filter(post => !previousIds.has(post.id)).map(post => post.id) : []);
            announcements = data;
            initialized = true;
            render(newIds);
            status(data.length ? 'Оголошень: ' + data.length : 'Оголошень поки немає. Додайте перше!');
        } catch (error) {
            status(error.message, true);
        } finally {
            setBusy(false);
        }
    }

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (busy) return;

        setBusy(true);
        publishing = true;
        cancelEditor.disabled = true;
        editorMessage.hidden = true;

        try {
            const post = await request('', new FormData(form));
            announcements = [post, ...announcements.filter(item => item.id !== post.id)];
            initialized = true;
            render(new Set([post.id]));
            form.reset();
            editor.close();
            status('Оголошення опубліковано. У стрічці: ' + announcements.length);
        } catch (error) {
            editorMessage.textContent = error.message;
            editorMessage.hidden = false;
        } finally {
            publishing = false;
            cancelEditor.disabled = false;
            setBusy(false);
        }
    });

    feed.addEventListener('click', async event => {
        const button = event.target.closest('.announcement-remove');

        if (!button || busy || !confirm('Видалити це оголошення?')) return;

        button.disabled = true;
        setBusy(true);
        const id = Number(button.closest('article').dataset.id);
        const values = new FormData();
        values.set('id', id);

        try {
            await request('&action=delete', values);
            announcements = announcements.filter(post => post.id !== id);
            render();
            status(announcements.length ? 'Оголошення видалено. У стрічці: ' + announcements.length : 'Оголошень поки немає. Додайте перше!');
        } catch (error) {
            status(error.message, true);
            button.disabled = false;
        } finally {
            setBusy(false);
        }
    });

    function finishAnimations() {
        feed.querySelectorAll('.announcement-enter').forEach(item => item.classList.remove('announcement-enter'));
        feed.querySelectorAll('.announcement-new').forEach(item => item.remove());
    }

    document.addEventListener('visibilitychange', () => feed.classList.toggle('animations-paused', document.hidden));
    window.addEventListener('pagehide', finishAnimations);
    reducedMotion.addEventListener('change', () => {
        if (reducedMotion.matches) finishAnimations();
    });
    refresh.addEventListener('click', load);
    load();
});
