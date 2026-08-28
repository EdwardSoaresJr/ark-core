<div class="ops-learn-prose">
    <h3>When to check in from the lot</h3>
    <p>Use ARK Mobile check-in when the customer and vehicle are in front of you and you do not want to walk to a desktop — drop-off lane, parking lot, shuttle return, or a second vehicle while the counter is busy.</p>
    <p>Desktop <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'advisor-intake']) }}">Service counter check-in</a> remains the full recognition surface; mobile check-in follows the same authority path and opens a real repair order in ARK.</p>

    <h3>Check-in flow</h3>
    <ol>
        <li>Open <strong>Check-in</strong> from the ARK Mobile home tab.</li>
        <li><strong>VIN</strong> — scan the barcode or enter manually; ARK decodes year/make/model when possible.</li>
        <li><strong>Customer</strong> — match an existing customer or create from the search result; duplicate hints appear while you type.</li>
        <li><strong>Concern</strong> — plain language, same as the counter (“check engine light”, “AC not cold”).</li>
        <li><strong>Mileage in</strong> — record odometer when the customer provides it.</li>
        <li><strong>Assign technician</strong> — pick the tech who will diagnose or perform the work; they see the RO in <strong>My Work</strong> immediately.</li>
        <li>Submit — RO opens in ARK with the first concern scoped from your intake sentence.</li>
    </ol>

    <h3>OBD scan at check-in (iCar Pro)</h3>
    <p>When a check-engine or drivability concern warrants codes before the car moves:</p>
    <ol>
        <li>Pair the iCar Pro adapter through the app (Bluetooth — native Android only, not web).</li>
        <li>Run <strong>Scan codes</strong> from the check-in OBD section.</li>
        <li>Review active and pending DTCs; ARK stores a summary on the RO at intake.</li>
        <li>Verified findings and recommendation fields can carry advisor notes from the scan session.</li>
    </ol>
    <p>The technician can refine DTC context later in the concern workspace — you are seeding diagnostic truth early, not replacing a full inspection.</p>

    <h3>After check-in</h3>
    <ul>
        <li>Technician receives assigned work on mobile and desktop production surfaces.</li>
        <li>Continue estimate build on desktop when lines, matrix pricing, or PartsTech are needed.</li>
        <li>Text or call the customer from ARK when follow-up is required — see <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'texting-customers']) }}">Texting customers</a>.</li>
        <li>Set visit posture on the RO when the vehicle is physically in the building — <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'visit-posture']) }}">Visit posture</a>.</li>
    </ul>

    <h3>What mobile check-in is not</h3>
    <p>Not a shortcut around customer recognition — match or create the customer before submit. Not estimate building — add labor, parts, and matrix lines on the worksheet. Not shop-wide workboard — you open one RO at a time from the lane.</p>

    <h3>Install the app</h3>
    <p>Admins deploy the Android APK per <a href="{{ route('operations.learn.show', ['role' => 'admin', 'article' => 'ark-mobile-android-deploy']) }}">ARK Mobile Android deploy</a>.</p>

    <h3>Related</h3>
    <p><a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'ark-mobile-attention']) }}">ARK Mobile Attention</a>, <a href="{{ route('operations.learn.show', ['role' => 'technician', 'article' => 'ark-mobile-concern-workspace']) }}">Technician concern workspace</a>, <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'customer-search']) }}">Customer search</a>.</p>
</div>
