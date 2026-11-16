
<?php
// Include this component inside an authenticated dashboard.
?>

<section class="dashboard-charts">

    <div class="chart-panel">
        <div class="chart-heading">
            <div>
                <h2>Stock Movement Analysis</h2>
                <p>Compare stock entries and exits.</p>
            </div>

            <select id="stockPeriod" aria-label="Stock chart period">
                <option value="7days">Last 7 Days</option>
                <option value="30days" selected>Last 30 Days</option>
                <option value="6months">Last 6 Months</option>
                <option value="year">This Year</option>
            </select>
        </div>

        <div class="chart-container">
            <canvas id="stockMovementChart"></canvas>
        </div>

        <p id="stockChartMessage" class="chart-message"></p>
    </div>

    <div class="chart-panel">
        <div class="chart-heading">
            <div>
                <h2>Income and Expenses</h2>
                <p>Compare customer payments with business expenses.</p>
            </div>

            <select id="financePeriod" aria-label="Finance chart period">
                <option value="7days">Last 7 Days</option>
                <option value="30days" selected>Last 30 Days</option>
                <option value="6months">Last 6 Months</option>
                <option value="year">This Year</option>
            </select>
        </div>

        <div class="chart-container">
            <canvas id="financeChart"></canvas>
        </div>

        <div class="finance-summary">
            <div>
                <span>Total Income</span>
                <strong id="totalIncome">0 FCFA</strong>
            </div>

            <div>
                <span>Total Expenses</span>
                <strong id="totalExpenses">0 FCFA</strong>
            </div>

            <div>
                <span>Net Cash Flow</span>
                <strong id="netCashFlow">0 FCFA</strong>
            </div>
        </div>

        <p id="financeChartMessage" class="chart-message"></p>
    </div>

</section>

<style>
.dashboard-charts {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 24px;
    margin: 25px 0;
}

.chart-panel {
    min-width: 0;
    padding: 22px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 3px 12px rgba(0, 0, 0, 0.08);
}

.chart-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 20px;
}

.chart-heading h2 {
    margin: 0 0 8px;
    font-size: 19px;
}

.chart-heading p {
    margin: 0;
    color: #6b7280;
    font-size: 13px;
}

.chart-heading select {
    max-width: 145px;
    padding: 8px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background: white;
}

.chart-container {
    position: relative;
    height: 300px;
    width: 100%;
}

.finance-summary {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
    margin-top: 20px;
}

.finance-summary div {
    padding: 12px;
    background: #f8fafc;
    border-radius: 8px;
    overflow-wrap: anywhere;
}

.finance-summary span {
    display: block;
    margin-bottom: 8px;
    color: #64748b;
    font-size: 12px;
}

.finance-summary strong {
    font-size: 14px;
}

.chart-message {
    min-height: 18px;
    margin-top: 12px;
    font-size: 13px;
    color: #64748b;
}

@media (max-width: 1000px) {
    .dashboard-charts {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 500px) {
    .chart-panel {
        padding: 14px;
    }

    .chart-heading {
        flex-direction: column;
    }

    .finance-summary {
        grid-template-columns: 1fr;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="/stock_finance_system/assets/js/dashboard_charts.js"></script>