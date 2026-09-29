(function () {
    'use strict';

    function installSortableList(list) {
        let dragged = null;

        list.addEventListener('dragstart', function (event) {
            const item = event.target.closest('[data-tab-id]');
            if (!item) return;
            dragged = item;
            item.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', item.dataset.tabId);
        });

        list.addEventListener('dragend', function () {
            if (dragged) dragged.classList.remove('is-dragging');
            dragged = null;
        });

        list.addEventListener('dragover', function (event) {
            event.preventDefault();
            if (!dragged) return;

            const target = event.target.closest('[data-tab-id]');
            if (!target || target === dragged) return;

            const bounds = target.getBoundingClientRect();
            const after = event.clientY > bounds.top + bounds.height / 2;
            list.insertBefore(dragged, after ? target.nextSibling : target);
        });

        list.addEventListener('click', function (event) {
            const button = event.target.closest('[data-move]');
            if (!button) return;
            const item = button.closest('[data-tab-id]');
            if (button.dataset.move === 'up' && item.previousElementSibling) {
                list.insertBefore(item, item.previousElementSibling);
            } else if (button.dataset.move === 'down' && item.nextElementSibling) {
                list.insertBefore(item.nextElementSibling, item);
            }
        });
    }

    document.querySelectorAll('[data-library-sortable]').forEach(installSortableList);
})();
