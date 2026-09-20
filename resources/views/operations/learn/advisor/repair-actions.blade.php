<div class="ops-learn-prose">
    <h3>Three layers — do not mix them</h3>
    <p>ARK separates <strong>the problem</strong>, <strong>the repairs you are selling</strong>, and <strong>the labor line</strong>. Each answers a different question.</p>

    <table class="ops-learn-table">
        <thead>
            <tr>
                <th>Layer</th>
                <th>Customer question</th>
                <th>What you write</th>
                <th>Example</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Scope</strong> (the problem)</td>
                <td>What is wrong?</td>
                <td>Symptom, complaint, or inspection finding</td>
                <td>Overheating</td>
            </tr>
            <tr>
                <td><strong>Scope recommendation</strong></td>
                <td>What do you recommend overall?</td>
                <td>One plain sentence — the story before the line items</td>
                <td>Replace leaking water pump and refill cooling system.</td>
            </tr>
            <tr>
                <td><strong>Repair action</strong></td>
                <td>What are we doing?</td>
                <td>One named repair — verb + component</td>
                <td>Replace Water Pump</td>
            </tr>
            <tr>
                <td><strong>Labor line</strong></td>
                <td>What is the billed operation?</td>
                <td>Usually the same as the repair action; set hours</td>
                <td>Replace water pump · 2.75 hr</td>
            </tr>
            <tr>
                <td><strong>Part line</strong></td>
                <td>What parts does that repair need?</td>
                <td>Short part name only</td>
                <td>Water pump · Coolant</td>
            </tr>
        </tbody>
    </table>

    <h3>The mistake to avoid</h3>
    <p>Do <strong>not</strong> put a repair name in the scope when you have multiple repairs under one problem.</p>

    <pre class="ops-learn-code">WRONG — scope is a repair, not a problem
Scope: Replace radiator
  Repair action: Radiator R&R
  Repair action: Water Pump
(Customer thinks the whole job is only "replace radiator.")

