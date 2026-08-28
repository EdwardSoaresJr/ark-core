@php
    $overheadSettings = route('operations.settings.shop.edit', ['section' => 'overhead']);
    $staffSettings = route('operations.settings.shop.edit', ['section' => 'staff']);
    $excellenceSettings = route('operations.settings.shop.edit', ['section' => 'excellence']);
@endphp

<div class="ops-learn-prose">
    <h3>Why ARK splits overhead and payroll</h3>
    <p>Shop economics use <strong>two different math paths</strong>. Mixing them up is the main reason owners look for a “payroll” field on Shop Overhead and cannot find one.</p>
    <ul>
        <li><strong>Shop overhead / billed hr</strong> — spreads <em>non-wage</em> monthly shop costs (plus card processing) across expected <em>billed labor hours</em>. Same rate for every tech.</li>
        <li><strong>Loaded labor cost / hr</strong> — per <em>technician</em> true cost on each billed hour: their wage + employer burden + that shop overhead rate.</li>
        <li><strong>Advisor / front desk payroll</strong> — monthly salary for people who do not bill labor hours. Goes in Shop Overhead → <strong>Office and advisor payroll</strong>, not in a tech’s loaded cost calculator.</li>
    </ul>

    <h3>The three places numbers live</h3>
    <ol>
        <li>
            <strong><a href="{{ $overheadSettings }}">Settings → Shop Overhead</a></strong>
            — worksheet for rent, utilities, insurance, software, equipment, <strong>office & advisor payroll</strong>, card processing, and billing capacity. Output: <strong>shop overhead / billed hr</strong>.
        </li>
        <li>
            <strong><a href="{{ $staffSettings }}">Settings → Staff</a></strong> (technicians only)
            — each tech’s <strong>base pay / hr</strong>, <strong>burden %</strong>, and loaded cost calculator. Shop overhead / hr prefills from step 1.
        </li>
        <li>
            <strong><a href="{{ $excellenceSettings }}">Settings → Owner Targets</a></strong>
            — <strong>Monthly fixed costs</strong> for break-even on Margin Health. Usually your P&amp;L fixed total: shop overhead worksheet total <em>plus</em> all technician payroll (or use accountant’s number).
        </li>
    </ol>

    <h3>Step-by-step: Shop Overhead worksheet</h3>
    <p>Open <a href="{{ $overheadSettings }}">Settings → Shop Overhead</a>. Work left to right through the tabs, then save once at the bottom.</p>

    <h3>Tab 1 — Fixed Costs</h3>
    <p>Enter <strong>monthly</strong> amounts (what you pay whether the shop is slow or busy):</p>
    <ul>
        <li><strong>Rent / mortgage</strong> — building or bay rent.</li>
        <li><strong>Utilities</strong> — electric, gas, water, trash.</li>
        <li><strong>Insurance</strong> — GL, garagekeepers, etc.</li>
        <li><strong>Software & subscriptions</strong> — shop software, Identifix, phone, etc.</li>
        <li><strong>Equipment & tools</strong> — tool payments, bay equipment leases (monthly portion).</li>
        <li><strong>Office and advisor payroll</strong> — monthly gross pay for advisors, front desk, owner (if not on a tech line), and other non-billing staff. Example: two advisors at $4,500/mo each → enter <code>9000</code>.</li>
        <li><strong>Other fixed shop costs</strong> — anything else fixed monthly not listed above.</li>
    </ul>
    <blockquote>
        <strong>Do not enter technician straight wages here.</strong> Tech wages belong in Staff → Loaded cost calculator so burden and utilization apply correctly per person.
    </blockquote>

    <h3>Tab 2 — Payment Processing</h3>
    <ul>
        <li><strong>Estimated monthly card volume</strong> — typical month of customer card payments (RO closeouts, deposits).</li>
        <li><strong>Processing fee %</strong> — effective rate from your processor statement (often 2.6–3.3%).</li>
        <li><strong>Merchant loan holdback %</strong> or <strong>fixed monthly financing payment</strong> — if applicable.</li>
    </ul>
    <p>These costs are part of the monthly overhead pool because they scale with shop revenue, not with a single tech’s hours.</p>

    <h3>Tab 3 — Billing Capacity</h3>
    <p>How many <strong>billed labor hours</strong> the shop produces in a typical month:</p>
    <ul>
        <li><strong>Active technicians</strong> — prefilled from active Staff with Technician role; adjust if reality differs.</li>
        <li><strong>Workdays / month</strong> — default 22 (Mon–Fri minus holidays).</li>
        <li><strong>Workday hours</strong> — average clock hours per tech per day (often 8).</li>
        <li><strong>Billable utilization %</strong> — share of paid time that becomes billable hours shop-wide (often 80–85%). Non-billable time includes cleanup, comebacks, training, parts runs.</li>
    </ul>
    <p><strong>Monthly billable hours</strong> = technicians × workdays × workday hours × utilization.</p>
    <p><strong>Shop overhead / billed hr</strong> = monthly overhead pool ÷ monthly billable hours.</p>

    <h3>Worked example</h3>
    <ul>
        <li>Fixed costs: rent $5,000 + utilities $800 + office/advisor payroll $9,000 = $14,800</li>
        <li>Card processing on $100,000 at 2.9% = $2,900</li>
        <li>Monthly overhead pool = $17,700</li>
        <li>2 techs × 22 days × 8 hr × 85% utilization = 299.2 billable hr</li>
        <li>Shop overhead / billed hr ≈ <strong>$59.16/hr</strong></li>
    </ul>
    <p>Click <strong>Save shop overhead</strong>. Staff loaded cost calculators pick up the saved rate automatically.</p>

    <h3>Step-by-step: Each technician (Staff)</h3>
    <p>Open <a href="{{ $staffSettings }}">Settings → Staff</a> → Edit a team member with the <strong>Technician</strong> role.</p>
    <ol>
        <li><strong>Pay basis</strong> — <strong>Hourly (clock)</strong> for straight clock wages, or <strong>Flag / book time</strong> when pay is already per billed hour (flat-rate).</li>
        <li><strong>Clock wage / hr</strong> or <strong>Flag rate / hr</strong> — straight pay before taxes and benefits.</li>
        <li><strong>Burden %</strong> — employer cost on top of wage: payroll tax, benefits, workers comp, retirement. Most shops use 25–35%; default 28%.</li>
        <li><strong>Overhead / billed hr</strong> — prefilled from Shop Overhead when saved.</li>
        <li><strong>Billable utilization %</strong> — hourly techs only: share of clock time that becomes billable hours (often 80–85%). Skip for flag pay — flag rate is already per billed hour.</li>
        <li>Review the breakdown → <strong>Use calculated loaded cost</strong> → <strong>Save changes</strong>.</li>
    </ol>
    <p>Formula shown in the calculator:</p>
    <ul>
        <li><strong>Hourly:</strong> payroll loaded = clock wage × (1 + burden); ÷ utilization; + shop overhead / billed hr</li>
        <li><strong>Flag / book:</strong> payroll loaded = flag rate × (1 + burden); + shop overhead / billed hr (no utilization divisor)</li>
    </ul>
    <p>Example: $30/hr base, 28% burden, 85% utilization, $29.08 overhead → loaded cost ≈ <strong>$67/hr</strong> on billed hours.</p>

    <h3>Advisors and loaded labor cost</h3>
    <p>ARK’s loaded labor cost and technician efficiency reports are built around <strong>billed labor hours</strong>. Advisors do not bill labor, so they do not get a loaded cost field.</p>
    <p>Capture advisor compensation in Shop Overhead → <strong>Office and advisor payroll</strong> (monthly). For owner break-even, include their pay again in Owner Targets → Monthly fixed costs together with tech payroll.</p>

    <h3>Owner Targets — monthly fixed costs</h3>
    <p>Margin Health break-even uses <strong>Monthly fixed costs</strong> under <a href="{{ $excellenceSettings }}">Owner Targets</a>. This should reflect your real P&amp;L fixed burn:</p>
    <ul>
        <li>Shop Overhead worksheet <strong>monthly overhead total</strong> (includes office/advisor payroll and processing), <strong>plus</strong></li>
        <li>All <strong>technician payroll</strong> (sum of base wages × paid hours, or use accountant’s payroll line), <strong>or</strong></li>
        <li>Enter the single number your accountant uses for monthly fixed costs.</li>
    </ul>
    <p>Shop Overhead is a worksheet to derive the allocation rate — not a duplicate of Owner Targets. Many shops copy the worksheet total, then add tech payroll for break-even.</p>

    <h3>Checklist</h3>
    <ol>
        <li>Shop Overhead fixed costs filled (including office/advisor payroll, excluding tech wages)</li>
        <li>Payment processing tab filled if you take cards</li>
        <li>Billing capacity reflects real tech count and utilization</li>
        <li>Shop overhead saved — rate shows at top of worksheet</li>
        <li>Each technician has loaded cost saved under Staff</li>
        <li>Owner Targets monthly fixed costs set for break-even (full P&amp;L picture)</li>
    </ol>
</div>
