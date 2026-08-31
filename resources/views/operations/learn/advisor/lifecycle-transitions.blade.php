<div class="ops-learn-prose">
    <h3>Lifecycle matches the bay</h3>
    <p>Repair order lifecycle transitions tell the shop where the car is in real work — not where you wish it were. Workboard lanes read from this posture; wrong transition equals wrong customer promise.</p>
    <p>Change lifecycle from the RO workspace when facts change — approval received, parts arrived, vehicle on lift, ready for pickup. Batch “lane housekeeping” without bay truth creates chaos by noon.</p>
    <p>Route authority lives on <code>operations.repair-orders.lifecycle.update</code> — server validates allowed moves; UI reflects current state only.</p>

    <x-operations.learn.figure
        role="advisor"
        article="lifecycle-transitions"
        file="lifecycle-controls.png"
        alt="Repair order lifecycle transition controls on workspace"
        caption="Say the transition out loud on the floor — if it sounds wrong, it probably is."
    />

    <h3>Common transitions</h3>
    <p><strong>Draft / intake</strong> — estimate building, customer not committed. Stay here until presentable estimate exists.</p>
    <p><strong>Awaiting approval</strong> — customer has numbers; decision pending. ARK moves here automatically when you email or SMS an estimate link from review mode. Comms follow-up lives here — not in a spreadsheet.</p>
    <p><strong>Approved / ready for work</strong> — ARK advances after portal authorization, recorded phone/in-person auth, or when all visible scopes are already approved and bay gates clear. You still confirm parts and schedule.</p>
    <p><strong>Waiting parts</strong> — approved work blocked on procurement. Honest ETA in notes; see <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'parts-procurement']) }}">Parts procurement</a>.</p>
    <p><strong>In progress / ready</strong> — bay execution and pickup readiness. Tech production sheet assumes authorization already captured.</p>

    <h3>Close, invoice, and post</h3>
    <p>Closing and invoice happen after work is complete — do not invoice from draft posture because the customer is at the counter impatient.</p>
    <p>After invoice and payment: <strong>Post to sales</strong> on Closeout. Posted ROs feed owner Sales Posted and margin KPIs. Closing as Paid posts automatically.</p>
    <p>Collecting payment without posting leaves owner reconciliation showing advance pay — fix before end of shift when possible.</p>

    <h3>Visit posture vs lifecycle</h3>
    <p>Visit posture (vehicle on lot, dropped off, waiting) complements lifecycle — both should agree. See <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'visit-posture']) }}">Visit posture</a>.</p>
    <p>Owner Day Review scans aging approvals and stuck transitions — advisors fix during the day, not at 6 PM surprise.</p>

    <x-operations.learn.video
        role="advisor"
        article="lifecycle-transitions"
        file="walkthrough.mp4"
        video-key="main"
        title="Lifecycle moves that match floor truth"
        poster-file="poster.jpg"
    />

    <h3>Related guides</h3>
    <p>Workboard: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'workboard-lanes']) }}">Workboard lanes</a>.</p>
    <p>Closeout: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'deposits-and-invoicing']) }}">Deposits, invoice, and closeout</a>.</p>
    <p>Authorization: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'customer-authorization']) }}">Customer authorization</a>.</p>
    <p>Owner end-of-day: <a href="{{ route('operations.learn.show', ['role' => 'owner', 'article' => 'bookend-walkthrough']) }}">Day Review walkthrough</a>.</p>
</div>
