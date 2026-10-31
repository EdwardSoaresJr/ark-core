<tr @class(['opacity-50' => ! $line['included_by_default']]) data-deposit-line>
    <td>
        <input
            type="checkbox"
            class="form-check-input m-0 align-middle"
            aria-label="Include {{ $line['description'] }}"
            data-deposit-line-checkbox
            data-amount-cents="{{ $line['amount_cents'] }}"
            @checked($line['included_by_default'])
        >
    </td>
    <td>{{ $line['description'] }}</td>
    <td class="text-secondary">{{ $line['category_label'] }}</td>
    <td class="text-end ark-money">{{ $line['sell'] }}</td>
    <td class="text-end ark-money">{{ $line['tax'] ?? '-' }}</td>
    <td class="text-end ark-money">{{ $line['shop_fee'] ?? '-' }}</td>
    <td class="text-end fw-bold ark-money">{{ $line['amount'] }}</td>
</tr>
