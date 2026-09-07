(function (root) {
    'use strict';

    root.CashbookSettlementSummary = {
        formatAmount(amount) {
            return Number(amount || 0).toLocaleString('en-IN', {style: 'currency', currency: 'INR', minimumFractionDigits: 2, maximumFractionDigits: 2});
        },

        calculate(relations, amounts, verifiedPayments = 0) {
            const rawSettlements = (relations || []).filter(relation => relation.enabled).map(relation => {
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

                return {
                    id: relation.id,
                    name: relation.name,
                    kind: relation.kind || relation.relation_type,
                    is_company_payable: !!relation.is_company_payable,
                    amount: cents / 100,
                    items: breakdown
                };
            });

            const companyPayable = rawSettlements.find(s => s.is_company_payable || s.kind === 'default_company_payable' || (s.name && s.name.toLowerCase().includes('company payable')));
            const balance = rawSettlements.find(s => s.kind === 'default_balance' || (s.name && s.name.toLowerCase() === 'balance'));

            let netBalance = 0;
            let netLabel = 'Company Payable';
            const verifiedAmt = Number(verifiedPayments || 0);

            if (companyPayable) {
                netBalance = Math.round((companyPayable.amount - verifiedAmt) * 100) / 100;
                netLabel = companyPayable.name;
            } else if (rawSettlements.length > 0) {
                netBalance = Math.round((rawSettlements[0].amount - verifiedAmt) * 100) / 100;
                netLabel = rawSettlements[0].name;
            }

            // Exclude old default balance, income, expense cards from display settlements
            const settlements = rawSettlements.filter(s => !['default_income', 'default_expense', 'default_balance'].includes(s.kind));

            return {
                settlements: settlements.length > 0 ? settlements : rawSettlements,
                companyPayable,
                verifiedPayments: verifiedAmt,
                netBalance,
                netLabel,
            };
        },
    };
})(typeof window === 'undefined' ? globalThis : window);
