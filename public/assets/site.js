'use strict';
const menu = document.querySelector('.menu-toggle');
const nav = document.querySelector('#main-nav');
if (menu && nav) {
    menu.hidden = false;
    menu.closest('.mast').classList.add('js-menu');
    const setMenu = (open) => {
        menu.setAttribute('aria-expanded', String(open));
        nav.classList.toggle('is-open', open);
        menu.querySelector('.menu-label').textContent = open ? 'Close' : 'Menu';
        menu.querySelector('.menu-symbol').textContent = open ? '−' : '+';
    };
    menu.addEventListener('click', () => setMenu(menu.getAttribute('aria-expanded') !== 'true'));
    document.addEventListener('click', (event) => {
        if (!menu.closest('.mast').contains(event.target)) setMenu(false);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && menu.getAttribute('aria-expanded') === 'true') {
            setMenu(false);
            menu.focus();
        }
    });
}
for (const form of document.forms) {
    const update = () => {
        for (const item of form.querySelectorAll('[data-other],[data-condition]')) {
            const name = item.dataset.other ?? item.dataset.condition;
            const value = item.dataset.equals ?? 'Other';
            const inputs = [...form.querySelectorAll('[name]')].filter((input) => input.name === name);
            const show = inputs.some((input) =>
                input.value === value && (!['radio', 'checkbox'].includes(input.type) || input.checked));
            item.hidden = !show;
            for (const input of item.querySelectorAll('input, select, textarea')) {
                input.disabled = !show;
                input.required = show;
            }
        }
        const email = form.elements.namedItem('email');
        const contact = form.elements.namedItem('contact_method');
        if (email && contact) email.required = contact.value === 'Email';
    };
    form.addEventListener('change', update);
    form.addEventListener('invalid', (event) => {
        const details = event.target.closest('details');
        if (details) details.open = true;
    }, true);
    update();
}
const motionAllowed = window.matchMedia('(prefers-reduced-motion: no-preference)');
const publicMain = document.querySelector('.public #main');
if (publicMain) {
    let leaving = false;
    window.addEventListener('pageshow', () => {
        leaving = false;
        publicMain.classList.remove('is-leaving');
    });
    document.addEventListener('click', (event) => {
        if (!motionAllowed.matches || event.defaultPrevented || event.button !== 0 ||
            event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) return;
        const link = event.target.closest('a[href]');
        if (!link || link.hasAttribute('download') || (link.target && link.target !== '_self')) return;
        const destination = new URL(link.href);
        const filename = destination.pathname.split('/').pop();
        if (destination.origin !== location.origin || !['index.php', 'booking.php'].includes(filename) ||
            (destination.pathname === location.pathname && destination.search === location.search)) return;
        event.preventDefault();
        if (leaving) return;
        leaving = true;
        publicMain.classList.add('is-leaving');
        // The short fade leaves the header in place and uses ordinary link navigation.
        setTimeout(() => location.assign(destination), 100);
    });
}
const projectObserver = 'IntersectionObserver' in window ? new IntersectionObserver((entries, observer) => {
    for (const entry of entries) {
        if (!entry.isIntersecting) continue;
        observer.unobserve(entry.target);
        if (motionAllowed.matches && !entry.target.matches(':focus-within')) {
            entry.target.classList.add('is-entering');
            entry.target.addEventListener('animationend', () => entry.target.classList.remove('is-entering'), {once: true});
        }
    }
}, {threshold: .12}) : null;
const observeProjects = (container) => {
    if (!projectObserver || !motionAllowed.matches) return;
    container.querySelectorAll('.work-card').forEach((card) => projectObserver.observe(card));
};
observeProjects(document);

const searchForm = document.querySelector('[data-live-search]');
if (searchForm) {
    const query = searchForm.elements.q;
    const results = document.querySelector('#site-search-results');
    const announcement = document.querySelector('[data-search-announcement]');
    let timer;
    let pending;
    const refreshResults = async (submit = false) => {
        pending?.abort();
        const request = new AbortController();
        pending = request;
        const url = new URL(searchForm.action);
        url.search = new URLSearchParams(new FormData(searchForm));
        if (!query.value.trim()) url.searchParams.delete('q');
        if (!url.searchParams.get('type')) url.searchParams.delete('type');
        const fragment = new URL(url);
        fragment.searchParams.set('fragment', 'results');
        results.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(fragment, {signal: request.signal});
            if (!response.ok) throw new Error('Search unavailable');
            const html = await response.text();
            if (request.signal.aborted) return;
            const template = document.createElement('template');
            template.innerHTML = html;
            results.replaceChildren(template.content);
            announcement.classList.add('visually-hidden');
            announcement.textContent = results.querySelector('[data-result-summary]')?.textContent ?? 'Search suggestions available.';
            history.replaceState(null, '', url);
        } catch (error) {
            if (request.signal.aborted) return;
            if (submit) HTMLFormElement.prototype.submit.call(searchForm);
            else {
                announcement.classList.remove('visually-hidden');
                announcement.textContent = 'Live results are unavailable. Press Search to try again.';
            }
        } finally {
            if (pending === request) results.removeAttribute('aria-busy');
        }
    };
    query.addEventListener('input', () => {
        clearTimeout(timer);
        pending?.abort();
        timer = setTimeout(refreshResults, 230);
    });
    searchForm.addEventListener('change', (event) => {
        if (event.target.name !== 'type') return;
        clearTimeout(timer);
        refreshResults();
    });
    searchForm.addEventListener('submit', (event) => {
        event.preventDefault();
        clearTimeout(timer);
        refreshResults(true);
    });
}

