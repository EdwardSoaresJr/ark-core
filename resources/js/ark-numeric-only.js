// Text inputs marked data-numeric-only accept digits and one decimal point.
// Used instead of type="number" so scroll wheels, arrows, and swipes cannot
// silently change operational values. Server-side validation remains authoritative.
export function initNumericOnlyInputs() {
    document.addEventListener('input', (event) => {
        const input = event.target;

        if (!(input instanceof HTMLInputElement) || !input.hasAttribute('data-numeric-only')) {
            return;
        }

        const cleaned = sanitizeDecimal(input.value);

        if (cleaned !== input.value) {
            const cursor = input.selectionStart;
            input.value = cleaned;

            if (cursor !== null) {
                const position = Math.min(cursor, cleaned.length);
                input.setSelectionRange(position, position);
            }
        }
    });
}

function sanitizeDecimal(value) {
    let result = value.replace(/[^0-9.]/g, '');

    const firstDot = result.indexOf('.');

    if (firstDot !== -1) {
        result = result.slice(0, firstDot + 1) + result.slice(firstDot + 1).replace(/\./g, '');
    }

    return result;
}
