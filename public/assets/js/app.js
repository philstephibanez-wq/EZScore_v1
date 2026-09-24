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

        button.addEventListener('click', function () {
            setOpen(!shell.classList.contains('ez-drawer-open'));
        });

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

    function initInteractionFeedback() {
        var selector = [
            'a[href]',
            'button:not(:disabled)',
            'input:not([type="hidden"]):not(:disabled)',
            'select:not(:disabled)',
            'textarea:not(:disabled)',
            'summary',
            '[role="button"]:not([aria-disabled="true"])'
        ].join(',');

        var previousStyles = new WeakMap();

        function interactive(target) {
            return target instanceof Element ? target.closest(selector) : null;
        }

        function enter(element) {
            if (previousStyles.has(element)) return;

            previousStyles.set(element, {
                transform: element.style.transform,
                filter: element.style.filter,
                boxShadow: element.style.boxShadow,
                borderColor: element.style.borderColor,
                backgroundColor: element.style.backgroundColor
            });

            if (element.matches('a.module-card, a.song-tile, .dashboard-grid a, .card-grid a')) {
                element.style.transform = 'translateY(-4px)';
                element.style.filter = 'brightness(1.22)';
                element.style.borderColor = '#8bd0ff';
                element.style.backgroundColor = '#203446';
                element.style.boxShadow =
                    '0 0 0 3px rgba(107,182,255,.48), 0 14px 30px rgba(0,0,0,.52)';
                return;
            }

            element.style.filter = 'brightness(1.24)';
            element.style.borderColor = '#8bd0ff';
            element.style.boxShadow =
                '0 0 0 2px rgba(107,182,255,.30), 0 6px 16px rgba(0,0,0,.38)';
        }

        function leave(element) {
            var previous = previousStyles.get(element);
            if (!previous) return;

            Object.assign(element.style, previous);
            previousStyles.delete(element);
        }

        document.addEventListener('pointerover', function (event) {
            var element = interactive(event.target);
            if (element) enter(element);
        }, true);

        document.addEventListener('pointerout', function (event) {
            var element = interactive(event.target);
            if (!element) return;

            if (event.relatedTarget instanceof Node && element.contains(event.relatedTarget)) {
                return;
            }

            leave(element);
        }, true);
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
        initInteractionFeedback();
        initExistingUi();
    });
})();
