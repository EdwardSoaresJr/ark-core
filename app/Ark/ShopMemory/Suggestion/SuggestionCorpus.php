<?php

namespace App\Ark\ShopMemory\Suggestion;

/**
 * Corpora Shop Memory serves. Providers declare which they answer.
 * Engine and projections use corpora — never concern/labor product names.
 */
enum SuggestionCorpus: string
{
    /** Customer / problem language — what problem are we tracking? */
    case ProblemLanguage = 'problem_language';

    /** Repair / work language — what work are we selling? */
    case WorkLanguage = 'work_language';
}
