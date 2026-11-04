document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('announcementForm');
    const feed = document.getElementById('announcementFeed');
    const message = document.getElementById('boardMessage');
    const refresh = document.getElementById('refreshAnnouncements');
    const publish = form.querySelector('button[type="submit"]');
    const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
    const token = form.elements.csrf.value;
    let announcements = [];
    let initialized = false;
    let requestNumber = 0;

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
        const current = ++requestNumber;
        feed.setAttribute('aria-busy', 'true');
        refresh.disabled = true;
        publish.disabled = true;

        try {
            const data = await request();

            if (current !== requestNumber) return;
            if (!Array.isArray(data)) throw new Error('Сервер повернув некоректний список.');

            const previousIds = new Set(announcements.map(post => post.id));
            const newIds = new Set(initialized ? data.filter(post => !previousIds.has(post.id)).map(post => post.id) : []);
            announcements = data;
            initialized = true;
            render(newIds);
            status(data.length ? 'Оголошень: ' + data.length : 'Оголошень поки немає. Додайте перше!');
        } catch (error) {
            if (current === requestNumber) status(error.message, true);
        } finally {
            if (current === requestNumber) {
                feed.setAttribute('aria-busy', 'false');
                refresh.disabled = false;
                publish.disabled = false;
            }
        }
    }

    form.addEventListener('submit', async event => {
        event.preventDefault();
        const button = form.querySelector('button[type="submit"]');

        if (button.disabled) return;

        button.disabled = true;
        refresh.disabled = true;

        try {
            const post = await request('', new FormData(form));
            ++requestNumber;
            announcements = [post, ...announcements.filter(item => item.id !== post.id)];
            initialized = true;
            render(new Set([post.id]));
            form.reset();
            status('Оголошення опубліковано. У стрічці: ' + announcements.length);
        } catch (error) {
            status(error.message, true);
        } finally {
            button.disabled = false;
            refresh.disabled = false;
            feed.setAttribute('aria-busy', 'false');
        }
    });

    feed.addEventListener('click', async event => {
        const button = event.target.closest('.announcement-remove');

        if (!button || button.disabled || !confirm('Видалити це оголошення?')) return;

        button.disabled = true;
        refresh.disabled = true;
        const id = Number(button.closest('article').dataset.id);
        const values = new FormData();
        values.set('id', id);

        try {
            await request('&action=delete', values);
            ++requestNumber;
            announcements = announcements.filter(post => post.id !== id);
            render();
            status(announcements.length ? 'Оголошення видалено. У стрічці: ' + announcements.length : 'Оголошень поки немає. Додайте перше!');
        } catch (error) {
            status(error.message, true);
            button.disabled = false;
        } finally {
            refresh.disabled = false;
            feed.setAttribute('aria-busy', 'false');
            publish.disabled = false;
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
