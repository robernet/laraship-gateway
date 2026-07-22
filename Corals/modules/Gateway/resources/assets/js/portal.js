import Vue from 'vue';
import axios from 'axios';

const csrfToken = document.querySelector('meta[name="csrf-token"]');
if (csrfToken) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken.getAttribute('content');
}

new Vue({
    el: '#gateway-portal-app',
    data: {
        intents: [],
        loading: false,
        error: null,
        form: {
            invoice_id: '',
            mid: '',
            amount: null,
        },
        invoiceLookup: '',
        invoiceResult: null,
        invoiceError: null,
    },
    created() {
        this.fetchIntents();
    },
    methods: {
        fetchIntents() {
            this.loading = true;
            axios.get('/portal/payment-intents')
                .then(({ data }) => { this.intents = data.data; })
                .finally(() => { this.loading = false; });
        },
        createIntent() {
            this.error = null;

            const payload = {
                invoice_id: this.form.invoice_id,
                mid: this.form.mid,
                mode: 'one_time',
                amount_policy: { type: 'fixed', amount: parseInt(this.form.amount, 10) },
            };

            axios.post('/portal/payment-intents', payload)
                .then(() => {
                    this.form = { invoice_id: '', mid: '', amount: null };
                    this.fetchIntents();
                })
                .catch((err) => {
                    this.error = (err.response && err.response.data && err.response.data.message)
                        || 'Could not create the payment intent.';
                });
        },
        lookupInvoice() {
            this.invoiceError = null;
            this.invoiceResult = null;

            axios.get(`/portal/invoices/${this.invoiceLookup}/status`)
                .then(({ data }) => { this.invoiceResult = data; })
                .catch(() => { this.invoiceError = 'No intent found for that invoice.'; });
        },
    },
    template: `
        <div>
            <div class="card">
                <h3>Create a payment intent</h3>
                <p class="error" v-if="error">{{ error }}</p>
                <form @submit.prevent="createIntent">
                    <div class="field">
                        <label>Invoice ID</label>
                        <input v-model="form.invoice_id" required>
                    </div>
                    <div class="field">
                        <label>Merchant MID</label>
                        <input v-model="form.mid" required maxlength="9">
                    </div>
                    <div class="field">
                        <label>Amount (centavos)</label>
                        <input type="number" v-model="form.amount" required min="0">
                    </div>
                    <button type="submit">Create</button>
                </form>
            </div>

            <div class="card">
                <h3>Look up an invoice</h3>
                <form @submit.prevent="lookupInvoice">
                    <input v-model="invoiceLookup" placeholder="invoice_id">
                    <button type="submit">Look up</button>
                </form>
                <p class="error" v-if="invoiceError">{{ invoiceError }}</p>
                <div v-if="invoiceResult">
                    <p>State: {{ invoiceResult.state }}</p>
                    <p v-if="invoiceResult.references[0]">Human reference: {{ invoiceResult.references[0].human_reference }}</p>
                </div>
            </div>

            <div class="card">
                <h3>Payment intents</h3>
                <p v-if="loading">Loading…</p>
                <table v-else>
                    <thead>
                        <tr><th>Invoice</th><th>State</th><th>Reference</th><th>Expires</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="intent in intents" :key="intent.public_id">
                            <td>{{ intent.invoice_id }}</td>
                            <td>{{ intent.state }}</td>
                            <td>{{ intent.references[0] ? intent.references[0].human_reference : '—' }}</td>
                            <td>{{ intent.expires_at || 'never' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    `,
});
