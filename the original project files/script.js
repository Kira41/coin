$(function() {
    function removeTradingViewSpan() {
        const span = document.querySelector('#tradingview_chart > div > div > a > span');
        if (span) {
            span.remove();
            return true;
        }
        return false;
    }

    if (!removeTradingViewSpan()) {
        const chart = document.getElementById('tradingview_chart');
        if (chart) {
            const observer = new MutationObserver(function() {
                if (removeTradingViewSpan()) {
                    observer.disconnect();
                }
            });
            observer.observe(chart, { childList: true, subtree: true });
        }
    }

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

    // Update the equivalent value whenever the amount is changed or typed
    $('#tradeAmount').on('input keyup change', updateTradeAmountEquivalent);

    $('#useCurrentLimitPrice').on('click', function() {
        const priceText = $('#currentPrice').text().replace(/[^0-9.-]/g, '');
        const priceNum = parseFloat(priceText);
        if (!isNaN(priceNum)) {
            $('#limitPrice').val(priceNum.toFixed(2));
        }
    });

    $('#useCurrentStopPrice').on('click', function() {
        const priceText = $('#currentPrice').text().replace(/[^0-9.-]/g, '');
        const priceNum = parseFloat(priceText);
        if (!isNaN(priceNum)) {
            $('#stopPrice').val(priceNum.toFixed(2));
        }
    });

    updateStopLossFields();
    updateTradeAmountCurrency();
    updateTradeAmountEquivalent();

    const revenueCard = document.querySelector('.chart-card');
    if (revenueCard) {
        const subtitle = document.getElementById('revenueSubtitle');
        const periodButtons = Array.from(document.querySelectorAll('.period-button-group .glassy-btn'));
        const chartGroups = Array.from(document.querySelectorAll('.chart-placeholder .chart-bar-group'));
        const yAxisLabels = Array.from(document.querySelectorAll('.chart-y-axis .y-value'));
        const defaultPeriod = periodButtons.find(button => button.classList.contains('is-active'))?.dataset.period || 'monthly';
        const periodCopy = {
            monthly: 'Monthly revenue overview',
            weekly: 'Weekly revenue overview',
            daily: 'Daily revenue overview',
        };

        const formatCurrency = (value) => {
            if (value >= 1000) {
                return `$${Math.round(value / 1000)}K`;
            }
            return `$${Math.round(value)}`;
        };

        const updateYAxis = (maxValue) => {
            const rawStep = Math.max(1, Math.ceil(maxValue / 5));
            const step = maxValue > 1000 ? Math.ceil(rawStep / 1000) * 1000 : rawStep;
            const top = step * 5;
            yAxisLabels.forEach((label, index) => {
                const value = top - (step * index);
                label.textContent = formatCurrency(Math.max(0, value));
            });
        };

        const updateBars = (labels, values) => {
            const maxValue = Math.max(...values, 1);
            chartGroups.forEach((group, index) => {
                const bar = group.querySelector('.chart-bar');
                const label = group.querySelector('.chart-label');
                const value = values[index] ?? 0;
                if (bar) {
                    const height = Math.max(16, Math.round((value / maxValue) * 200));
                    bar.style.height = `${height}px`;
                    bar.setAttribute('data-value', value.toFixed(2));
                }
                if (label) {
                    label.textContent = labels[index] ?? '';
                }
            });
            updateYAxis(maxValue);
        };

        const setActiveButton = (period) => {
            periodButtons.forEach(button => {
                button.classList.toggle('is-active', button.dataset.period === period);
            });
        };

        const loadRevenueData = async (period) => {
            const userId = localStorage.getItem('user_id') || 1;
            const response = await fetch(`php/revenue_analytics.php?period=${encodeURIComponent(period)}&user_id=${encodeURIComponent(userId)}`);
            if (!response.ok) {
                throw new Error('Failed to load revenue analytics');
            }
            return response.json();
        };

        const refreshRevenueAnalytics = (period) => {
            setActiveButton(period);
            if (subtitle) {
                subtitle.textContent = periodCopy[period] || periodCopy.monthly;
            }

            loadRevenueData(period)
                .then((data) => {
                    if (Array.isArray(data.labels) && Array.isArray(data.values)) {
                        updateBars(data.labels, data.values);
                    }
                })
                .catch(() => {
                    updateBars(chartGroups.map(() => ''), chartGroups.map(() => 0));
                });
        };

        periodButtons.forEach(button => {
            button.addEventListener('click', () => {
                const period = button.dataset.period || 'monthly';
                refreshRevenueAnalytics(period);
            });
        });

        refreshRevenueAnalytics(defaultPeriod);
    }
});
