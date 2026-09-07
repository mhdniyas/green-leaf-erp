(function (root) {
    'use strict';

    root.CashbookSettlementSummary = {
        formatAmount(amount) {
            return Number(amount || 0).toLocaleString('en-IN', {style: 'currency', currency: 'INR', minimumFractionDigits: 2, maximumFractionDigits: 2});
        },

        calculate(relations, amounts, verifiedPayments = 0) {
            const rawSettlements = (relations || []).filter(relation => relation.enabled).map(relation => {
                const getItemInfo = (item) => {
                    if (item.source_settlement_id) {
                        const amt = Number(amounts['settlement_' + item.source_settlement_id]) || 0;
                        return { name: item.name || ('Settlement #' + item.source_settlement_id), amount: Math.max(0, amt) };
                    }
                    if (item.header_group_id) {
                        const taggedAmt = Number(amounts['header_tagged_product_' + item.header_group_id]) || 0;
                        let catAmt = 0;
                        if (Array.isArray(item.header_setting_ids)) {
                            item.header_setting_ids.forEach(sId => { catAmt += Number(amounts[sId]) || 0; });
                        }
                        let mode = item.header_mode || 'all_categories';
                        let amt = 0;
                        if (mode === 'tagged_products_only') {
                            amt = taggedAmt;
                        } else if (mode === 'categories_and_products') {
                            amt = catAmt + taggedAmt;
                        } else {
                            amt = catAmt;
                        }
                        return { name: item.name || ('Header #' + item.header_group_id), amount: Math.max(0, amt) };
                    }
                    const amt = Number(amounts[item.setting_id]) || 0;
                    return { name: item.name || (item.setting_id ? ('Category #' + item.setting_id) : 'Item'), amount: Math.max(0, amt) };
                };

                const breakdown = (relation.items || []).map(item => {
                    const info = getItemInfo(item);
                    return {
                        setting_id: item.setting_id,
                        header_group_id: item.header_group_id,
                        source_settlement_id: item.source_settlement_id,
                        name: info.name,
                        role: item.role,
                        amount: info.amount,
                    };
                });

                const cents = (relation.items || []).reduce((total, item) => {
                    const info = getItemInfo(item);
                    const value = Math.round(info.amount * 100);
                    return total + (item.role === 'subtract' ? -value : value);
                }, 0);

                const amt = cents / 100;
                amounts['settlement_' + relation.id] = amt;

                return {
                    id: relation.id,
                    name: relation.name,
                    kind: relation.kind || relation.relation_type,
                    is_company_payable: !!relation.is_company_payable,
                    amount: amt,
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

            return {
                settlements: rawSettlements,
                companyPayable,
                verifiedPayments: verifiedAmt,
                netBalance,
                netLabel,
            };
        },
    };
})(typeof window === 'undefined' ? globalThis : window);
