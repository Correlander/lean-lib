(function () {
    'use strict';

    function installLayoutEditor(editor) {
        let dragged = null;

        editor.addEventListener('dragstart', function (event) {
            const item = event.target.closest('[data-widget-id]');
            if (!item) return;
            dragged = item;
            item.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', item.dataset.widgetId);
        });

        editor.addEventListener('dragend', function () {
            if (dragged) dragged.classList.remove('is-dragging');
            dragged = null;
        });

        editor.addEventListener('dragover', function (event) {
            event.preventDefault();
            if (!dragged) return;

            const lane = event.target.closest('[data-library-lane]');
            if (!lane || !editor.contains(lane)) return;
            const target = event.target.closest('[data-widget-id]');
            if (!target || target === dragged) {
                lane.appendChild(dragged);
                return;
            }

            const bounds = target.getBoundingClientRect();
            const vertical = lane.dataset.libraryLane === 'visible' || lane.dataset.libraryLane === 'hidden';
            const after = vertical
                ? event.clientY > bounds.top + bounds.height / 2
                : event.clientX > bounds.left + bounds.width / 2;
            lane.insertBefore(dragged, after ? target.nextSibling : target);
        });

        editor.addEventListener('click', function (event) {
            const button = event.target.closest('[data-widget-hide], [data-widget-show]');
            if (!button) return;
            const item = button.closest('[data-widget-id]');
            const laneName = button.hasAttribute('data-widget-hide') ? 'hidden' : 'visible';
            const lane = editor.querySelector('[data-library-lane="' + laneName + '"]');
            if (item && lane) lane.appendChild(item);
        });

        editor.addEventListener('drop', function (event) { event.preventDefault(); });

        const form = editor.closest('form');
        if (form) form.addEventListener('submit', function () {
            editor.querySelectorAll('[data-widget-enabled]').forEach(function (input) {
                input.disabled = input.closest('[data-library-lane]').dataset.libraryLane !== 'visible';
            });
        });
    }

    function scan(root) {
        if (root.matches && root.matches('[data-library-layout-editor]')) installLayoutEditor(root);
        if (root.querySelectorAll) root.querySelectorAll('[data-library-layout-editor]').forEach(installLayoutEditor);
    }

    function start() {
        scan(document);
        if (!window.MutationObserver || !document.body) return;
        new MutationObserver(function (records) {
            records.forEach(function (record) {
                record.addedNodes.forEach(function (node) {
                    if (node.nodeType === 1) scan(node);
                });
            });
        }).observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
    else start();
})();
