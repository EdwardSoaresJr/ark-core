<?php

namespace App\Ark\Operations\Leads;

final class LeadConcernComparator
{
    public function concernKey(string $concern): string
    {
        $normalized = preg_replace('/\s+/', ' ', strtolower(trim($concern)));

        return is_string($normalized) ? $normalized : '';
    }

    public function isAcknowledgmentMessage(string $concern): bool
    {
        $normalized = preg_replace('/\s+/', ' ', strtolower(trim($concern)));

        if (! is_string($normalized) || $normalized === '' || $normalized === '(attachment)') {
            return false;
        }

        if (strlen($normalized) > 60) {
            return false;
        }

        $compact = preg_replace('/[^\w\s]/', '', $normalized);

        if (! is_string($compact) || $compact === '') {
            return false;
        }

        return preg_match(
            '/^(ok|okay|k|thanks|thank you|thank u|thx|ty|got it|perfect|sounds good|will do|see you|great|awesome|cool|noted|appreciate it)(\s+(ok|thanks|thank you|so much|you))?$/',
            $compact,
        ) === 1;
    }

    public function isMateriallyDistinctConcern(string $existing, string $incoming): bool
    {
        if ($this->concernKey($existing) === $this->concernKey($incoming)) {
            return false;
        }

        if ($this->isAcknowledgmentMessage($incoming) || $this->isAcknowledgmentMessage($existing)) {
            return false;
        }

        $existingWords = $this->significantWords($existing);
        $incomingWords = $this->significantWords($incoming);

        if ($existingWords === [] || $incomingWords === []) {
            return false;
        }

        $intersection = array_intersect($existingWords, $incomingWords);
        $union = array_values(array_unique([...$existingWords, ...$incomingWords]));

        if ($union === []) {
            return false;
        }

        return (count($intersection) / count($union)) < 0.12;
    }

    public function belongsToSameThread(string $representativeConcern, string $candidateConcern): bool
    {
        if ($this->isAcknowledgmentMessage($candidateConcern)) {
            return true;
        }

        return ! $this->isMateriallyDistinctConcern($representativeConcern, $candidateConcern);
    }

    /**
     * @return list<string>
     */
    public function significantWords(string $concern): array
    {
        $stopWords = [
            'a', 'an', 'the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with',
            'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did',
            'will', 'would', 'could', 'should', 'may', 'might', 'must', 'shall', 'can', 'need',
            'i', 'me', 'my', 'we', 'our', 'you', 'your', 'he', 'she', 'it', 'they', 'them', 'this', 'that',
            'from', 'as', 'by', 'not', 'no', 'yes', 'am', 'im', 'hello', 'hi', 'hey', 'name', 'looking',
        ];

        $normalized = preg_replace('/[^\w\s]/', ' ', strtolower(trim($concern)));

        if (! is_string($normalized) || $normalized === '') {
            return [];
        }

        $tokens = preg_split('/\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(
            $tokens,
            fn (string $word): bool => strlen($word) > 2 && ! in_array($word, $stopWords, true),
        )));
    }
}
