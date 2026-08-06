/**
 * Shared fullscreen photo viewer with close button, backdrop click, and Escape.
 * Usage: AlertaraPhotoLightbox.open(src [, altText])
 */
(function (global) {
    'use strict';

    var MODAL_ID = 'alertara-photo-lightbox';

    function closeExisting() {
        var existing = document.getElementById(MODAL_ID);
        if (existing && existing.parentNode) {
            existing.parentNode.removeChild(existing);
        }
        if (global.__alertaraPhotoLightboxKeyHandler) {
            document.removeEventListener('keydown', global.__alertaraPhotoLightboxKeyHandler);
            global.__alertaraPhotoLightboxKeyHandler = null;
        }
    }

    function open(src, altText) {
        var photoSrc = String(src || '').trim();
        if (!photoSrc) {
            alert('No photo available.');
            return;
        }

        closeExisting();

        var modal = document.createElement('div');
        modal.id = MODAL_ID;
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-label', 'Photo viewer');
        modal.style.cssText = 'position:fixed;z-index:5000;inset:0;background:rgba(0,0,0,0.92);display:flex;align-items:center;justify-content:center;padding:1rem;';

        var img = document.createElement('img');
        img.src = photoSrc;
        img.alt = altText || 'Photo';
        img.style.cssText = 'max-width:95%;max-height:95%;border-radius:8px;object-fit:contain;box-shadow:0 10px 40px rgba(0,0,0,0.45);background:#fff;';
        img.onclick = function (e) { e.stopPropagation(); };
        img.onerror = function () {
            alert('Failed to load photo.');
            closeExisting();
        };

        var closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.innerHTML = '&times;';
        closeBtn.setAttribute('aria-label', 'Close photo');
        closeBtn.title = 'Close';
        closeBtn.style.cssText = 'position:absolute;top:16px;right:20px;width:48px;height:48px;border:none;border-radius:50%;background:rgba(255,255,255,0.22);color:#fff;font-size:2rem;line-height:1;cursor:pointer;z-index:5001;display:flex;align-items:center;justify-content:center;';
        closeBtn.onmouseover = function () { this.style.background = 'rgba(255,255,255,0.35)'; };
        closeBtn.onmouseout = function () { this.style.background = 'rgba(255,255,255,0.22)'; };

        var onKeyDown = function (e) {
            if (e.key === 'Escape' || e.keyCode === 27) {
                closeExisting();
            }
        };
        global.__alertaraPhotoLightboxKeyHandler = onKeyDown;

        modal.onclick = closeExisting;
        closeBtn.onclick = function (e) {
            e.stopPropagation();
            closeExisting();
        };

        document.addEventListener('keydown', onKeyDown);
        modal.appendChild(closeBtn);
        modal.appendChild(img);
        document.body.appendChild(modal);
    }

    global.AlertaraPhotoLightbox = {
        open: open,
        close: closeExisting
    };

    // Backward-compatible global helpers used by older pages.
    global.viewPhoto = function (src) {
        open(src, 'Photo');
    };
    global.viewPhotoFull = function (src) {
        open(src, 'Photo');
    };
})(window);
