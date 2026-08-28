<div class="ops-learn-prose">
    <h3>One surface for the relationship</h3>
    <p>The Customer Service Hub is where you answer “what do we know about this person?” without opening five ROs. Reach it from <a href="{{ route('operations.customers.search') }}">Customer search</a> or any customer link in ARK.</p>
    <p>Hub tabs: <strong>Work</strong>, <strong>Vehicles</strong>, <strong>Comms</strong>, and <strong>History</strong>. Recognition stays up front — name, phones, address, referral source, vehicles, then active work. Metadata stays quiet; vehicles and open jobs scan first.</p>
    <p>Think of the hub as home base between counter bursts — not a CRM dashboard you maintain for its own sake.</p>

    <x-operations.learn.figure
        role="advisor"
        article="customer-hub"
        file="customer-hub-layout.png"
        alt="Customer hub with vehicles, open ROs, and Comms tab"
        caption="Comms tab is the relationship timeline — calls, texts, email, and portal activity in one feed."
    />

    <h3>Work and vehicles</h3>
    <p>Open repair orders list with posture hints so you see what is waiting approval, in progress, or ready for pickup. Click through to repair order detail or open in a workspace tab.</p>
    <p>Vehicle cards show year/make/model, plate, and VIN for scan speed. Add or edit vehicles here when the customer buys a new car — do not wait until invoice time.</p>
    <p>Start a draft RO from the hub when the customer calls back about the same vehicle. Concern capture still happens on intake — the hub is launch, not worksheet.</p>

    <h3>Comms tab</h3>
    <p>The <strong>Comms</strong> tab is one timeline for calls, text, email, portal views, and logged contact — filter by type when you need focus (All · Calls · Text · Email · Portal · Logged).</p>
    <p>Quick Reply sits <strong>always open</strong> at the top of Comms — reply, attach MMS, send estimate or payment links without toggling a composer open first. Interrupt <strong>Reply</strong> and queue <strong>Reply</strong> land here with the textarea focused.</p>
    <p>New inbound messages prepend live when Reverb is healthy; ARK polls every few seconds as fallback so the timeline stays current without a full refresh.</p>
    <p>Read state is per advisor; unread does not mean unattended on the floor. Hand off with a note when another advisor owns the thread.</p>

    <x-operations.learn.video
        role="advisor"
        article="customer-hub"
        file="walkthrough.mp4"
        video-key="main"
        title="Hub tour — vehicles, open work, and Comms timeline"
        poster-file="poster.jpg"
    />

    <h3>When to use hub vs RO</h3>
    <p>Hub for relationship questions — history, texting, second vehicle, “what’s the status on my other car?” RO workspace for building and selling the active estimate.</p>
    <p>Morning triage starts at <a href="{{ route('operations.index') }}">Work</a>; open the <a href="{{ route('operations.workboard') }}">Workboard</a> when you need lane detail. Hub is where you go once you know the customer name.</p>
    <p>Related: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'customer-search']) }}">Customer search</a>, <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'texting-customers']) }}">Texting customers</a>, <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'comms-queue']) }}">Comms Queue triage</a>.</p>
</div>
