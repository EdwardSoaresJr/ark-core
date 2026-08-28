<?php

namespace App\View\Components\Operations;

use App\Ark\Runtime\Ecosystem\EcosystemProduct;
use App\Ark\Runtime\Ecosystem\EcosystemSwitcherProjection;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class EcosystemSwitcher extends Component
{
  /**
     * @var list<array{id: string, label: string, url: string, external: bool, current: bool}>
     */
    public readonly array $items;

    public readonly bool $visible;

    public function __construct(
        EcosystemSwitcherProjection $projection,
        public string $current = EcosystemProduct::Operations->value,
    ) {
        $surface = EcosystemProduct::tryFrom($current) ?? EcosystemProduct::Operations;
        $this->items = $projection->forUser(auth()->user(), $surface);
        $this->visible = count($this->items) > 1;
    }

    public function render(): View|Closure|string
    {
        return view('components.operations.ecosystem-switcher');
    }
}
