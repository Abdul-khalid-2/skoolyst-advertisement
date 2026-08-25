<?php
/**
 * Renders ONE reusable "are you sure?" confirmation modal shell per page.
 * Instead of writing separate modal markup for every destructive action
 * (delete ad, regenerate API key, etc.), a page includes this once and
 * JS fills in the title/body/button label before showing it — see
 * confirmAction() in js/dashboard.js.
 */
function modal_confirm(string $id = 'confirm-modal'): string
{
    $id = htmlspecialchars($id);
    return '
    <div class="modal fade db-modal" id="' . $id . '" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="' . $id . '-title">Are you sure?</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" id="' . $id . '-body"></div>
          <div class="modal-footer">
            <button type="button" class="btn btn-sk-outline" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-sk-primary" id="' . $id . '-confirm">Confirm</button>
          </div>
        </div>
      </div>
    </div>';
}
