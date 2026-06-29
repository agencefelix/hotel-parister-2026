import rewriteUrl from "./rewrite-url";
import resetCounter from "./reset-counter";
import loadItems from "./load-items";

/**
 * Refresh
 *
 * @copyright 2026
 * @author Sébastien FOURNIER <contact@sebastien-fournier.com>
 * @licence under the MIT License (LICENSE.txt)
 */

export default function(body, uri, response, loader, term = null) {

    let items = $(response.html).find(".item");

    rewriteUrl(uri);
    resetCounter(body, items.length);
    loadItems(body, items, loader, term);
}