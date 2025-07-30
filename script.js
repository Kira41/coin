$(function() {
    function updateStopLossFields() {
        const type = $('#stopLossType').val();
        $('#stopLossPriceDiv').toggle(type === 'price');
        $('#stopLossPercentageDiv').toggle(type === 'percentage');
        $('#stopLossTimeDiv').toggle(type === 'time');
        $('#trailingPercentageDiv').toggle(type === 'trailing');
    }

    $('#enableStopLoss').on('change', function() {
        $('#stopLossSettings').toggle(this.checked);
    });

    $('#stopLossType').on('change', updateStopLossFields);

    $('#enableOCO').on('change', function() {
        $('#takeProfitDiv').toggle(this.checked);
    });

    function updateTradeAmountCurrency() {
        const pairText = $('#currencyPair option:selected').text() || '';
        const parts = pairText.split('/');
        const base = parts[0] || 'BTC';
        const quote = parts[1] || 'USD';
        const $span = $('#tradeAmountCurrency');
        $span.text(quote);
        $span.data({ base, quote, show: 'quote' });
    }

    $('#tradeAmountCurrency').on('click', function() {
        const $el = $(this);
        const show = $el.data('show');
        const base = $el.data('base');
        const quote = $el.data('quote');
        if (show === 'quote') {
            $el.text(base);
            $el.data('show', 'base');
        } else {
            $el.text(quote);
            $el.data('show', 'quote');
        }
    });

    $('#currencyPair').on('change', updateTradeAmountCurrency);

    $('#useCurrentLimitPrice').on('click', function() {
        if (typeof currentPrice !== 'undefined') {
            $('#limitPrice').val(currentPrice.toFixed(2));
        }
    });

    $('#useCurrentStopPrice').on('click', function() {
        if (typeof currentPrice !== 'undefined') {
            $('#stopPrice').val(currentPrice.toFixed(2));
        }
    });

    updateStopLossFields();
    updateTradeAmountCurrency();
});
