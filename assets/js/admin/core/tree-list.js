/**
 * Tree list
 *
 * @copyright 2026
 * @author Sébastien FOURNIER <contact@sebastien-fournier.com>
 * @licence under the MIT License (LICENSE.txt)
 */

$('body .tree-list').on('click', '.caret', function () {

    let child = $(this).closest('li.item').find('.nested').first();

    if (child.hasClass('active')) {
        child.removeClass('active');
    } else {
        child.addClass('active');
    }
});