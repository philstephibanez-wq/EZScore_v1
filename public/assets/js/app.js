(function () {
    'use strict';

    function initShell() {
        var shell = document.querySelector('[data-ez-shell]');
        var button = document.querySelector('[data-ez-menu-button]');
        var backdrop = document.querySelector('[data-ez-drawer-backdrop]');
        if (!shell || !button || !backdrop) return;

        function setOpen(open) {
            shell.classList.toggle('ez-drawer-open', open);
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
            document.body.classList.toggle('ez-no-scroll', open);
        }

        button.addEventListener('click', function () { setOpen(!shell.classList.contains('ez-drawer-open')); });
        backdrop.addEventListener('click', function () { setOpen(false); });
        shell.querySelectorAll('.ez-drawer-nav a').forEach(function (link) {
            link.addEventListener('click', function () { setOpen(false); });
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && shell.classList.contains('ez-drawer-open')) {
                setOpen(false);
                button.focus();
            }
        });
        setOpen(false);
    }

    function formState(form) {
        var values = [];
        Array.from(form.elements).forEach(function (field) {
            if (!field.name || field.disabled || field.name === '_token') return;
            if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) {
                values.push(field.name + '=');
                return;
            }
            values.push(field.name + '=' + String(field.value));
        });
        return values.join('&');
    }

    function actionButtons(form) {
        var buttons = Array.from(form.querySelectorAll('button[type="submit"],input[type="submit"]'));
        if (form.id) {
            document.querySelectorAll('[form="' + form.id + '"]').forEach(function (button) {
                if (!buttons.includes(button)) buttons.push(button);
            });
        }
        return buttons;
    }

    function initDirtyTracking() {
        document.querySelectorAll('form[data-dirty-track]').forEach(function (form) {
            var initial = formState(form);

            function refresh() {
                var dirty = formState(form) !== initial;
                form.classList.toggle('is-dirty', dirty);
                actionButtons(form).forEach(function (button) {
                    button.classList.toggle('is-dirty-action', dirty);
                });
            }

            form.addEventListener('input', refresh);
            form.addEventListener('change', refresh);
            form.addEventListener('reset', function () { window.setTimeout(refresh, 0); });
            refresh();
        });
    }

    function initExistingUi() {
        if (!window.jQuery) return;
        var $ = window.jQuery;

        $('.owner-type').on('change', function () {
            $('.group-owner').prop('hidden', $(this).val() !== 'group');
        }).trigger('change');

        $('.workflow-tab').on('click', function () {
            var step = String($(this).data('step'));
            $('.workflow-tab').removeClass('active');
            $(this).addClass('active');
            $('.step-lyrics').toggle(step === '2');
            $('.editor-panel').prop('open', step === '2');
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.documentElement.classList.add('js-ready');
        initShell();
        initDirtyTracking();
        initExistingUi();
    });
})();
