/**
 * Rewrite url
 *
 * @copyright 2026
 * @author Sébastien FOURNIER <contact@sebastien-fournier.com>
 * @licence under the MIT License (LICENSE.txt)
 */

export default function(uri) {

    if(uri) {
        history.pushState({}, null, uri);
    }
    else {
        let uri = window.location.toString();
        let cleanUri = uri.substring(0, uri.indexOf("?"));
        window.history.replaceState({}, document.title, cleanUri);
    }
}