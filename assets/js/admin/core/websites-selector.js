/**
 * Websites selector
 *
 * @copyright 2026
 * @author Sébastien FOURNIER <contact@sebastien-fournier.com>
 * @licence under the MIT License (LICENSE.txt)
 */

export default function () {

    let websitesSelector = $('#websites-selector-form');

    if (websitesSelector.length > 0) {

        let websiteId = websitesSelector.data('id');
        websitesSelector.find('option[value=' + websiteId + ']').prop('selected', true);

        $('body').on('change', '#websites-selector-form select', function () {
            $(this).closest('form').submit();
        });
    }
}