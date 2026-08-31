/**
 * Neighborhood Watch portal notifications (bell dropdown).
 */
(function () {
    'use strict';

    var API_URL = 'api/nw_notifications.php';
    var PAGE_SIZE = 20;
    var refreshTimer = null;
    var outsideClickBound = false;
    var suppressOutsideClose = false;
    var loadedNotifications = [];
    var hasMoreNotifications = false;
    var loadingMore = false;

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
            loadedNotifications = [];
            hasMoreNotifications = false;
            list.innerHTML = '<div class="notification-empty"><i class="fas fa-bell-slash"></i><p>No notifications</p></div>';
            return;
        }
        list.innerHTML = notifications.map(notificationItemHtml).join('');
        appendLoadMoreButton(list);
    }

    function notificationItemHtml(n) {
            var icon = 'fa-bell';
            if (n.type === 'bulletin_announcement') {
                icon = 'fa-bullhorn';
            } else if (n.type === 'incident_update') {
                icon = 'fa-shield-alt';
            } else if (n.type === 'application_status') {
                icon = 'fa-user-check';
            }
            return '<div class="notification-item ' + (n.is_read ? '' : 'unread') + '" data-id="' + escapeHtml(String(n.id)) + '" data-link="' + escapeHtml(n.link || '') + '">' +
                '<div class="notification-icon event"><i class="fas ' + icon + '"></i></div>' +
                '<div class="notification-content">' +
                    '<div class="notification-title">' + escapeHtml(n.title) + '</div>' +
                    '<div class="notification-message">' + escapeHtml(n.message) + '</div>' +
                    '<div class="notification-time">' + escapeHtml(n.time_ago || '') + '</div>' +
                '</div>' +
            '</div>';
    }

    function appendLoadMoreButton(list) {
        var existing = list.querySelector('.notification-load-more');
        if (existing) existing.remove();
        if (!hasMoreNotifications) return;
        var wrap = document.createElement('div');
        wrap.className = 'notification-load-more';
        wrap.innerHTML = '<button type="button">' + (loadingMore ? 'Loading…' : 'See previous notifications') + '</button>';
        var btn = wrap.querySelector('button');
        btn.disabled = loadingMore;
        btn.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            loadMoreNotifications();
        });
        list.appendChild(wrap);
    }

    function listUrl(offset, limit) {
        return API_URL + '?action=list&offset=' + encodeURIComponent(String(offset)) +
            '&limit=' + encodeURIComponent(String(limit));
    }

    async function fetchNotificationPage(offset, limit) {
        var res = await fetch(listUrl(offset, limit), { credentials: 'same-origin', cache: 'no-store' });
        if (!res.ok) {
            var error = new Error('Failed to load notifications');
            error.status = res.status;
            throw error;
        }
        return res.json();
    }

    async function loadNotifications(options) {
        var reset = !options || options.reset !== false;
        var list = getEls().list;
        try {
            await fetch(API_URL + '?action=sync', { credentials: 'same-origin', cache: 'no-store' });
            var limit = reset ? PAGE_SIZE : Math.max(PAGE_SIZE, loadedNotifications.length);
            var data = await fetchNotificationPage(0, limit);
            if (!data.success) return;
            updateBadge(data.unread_count || 0);
            loadedNotifications = data.notifications || [];
            hasMoreNotifications = Boolean(data.has_more);
            render(loadedNotifications);
        } catch (e) {
            console.error(e);
            if (e && e.status === 401 && list) {
                list.innerHTML = '<div class="notification-empty"><i class="fas fa-exclamation-triangle"></i><p>Session expired. Please refresh.</p></div>';
            } else if (list) {
                list.innerHTML = '<div class="notification-empty"><i class="fas fa-exclamation-triangle"></i><p>Failed to load notifications</p></div>';
            }
        }
    }

    async function loadMoreNotifications() {
        if (loadingMore || !hasMoreNotifications) return;
        var list = getEls().list;
        loadingMore = true;
        if (list) appendLoadMoreButton(list);
        try {
            var data = await fetchNotificationPage(loadedNotifications.length, PAGE_SIZE);
            if (!data.success) return;
            updateBadge(data.unread_count || 0);
            var seen = {};
            loadedNotifications.forEach(function (item) { seen[item.id] = true; });
            (data.notifications || []).forEach(function (item) {
                if (!seen[item.id]) loadedNotifications.push(item);
            });
            hasMoreNotifications = Boolean(data.has_more);
            render(loadedNotifications);
        } catch (e) {
            console.error(e);
        } finally {
            loadingMore = false;
            if (list) appendLoadMoreButton(list);
        }
    }

    window.toggleNotifications = function (evt) {
        if (evt) {
            evt.preventDefault();
            evt.stopPropagation();
        }
        var dropdown = getEls().dropdown;
        if (!dropdown) return;

        var opening = !dropdown.classList.contains('show');
        dropdown.classList.toggle('show');

        if (opening) {
            suppressOutsideClose = true;
            window.setTimeout(function () {
                suppressOutsideClose = false;
            }, 150);
            loadNotifications();
        }
    };

    window.markAllNotificationsRead = async function () {
        try {
            await fetch(API_URL + '?action=mark_read', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=mark_read'
            });
            loadNotifications({ reset: false });
        } catch (e) {
            console.error(e);
        }
    };

    window.markAllAsRead = window.markAllNotificationsRead;

    function handleClick(id, link) {
        fetch(API_URL + '?action=mark_read', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + encodeURIComponent(String(id))
        }).finally(function () {
            loadNotifications({ reset: false });
        });

        var dropdown = getEls().dropdown;
        if (dropdown) dropdown.classList.remove('show');

        if (link && link.indexOf('section:') === 0) {
            var parts = link.split(':');
            var sectionId = parts[1] || 'bulletinSection';
            var postId = parseInt(parts[2], 10) || 0;
            if (typeof window.showPortalSection === 'function') {
                window.showPortalSection(sectionId, true, postId);
            } else {
                var dest = 'neighborhood-watcher-dashboard.php#' + encodeURIComponent(sectionId);
                if (postId > 0) {
                    dest += ':' + postId;
                }
                window.location.href = dest;
            }
        } else if (link) {
            window.location.href = link;
        }
    }

    function init() {
        var els = getEls();
        if (!els.dropdown || !els.list) return;

        if (els.list.dataset.clickBound !== '1') {
            els.list.dataset.clickBound = '1';
            els.list.addEventListener('click', function (e) {
                var item = e.target.closest('.notification-item');
                if (!item) return;
                handleClick(parseInt(item.getAttribute('data-id'), 10), item.getAttribute('data-link') || '');
            });
        }

        if (!outsideClickBound) {
            outsideClickBound = true;
            document.addEventListener('click', function (e) {
                if (suppressOutsideClose) return;
                var dropdown = getEls().dropdown;
                if (dropdown && dropdown.classList.contains('show') && !e.target.closest('.notification-container')) {
                    dropdown.classList.remove('show');
                }
            });
        }

        loadNotifications();
        if (!refreshTimer) {
            refreshTimer = setInterval(function () {
                loadNotifications({ reset: false });
            }, 30000);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
