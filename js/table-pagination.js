(function (global) {
    'use strict';

    function create(options) {
        const opts = options || {};
        const pageSize = Math.max(1, Number(opts.pageSize) || 10);
        const itemLabel = opts.itemLabel || 'items';
        let page = 1;

        function els() {
            return {
                info: opts.pageInfoId ? document.getElementById(opts.pageInfoId) : null,
                prev: opts.prevBtnId ? document.getElementById(opts.prevBtnId) : null,
                next: opts.nextBtnId ? document.getElementById(opts.nextBtnId) : null
            };
        }

        function updateControls(total) {
            const totalPages = Math.max(1, Math.ceil(total / pageSize));
            page = Math.min(Math.max(1, page), totalPages);
            const nodes = els();
            if (nodes.info) {
                nodes.info.textContent = total === 0
                    ? ('No ' + itemLabel + ' to display')
                    : ('Page ' + page + ' of ' + totalPages + ' · ' + total + ' ' + itemLabel);
            }
            if (nodes.prev) nodes.prev.disabled = page <= 1 || total === 0;
            if (nodes.next) nodes.next.disabled = page >= totalPages || total === 0;
            return { page: page, totalPages: totalPages, pageSize: pageSize, total: total };
        }

        return {
            get page() { return page; },
            set page(value) { page = Math.max(1, Number(value) || 1); },
            get pageSize() { return pageSize; },
            reset: function () { page = 1; },
            change: function (delta, total) {
                const totalPages = Math.max(1, Math.ceil((Number(total) || 0) / pageSize));
                page = Math.min(totalPages, Math.max(1, page + Number(delta || 0)));
            },
            slice: function (rows) {
                const list = Array.isArray(rows) ? rows : [];
                const meta = updateControls(list.length);
                const start = (meta.page - 1) * pageSize;
                return list.slice(start, start + pageSize);
            }
        };
    }

    global.AlertaraTablePager = { create: create };
})(window);
