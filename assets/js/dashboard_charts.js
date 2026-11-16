console.log("StockWise chart JavaScript is running!");
document.addEventListener('DOMContentLoaded', function () {
console.log("Stock canvas:", document.getElementById('stockMovementChart'));
console.log("Finance canvas:", document.getElementById('financeChart'));
console.log("Chart library:", typeof Chart);
    const stockCanvas = document.getElementById('stockMovementChart');
    const financeCanvas = document.getElementById('financeChart');

    if (!stockCanvas || !financeCanvas) {
        return;
    }

    const stockPeriod = document.getElementById('stockPeriod');
    const financePeriod = document.getElementById('financePeriod');

    const stockMessage = document.getElementById('stockChartMessage');
    const financeMessage = document.getElementById('financeChartMessage');

    const moneyFormat = new Intl.NumberFormat('fr-FR', {
        maximumFractionDigits: 0
    });

    let stockChart;
    let financeChart;

    function formatFCFA(value) {
        return moneyFormat.format(value) + ' FCFA';
    }

    async function getData(url) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json'
            }
        });

        const data = await response.json();

        if (!response.ok || data.error) {
            throw new Error(data.error || 'Unable to load chart data.');
        }

        return data;
    }

    async function loadStockChart() {
        stockMessage.textContent = 'Loading stock data...';

        try {
            const period = encodeURIComponent(stockPeriod.value);
            const data = await getData(
                'stock_chart_data.php?period=' + period
            );

            if (stockChart) {
                stockChart.destroy();
            }

            stockChart = new Chart(stockCanvas, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [
                        {
                            label: 'Stock Entries',
                            data: data.entries,
                            borderColor: '#198754',
                            backgroundColor: 'rgba(25, 135, 84, 0.12)',
                            fill: true,
                            tension: 0.3
                        },
                        {
                            label: 'Stock Exits',
                            data: data.exits,
                            borderColor: '#dc3545',
                            backgroundColor: 'rgba(220, 53, 69, 0.08)',
                            fill: false,
                            tension: 0.3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    return context.dataset.label + ': ' +
                                        context.parsed.y + ' products';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Quantity'
                            }
                        }
                    }
                }
            });

            stockMessage.textContent = data.entries.every(v => v === 0) &&
                data.exits.every(v => v === 0)
                ? 'No stock movements were recorded during this period.'
                : '';

        } catch (error) {
            stockMessage.textContent = error.message;
        }
    }

    async function loadFinanceChart() {
        financeMessage.textContent = 'Loading financial data...';

        try {
            const period = encodeURIComponent(financePeriod.value);
            const data = await getData(
                'finance_chart_data.php?period=' + period
            );

            if (financeChart) {
                financeChart.destroy();
            }

            financeChart = new Chart(financeCanvas, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [
                        {
                            label: 'Income',
                            data: data.income,
                            borderColor: '#198754',
                            backgroundColor: 'rgba(25, 135, 84, 0.12)',
                            fill: true,
                            tension: 0.3
                        },
                        {
                            label: 'Expenses',
                            data: data.expenses,
                            borderColor: '#dc3545',
                            backgroundColor: 'rgba(220, 53, 69, 0.08)',
                            fill: false,
                            tension: 0.3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    return context.dataset.label + ': ' +
                                        formatFCFA(context.parsed.y);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function (value) {
                                    return moneyFormat.format(value);
                                }
                            },
                            title: {
                                display: true,
                                text: 'Amount (FCFA)'
                            }
                        }
                    }
                }
            });

            document.getElementById('totalIncome').textContent =
                formatFCFA(data.totalIncome);

            document.getElementById('totalExpenses').textContent =
                formatFCFA(data.totalExpenses);

            document.getElementById('netCashFlow').textContent =
                formatFCFA(data.netCashFlow);

            financeMessage.textContent =
                data.totalIncome === 0 && data.totalExpenses === 0
                ? 'No payment or expense records were found for this period.'
                : '';

        } catch (error) {
            financeMessage.textContent = error.message;
        }
    }

    stockPeriod.addEventListener('change', loadStockChart);
    financePeriod.addEventListener('change', loadFinanceChart);

    loadStockChart();
    loadFinanceChart();
});


