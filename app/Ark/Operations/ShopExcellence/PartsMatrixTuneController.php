<?php

namespace App\Ark\Operations\ShopExcellence;

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\ShopExcellence\PartsMatrixTune\PartsMatrixTuneAssistant;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PartsMatrixTuneController
{
    public function __invoke(Request $request, PartsMatrixTuneAssistant $assistant): View
    {
        $settings = ShopSettings::current();
        $matrices = $settings->partsMatrices();
        $matrixKey = $request->string('matrix')->toString()
            ?: $settings->defaultPartsMatrix()['key'];

        [$from, $to] = $assistant->resolveDefaultRange(
            $request->string('from')->toString() ?: null,
            $request->string('to')->toString() ?: null,
        );

        $proposedMarkups = null;
        if ($request->boolean('simulate')) {
            $proposedMarkups = collect($request->input('proposed_markup', []))
                ->mapWithKeys(fn (mixed $value, mixed $index): array => [(int) $index => (string) $value])
                ->all();
        }

        $analysis = $assistant->analyze($from, $to, $matrixKey, $proposedMarkups);

        return view('operations.owner.parts-matrix-tune', [
            'analysis' => $analysis,
            'matrices' => $matrices,
            'money' => fn (int $cents): string => '$'.number_format($cents / 100, 2),
        ]);
    }
}
