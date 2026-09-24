(function () {
    'use strict';

    function state(form) {
        var data = new FormData(form);
        var values = [];
        data.forEach(function (value, key) {
            if (key !== '_token') {
                values.push(key + '=' + String(value));
            }
        });
        values.sort();
        return values.join('&');
    }

    function actionButtons(form) {
        var list = Array.from(form.querySelectorAll('button[type="submit"], input[type="submit"]'));

        if (form.id) {
            document.querySelectorAll('[form="' + CSS.escape(form.id) + '"]').forEach(function (button) {
                if (!list.includes(button)) list.push(button);
            });
        }

        return list;
    }

    function refresh(form) {
        if (!form || !form.matches('form[data-dirty-track]')) return;

        var dirty = state(form) !== form.dataset.initialState;
        form.classList.toggle('is-dirty', dirty);

        actionButtons(form).forEach(function (button) {
            button.classList.toggle('is-dirty-action', dirty);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form[data-dirty-track]').forEach(function (form) {
            form.dataset.initialState = state(form);
            refresh(form);
        });
    });

    document.addEventListener('input', function (event) {
        refresh(event.target && event.target.form);
    }, true);

    document.addEventListener('change', function (event) {
        refresh(event.target && event.target.form);
    }, true);
})();
