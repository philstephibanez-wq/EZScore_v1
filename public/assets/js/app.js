(function ($) {
    'use strict';
    $(function () {
        document.documentElement.classList.add('js-ready');

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
        $('.workflow-tab[data-step="1"]').trigger('click');

        $('.mix-row input').on('input change', function () {
            $(this).closest('.mix-row').addClass('touched');
        });
    });
})(jQuery);
