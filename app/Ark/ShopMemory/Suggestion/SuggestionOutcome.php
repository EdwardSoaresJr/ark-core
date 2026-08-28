<?php

namespace App\Ark\ShopMemory\Suggestion;

/**
 * Frozen outcome meanings for shop_memory_suggestion_events.
 *
 * accepted_unchanged — selected suggestion became authority without textual modification.
 * accepted_edited — selected suggestion was modified before authority creation.
 * ignored — suggestions were shown, but the advisor typed and created different authority.
 * dismissed — suggestion surface was deliberately closed or cleared without authority creation.
 *
 * Do not infer dismissed merely because the popup closed after a concern was created.
 * Record one terminal outcome per suggestion interaction where practical.
 */
enum SuggestionOutcome: string
{
    case AcceptedUnchanged = 'accepted_unchanged';
    case AcceptedEdited = 'accepted_edited';
    case Ignored = 'ignored';
    case Dismissed = 'dismissed';
}
