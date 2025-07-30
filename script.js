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
        $span.html(`<i class="fas fa-exchange-alt me-1"></i>${quote}`);
        $span.data({ base, quote, show: 'quote' });
    }

    $('#tradeAmountCurrency').on('click', function() {
        const $el = $(this);
        const show = $el.data('show');
        const base = $el.data('base');
        const quote = $el.data('quote');
        if (show === 'quote') {
            $el.html(`<i class="fas fa-exchange-alt me-1"></i>${base}`);
            $el.data('show', 'base');
        } else {
            $el.html(`<i class="fas fa-exchange-alt me-1"></i>${quote}`);
            $el.data('show', 'quote');
        }
    });

    $('#currencyPair').on('change', updateTradeAmountCurrency);

    $('#useCurrentLimitPrice').on('click', function() {
        const priceNum = parseFloat(currentPrice);
        if (!isNaN(priceNum)) {
            $('#limitPrice').val(priceNum.toFixed(2));
        }
    });

    $('#useCurrentStopPrice').on('click', function() {
        const priceNum = parseFloat(currentPrice);
        if (!isNaN(priceNum)) {
            $('#stopPrice').val(priceNum.toFixed(2));
        }
    });

    updateStopLossFields();
    updateTradeAmountCurrency();
});
