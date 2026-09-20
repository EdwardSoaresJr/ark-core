<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * Local-only interactive mock of Companion v1 gate screens — design review, not production UI.
 */
final class CompanionPreviewController
{
    public function __invoke(): View
    {
        abort_unless(app()->environment('local'), 404);

        return view('companion.preview.index', [
            'screens' => [
                'incoming' => [
                    'label' => 'Incoming call',
                    'spec' => 'docs/companion-v1/screens/incoming-call.md',
                    'refs' => [
                        ['label' => 'Quo incoming modal', 'src' => '/companion-preview/refs/quo-incoming.png'],
                    ],
                ],
                'thread' => [
                    'label' => 'Conversation thread',
                    'spec' => 'docs/companion-v1/screens/conversation-thread.md',
                    'refs' => [
                        ['label' => 'Quo inbox rhythm', 'src' => '/companion-preview/refs/quo-threads.png'],
                    ],
                ],
                'payment' => [
                    'label' => 'Payment sheet',
                    'spec' => 'docs/companion-v1/screens/payment-sheet.md',
                    'refs' => [],
                ],
                'inspection' => [
                    'label' => 'Inspection item',
                    'spec' => 'docs/companion-v1/screens/inspection-item.md',
                    'refs' => [],
                ],
                'home' => [
                    'label' => 'Home continuity',
                    'spec' => 'docs/companion-v1/screens/home-continuity.md',
                    'refs' => [],
                ],
                'search' => [
                    'label' => 'Global search',
                    'spec' => 'docs/companion-v1/screens/global-search.md',
                    'refs' => [],
                ],
            ],
        ]);
    }
}
