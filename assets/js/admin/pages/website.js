import '../../../scss/admin/pages/website.scss';

/**
 * Website
 *
 * @copyright 2026
 * @author Sébastien FOURNIER <contact@sebastien-fournier.com>
 * @licence under the MIT License (LICENSE.txt)
 */

$('body').on('change', '.input-theme', function () {
    let inputs = $(this).closest('.themes-group').find('.input-theme');
    inputs.each(function () {
        let input = $(this);
        let card = input.closest('.card');
        if (input.is(':checked') && !input.hasClass('active')) {
            input.addClass('active');
            card.addClass('active');
        } else if (!input.is(':checked')) {
            input.removeClass('active');
            card.removeClass('active');
        }
    });
});