RIGHT — scope is the problem; repairs live underneath
Scope: Overheating
  Finding: Coolant leak at water pump.
  Recommendation: Replace leaking water pump and radiator as needed.
  Repair action: Replace Radiator
    Labor · Radiator · Coolant
  Repair action: Replace Water Pump
    Labor · Water pump</pre>

    <h3>How to write the scope (the problem)</h3>
    <p>The <strong>scope summary</strong> is the headline on the estimate — the reason this block exists.</p>
    <ul>
        <li><strong>Write:</strong> what the customer reported, what you found, or the inspection theme.</li>
        <li><strong>Do not write:</strong> individual repairs, part names, labor operations, or prices.</li>
    </ul>

    <table class="ops-learn-table">
        <thead>
            <tr>
                <th>Good scope summaries</th>
                <th>Bad scope summaries</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Overheating</td>
                <td>Replace radiator</td>
            </tr>
            <tr>
                <td>Coolant leak</td>
                <td>Radiator R&R + water pump</td>
            </tr>
            <tr>
                <td>Brake pulsation at highway speed</td>
                <td>Front brakes</td>
            </tr>
            <tr>
                <td>Check engine light — misfire</td>
                <td>Labor · Parts · Misc</td>
            </tr>
            <tr>
                <td>30k maintenance due</td>
                <td>Oil change (when scope also has filters, fluids, etc.)</td>
            </tr>
        </tbody>
    </table>

    <p>Expand <strong>Why / Evidence</strong> when it helps the customer trust the story:</p>
    <ul>
        <li><strong>Customer states</strong> — their words: <em>Temp gauge goes to red in traffic.</em></li>
        <li><strong>Verified findings</strong> — your proof: <em>Coolant low; dye at water pump weep hole.</em></li>
        <li><strong>Recommendation</strong> — the sell in one sentence before line items: <em>Replace water pump, radiator, and refill system.</em></li>
    </ul>

    <p>See also: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'scopes-and-intent']) }}">Scopes and recommendation intent</a>.</p>

    <h3>How to write repair actions</h3>
    <p>After the scope names the <em>problem</em>, each <strong>repair action</strong> names one <em>fix</em>.</p>
    <ul>
        <li><strong>Formula:</strong> verb + component — words you would say at the counter.</li>
        <li><strong>One repair per action</strong> — not "water pump and thermostat" in one title.</li>
        <li><strong>No prices, vendors, or part numbers</strong> in the title.</li>
    </ul>

    <table class="ops-learn-table">
        <thead>
            <tr>
                <th>Good repair actions</th>
                <th>Bad repair actions</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Replace Water Pump</td>
                <td>WP</td>
            </tr>
            <tr>
                <td>Replace Radiator</td>
                <td>Radiator R&R (abbreviation-only — weak for customers)</td>
            </tr>
            <tr>
                <td>Replace Timing Cover Gasket</td>
                <td>Labor #2</td>
            </tr>
            <tr>
                <td>Further Electrical Testing</td>
                <td>Diagnostic</td>
            </tr>
        </tbody>
    </table>

    <h3>How to write the labor line</h3>
    <p>Labor is the <strong>anchor</strong> inside a repair. Hours and pricing live on labor. When there is one labor line that matches the repair title, ARK hides the duplicate description by default — you usually only set hours.</p>
    <ul>
        <li><strong>Default:</strong> leave labor matching the repair; the worksheet shows a compact hours summary.</li>
        <li><strong>Use Advanced</strong> only when the tech needs shop shorthand the customer does not need on the repair header — e.g. repair <em>Replace Water Pump</em>, labor <em>R&R water pump</em>.</li>
        <li><strong>Multiple labor lines</strong> under one repair keep their own descriptions (Remove Engine / Install Engine).</li>
        <li><strong>Hours</strong> go on the labor line, not in the scope or repair title.</li>
    </ul>

    <blockquote>
        <strong>Scope</strong> = what is wrong.<br>
        <strong>What are we doing?</strong> = the repair we are selling.<br>
        <strong>Labor</strong> = hours and rate for that repair.
    </blockquote>

    <h3>Full example — timing cover job</h3>
    <pre class="ops-learn-code">Scope: Timing cover oil leak
Intent: Safety / Drivability
Finding: Oil seep at timing cover gasket; coolant unrelated.
Recommendation: Reseal timing cover and address related cooling components while apart.

Repair: Replace Timing Cover Gasket
  Labor — 4.0 hr
  Part — Timing cover gasket set

Repair: Replace Water Pump
  Labor — 1.5 hr
  Part — Water pump
  Part — Coolant

Repair: Replace Thermostat
  Labor — 0.5 hr
  Part — Thermostat</pre>

    <p>Customer hears: <em>Three repairs under one oil leak concern</em> — not a scrambled list of labor and parts.</p>

    <h3>Workflow</h3>
    <ol>
        <li><strong>Add Work → Customer Concern</strong> — name the problem; set intent; fill finding/recommendation if helpful.</li>
        <li><strong>Suggested repairs</strong> (when offered) — one repair title per fix (<em>What are we doing?</em>).</li>
        <li><strong>+ Labor</strong> — set hours; description stays under Advanced when it matches the repair.</li>
        <li><strong>+ Supporting Part / Sublet / Note</strong> — attach under that repair.</li>
        <li><strong>Standalone note</strong> — only for scope-level context with no repair yet.</li>
    </ol>

    <h3>Diagnostics</h3>
    <p>Not every repair starts with labor. <em>Further Electrical Testing</em> can begin with a <strong>Note</strong> or <strong>Sublet</strong>. Parts still wait until labor exists.</p>

    <h3>Quick check</h3>
    <p>Read the scope title alone. If it sounds like a single repair, move that wording into <em>What are we doing?</em> and rename the scope as the problem.</p>
</div>
