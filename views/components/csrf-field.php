<?php

use Core\Security\Csrf;

/**
 * Renders the hidden CSRF input every state-changing dashboard form
 * must include (Section 6.k/6.l) — the create-ad form, the connect-app
 * modal, and any future form that POSTs/PATCHes. Front-end JS reads
 * this same value to set the `X-CSRF-Token` header on fetch() calls
 * for forms that submit via JS rather than a native form post.
 *
 * $id defaults to "_csrf" (every existing caller's assumption) — pass
 * a distinct id when rendering more than one on the same page, e.g.
 * the shared logout control in views/partials/scripts.php, so it
 * never collides with a page's own form (create-ad.php has its own
 * "_csrf" input already; two elements sharing an id is invalid HTML
 * and document.getElementById() would only ever see the first one).
 */
function csrf_field(string $id = '_csrf'): string
{
    return '<input type="hidden" name="_csrf" id="' . htmlspecialchars($id) . '" value="' . htmlspecialchars(Csrf::token()) . '">';
}
