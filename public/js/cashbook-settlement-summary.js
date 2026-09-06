(function (root) {
    'use strict';

    root.CashbookSettlementSummary = {
        formatAmount(amount) {
            return Number(amount || 0).toLocaleString('en-IN', {style: 'currency', currency: 'INR', minimumFractionDigits: 2, maximumFractionDigits: 2});
        },

        calculate(relations, amounts) {
            const settlements = (relations || []).filter(relation => relation.enabled).map(relation => {
                const breakdown = (relation.items || []).map(item => {
                    const amount = Number(amounts[item.setting_id]) || 0;
                    return {
                        setting_id: item.setting_id,
                        name: item.name || ('Category #' + item.setting_id),
                        role: item.role,
                        amount: amount,
                    };
                });

                const cents = (relation.items || []).reduce((total, item) => {
                    const amount = Number(amounts[item.setting_id]) || 0;
                    const value = Math.round(Math.max(0, amount) * 100);
                    return total + (item.role === 'subtract' ? -value : value);
                }, 0);

                return { id: relation.id, name: relation.name, kind: relation.kind, amount: cents / 100, items: breakdown };
            });

            const balance = settlements.find(s => s.kind === 'default_balance' || (s.name && s.name.toLowerCase() === 'balance'));
            const income = settlements.find(s => s.kind === 'default_income');
            const expense = settlements.find(s => s.kind === 'default_expense');

            let netBalance = 0;
            let netLabel = 'Balance';
            let incomeAmount = income?.amount ?? 0;
            let expenseAmount = expense?.amount ?? 0;

            if (balance) {
                netBalance = balance.amount;
                netLabel = balance.name;
            } else if (income || expense) {
                netBalance = (Math.round(incomeAmount * 100) - Math.round(expenseAmount * 100)) / 100;
                netLabel = `${income?.name ?? 'Income'} − ${expense?.name ?? 'Expense'}`;
            } else if (settlements.length > 0) {
                netBalance = settlements[0].amount;
                netLabel = settlements[0].name;
            }

            return {
                settlements,
                income: incomeAmount,
                expense: expenseAmount,
                netBalance,
                netLabel,
            };
        },
    };
})(typeof window === 'undefined' ? globalThis : window);
