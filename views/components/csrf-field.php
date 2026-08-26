<?php

use Core\Security\Csrf;

/**
 * Renders the hidden CSRF input every state-changing dashboard form
 * must include (Section 6.k/6.l) — the create-ad form, the connect-app
 * modal, and any future form that POSTs/PATCHes. Front-end JS reads
 * this same value to set the `X-CSRF-Token` header on fetch() calls
 * for forms that submit via JS rather than a native form post.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" id="_csrf" value="' . htmlspecialchars(Csrf::token()) . '">';
}
