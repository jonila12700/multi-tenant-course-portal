(function () {
    const widget = document.querySelector('[data-realtime-widget]');

    if (!widget) {
        return;
    }

    const basePath = widget.dataset.basePath || '.';
    const csrfToken = widget.dataset.csrfToken || '';
    const onlineList = document.getElementById('liveOnlineUsers');
    const notificationsList = document.getElementById('liveNotifications');
    const activityList = document.getElementById('liveActivity');
    const messagesList = document.getElementById('liveMessages');
    const messageForm = document.getElementById('liveMessageForm');
    const messageInput = document.getElementById('liveMessageInput');
    const statusDot = document.getElementById('liveStatusDot');

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char];
        });
    }

    function emptyState(text) {
        return '<li class="live-empty">' + escapeHtml(text) + '</li>';
    }

    function renderFeed(data) {
        statusDot.classList.add('is-online');

        onlineList.innerHTML = data.onlineUsers.length
            ? data.onlineUsers.map(function (user) {
                return '<li><span class="live-avatar">' + escapeHtml(user.name.charAt(0)) + '</span><div><strong>' +
                    escapeHtml(user.name) + '</strong><small>' + escapeHtml(user.role === 'instructor' ? 'professor' : user.role) +
                    '</small></div></li>';
            }).join('')
            : emptyState('No online users yet.');

        notificationsList.innerHTML = data.notifications.length
            ? data.notifications.map(function (item) {
                return '<li><strong>' + escapeHtml(item.title) + '</strong><small>' +
                    escapeHtml(item.body || item.created_at) + '</small></li>';
            }).join('')
            : emptyState('No live notifications.');

        activityList.innerHTML = data.activity.length
            ? data.activity.map(function (item) {
                return '<li><strong>' + escapeHtml(item.name || 'System') + '</strong><small>' +
                    escapeHtml(item.description || item.action) + '</small></li>';
            }).join('')
            : emptyState('No activity yet.');

        messagesList.innerHTML = data.messages.length
            ? data.messages.map(function (message) {
                return '<li><strong>' + escapeHtml(message.sender_name) + '</strong><span>' +
                    escapeHtml(message.body) + '</span></li>';
            }).join('')
            : emptyState('No messages yet.');

        document.querySelectorAll('[data-live-stat]').forEach(function (element) {
            const key = element.dataset.liveStat;
            if (data.stats && Object.prototype.hasOwnProperty.call(data.stats, key)) {
                element.textContent = data.stats[key];
            }
        });
    }

    function loadFeed() {
        fetch(basePath + '/realtime/feed.php', { credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.ok) {
                    renderFeed(data);
                }
            })
            .catch(function () {
                statusDot.classList.remove('is-online');
            });
    }

    function ping() {
        const data = new FormData();
        data.append('page', window.location.pathname);

        fetch(basePath + '/realtime/ping.php', {
            method: 'POST',
            body: data,
            credentials: 'same-origin'
        }).catch(function () {});
    }

    if (messageForm) {
        messageForm.addEventListener('submit', function (event) {
            event.preventDefault();

            const body = messageInput.value.trim();
            if (!body) {
                return;
            }

            const data = new FormData();
            data.append('csrf_token', csrfToken);
            data.append('body', body);

            fetch(basePath + '/realtime/send_message.php', {
                method: 'POST',
                body: data,
                credentials: 'same-origin'
            })
                .then(function (response) { return response.json(); })
                .then(function (result) {
                    if (result.ok) {
                        messageInput.value = '';
                        loadFeed();
                    }
                });
        });
    }

    ping();
    loadFeed();
    setInterval(ping, 20000);
    setInterval(loadFeed, 5000);
})();
