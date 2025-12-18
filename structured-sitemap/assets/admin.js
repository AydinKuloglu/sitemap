(function ($) {
    'use strict';

    function updateOrder(listSelector, fieldSelector) {
        var ids = [];
        $(listSelector)
            .find('.structured-sitemap-item')
            .each(function () {
                ids.push($(this).data('id'));
            });

        $(fieldSelector).val(ids.join(','));
    }

    function bindSortable(listSelector, fieldSelector) {
        $(listSelector).sortable({
            placeholder: 'structured-sitemap-placeholder',
            update: function () {
                updateOrder(listSelector, fieldSelector);
            },
        });

        updateOrder(listSelector, fieldSelector);
    }

    $(function () {
        bindSortable('#structured-sitemap-pages', '#structured-sitemap-pages-order');
        bindSortable('#structured-sitemap-posts', '#structured-sitemap-posts-order');
    });
})(jQuery);
