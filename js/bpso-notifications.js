(function () {
    'use strict';

    var API_URL = 'api/bpso_notifications.php';
    var PAGE_SIZE = 20;
    var refreshTimer = null;
    var outsideClickBound = false;
    var suppressOutsideClose = false;
    var loadedNotifications = [];
    var hasMoreNotifications = false;
    var loadingMore = false;

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
            loadedNotifications = [];
            hasMoreNotifications = false;
            showNotificationMessage(
                '<div class="notification-empty">' +
                    '<i class="fas fa-bell-slash"></i>' +
                    '<p>No notifications</p>' +
                '</div>'
            );
            return;
        }

        list.innerHTML = notifications.map(notificationItemHtml).join('');
        appendLoadMoreButton(list);
    }

    function notificationItemHtml(notif) {
            var iconClass = 'patrol';
            var icon = 'fa-walking';
            if (notif.type === 'complaint_assignment') {
                iconClass = 'complaint';
                icon = 'fa-exclamation-circle';
            } else if (notif.type === 'nw_incident_assignment') {
                iconClass = 'complaint';
                icon = 'fa-shield-alt';
            } else if (notif.type === 'duty_schedule') {
                iconClass = 'patrol';
                icon = 'fa-calendar-alt';
            } else if (notif.type === 'patrol_request_assignment') {
                iconClass = 'patrol';
                icon = 'fa-user-check';
            } else if (notif.type === 'missed_patrol_report') {
                iconClass = 'complaint';
                icon = 'fa-exclamation-triangle';
            } else if (notif.type === 'submit_report') {
                iconClass = 'patrol';
                icon = 'fa-file-alt';
            } else if (notif.type === 'bulletin_announcement') {
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
    }

    function loadMoreLinkHtml(loading) {
        return loading ? 'Loading previous notifications…' : 'View previous notifications';
    }

    function appendLoadMoreButton(list) {
        var existing = list.querySelector('.notification-load-more');
        if (existing) {
            existing.remove();
        }
        if (!hasMoreNotifications) {
            return;
        }
        var wrap = document.createElement('div');
        wrap.className = 'notification-load-more';
        wrap.innerHTML = '<a href="#" class="notification-load-more-link" role="button">' + loadMoreLinkHtml(loadingMore) + '</a>';
        var link = wrap.querySelector('.notification-load-more-link');
        if (loadingMore) {
            link.classList.add('is-loading');
        }
        link.addEventListener('click', function (event) {
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
        var response = await fetch(listUrl(offset, limit), { credentials: 'same-origin', cache: 'no-store' });
        if (!response.ok) {
            var error = new Error('Failed to load notifications');
            error.status = response.status;
            throw error;
        }
        return response.json();
    }

    async function loadNotifications(options) {
        var reset = !options || options.reset !== false;
        var list = getElements().list;

        try {
            await fetch(API_URL + '?action=sync', { credentials: 'same-origin', cache: 'no-store' });
            var limit = reset ? PAGE_SIZE : Math.max(PAGE_SIZE, loadedNotifications.length);
            var data = await fetchNotificationPage(0, limit);

            if (data.success) {
                updateNotificationBadge(data.unread_count || 0);
                loadedNotifications = data.notifications || [];
                hasMoreNotifications = Boolean(data.has_more);
                renderNotifications(loadedNotifications);
            }
        } catch (error) {
            console.error('Error loading notifications:', error);
            if (error && error.status === 401 && list) {
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
                        '<p>Failed to load notifications</p>' +
                    '</div>'
                );
            }
        }
    }

    async function loadMoreNotifications() {
        if (loadingMore || !hasMoreNotifications) {
            return;
        }
        var list = getElements().list;
        loadingMore = true;
        if (list) {
            appendLoadMoreButton(list);
        }
        try {
            var data = await fetchNotificationPage(loadedNotifications.length, PAGE_SIZE);
            if (!data.success) {
                return;
            }
            updateNotificationBadge(data.unread_count || 0);
            var seen = {};
            loadedNotifications.forEach(function (item) {
                seen[item.id] = true;
            });
            (data.notifications || []).forEach(function (item) {
                if (!seen[item.id]) {
                    loadedNotifications.push(item);
                }
            });
            hasMoreNotifications = Boolean(data.has_more);
            renderNotifications(loadedNotifications);
        } catch (error) {
            console.error('Error loading more notifications:', error);
        } finally {
            loadingMore = false;
            if (list) {
                appendLoadMoreButton(list);
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

    function initBpsoNotifications() {
        var elements = getElements();
        if (!elements.dropdown || !elements.list) {
            return;
        }

        bindOutsideClick();
        bindNotificationListClicks();
        loadNotifications();

        if (!refreshTimer) {
            refreshTimer = window.setInterval(function () {
                loadNotifications({ reset: false });
            }, 30000);
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
            loadNotifications({ reset: false });
        });

        if (link && link.indexOf('tab:') === 0) {
            var parts = link.split(':');
            var tab = parts[1] || 'dashboard';
            var openPostId = parseInt(parts[2], 10) || 0;
            if (typeof window.goToTab === 'function') {
                window.goToTab(tab, openPostId);
            }
            var dropdown = getElements().dropdown;
            if (dropdown) {
                dropdown.classList.remove('show');
            }
        } else if (link) {
            window.location.href = link;
        }

        if (typeof window.refreshAllData === 'function') {
            window.refreshAllData();
        }
    };

    window.markAllNotificationsRead = async function () {
        try {
            await fetch(API_URL + '?action=mark_read', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
            });
            loadNotifications({ reset: false });
        } catch (error) {
            console.error('Error marking notifications as read:', error);
        }
    };

    window.markAllAsRead = window.markAllNotificationsRead;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBpsoNotifications);
    } else {
        initBpsoNotifications();
    }
})();