const archiveFilter = document.querySelector('[data-archive-filter]');
if (archiveFilter) {
    const grid = document.querySelector('[data-archive-grid]');
    const count = document.querySelector('[data-archive-count]');
    let entries;
    const renderCard = (work) => {
        const card = document.createElement('article');
        card.className = 'work-card';
        const link = document.createElement('a');
        link.href = 'index.php?page=project&id=' + encodeURIComponent(work.id);
        const image = work.images[0];
        const media = document.createElement('div');
        media.className = 'work-media';
        const visual = document.createElement(image ? 'img' : 'div');
        media.append(visual);
        if (image) {
            visual.src = image.path.startsWith('assets/') ? image.path : 'media.php?id=' + encodeURIComponent(image.id);
            visual.alt = image.alt;
            visual.loading = 'lazy';
        } else {
            visual.className = 'work-placeholder';
        }
        const caption = document.createElement('div');
        caption.className = 'work-caption';
        const info = document.createElement('div');
        const title = document.createElement('h2');
        title.textContent = work.title;
        const location = document.createElement('p');
        location.textContent = work.location ?? '';
        const arrow = document.createElement('span');
        arrow.className = 'work-arrow';
        arrow.setAttribute('aria-hidden', 'true');
        arrow.textContent = '↗';
        info.append(title);
        if (work.location) info.append(location);
        caption.append(info, arrow);
        link.append(media, caption);
        card.append(link);
        return card;
    };
    archiveFilter.addEventListener('submit', async (event) => {
        event.preventDefault();
        const category = archiveFilter.elements.category.value;
        try {
            if (!entries) {
                const response = await fetch('api.php?resource=portfolio');
                if (!response.ok) throw new Error('Portfolio unavailable');
                entries = (await response.json()).data;
            }
            const visible = category ? entries.filter((item) => item.category === category) : entries;
            grid.querySelectorAll('.work-card').forEach((card) => projectObserver?.unobserve(card));
            grid.replaceChildren(...visible.map(renderCard));
            observeProjects(grid);
            if (motionAllowed.matches && typeof grid.animate === 'function') {
                grid.animate([{opacity: .72}, {opacity: 1}], {duration: 220, easing: 'ease-out'});
            }
            count.textContent = visible.length + ' projects';
            const url = new URL(location.href);
            if (category) url.searchParams.set('category', category);
            else url.searchParams.delete('category');
            history.replaceState(null, '', url);
            for (const link of document.querySelectorAll('[data-archive-view]')) {
                const target = new URL(link.href);
                if (category) target.searchParams.set('category', category);
                else target.searchParams.delete('category');
                link.href = target;
            }
        } catch {
            HTMLFormElement.prototype.submit.call(archiveFilter);
        }
    });
}
for (const form of document.querySelectorAll('[data-cms-api]')) {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const fields = Object.fromEntries(new FormData(form));
        const action = event.submitter?.value;
        const deleting = action === 'delete_content';
        const url = 'api.php?resource=' + encodeURIComponent(fields.kind)
            + (fields.id ? '&id=' + encodeURIComponent(fields.id) : '');
        const button = event.submitter ?? form.querySelector('button');
        const showError = (message) => {
            let notice = form.querySelector('[data-api-error]');
            if (!notice) {
                notice = document.createElement('p');
                notice.className = 'alert alert-danger';
                notice.dataset.apiError = '';
                notice.setAttribute('role', 'alert');
                form.prepend(notice);
            }
            notice.textContent = message;
        };
        button.disabled = true;
        try {
            const response = await fetch(url, {
                method: deleting ? 'DELETE' : fields.id ? 'PATCH' : 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-Token': fields.csrf},
                body: JSON.stringify(deleting ? {} : fields)
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.error ?? 'The change could not be saved.');
            location.href = 'admin.php?tab=cms&kind=' + encodeURIComponent(fields.kind)
                + (deleting ? '' : '&id=' + encodeURIComponent(payload.data.id));
        } catch (error) {
            showError(error.message || 'The change could not be saved. Reload the page and try again.');
        } finally {
            button.disabled = false;
        }
    });
}
for (const trigger of document.querySelectorAll('[data-print]')) {
    trigger.addEventListener('click', () => window.print());
}
for (const trigger of document.querySelectorAll('[data-confirm]')) {
    trigger.addEventListener('click', (event) => {
        if (!window.confirm(trigger.dataset.confirm)) event.preventDefault();
    });
}
