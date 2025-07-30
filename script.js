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

    function updateTradeAmountEquivalent() {
        const $span = $('#tradeAmountCurrency');
        const show = $span.data('show');
        const base = $span.data('base');
        const quote = $span.data('quote');
        const amount = parseFloat($('#tradeAmount').val());
        const priceNum = parseFloat(currentPrice);
        let text = '--';
        if (!isNaN(amount) && !isNaN(priceNum)) {
            if (show === 'base') {
                text = (amount * priceNum).toFixed(2) + ' ' + quote;
            } else {
                text = (amount / priceNum).toFixed(8) + ' ' + base;
            }
        }
        $('#tradeAmountEquivalent').text(text);
    }

    $('#tradeAmountCurrency').on('click', function() {
        const $el = $(this);
        const show = $el.data('show');
        const base = $el.data('base');
        const quote = $el.data('quote');
        let amount = parseFloat($('#tradeAmount').val());
        const priceNum = parseFloat(currentPrice);
        if (!isNaN(amount) && !isNaN(priceNum)) {
            if (show === 'quote') {
                amount = amount / priceNum;
                $('#tradeAmount').val(amount.toFixed(8));
            } else {
                amount = amount * priceNum;
                $('#tradeAmount').val(amount.toFixed(2));
            }
        }
        if (show === 'quote') {
            $el.html(`<i class="fas fa-exchange-alt me-1"></i>${base}`);
            $el.data('show', 'base');
        } else {
            $el.html(`<i class="fas fa-exchange-alt me-1"></i>${quote}`);
            $el.data('show', 'quote');
        }
        updateTradeAmountEquivalent();
    });

    $('#currencyPair').on('change', function(){
        updateTradeAmountCurrency();
        updateTradeAmountEquivalent();
    });

    $('#tradeAmount').on('input', updateTradeAmountEquivalent);

    $('#useCurrentLimitPrice').on('click', function() {
        const changeText = $('#priceChange').text();
        const changeNum = parseFloat(changeText);
        if (!isNaN(changeNum)) {
            $('#limitPrice').val(changeNum);
        } else {
            $('#limitPrice').val(changeText);
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
    updateTradeAmountEquivalent();
});
