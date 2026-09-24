(function () {
    'use strict';

    var interactiveSelector = [
        'a[href]',
        'button:not(:disabled)',
        'input[type="submit"]:not(:disabled)',
        'input[type="button"]:not(:disabled)',
        'input[type="checkbox"]:not(:disabled)',
        'input[type="radio"]:not(:disabled)',
        'select:not(:disabled)',
        'summary',
        'label[for]',
        '[role="button"]:not([aria-disabled="true"])'
    ].join(',');

    function interactiveFrom(target) {
        if (!(target instanceof Element)) {
            return null;
        }

        return target.closest(interactiveSelector);
    }

    document.addEventListener('pointerover', function (event) {
        var element = interactiveFrom(event.target);
        if (!element) {
            return;
        }

        element.classList.add('is-ui-hover');
    }, true);

    document.addEventListener('pointerout', function (event) {
        var element = interactiveFrom(event.target);
        if (!element) {
            return;
        }

        var related = event.relatedTarget;
        if (related instanceof Node && element.contains(related)) {
            return;
        }

        element.classList.remove('is-ui-hover');
    }, true);

    document.addEventListener('focusin', function (event) {
        var element = interactiveFrom(event.target);
        if (element) {
            element.classList.add('is-ui-focus');
        }
    }, true);

    document.addEventListener('focusout', function (event) {
        var element = interactiveFrom(event.target);
        if (element) {
            element.classList.remove('is-ui-focus');
        }
    }, true);
})();
