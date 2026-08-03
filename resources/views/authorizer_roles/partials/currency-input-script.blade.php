<script>
    document.addEventListener('DOMContentLoaded', function () {
        const input = document.querySelector('[data-currency-input]');

        if (! input) {
            return;
        }

        const formatCurrency = function (value, fixedDecimals) {
            const sanitized = String(value).replace(/[^\d.]/g, '');
            const [whole = '', ...decimalParts] = sanitized.split('.');
            const decimal = decimalParts.join('').slice(0, 2);
            const normalizedWhole = whole.replace(/^0+(?=\d)/, '') || '0';
            const formattedWhole = normalizedWhole.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

            if (fixedDecimals && sanitized !== '') {
                return formattedWhole + '.' + decimal.padEnd(2, '0');
            }

            return decimalParts.length > 0 ? formattedWhole + '.' + decimal : formattedWhole;
        };

        input.addEventListener('input', function () {
            const caretAtEnd = this.selectionStart === this.value.length;
            this.value = formatCurrency(this.value, false);

            if (caretAtEnd) {
                this.setSelectionRange(this.value.length, this.value.length);
            }
        });

        input.addEventListener('blur', function () {
            if (this.value !== '') {
                this.value = formatCurrency(this.value, true);
            }
        });

        input.form.addEventListener('submit', function () {
            input.value = input.value.replace(/,/g, '');
        });
    });
</script>
