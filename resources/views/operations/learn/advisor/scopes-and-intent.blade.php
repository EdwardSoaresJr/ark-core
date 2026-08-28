<div class="ops-learn-prose">
    <h3>Scope = the problem, not the repair</h3>
    <p>The <strong>scope summary</strong> is the customer-facing headline for one concern. It should answer <em>what is wrong</em>, not <em>what we are replacing</em>.</p>

    <table class="ops-learn-table">
        <thead>
            <tr>
                <th>Field</th>
                <th>Purpose</th>
                <th>Write like this</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Scope summary</strong></td>
                <td>The problem headline</td>
                <td>Overheating · Brake noise · Oil leak at timing cover</td>
            </tr>
            <tr>
                <td><strong>Customer states</strong></td>
                <td>What they told you</td>
                <td>Temp gauge goes to red in stop-and-go traffic.</td>
            </tr>
            <tr>
                <td><strong>Verified findings</strong></td>
                <td>What you proved</td>
                <td>Coolant low; leak at water pump weep hole; radiator end tank seeping.</td>
            </tr>
            <tr>
                <td><strong>Recommendation</strong></td>
                <td>Overall sell in one sentence</td>
                <td>Replace water pump and radiator; refill and pressure-test cooling system.</td>
            </tr>
            <tr>
                <td><strong>Scope notes</strong></td>
                <td>Staff-only context</td>
                <td>Customer may defer thermostat until next visit.</td>
            </tr>
        </tbody>
    </table>

    <p><strong>Repairs belong in repair actions</strong>, not in the scope title. If you write <em>Replace radiator</em> as the scope but also sell a water pump, the customer misreads the job.</p>

    <p>Full writing guide: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'repair-actions']) }}">Repair actions — scope vs repair action vs labor</a>.</p>

    <h3>Recommendation status</h3>
    <p>Status is advisor posture — <strong>what action the customer should take</strong> about this condition. It is not part type, part source, or warranty.</p>
    <table class="ops-learn-table">
        <thead>
            <tr>
                <th>You select</th>
                <th>When to use</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Immediate Attention</td>
                <td>Safety, breakdown risk, or drivability — address before return or further driving</td>
            </tr>
            <tr>
                <td>Plan Soon</td>
                <td>Meaningful wear or failure risk — schedule in the near term</td>
            </tr>
            <tr>
                <td>Maintenance</td>
                <td>Interval service, fluids, filters, and planned upkeep</td>
            </tr>
            <tr>
                <td>Diagnostic</td>
                <td>Investigation, testing, or quoting after findings</td>
            </tr>
            <tr>
                <td>Repair Verification</td>
                <td>Post-repair checks or quality verification</td>
            </tr>
            <tr>
                <td>Information Only</td>
                <td>Observation or context — no repair recommendation yet</td>
            </tr>
        </tbody>
    </table>

    <p>For OEM vs aftermarket, customer-supplied parts, performance upgrades, and line warranty notes, use <strong>part line fields</strong> on the part row — not recommendation status.</p>

    <h3>Disposition</h3>
    <p><strong>Draft</strong> while building — hidden from customer PDF, portal, and email. <strong>Recommended</strong> when ready to present — customer chooses approve, defer, or decline on the portal.</p>
    <p><strong>Approved</strong>, <strong>Deferred</strong>, and <strong>Declined</strong> track customer decisions. Deferred keeps honest follow-up revenue on file; declined means the customer does not want that work.</p>
    <p>Pre-approved scopes show read-only on the portal; customer status reflects visible scope state, not internal RO labels. See <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'customer-authorization']) }}">Customer authorization</a>.</p>

    <h3>Layered story on the PDF</h3>
    <pre class="ops-learn-code">IMMEDIATE ATTENTION
Overheating                          ← scope (problem)
  Finding: Coolant leak at pump.
  Recommendation: Replace pump and radiator; refill system.
  Replace Water Pump                 ← repair action
    Labor · Water pump · Coolant
  Replace Radiator                   ← repair action
    Labor · Radiator</pre>
</div>
