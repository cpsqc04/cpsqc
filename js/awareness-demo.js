/**
 * Auto-load realistic Brgy. San Agustin demo data into Event List / Event Reports.
 * Runs quietly from the Awareness module — no admin seed URL or toolbar button needed.
 */
(function (global) {
    'use strict';

    var seedUrl = 'api/seed_awareness_events_sample.php';
    var loading = false;
    var autoSeedAttempted = false;

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

    function seedChanged(result) {
        if (!result) {
            return false;
        }
        return (
            (result.events_inserted || 0) > 0 ||
            (result.reports_inserted || 0) > 0 ||
            (result.removed || 0) > 0
        );
    }

    async function tryAutoSeed() {
        if (autoSeedAttempted) {
            return null;
        }
        autoSeedAttempted = true;
        try {
            var result = await loadDemoData();
            return seedChanged(result) ? result : null;
        } catch (e) {
            console.warn('Awareness demo auto-seed skipped:', e);
            return null;
        }
    }

    global.AwarenessDemo = {
        loadDemoData: loadDemoData,
        tryAutoSeed: tryAutoSeed,
        seedChanged: seedChanged
    };
})(window);
