/**
 * Digital Bulletin: image slideshow + text announcement list + detail modal.
 * Usage: DigitalBulletin.mount({ root, audience, apiUrl, intervalMs, openPostId })
 */
(function (global) {
    'use strict';

    var activeTimer = null;
    var lastPosts = [];

    function escapeHtml(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function postSortTime(post) {
        var raw = post && (post.created_at || post.publish_at || '');
        var t = Date.parse(String(raw).replace(' ', 'T'));
        return Number.isNaN(t) ? 0 : t;
    }

    function sortPostsForBulletin(posts) {
        return (posts || []).slice().sort(function (a, b) {
            var pinA = a && a.is_pinned ? 1 : 0;
            var pinB = b && b.is_pinned ? 1 : 0;
            if (pinA !== pinB) {
                return pinB - pinA;
            }

            var timeA = postSortTime(a);
            var timeB = postSortTime(b);
            var idA = (a && a.id) || 0;
            var idB = (b && b.id) || 0;

            if (pinA === 1) {
                if (timeA !== timeB) return timeB - timeA;
                return idB - idA;
            }

            if (timeA !== timeB) return timeA - timeB;
            return idA - idB;
        });
    }

    function hasImages(post) {
        return !!(post && Array.isArray(post.media) && post.media.length > 0);
    }

    function collectImageSlides(posts) {
        var slides = [];
        sortPostsForBulletin(posts).filter(hasImages).forEach(function (post) {
            (post.media || []).forEach(function (src) {
                slides.push({
                    src: src,
                    postId: post.id,
                    title: post.title || '',
                    body: post.body || '',
                    link_url: post.link_url || '',
                    attachments: post.attachments || [],
                    pinned: !!post.is_pinned,
                    created_at: post.created_at || post.publish_at || '',
                    publish_at: post.publish_at || ''
                });
            });
        });
        return slides;
    }

    function normalizeExternalUrl(url) {
        var value = String(url || '').trim();
        if (!value) return '';
        if (!/^https?:\/\//i.test(value)) {
            value = 'https://' + value.replace(/^\/+/, '');
        }
        return value;
    }

    function openExternalUrl(url) {
        var href = normalizeExternalUrl(url);
        if (!href) return false;
        window.open(href, '_blank', 'noopener,noreferrer');
        return true;
    }

    function collectListPosts(posts) {
        // Every announcement appears in the list (title + details only; no images).
        return sortPostsForBulletin(posts);
    }

    function formatTimeAgo(raw) {
        if (!raw) return '';
        var t = Date.parse(String(raw).replace(' ', 'T'));
        if (Number.isNaN(t)) return String(raw);
        var diff = Math.max(0, Math.floor((Date.now() - t) / 1000));
        if (diff < 60) return 'Just now';
        if (diff < 3600) {
            var m = Math.floor(diff / 60);
            return m + ' minute' + (m > 1 ? 's' : '') + ' ago';
        }
        if (diff < 86400) {
            var h = Math.floor(diff / 3600);
            return h + ' hour' + (h > 1 ? 's' : '') + ' ago';
        }
        if (diff < 604800) {
            var d = Math.floor(diff / 86400);
            return d + ' day' + (d > 1 ? 's' : '') + ' ago';
        }
        return new Date(t).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function clearAutoplay() {
        if (activeTimer) {
            clearInterval(activeTimer);
            activeTimer = null;
        }
    }

    function ensureModal() {
        var modal = document.getElementById('dbDetailModal');
        if (modal) return modal;

        modal = document.createElement('div');
        modal.id = 'dbDetailModal';
        modal.className = 'db-detail-modal';
        modal.innerHTML =
            '<div class="db-detail-dialog" role="dialog" aria-modal="true" aria-labelledby="dbDetailTitle">' +
                '<button type="button" class="db-detail-close" aria-label="Close">&times;</button>' +
                '<div class="db-detail-media" id="dbDetailMedia"></div>' +
                '<h3 id="dbDetailTitle"></h3>' +
                '<p class="db-detail-meta" id="dbDetailMeta"></p>' +
                '<p class="db-detail-body" id="dbDetailBody"></p>' +
                '<div class="db-detail-attachments" id="dbDetailAttachments"></div>' +
            '</div>';
        document.body.appendChild(modal);

        modal.addEventListener('click', function (e) {
            if (e.target === modal || e.target.classList.contains('db-detail-close')) {
                closeDetail();
            }
        });
        return modal;
    }

    function findPostById(postId) {
        postId = parseInt(postId, 10) || 0;
        for (var i = 0; i < lastPosts.length; i++) {
            if (parseInt(lastPosts[i].id, 10) === postId) return lastPosts[i];
        }
        return null;
    }

    function openDetail(postOrSlide) {
        if (!postOrSlide) return;
        var post = postOrSlide.id ? postOrSlide : findPostById(postOrSlide.postId);
        if (!post && (postOrSlide.title || postOrSlide.src)) {
            post = {
                id: postOrSlide.postId || 0,
                title: postOrSlide.title || 'Announcement',
                body: postOrSlide.body || '',
                media: postOrSlide.src ? [postOrSlide.src] : (postOrSlide.media || []),
                link_url: postOrSlide.link_url || '',
                attachments: postOrSlide.attachments || [],
                created_at: postOrSlide.created_at || '',
                publish_at: postOrSlide.publish_at || '',
                is_pinned: !!postOrSlide.pinned
            };
        }
        if (!post) return;

        var modal = ensureModal();
        var mediaEl = document.getElementById('dbDetailMedia');
        var titleEl = document.getElementById('dbDetailTitle');
        var metaEl = document.getElementById('dbDetailMeta');
        var bodyEl = document.getElementById('dbDetailBody');
        var attachEl = document.getElementById('dbDetailAttachments');

        var media = Array.isArray(post.media) && post.media.length
            ? post.media
            : (postOrSlide && postOrSlide.src ? [postOrSlide.src] : []);
        mediaEl.innerHTML = media.length
            ? media.map(function (src) {
                var link = normalizeExternalUrl(post.link_url || postOrSlide.link_url || '');
                if (link) {
                    return '<a href="' + escapeHtml(link) + '" target="_blank" rel="noopener noreferrer" class="db-detail-media-link">' +
                        '<img src="' + escapeHtml(src) + '" alt="">' +
                        '</a>';
                }
                return '<img src="' + escapeHtml(src) + '" alt="">';
            }).join('')
            : '';

        titleEl.textContent = post.title || 'Announcement';
        metaEl.textContent = (post.is_pinned ? 'Pinned · ' : '') + formatTimeAgo(post.created_at || post.publish_at || '');
        bodyEl.textContent = (post.body && String(post.body).trim())
            ? post.body
            : 'No additional details provided.';

        var attachments = post.attachments || [];
        attachEl.innerHTML = attachments.map(function (path) {
            var name = String(path).split('/').pop() || 'Attachment';
            return '<a href="' + escapeHtml(path) + '" target="_blank" rel="noopener"><i class="fas fa-paperclip"></i> ' + escapeHtml(name) + '</a>';
        }).join('');

        modal.classList.add('show');
        clearAutoplay();
    }

    function closeDetail() {
        var modal = document.getElementById('dbDetailModal');
        if (modal) modal.classList.remove('show');
    }

    function renderCarousel(container, slides, intervalMs) {
        clearAutoplay();

        if (!slides.length) {
            container.innerHTML = '<div class="digital-bulletin-empty"><i class="fas fa-image"></i><p>No bulletin images yet.</p></div>';
            return;
        }

        var slidesHtml = slides.map(function (slide, i) {
            var hasLink = !!normalizeExternalUrl(slide.link_url || '');
            return '<div class="db-slide' + (i === 0 ? ' active' : '') + (hasLink ? ' db-slide-linked' : '') + '" data-index="' + i + '" data-post-id="' + escapeHtml(String(slide.postId || '')) + '">' +
                '<img src="' + escapeHtml(slide.src) + '" alt="' + escapeHtml(slide.title || 'Bulletin image') + '" class="db-slide-img" data-index="' + i + '">' +
                '</div>';
        }).join('');

        var dotsHtml = slides.map(function (_, i) {
            return '<button type="button" class="db-dot' + (i === 0 ? ' active' : '') + '" data-index="' + i + '" aria-label="Go to slide ' + (i + 1) + '"></button>';
        }).join('');

        container.innerHTML =
            '<div class="digital-bulletin-carousel" data-count="' + slides.length + '">' +
            '<div class="db-slide-track">' +
            '<button type="button" class="db-arrow prev" aria-label="Previous image"><i class="fas fa-chevron-left"></i></button>' +
            slidesHtml +
            '<button type="button" class="db-arrow next" aria-label="Next image"><i class="fas fa-chevron-right"></i></button>' +
            '</div>' +
            (slides.length > 1 ? '<div class="db-dots">' + dotsHtml + '</div>' : '') +
            '</div>';

        var index = 0;
        var root = container.querySelector('.digital-bulletin-carousel');
        var slideEls = root.querySelectorAll('.db-slide');
        var dotEls = root.querySelectorAll('.db-dot');

        function goTo(next) {
            index = (next + slides.length) % slides.length;
            slideEls.forEach(function (el, i) {
                el.classList.toggle('active', i === index);
            });
            dotEls.forEach(function (el, i) {
                el.classList.toggle('active', i === index);
            });
        }

        function restartAutoplay() {
            clearAutoplay();
            if (slides.length < 2) return;
            activeTimer = setInterval(function () {
                goTo(index + 1);
            }, intervalMs);
        }

        root.querySelector('.db-arrow.prev').addEventListener('click', function (e) {
            e.stopPropagation();
            goTo(index - 1);
            restartAutoplay();
        });
        root.querySelector('.db-arrow.next').addEventListener('click', function (e) {
            e.stopPropagation();
            goTo(index + 1);
            restartAutoplay();
        });
        dotEls.forEach(function (dot) {
            dot.addEventListener('click', function (e) {
                e.stopPropagation();
                goTo(parseInt(dot.getAttribute('data-index'), 10) || 0);
                restartAutoplay();
            });
        });

        // Click the image/slide: open linked webpage if URL is set, otherwise announcement details
        slideEls.forEach(function (slideEl) {
            slideEl.addEventListener('click', function (e) {
                if (e.target.closest('.db-arrow') || e.target.closest('.db-dot')) {
                    return;
                }
                var i = parseInt(slideEl.getAttribute('data-index'), 10);
                if (Number.isNaN(i)) i = index;
                var slide = slides[i];
                if (!slide) return;
                e.preventDefault();
                e.stopPropagation();
                if (openExternalUrl(slide.link_url)) {
                    return;
                }
                openDetail(slide);
            });
        });

        restartAutoplay();
    }

    function renderAnnouncementList(container, posts) {
        if (!container) return;

        var items = collectListPosts(posts);
        var listHtml;
        if (!items.length) {
            listHtml = '<div class="db-announcement-empty">No announcements right now.</div>';
        } else {
            listHtml = items.map(function (post) {
                var details = (post.body || '').trim();
                if (!details) {
                    details = hasImages(post)
                        ? 'Tap to view announcement details.'
                        : 'No additional details.';
                }
                return '<div class="db-announcement-item' + (post.is_pinned ? ' pinned' : '') + '" data-post-id="' + escapeHtml(String(post.id)) + '">' +
                    '<div class="db-announcement-icon"><i class="fas fa-bullhorn"></i></div>' +
                    '<div class="db-announcement-content">' +
                        '<p class="db-announcement-title">' + escapeHtml(post.title || 'Announcement') +
                        (post.is_pinned ? ' <span class="db-pin-tag">Pinned</span>' : '') +
                        '</p>' +
                        '<p class="db-announcement-details">' + escapeHtml(details) + '</p>' +
                        '<p class="db-announcement-time">' + escapeHtml(formatTimeAgo(post.created_at || post.publish_at || '')) + '</p>' +
                    '</div>' +
                '</div>';
            }).join('');
        }

        container.innerHTML =
            '<div class="db-announcement-section">' +
                '<h3 class="db-announcement-heading">Announcement</h3>' +
                '<div class="db-announcement-list">' + listHtml + '</div>' +
            '</div>';

        container.querySelectorAll('.db-announcement-item').forEach(function (item) {
            item.addEventListener('click', function () {
                openDetail(findPostById(item.getAttribute('data-post-id')));
            });
        });
    }

    async function mount(options) {
        var root = typeof options.root === 'string'
            ? document.querySelector(options.root)
            : options.root;
        if (!root) return;

        var carouselEl = root.querySelector('[data-db-carousel]');
        var listEl = root.querySelector('[data-db-announcements]');
        if (!carouselEl) {
            root.innerHTML = '<div data-db-carousel></div><div data-db-announcements></div>';
            carouselEl = root.querySelector('[data-db-carousel]');
            listEl = root.querySelector('[data-db-announcements]');
        } else if (!listEl) {
            listEl = document.createElement('div');
            listEl.setAttribute('data-db-announcements', '');
            root.appendChild(listEl);
        }

        var apiUrl = options.apiUrl || 'api/bulletin_board.php';
        var audience = options.audience || 'all';
        var intervalMs = options.intervalMs || 5000;
        var openPostId = parseInt(options.openPostId, 10) || 0;

        carouselEl.classList.add('digital-bulletin-root');
        carouselEl.innerHTML = '<div class="digital-bulletin-empty"><i class="fas fa-spinner fa-spin"></i><p>Loading bulletin...</p></div>';
        listEl.innerHTML = '';

        try {
            var res = await fetch(apiUrl + '?audience=' + encodeURIComponent(audience) + '&status=active', {
                credentials: 'same-origin'
            });
            var json = await res.json();
            if (!json.success) {
                throw new Error(json.message || 'Failed to load bulletin');
            }

            lastPosts = json.data || [];
            var slides = collectImageSlides(lastPosts);
            renderCarousel(carouselEl, slides, intervalMs);
            renderAnnouncementList(listEl, lastPosts);

            if (openPostId) {
                openDetail(findPostById(openPostId));
            }
        } catch (err) {
            clearAutoplay();
            carouselEl.innerHTML = '<div class="digital-bulletin-empty"><p>' + escapeHtml(err.message || 'Unable to load bulletin') + '</p></div>';
            listEl.innerHTML = '';
        }
    }

    global.DigitalBulletin = {
        mount: mount,
        openDetailById: function (id) {
            openDetail(findPostById(id));
        },
        closeDetail: closeDetail
    };
})(window);
