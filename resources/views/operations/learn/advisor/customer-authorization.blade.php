<div class="ops-learn-prose">
    <h3>Authorization is operational truth</h3>
    <p>Customer authorization in ARK drives lifecycle, dispatch, and financial posture — not a signature gimmick. Verbal, portal, SMS thread, or in-person approval all record the same authority: which scopes may be performed and invoiced.</p>
    <p>Do not dispatch approved work without authorization captured — techs assume green means go, and warranty disputes assume the same.</p>
    <p>Partial authorization is normal on inspection-heavy ROs — approved now vs deferred later must be explicit per scope, not guessed from memory.</p>

    <x-operations.learn.figure
        role="advisor"
        article="customer-authorization"
        file="authorization-disposition.png"
        alt="Scope disposition controls for approved and deferred"
        caption="Deferred is not lost revenue — it is honest follow-up the customer chose today."
    />

    <h3>Concern disposition</h3>
    <table class="ops-learn-table">
        <thead>
            <tr>
                <th>Disposition</th>
                <th>Customer sees</th>
                <th>Portal behavior</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Draft</strong></td>
                <td>Hidden</td>
                <td>Not on portal, PDF, or email — internal build only</td>
            </tr>
            <tr>
                <td><strong>Recommended</strong></td>
                <td>Pending decision</td>
                <td>Customer chooses approve or defer on portal link</td>
            </tr>
            <tr>
                <td><strong>Approved</strong></td>
                <td>Approved</td>
                <td>Read-only review — no second authorization form</td>
            </tr>
            <tr>
                <td><strong>Deferred</strong></td>
                <td>Deferred</td>
                <td>Excluded from current authorized total</td>
            </tr>
        </tbody>
    </table>

    <h3>Record authorization (counter and phone)</h3>
    <p>When the customer approves by phone or at the counter before or instead of the portal, use <strong>Record authorization</strong> on the authorization rail: type (repair / partial), method (phone, in person, SMS, email), approved by, amount.</p>
    <p>That creates an <code>ApprovalEvent</code> the portal can display as “Authorization on file” if you later send a review link.</p>
    <p>Text-only “yes do it” belongs in conversation history — still set disposition and record authorization so totals and production sheet match.</p>

    <h3>Portal path</h3>
    <p>Portal authorization is per <strong>recommended</strong> scope — customer approves or defers each, confirms name, optionally signs when shop setting requires it.</p>
    <p>Pre-approved scopes do not ask the customer to sign again — see <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'remote-sell']) }}">Remote sell after check-in</a>.</p>
    <p>Portal view logs <strong>Estimate viewed</strong> once; queue may surface follow-up when viewed but silent.</p>

    <h3>After authorization</h3>
    <p>ARK advances lifecycle toward production when approved amount is captured and bay gates allow — you still confirm parts and schedule. See <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'lifecycle-transitions']) }}">Lifecycle transitions</a>.</p>
    <p>Collect deposits when shop policy requires before ordering exotic parts — record them on the financial rail. See <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'deposits-and-invoicing']) }}">Deposits and invoicing</a>.</p>

    <x-operations.learn.video
        role="advisor"
        article="customer-authorization"
        file="walkthrough.mp4"
        video-key="main"
        title="Present, disposition, and dispatch handoff"
        poster-file="poster.jpg"
    />

    <h3>Related guides</h3>
    <p>Remote sell: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'remote-sell']) }}">Remote sell after check-in</a>.</p>
    <p>Texting: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'texting-customers']) }}">Texting customers</a>.</p>
    <p>Inspection sell: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'inspection-to-aro']) }}">Inspection to ARO</a>.</p>
</div>
