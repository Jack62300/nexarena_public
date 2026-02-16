(function () {
    'use strict';

    const container = document.getElementById('notification-container');
    const icons = {
        success: '&#10003;',
        error:   '&#10007;',
        vote:    '&#9733;',
        info:    '&#9432;',
    };

    let notifCount = 0;
    const MAX_VISIBLE = 5;

    function sanitize(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function createNotification(data) {
        const type     = data.type || 'info';
        const title    = sanitize(data.title || 'Nexarena');
        const message  = sanitize(data.message || '');
        const duration = data.duration || 8000;
        const position = data.position || 'top';

        container.className = position;

        // Remove oldest if too many
        const existing = container.querySelectorAll('.nexarena-notification:not(.hiding)');
        if (existing.length >= MAX_VISIBLE) {
            hideNotification(existing[0]);
        }

        const notif = document.createElement('div');
        notif.className = 'nexarena-notification type-' + type;

        notif.innerHTML =
            '<div class="notif-inner">' +
                '<div class="notif-accent"></div>' +
                '<div class="notif-content">' +
                    '<div class="notif-icon">' + (icons[type] || icons.info) + '</div>' +
                    '<div class="notif-text">' +
                        '<div class="notif-title">' +
                            title +
                            '<span class="notif-badge">NEXARENA</span>' +
                        '</div>' +
                        '<div class="notif-message">' + message + '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="notif-progress">' +
                    '<div class="notif-progress-bar" style="width: 100%"></div>' +
                '</div>' +
            '</div>';

        container.appendChild(notif);
        notifCount++;

        // Animate progress bar
        const progressBar = notif.querySelector('.notif-progress-bar');
        requestAnimationFrame(function () {
            progressBar.style.transitionDuration = duration + 'ms';
            progressBar.style.width = '0%';
        });

        // Auto-hide
        setTimeout(function () {
            hideNotification(notif);
        }, duration);
    }

    function hideNotification(notif) {
        if (notif.classList.contains('hiding')) return;
        notif.classList.add('hiding');

        setTimeout(function () {
            if (notif.parentNode) {
                notif.parentNode.removeChild(notif);
                notifCount--;
            }
        }, 400);
    }

    // Listen for NUI messages
    window.addEventListener('message', function (event) {
        if (!event.data || event.data.action !== 'showNotification') return;
        createNotification(event.data);
    });
})();
