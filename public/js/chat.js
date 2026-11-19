(function () {
    var root = document.getElementById('uf-chat');
    if (!root) return;

    var panel = document.getElementById('uf-chat-panel');
    var launcher = root.querySelector('.uf-chat-launcher');
    var closer = root.querySelector('.uf-chat-close');
    var log = root.querySelector('[data-chat-log]');
    var compose = root.querySelector('[data-chat-compose]');
    var availability = root.querySelector('[data-chat-availability]');
    var token = document.querySelector('meta[name="csrf-token"]');
    var poll = null;

    function csrf() {
        return token ? token.getAttribute('content') : '';
    }

    function setOpen(open) {
        panel.hidden = !open;
        launcher.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) {
            refresh();
            poll = window.setInterval(refresh, 8000);
            var field = compose.querySelector('textarea');
            if (field) field.focus();
        } else if (poll) {
            window.clearInterval(poll);
            poll = null;
        }
    }

    launcher.addEventListener('click', function () { setOpen(panel.hidden); });
    closer.addEventListener('click', function () { setOpen(false); });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !panel.hidden) setOpen(false);
    });

    root.querySelectorAll('[data-chat-view]').forEach(function (button) {
        button.addEventListener('click', function () { showView(button.getAttribute('data-chat-view')); });
    });

    var handover = root.querySelector('[data-chat-handover]');
    handover.addEventListener('click', function () {
        showView('messages');
        send(root.getAttribute('data-handover-url'), {});
    });

    compose.addEventListener('submit', function (event) {
        event.preventDefault();
        var field = compose.querySelector('textarea');
        var body = field.value.trim();
        if (!body) return;
        field.value = '';
        send(root.getAttribute('data-message-url'), { body: body, page_url: window.location.pathname });
    });

    root.querySelectorAll('[data-chat-form]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var kind = form.getAttribute('data-chat-form');
            var data = formData(form);
            data.page_url = window.location.pathname;
            if (kind === 'track') {
                send(root.getAttribute('data-track-url'), data).then(function (ok) {
                    if (ok) showView('messages');
                });
                return;
            }
            data.type = kind;
            send(root.getAttribute('data-request-url'), data).then(function (ok) {
                if (ok) {
                    form.reset();
                    showView('messages');
                }
            });
        });
    });

    function showView(name) {
        compose.hidden = name !== 'messages';
        root.querySelectorAll('[data-chat-form]').forEach(function (form) {
            form.hidden = form.getAttribute('data-chat-form') !== name;
        });
    }

    function formData(form) {
        var data = {};
        new FormData(form).forEach(function (value, key) { data[key] = value; });
        return data;
    }

    function send(url, data) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(data)
        }).then(function (response) {
            if (response.status === 419) {
                note('Your session expired. Refresh the page and try again.');
                return false;
            }
            return response.json().then(function (payload) {
                if (!response.ok) {
                    var message = payload && payload.message ? payload.message : 'We could not send that. You can email or call us using the details above.';
                    if (payload && payload.errors) {
                        message = Object.values(payload.errors)[0][0] || message;
                    }
                    note(message);
                    return false;
                }
                render(payload);
                return true;
            });
        }).catch(function () {
            note('We could not send that. You can email or call us using the details above.');
            return false;
        });
    }

    function refresh() {
        fetch(root.getAttribute('data-session-url'), {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        }).then(function (response) {
            if (!response.ok) return null;
            return response.json();
        }).then(function (payload) {
            if (payload) render(payload);
        }).catch(function () {});
    }

    function render(payload) {
        if (payload.availability && availability) {
            availability.textContent = payload.availability.label + '. ' + payload.availability.message;
            root.setAttribute('data-open', payload.availability.open ? '1' : '0');
        }
        log.replaceChildren();
        (payload.messages || []).forEach(function (message) {
            log.appendChild(messageNode(message));
        });
        log.scrollTop = log.scrollHeight;
    }

    function messageNode(message) {
        var item = document.createElement('article');
        item.className = 'uf-chat-message uf-chat-message-' + (message.sender || 'assistant');
        var who = document.createElement('p');
        who.className = 'uf-chat-who';
        who.textContent = message.sender === 'visitor' ? 'You' : (message.sender === 'staff' ? 'Urban Focus team' : 'Urban Focus');
        var body = document.createElement('p');
        body.className = 'uf-chat-text';
        body.textContent = message.body || '';
        item.appendChild(who);
        item.appendChild(body);
        (message.cards || []).forEach(function (card) {
            if (!card || !card.url) return;
            var safeUrl = card.url.charAt(0) === '/' || /^https?:\/\//.test(card.url);
            if (!safeUrl) return;
            var link = document.createElement('a');
            link.href = card.url;
            link.textContent = (card.name || 'Product') + ' — ' + (card.price_label || '') + ' — ' + (card.availability || '');
            item.appendChild(link);
        });
        if (message.review_status) {
            var status = document.createElement('p');
            status.className = 'uf-chat-status';
            status.textContent = 'Request status: ' + String(message.review_status).replace('_', ' ') + '. This is not an approval.';
            item.appendChild(status);
        }
        return item;
    }

    function note(text) {
        var item = document.createElement('p');
        item.className = 'uf-chat-note';
        item.textContent = text;
        log.appendChild(item);
    }
})();
