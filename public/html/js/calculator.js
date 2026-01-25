(function (global) {
    const { formatDate } = global.AppUtils;

    const DEFAULT_LOAN_AMOUNT = '600000';
    const DEFAULT_LOAN_MONTHS = '60';
    const DEFAULT_TOTAL_INTEREST = '396000';

    let latestCalculation = null;

    function initCalculator() {
        const calculateBtn = document.getElementById('calculate-btn');
        const resetBtn = document.getElementById('reset-btn');
        const interestCard = document.getElementById('interest-card');
        const loanDateInput = document.getElementById('loan-date');

        if (calculateBtn) {
            calculateBtn.addEventListener('click', calculateRepaymentPlan);
        }

        if (resetBtn) {
            resetBtn.addEventListener('click', resetInputs);
        }

        if (loanDateInput && !loanDateInput.value) {
            const today = new Date();
            const offsetMinutes = today.getTimezoneOffset();
            const localISODate = new Date(today.getTime() - offsetMinutes * 60000)
                .toISOString()
                .slice(0, 10);
            loanDateInput.value = localISODate;
        }

        setupRateCardToggle(interestCard, document.getElementById('rate-card'));
        calculateRepaymentPlan();
    }

    function calculateRepaymentPlan() {
        const loanAmount = parseFloat(document.getElementById('loan-amount')?.value || '0');
        const loanMonths = parseFloat(document.getElementById('loan-month')?.value || '0');
        const totalInterest = parseFloat(document.getElementById('total-interest')?.value || '0');

        if (isInvalidLoanInput(loanAmount, loanMonths, totalInterest)) {
            alert('请输入有效的贷款信息！');
            latestCalculation = null;
            return null;
        }

        const totalMonths = loanMonths;
        const totalPayment = loanAmount + totalInterest;
        const loanDateInput = document.getElementById('loan-date')?.value;
        const startDate = loanDateInput ? new Date(loanDateInput) : new Date();
        startDate.setHours(0, 0, 0, 0);

        let low = 0;
        let high = 1;
        let mid = 0;

        for (let i = 0; i < 100; i += 1) {
            mid = (low + high) / 2;
            const monthlyPayment = loanAmount * mid * Math.pow(1 + mid, totalMonths)
                / (Math.pow(1 + mid, totalMonths) - 1);
            const estimateTotal = monthlyPayment * totalMonths;

            if (estimateTotal > totalPayment) {
                high = mid;
            } else {
                low = mid;
            }
        }

        const monthlyRate = mid;
        const monthlyPayment = loanAmount * monthlyRate * Math.pow(1 + monthlyRate, totalMonths)
            / (Math.pow(1 + monthlyRate, totalMonths) - 1);

        updateSummary(monthlyPayment, totalMonths, loanAmount, monthlyRate);
        const schedule = generateRepaymentTable(loanAmount, monthlyPayment, monthlyRate, totalMonths, startDate);

        const endDate = schedule.length > 0
            ? new Date(schedule[schedule.length - 1].date.getTime())
            : null;

        latestCalculation = {
            loanAmount,
            loanMonths: totalMonths,
            totalInterest,
            monthlyRate,
            monthlyPayment,
            startDate: new Date(startDate.getTime()),
            endDate,
            schedule,
        };

        return latestCalculation;
    }

    function resetInputs() {
        const loanAmount = document.getElementById('loan-amount');
        const loanMonth = document.getElementById('loan-month');
        const totalInterest = document.getElementById('total-interest');

        if (loanAmount) loanAmount.value = DEFAULT_LOAN_AMOUNT;
        if (loanMonth) loanMonth.value = DEFAULT_LOAN_MONTHS;
        if (totalInterest) totalInterest.value = DEFAULT_TOTAL_INTEREST;

        calculateRepaymentPlan();
    }

    function getLatestCalculation() {
        return latestCalculation;
    }

    function updateSummary(monthlyPayment, totalMonths, loanAmount, monthlyRate) {
        const totalPayment = monthlyPayment * totalMonths;
        const actualInterest = totalPayment - loanAmount;
        const annualRate = monthlyRate * 12;

        const monthlyPaymentEl = document.getElementById('monthly-payment');
        const totalPaymentEl = document.getElementById('total-payment');
        const actualInterestEl = document.getElementById('actual-interest');
        const annualRateEl = document.getElementById('annual-rate');

        if (monthlyPaymentEl) monthlyPaymentEl.textContent = `${monthlyPayment.toFixed(2)} 元`;
        if (totalPaymentEl) totalPaymentEl.textContent = `${totalPayment.toFixed(2)} 元`;
        if (actualInterestEl) actualInterestEl.textContent = `${actualInterest.toFixed(2)} 元`;
        if (annualRateEl) annualRateEl.textContent = `${(annualRate * 100).toFixed(6)}%`;
    }

    function generateRepaymentTable(loanAmount, monthlyPayment, monthlyRate, totalMonths, startDate) {
        const tableBody = document.querySelector('#repayment-table tbody');
        if (!tableBody) {
            return [];
        }

        tableBody.innerHTML = '';
        let remainingPrincipal = loanAmount;
        const schedule = [];

        for (let i = 1; i <= totalMonths; i += 1) {
            const interestPayment = remainingPrincipal * monthlyRate;
            const principalPayment = monthlyPayment - interestPayment;
            remainingPrincipal -= principalPayment;

            if (i === totalMonths) {
                remainingPrincipal = 0;
            }

            const repaymentDate = new Date(startDate);
            repaymentDate.setMonth(startDate.getMonth() + i);
            repaymentDate.setDate(startDate.getDate() - 1);

            const safeRemaining = remainingPrincipal > 0 ? remainingPrincipal : 0;
            const safePrincipal = principalPayment > 0 ? principalPayment : 0;
            const safeInterest = interestPayment > 0 ? interestPayment : 0;

            schedule.push({
                period: i,
                date: new Date(repaymentDate.getTime()),
                monthlyPayment,
                principal: safePrincipal,
                interest: safeInterest,
                remainingPrincipal: safeRemaining,
            });

            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${i}</td>
                <td>${formatDate(repaymentDate)}</td>
                <td>${monthlyPayment.toFixed(2)}</td>
                <td>${safePrincipal.toFixed(2)}</td>
                <td>${safeInterest.toFixed(2)}</td>
                <td>${safeRemaining > 0 ? safeRemaining.toFixed(2) : '0.00'}</td>
            `;
            tableBody.appendChild(row);
        }

        return schedule;
    }

    function setupRateCardToggle(interestCard, rateCard) {
        if (!interestCard || !rateCard) {
            return;
        }

        const toggleRateCard = () => {
            rateCard.style.display = rateCard.style.display === 'none' ? 'block' : 'none';
        };

        interestCard.addEventListener('dblclick', toggleRateCard);

        let pressTimer;
        interestCard.addEventListener('touchstart', () => {
            pressTimer = setTimeout(toggleRateCard, 1000);
        });
        interestCard.addEventListener('touchend', () => {
            clearTimeout(pressTimer);
        });
    }

    function isInvalidLoanInput(loanAmount, loanMonths, totalInterest) {
        return (
            Number.isNaN(loanAmount) ||
            Number.isNaN(loanMonths) ||
            Number.isNaN(totalInterest) ||
            loanAmount <= 0 ||
            loanMonths <= 0 ||
            totalInterest < 0
        );
    }

    global.AppCalculator = {
        initCalculator,
        calculateRepaymentPlan,
        getLatestCalculation,
    };
})(window);
