<div class="ops-learn-prose">
    <h3>Service counter posture</h3>
    <p>Check-in is recognition first — who is here, what vehicle, why they came in. Open <a href="{{ route('operations.intake.create') }}">+ Check In</a> when the customer is at the counter or on a live call you are converting to a repair order.</p>
    <p>Do not treat check-in as a data-entry wizard. Unified search resolves customer and vehicle as you type; VIN decode can fill year/make/model when the customer only has the windshield sticker. Land on Open RO with the concern field in front of you when recognition succeeds.</p>
    <p>If recognition fails, create or attach the customer from search before you write estimate lines. Duplicate hints appear live while you add someone new — see <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'customer-search']) }}">Customer search and recognition</a>.</p>

    <x-operations.learn.figure
        role="advisor"
        article="advisor-intake"
        file="intake-recognition-band.png"
        alt="Check-in recognition band with customer, vehicle, and concern capture"
        caption="Tier 1 is recognition; Tier 2 is why they are here — same words you would say on the phone."
    />

    <h3>Why are they here?</h3>
    <p>Enter the customer concern in plain language — overheating, noise over bumps, check engine light. ARK creates the repair order and first scope from that sentence.</p>
    <p>Visit mode and billing class affect disclaimer and pricing posture. Set them when you know them; do not block the RO on perfect metadata. You can refine on the worksheet after the customer has a place to sit.</p>
    <p>When the visit started from a phone call or walk-in without a file, use intake with the caller’s phone prefilled — do not open a repair order until customer and vehicle are recognized. See <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'incoming-calls-floor']) }}">Answering calls in ARK</a>.</p>

    <h3>After Open RO</h3>
    <p>You land on the estimate worksheet in build mode. Name scopes as problems, not repairs — <em>Brake noise</em>, not <em>Replace pads</em>. Read <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'scopes-and-intent']) }}">Scopes and recommendation intent</a> before your first grouped estimate.</p>
    <p>Keep workspace tabs open for parallel counter work. Yellow bars and orange dots tell you which RO needs the next human decision. See <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'workspace-tabs']) }}">Workspace tabs</a>.</p>
    <p>When the car is physically on the lot, set visit posture honestly so the workboard and bay see the same truth. See <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'visit-posture']) }}">Visit posture on the RO</a>.</p>

    <x-operations.learn.video
        role="advisor"
        article="advisor-intake"
        file="walkthrough.mp4"
        video-key="main"
        title="Counter intake — recognize, capture concern, open RO"
        poster-file="poster.jpg"
    />

    <h3>Counter habits</h3>
    <p>Phone ringing while you intake? Answer in ARK — screen pop carries caller context into lookup. See <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'incoming-calls-floor']) }}">Answering calls in ARK</a>.</p>
    <p>Repeat customers: search from <a href="{{ route('operations.customers.search') }}">Customers</a> or intake unified search before you create a second file. Recognition speed beats form completeness at the counter.</p>
    <p>New advisors: read <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'getting-started']) }}">Advisor basics</a>, then run one live intake with a senior advisor watching scope naming only.</p>
</div>
