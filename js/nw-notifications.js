/**
 * Neighborhood Watch portal notifications (bell dropdown).
 */
(function () {
    'use strict';

    var API_URL = 'api/nw_notifications.php';
    var refreshTimer = null;

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function getEls() {
        return {
            dropdown: document.getElementById('notificationDropdown'),
            badge: document.getElementById('notificationBadge'),
            list: document.getElementById('notificationList')
        };
    }

    function updateBadge(count) {
        var badge = getEls().badge;
        if (!badge) return;
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.classList.add('show');
        } else {
            badge.textContent = '';
            badge.classList.remove('show');
        }
    }

    function render(notifications) {
        var list = getEls().list;
        if (!list) return;
        if (!notifications || !notifications.length) {
            list.innerHTML = '<div class="notification-empty"><i class="fas fa-bell-slash"></i><p>No notifications</p></div>';
            return;
        }
        list.innerHTML = notifications.map(function (n) {
            return '<div class="notification-item ' + (n.is_read ? '' : 'unread') + '" data-id="' + escapeHtml(String(n.id)) + '" data-link="' + escapeHtml(n.link || '') + '">' +
                '<div class="notification-icon patrol"><i class="fas fa-bullhorn"></i></div>' +
                '<div class="notification-content">' +
                    '<div class="notification-title">' + escapeHtml(n.title) + '</div>' +
                    '<div class="notification-message">' + escapeHtml(n.message) + '</div>' +
                    '<div class="notification-time">' + escapeHtml(n.time_ago || '') + '</div>' +
                '</div>' +
            '</div>';
        }).join('');
    }

    async function loadNotifications() {
        try {
            await fetch(API_URL + '?action=sync', { credentials: 'same-origin' });
            var res = await fetch(API_URL + '?action=list', { credentials: 'same-origin' });
            if (!res.ok) return;
            var data = await res.json();
            if (!data.success) return;
            updateBadge(data.unread_count || 0);
            render(data.notifications || []);
        } catch (e) {
            console.error(e);
        }
    }

    window.toggleNotifications = function (evt) {
        if (evt) {
            evt.preventDefault();
            evt.stopPropagation();
        }
        var dropdown = getEls().dropdown;
        if (!dropdown) return;
        dropdown.classList.toggle('show');
    };

    window.markAllNotificationsRead = async function () {
        await fetch(API_URL + '?action=mark_read', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=mark_read'
        });
        loadNotifications();
    };

    function handleClick(id, link) {
        fetch(API_URL + '?action=mark_read', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + encodeURIComponent(String(id))
        }).finally(loadNotifications);

        var dropdown = getEls().dropdown;
        if (dropdown) dropdown.classList.remove('show');

        if (link && link.indexOf('section:') === 0) {
            var parts = link.split(':');
            var sectionId = parts[1] || 'bulletinSection';
            var postId = parseInt(parts[2], 10) || 0;
            if (typeof window.showPortalSection === 'function') {
                window.showPortalSection(sectionId, true);
            }
            if (postId && window.DigitalBulletin && typeof window.DigitalBulletin.openDetailById === 'function') {
                setTimeout(function () {
                    window.DigitalBulletin.openDetailById(postId);
                }, 350);
            }
        } else if (link) {
            window.location.href = link;
        }
    }

    function init() {
        var list = getEls().list;
        if (!list) return;

        list.addEventListener('click', function (e) {
            var item = e.target.closest('.notification-item');
            if (!item) return;
            handleClick(parseInt(item.getAttribute('data-id'), 10), item.getAttribute('data-link') || '');
        });

        document.addEventListener('click', function (e) {
            var dropdown = getEls().dropdown;
            if (dropdown && dropdown.classList.contains('show') && !e.target.closest('.notification-container')) {
                dropdown.classList.remove('show');
            }
        });

        loadNotifications();
        refreshTimer = setInterval(loadNotifications, 30000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
