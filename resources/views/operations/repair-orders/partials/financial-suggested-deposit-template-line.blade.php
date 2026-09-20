<tr @class(['ops-deposit-breakdown-modal__line--excluded' => ! $line['included_by_default']])>
    <td class="ops-deposit-breakdown-modal__include-col">
        <input
            type="checkbox"
            class="rounded border-slate-300 text-slate-950 focus:ring-slate-500"
            data-deposit-line-checkbox
            data-amount-cents="{{ $line['amount_cents'] }}"
            @checked($line['included_by_default'])
        >
    </td>
    <td class="ops-deposit-breakdown-modal__description">{{ $line['description'] }}</td>
    <td class="ops-deposit-breakdown-modal__category">{{ $line['category_label'] }}</td>
    <td class="ops-deposit-breakdown-modal__amount">{{ $line['sell'] }}</td>
    <td class="ops-deposit-breakdown-modal__amount">{{ $line['tax'] ?? '—' }}</td>
    <td class="ops-deposit-breakdown-modal__amount">{{ $line['shop_fee'] ?? '—' }}</td>
    <td class="ops-deposit-breakdown-modal__amount ops-deposit-breakdown-modal__amount--total">{{ $line['amount'] }}</td>
</tr>
