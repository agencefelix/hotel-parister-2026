/**
 * Security back
 *
 * @copyright 2026
 * @author Sébastien FOURNIER <contact@sebastien-fournier.com>
 * @licence under the MIT License (LICENSE.txt)
 */

/** Import CSS */
import '../../../../scss/front/default/templates/security-back.scss';

/** Import JS */

const form = document.querySelector('form');
if (form) {
    import('../components/form/form').then(({default: Form}) => {
        new Form();
    }).catch(error => console.error(error.message));
}