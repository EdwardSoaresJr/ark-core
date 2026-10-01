<?php

namespace App\Ark\Operations\Communications;

use App\Models\User;
use Illuminate\Contracts\View\View;

class CommunicationsPersonController
{
    public function __invoke(User $user, CommunicationsShopProjection $projection): View
    {
        return view('operations.shop.communications.person', [
            'context' => $projection->personContext($user),
        ]);
    }
}
