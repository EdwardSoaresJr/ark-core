<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Documents\HtmlPdfBuilder;

final class CustomerAccountStatementPdf
{
    public function __construct(
        private readonly HtmlPdfBuilder $pdf,
    ) {}

    /**
     * @param  array<string, mixed>  $statement
     */
    public function bytes(array $statement): string
    {
        $html = view('operations.financials.accounts-receivable.statement-document', [
            'statement' => $statement,
            'money' => fn (int $cents): string => '$'.number_format($cents / 100, 2),
            'combined' => false,
        ])->render();

        return $this->pdf->toPdfBytes($html);
    }

    /**
     * @param  list<array<string, mixed>>  $statements
     */
    public function combinedBytes(array $statements): string
    {
        $html = view('operations.financials.accounts-receivable.statement-document', [
            'statements' => $statements,
            'money' => fn (int $cents): string => '$'.number_format($cents / 100, 2),
            'combined' => true,
        ])->render();

        return $this->pdf->toPdfBytes($html);
    }
}
