(function () {
    var root = document.getElementById('uf-chat');
    if (!root) return;

    var panel = document.getElementById('uf-chat-panel');
    var launcher = root.querySelector('.uf-chat-launcher');
    var closer = root.querySelector('.uf-chat-close');
    var minimiser = root.querySelector('.uf-chat-min');
    var log = root.querySelector('[data-chat-log]');
    var compose = root.querySelector('[data-chat-compose]');
    var field = compose.querySelector('textarea');
    var availability = root.querySelector('[data-chat-availability]');
    var token = document.querySelector('meta[name="csrf-token"]');
    var poll = null;
    var unsent = [];
    var troubleText = '';
    var latest = null;

    function csrf() {
        return token ? token.getAttribute('content') : '';
    }

    function troubleLine() {
        return root.getAttribute('data-trouble') || 'Sorry, chat is having trouble. Email sales@urbanfocus.co.za or call 087 550 1813.';
    }

    function setOpen(open) {
        panel.hidden = !open;
        if (open) {
            panel.classList.remove('is-min');
            if (minimiser) minimiser.setAttribute('aria-label', 'Minimise chat');
        }
        launcher.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) {
            refresh();
            if (!poll) poll = window.setInterval(refresh, 4000);
            if (field) field.focus();
        } else if (poll) {
            window.clearInterval(poll);
            poll = null;
        }
    }

    function setMinimised(min) {
        panel.classList.toggle('is-min', min);
        if (minimiser) minimiser.setAttribute('aria-label', min ? 'Restore chat' : 'Minimise chat');
        if (!min && field) field.focus();
    }

    launcher.addEventListener('click', function () { setOpen(panel.hidden || panel.classList.contains('is-min')); });
    closer.addEventListener('click', function () { setOpen(false); });
    if (minimiser) {
        minimiser.addEventListener('click', function () {
            setMinimised(!panel.classList.contains('is-min'));
        });
    }
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !panel.hidden) setOpen(false);
    });

    root.querySelectorAll('[data-chat-view]').forEach(function (button) {
        button.addEventListener('click', function () { showView(button.getAttribute('data-chat-view')); });
    });

    var handover = root.querySelector('[data-chat-handover]');
    handover.addEventListener('click', function () {
        if (!contactReady()) return;
        showView('messages');
        var data = contactPayload();
        data.page_url = window.location.pathname;
        send(root.getAttribute('data-handover-url'), data);
    });

    field.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            if (typeof compose.requestSubmit === 'function') {
                compose.requestSubmit();
            } else {
                compose.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
            }
        }
    });

    compose.addEventListener('submit', function (event) {
        event.preventDefault();
        var body = field.value.trim();
        if (!body || !contactReady()) return;
        field.value = '';
        unsent.push(body);
        troubleText = '';
        paint();
        var data = contactPayload();
        data.body = body;
        data.page_url = window.location.pathname;
        send(root.getAttribute('data-message-url'), data);
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

    function contactBox() {
        return root.querySelector('[data-chat-contact]');
    }

    function setContactVisible(show) {
        var box = contactBox();
        if (!box) return;
        box.hidden = !show;
        box.querySelectorAll('input').forEach(function (input) {
            input.required = !!show;
        });
    }

    function contactPayload() {
        var box = contactBox();
        var data = {};
        if (!box || box.hidden) return data;
        var name = box.querySelector('[name="name"]');
        var email = box.querySelector('[name="email"]');
        var phone = box.querySelector('[name="phone"]');
        if (name && name.value.trim()) data.name = name.value.trim();
        if (email && email.value.trim()) data.email = email.value.trim();
        if (phone && phone.value.trim()) data.phone = phone.value.trim();
        return data;
    }

    function contactReady() {
        var box = contactBox();
        if (!box || box.hidden) return true;
        var name = box.querySelector('[name="name"]');
        var email = box.querySelector('[name="email"]');
        if (name && name.value.trim() && email && email.value.trim()) return true;
        troubleText = 'Please leave your name and email so we can reply.';
        paint();
        if (name && !name.value.trim()) name.focus();
        else if (email) email.focus();
        return false;
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
                troubleText = 'Your session expired. Refresh the page and try again.';
                paint();
                return false;
            }
            if (response.status >= 500) {
                troubleText = troubleLine();
                paint();
                return false;
            }
            return response.json().then(function (payload) {
                if (!response.ok) {
                    var message = payload && payload.message ? payload.message : 'We could not send that. You can email or call us using the details above.';
                    if (payload && payload.errors) {
                        message = Object.values(payload.errors)[0][0] || message;
                    }
                    if (message === 'Server Error') message = troubleLine();
                    troubleText = message;
                    paint();
                    return false;
                }
                if (data && data.body) {
                    unsent = unsent.filter(function (item) { return item !== data.body; });
                }
                troubleText = '';
                render(payload);
                return true;
            }).catch(function () {
                troubleText = troubleLine();
                paint();
                return false;
            });
        }).catch(function () {
            troubleText = troubleLine();
            paint();
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
        latest = payload;
        if (payload.availability) {
            if (availability) availability.textContent = payload.availability.message || '';
            root.setAttribute('data-open', payload.availability.open ? '1' : '0');
        }
        if (typeof payload.needs_contact === 'boolean') {
            setContactVisible(payload.needs_contact);
        }
        paint();
    }

    function paint() {
        var messages = (latest && latest.messages) || [];
        log.replaceChildren();
        log.appendChild(greetingNode());
        messages.forEach(function (message) {
            log.appendChild(messageNode(message));
        });
        unsent.forEach(function (body) {
            log.appendChild(messageNode({ sender: 'visitor', sender_name: 'You', body: body }));
        });
        var tools = root.querySelector('[data-chat-tools]');
        if (tools) tools.classList.toggle('is-compact', messages.length > 0 || unsent.length > 0);
        if (troubleText) {
            var item = document.createElement('p');
            item.className = 'uf-chat-note';
            item.textContent = troubleText;
            log.appendChild(item);
        }
        log.scrollTop = log.scrollHeight;
    }

    function greetingNode() {
        var item = document.createElement('p');
        item.className = 'uf-chat-greeting';
        item.textContent = 'Hi! Looking for a product or a quote? Ask away.';
        return item;
    }

    function messageNode(message) {
        var item = document.createElement('article');
        var sender = message.sender || 'assistant';
        item.className = 'uf-chat-message uf-chat-message-' + sender;
        if (sender === 'system' || message.kind === 'notice') {
            var notice = document.createElement('p');
            notice.className = 'uf-chat-system';
            notice.textContent = message.body || '';
            item.appendChild(notice);
            return item;
        }
        var who = document.createElement('p');
        who.className = 'uf-chat-who';
        who.textContent = message.sender_name || (sender === 'visitor' ? 'You' : (sender === 'staff' ? 'Urban Focus team' : 'Urban Focus'));
        var bubble = document.createElement('div');
        bubble.className = 'uf-chat-bubble';
        var body = document.createElement('p');
        body.className = 'uf-chat-text';
        body.textContent = message.body || '';
        item.appendChild(who);
        bubble.appendChild(body);
        (message.cards || []).forEach(function (card) {
            if (!card || !card.url) return;
            var safeUrl = card.url.charAt(0) === '/' || /^https?:\/\//.test(card.url);
            if (!safeUrl) return;
            var link = document.createElement('a');
            link.href = card.url;
            link.textContent = (card.name || 'Product') + ' — ' + (card.price_label || '') + ' — ' + (card.availability || '');
            bubble.appendChild(link);
        });
        if (message.review_status) {
            var status = document.createElement('p');
            status.className = 'uf-chat-status';
            status.textContent = 'Request status: ' + String(message.review_status).replace('_', ' ') + '. This is not an approval.';
            bubble.appendChild(status);
        }
        item.appendChild(bubble);
        return item;
    }

    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', function () {
            if (!window.matchMedia('(max-width: 640px)').matches || panel.hidden) {
                panel.style.height = '';
                return;
            }
            panel.style.height = Math.round(window.visualViewport.height) + 'px';
        });
    }
})();
