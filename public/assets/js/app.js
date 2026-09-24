(function () {
    'use strict';

    function initShell() {
        var shell = document.querySelector('[data-ez-shell]');
        var button = document.querySelector('[data-ez-menu-button]');
        var backdrop = document.querySelector('[data-ez-drawer-backdrop]');

        if (!shell || !button || !backdrop) {
            return;
        }

        function setOpen(open) {
            shell.classList.toggle('ez-drawer-open', open);
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
            document.body.classList.toggle('ez-no-scroll', open);
        }

        button.addEventListener('click', function () {
            setOpen(!shell.classList.contains('ez-drawer-open'));
        });

        backdrop.addEventListener('click', function () {
            setOpen(false);
        });

        shell.querySelectorAll('.ez-drawer-nav a').forEach(function (link) {
            link.addEventListener('click', function () {
                setOpen(false);
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && shell.classList.contains('ez-drawer-open')) {
                setOpen(false);
                button.focus();
            }
        });

        setOpen(false);
    }

    function initExistingUi() {
        if (!window.jQuery) {
            return;
        }

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
        initExistingUi();
    });
})();
