(function () {
    'use strict';

    var API_URL = 'api/notifications.php';
    var refreshTimer = null;
    var outsideClickBound = false;
    var suppressOutsideClose = false;

    function getElements() {
        return {
            dropdown: document.getElementById('notificationDropdown'),
            badge: document.getElementById('notificationBadge'),
            list: document.getElementById('notificationList')
        };
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function updateNotificationBadge(count) {
        var badge = getElements().badge;
        if (!badge) {
            return;
        }

        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.classList.add('show');
        } else {
            badge.textContent = '';
            badge.classList.remove('show');
        }
    }

    function showNotificationMessage(html) {
        var list = getElements().list;
        if (list) {
            list.innerHTML = html;
        }
    }

    function renderNotifications(notifications) {
        var list = getElements().list;
        if (!list) {
            return;
        }

        if (!notifications || notifications.length === 0) {
            showNotificationMessage(
                '<div class="notification-empty">' +
                    '<i class="fas fa-bell-slash"></i>' +
                    '<p>No notifications</p>' +
                '</div>'
            );
            return;
        }

        list.innerHTML = notifications.map(function (notif) {
            var iconClass;
            var icon;

            if (notif.type === 'complaint' || notif.type === 'incident') {
                iconClass = 'complaint';
                icon = 'fa-file-alt';
            } else if (notif.type === 'tip') {
                iconClass = 'tip';
                icon = 'fa-comments';
            } else if (notif.type === 'volunteer' || notif.type === 'volunteer_request') {
                iconClass = 'volunteer';
                icon = 'fa-handshake';
            } else if (notif.type === 'cctv_request') {
                iconClass = 'cctv_request';
                icon = 'fa-file-video';
            } else if (notif.type === 'patrol_request') {
                iconClass = 'patrol_request';
                icon = 'fa-clipboard-check';
            } else if (notif.type === 'awareness_event') {
                iconClass = 'event';
                icon = 'fa-bullhorn';
            } else if (notif.type === 'login') {
                iconClass = 'login';
                icon = 'fa-sign-in-alt';
            } else if (notif.type === 'logout') {
                iconClass = 'logout';
                icon = 'fa-sign-out-alt';
            } else {
                iconClass = 'event';
                icon = 'fa-bullhorn';
            }

            var safeLink = escapeHtml(notif.link || '');

            return (
                '<div class="notification-item ' + (notif.is_read ? '' : 'unread') + '"' +
                    ' data-id="' + escapeHtml(String(notif.id)) + '"' +
                    ' data-link="' + safeLink + '">' +
                    '<div class="notification-icon ' + iconClass + '">' +
                        '<i class="fas ' + icon + '"></i>' +
                    '</div>' +
                    '<div class="notification-content">' +
                        '<div class="notification-title">' + escapeHtml(notif.title) + '</div>' +
                        '<div class="notification-message">' + escapeHtml(notif.message) + '</div>' +
                        '<div class="notification-time">' + escapeHtml(notif.time_ago || '') + '</div>' +
                    '</div>' +
                '</div>'
            );
        }).join('');
    }

    async function loadNotifications() {
        var list = getElements().list;

        try {
            await fetch(API_URL + '?action=sync', { credentials: 'same-origin' });

            var response = await fetch(API_URL + '?action=list', { credentials: 'same-origin' });
            if (!response.ok) {
                console.error('Failed to load notifications:', response.status, response.statusText);
                if (response.status === 401) {
                    showNotificationMessage(
                        '<div class="notification-empty">' +
                            '<i class="fas fa-exclamation-triangle"></i>' +
                            '<p>Session expired. Please refresh the page.</p>' +
                        '</div>'
                    );
                } else if (list) {
                    showNotificationMessage(
                        '<div class="notification-empty">' +
                            '<i class="fas fa-exclamation-triangle"></i>' +
                            '<p>Error loading notifications (' + response.status + ')</p>' +
                        '</div>'
                    );
                }
                return;
            }

            var data = await response.json();
            if (data.success) {
                updateNotificationBadge(data.unread_count || 0);
                renderNotifications(data.notifications || []);
            } else if (list) {
                showNotificationMessage(
                    '<div class="notification-empty">' +
                        '<i class="fas fa-bell-slash"></i>' +
                        '<p>No notifications available</p>' +
                    '</div>'
                );
            }
        } catch (error) {
            console.error('Error loading notifications:', error);
            if (list) {
                showNotificationMessage(
                    '<div class="notification-empty">' +
                        '<i class="fas fa-exclamation-triangle"></i>' +
                        '<p>Failed to load notifications</p>' +
                    '</div>'
                );
            }
        }
    }

    function bindOutsideClick() {
        if (outsideClickBound) {
            return;
        }

        outsideClickBound = true;
        document.addEventListener('click', function (event) {
            if (suppressOutsideClose) {
                return;
            }

            var dropdown = getElements().dropdown;
            if (dropdown && dropdown.classList.contains('show') && !event.target.closest('.notification-container')) {
                dropdown.classList.remove('show');
            }
        });
    }

    function bindNotificationListClicks() {
        var list = getElements().list;
        if (!list || list.dataset.clickBound === '1') {
            return;
        }

        list.dataset.clickBound = '1';
        list.addEventListener('click', function (event) {
            var item = event.target.closest('.notification-item');
            if (!item) {
                return;
            }

            window.handleNotificationClick(
                parseInt(item.getAttribute('data-id'), 10),
                item.getAttribute('data-link') || '',
                item
            );
        });
    }

    function initAdminNotifications() {
        var elements = getElements();
        if (!elements.dropdown || !elements.list) {
            return;
        }

        bindOutsideClick();
        bindNotificationListClicks();
        loadNotifications();

        if (!refreshTimer) {
            refreshTimer = window.setInterval(loadNotifications, 30000);
        }
    }

    window.toggleNotifications = function (evt) {
        if (evt) {
            evt.preventDefault();
            evt.stopPropagation();
        }

        var dropdown = getElements().dropdown;
        if (!dropdown) {
            return;
        }

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

    window.handleNotificationClick = function (id, link, itemEl) {
        if (itemEl) {
            itemEl.classList.remove('unread');
        }

        fetch(API_URL + '?action=mark_read', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + encodeURIComponent(String(id))
        }).finally(function () {
            loadNotifications();
        });

        if (link) {
            window.location.href = link;
        }
    };

    window.markAllAsRead = async function () {
        try {
            await fetch(API_URL + '?action=mark_read', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
            });
            loadNotifications();
        } catch (error) {
            console.error('Error marking all as read:', error);
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAdminNotifications);
    } else {
        initAdminNotifications();
    }
})();
