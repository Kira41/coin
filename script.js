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

    updateStopLossFields();
});
