/**
 * Load realistic Brgy. San Agustin demo data into Event List / Event Reports.
 * Used from the Awareness module UI — admins never need a separate seed URL.
 */
(function (global) {
    'use strict';

    var seedUrl = 'api/seed_awareness_events_sample.php';
    var loading = false;
    var autoSeedAttempted = false;

    function showBanner(bannerId, message) {
        var banner = document.getElementById(bannerId);
        if (!banner) return;
        banner.textContent = message;
        banner.hidden = false;
    }

    function hideBanner(bannerId) {
        var banner = document.getElementById(bannerId);
        if (banner) banner.hidden = true;
    }

    async function loadDemoData() {
        if (loading) {
            return null;
        }
        loading = true;
        try {
            var res = await fetch(seedUrl, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { Accept: 'application/json' }
            });
            var result = await res.json();
            if (!res.ok || !result.success) {
                throw new Error((result && result.message) || 'Unable to load demo data.');
            }
            return result;
        } finally {
            loading = false;
        }
    }

    function formatSeedMessage(result) {
        var inserted = (result.events_inserted || 0) + (result.reports_inserted || 0);
        var skipped = (result.events_skipped || 0) + (result.reports_skipped || 0);
        if (inserted > 0) {
            return 'Demo data loaded — ' + inserted + ' Brgy. San Agustin outreach record(s) added.';
        }
        if (skipped > 0) {
            return 'Demo data is already in the system (' + skipped + ' record(s) skipped as duplicates).';
        }
        return 'Demo data load finished.';
    }

    async function tryAutoSeed(bannerId) {
        if (autoSeedAttempted) {
            return null;
        }
        autoSeedAttempted = true;
        try {
            var result = await loadDemoData();
            if (result && ((result.events_inserted || 0) > 0 || (result.reports_inserted || 0) > 0)) {
                showBanner(bannerId, formatSeedMessage(result));
                return result;
            }
        } catch (e) {
            console.warn('Awareness demo auto-seed skipped:', e);
        }
        return null;
    }

    async function loadAndReload(reloadFn, bannerId, buttonEl) {
        if (buttonEl) {
            buttonEl.disabled = true;
            buttonEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading demo data…';
        }
        try {
            var result = await loadDemoData();
            showBanner(bannerId, formatSeedMessage(result));
            if (typeof reloadFn === 'function') {
                await reloadFn();
            }
        } catch (e) {
            console.error(e);
            alert(e.message || 'Failed to load demo data. Please try again.');
        } finally {
            if (buttonEl) {
                buttonEl.disabled = false;
                buttonEl.innerHTML = '<i class="fas fa-database"></i> Load demo data';
            }
        }
    }

    global.AwarenessDemo = {
        loadDemoData: loadDemoData,
        tryAutoSeed: tryAutoSeed,
        loadAndReload: loadAndReload,
        showBanner: showBanner,
        hideBanner: hideBanner,
        formatSeedMessage: formatSeedMessage
    };
})(window);
