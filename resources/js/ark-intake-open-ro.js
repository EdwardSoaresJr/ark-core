export function arkIntakeOpenRo(config = {}) {
    const defaultRecommendationIntent = config.defaultRecommendationIntent ?? 'maintenance';
    const defaultBilling = config.defaultBilling ?? 'default';
    const laborRatesByBilling = config.laborRatesByBilling ?? {};

    const seedRows = Array.isArray(config.initialRows) && config.initialRows.length > 0
        ? config.initialRows
        : [{
            customer_states: '',
            recommendation_intent: defaultRecommendationIntent,
            billing_posture: defaultBilling,
        }];

    return {
        defaultRecommendationIntent,
        defaultBilling,
        laborRatesByBilling,

        laborRateLabel(billingPosture) {
            const rate = laborRatesByBilling[billingPosture];

            return rate ? `$${rate}/hr` : '';
        },

        rows: seedRows.map((row, index) => ({
            key: `row-${index}-${Date.now()}`,
            customer_states: row.customer_states ?? '',
            recommendation_intent: row.recommendation_intent || defaultRecommendationIntent,
            billing_posture: row.billing_posture || defaultBilling,
        })),

        addRow() {
            this.rows.push({
                key: `row-${Date.now()}`,
                customer_states: '',
                recommendation_intent: this.defaultRecommendationIntent,
                billing_posture: this.defaultBilling,
            });
        },

        removeRow(key) {
            this.rows = this.rows.filter((row) => row.key !== key);
        },
    };
}
