# Dual-map annotation — proposed implementation batches

Status: breakdown approved; batch 1 explicitly authorized for local implementation and commit.
Implementation base: `add-historic-maps1` (`c09ee1c`). Batch 1 is tracked locally; no remote tracker destination has been selected.
Source: the user-approved dual-map annotation specification.

All new UI copy, accessible labels, errors, ticket content, and commit messages must be in English.
Preserve original source metadata and historical image content. Each approved batch should produce
an independently verifiable increment and a focused commit after its relevant checks pass.

## 1. Browse the seeded historical map beside existing walking tours

Blocked by: None — can start immediately.

Deliver a persistent historical-map catalog, initially containing the supplied BnF map and an
independent snapshot of its ten control-point pairs. Replace the existing map area with the
unwarped image viewer and modern map, preserving existing modern-map tour behavior. Expose the
catalog through a read interface and a collapsible map list. Show imported paired points with
matching identifiers and linked selection. Support independent pan/zoom and stacked small-screen layout.

Acceptance:
- Fresh installation and plugin upgrade initialize the new data without replacing tour data.
- Repeated initialization does not duplicate the map or overwrite later edits.
- Reloading reads the seeded map and points from persistence; source provenance is retained.
- The original image is never displayed as a warped geographic tile layer on the left.
- Existing modern-map routes, filters, and details remain usable.
- Loading failures have English feedback and do not silently disable the modern map.
- Establish a reproducible Omeka/database validation environment or document the exact unavailable
  dependency and distinguish performed checks from unperformed integration checks.

## 2. Create and save estimated annotations from either map

Blocked by: 1 — Browse the seeded historical map beside existing walking tours.

Enable a pencil control on each map. A click creates an annotation at the clicked location and
estimates the counterpart using the initial calibration. Save or cancel the draft. Persist the
source side, original click, estimated counterpart, status, and calibration revision. Expose a
point-selection hook for future historical information.

Acceptance:
- Both creation directions work, with stable pairing identifiers and linked selection.
- Estimates are visibly distinguished from confirmed control pairs and never train the transform.
- Save persists across reloads; cancellation and failed requests do not create phantom saved points.
- New writes enforce existing editor authorization, request protection, input validation, and
  atomic revision checks from their introduction; stale writes cannot overwrite newer data.
- Unreliable predictions preserve the clicked position; extrapolated predictions are flagged.
- Verify coordinate order, image-axis conventions, round trips where valid, and representative
  control-point and out-of-range cases without treating fitted points as independent accuracy evidence.

## 3. Refine and confirm point pairs to update calibration

Blocked by: 2 — Create and save estimated annotations from either map.

Provide the point adjustment tools. Move either endpoint while keeping the other fixed, or manually
place a missing counterpart when estimation is unreliable. Explicitly confirm and save a pair to
use it as a control point. Support editing imported and subsequently confirmed pairs.

Acceptance:
- Explicit confirmation is required even when the initial estimate needs no adjustment.
- Saving a calibration change fixes confirmed endpoints and recalculates only the predicted side
  of unconfirmed annotations, preserving every original click.
- Cancellation restores the previous saved state; a failed save retains the editable draft.
- Degenerate, ambiguous, or unstable transforms do not produce invented counterpart coordinates.
- Calibration revisions and dependent predictions form a consistent saved state.
- UI selection/update hooks support dependent display layers without allowing those layers to
  modify source tour data.

## 4. Delete points and undo the latest calibration change

Blocked by: 3 — Refine and confirm point pairs to update calibration.

Allow saved deletion of ordinary annotations and control pairs, with cancellation before saving.
Expose an undo action for the latest calibration mutation, including addition, adjustment, or deletion.

Acceptance:
- Removing an ordinary annotation leaves calibration unchanged.
- Removing a control pair updates predictions while preserving confirmed endpoints and source clicks.
- Insufficient or unusable calibration results in explicit unavailable estimates, never silent nonsense.
- Undo restores the preceding calibration and consistently updates dependent predictions.
- Undo persists across reloads and cannot overwrite an intervening concurrent edit.
- Historical-map records, source tour data, and unrelated annotations are not deleted by calibration undo.

## 5. Recover shared editing conflicts without losing drafts

Blocked by: 4 — Delete points and undo the latest calibration change.

Complete the shared-editor workflow across creation, confirmation, editing, deletion, and undo.
When another editor saves first, show an English conflict message, retain the current draft, and
allow the editor to load current saved data and review their intended change before retrying.

Acceptance:
- Two-session checks demonstrate atomic conflict detection across all mutation types.
- No stale request silently overwrites a newer point or calibration revision.
- Recovery preserves the user's intended edit for review without automatically resubmitting stale data.
- Read-only visitors can browse but cannot perform mutations; editor access follows existing Omeka roles.
- Network errors, session expiry, and permission errors preserve unsaved work and provide clear feedback.

## 6. Locate existing tour stops on the historical image

Blocked by: 3 — Refine and confirm point pairs to update calibration.

Display historical-image counterparts for existing tour stops where conversion is usable. Preserve
modern-map routes and existing detail behavior while making route stops visually distinct from
editable annotation pairs.

Acceptance:
- Tour filters update the relevant modern and historical stop displays consistently.
- Selecting a displayed stop preserves access to existing details and highlights its counterpart.
- Historical stop estimates respond to the shared calibration update mechanism, including undo
  once available; unavailable estimates are handled explicitly.
- Historical-image route lines are not drawn in this version.
- No calibration or annotation action writes to original tour-stop coordinates.

## Execution and verification

Suggested serial order: 1, 2, 3, 4, 5, 6. Ticket 6 depends only on 3 and may be implemented earlier.
There is no permission to publish to a remote tracker or push commits implied by this draft.
The requested implementation consists of local incremental work and commits after the breakdown is approved.

Run checks relevant to each increment, including numerical/state-transition checks and Omeka persistence
and authorization checks where applicable. Inspect the desktop and small-screen UI in a browser.
At completion, exercise the full two-direction create → adjust → confirm → save → reload flow,
calibration deletion/undo, two-session conflicts, and existing tour interactions. Report unavailable
integration checks rather than describing a static fixture as a tested Omeka deployment.
