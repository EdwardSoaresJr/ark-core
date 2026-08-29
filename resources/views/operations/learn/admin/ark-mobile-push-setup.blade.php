<div class="ops-learn-prose">
    <h3>Status — production operational (2026-06-27)</h3>
    <p>ARK owns notification authority. Firebase is <strong>FCM transport only</strong> — free tier, no Firestore/Auth/Analytics. Demo Auto Repair production push is <strong>enabled</strong> (<code>demo-auto-ark-mobile</code>). iOS still needs APNs <code>.p8</code> in Firebase Console before iPhone push works. Advisors still have Attention polling when push fails.</p>

    <h3>When push is justified (later)</h3>
    <p>ARK owns notification authority. Firebase is <strong>transport only</strong> — not Auth, Firestore, or workflow. ARK sends via FCM HTTP v1; Flutter registers a token via <code>POST /api/mobile/device</code> only.</p>

    <h3>Settings (preferred)</h3>
    <p><strong>Settings → Communications → Mobile</strong></p>
    <ul>
        <li><strong>Enable mobile push</strong> — master switch (leave off until credentials exist).</li>
        <li><strong>Firebase project ID</strong> — from Firebase console project settings.</li>
        <li><strong>Firebase service account JSON</strong> — paste full JSON from Project settings → Service accounts → Generate new private key (Firebase Cloud Messaging Admin).</li>
    </ul>
    <p>Credentials are stored encrypted in shop settings — not in git.</p>

    <h3>Optional server file (.env)</h3>
    <p>When JSON is not pasted in Settings, ARK can read a file path from <code>FIREBASE_CREDENTIALS</code> on the server:</p>
    <ul>
        <li><strong>Local:</strong> <code>storage/app/private/firebase-mobile-service-account.json</code> (gitignored)</li>
        <li><strong>Production file fallback:</strong> <code>/data/ark-shared/storage/app/private/firebase-mobile-service-account.json</code> (mode <code>600</code>) — primary path is encrypted JSON in Settings</li>
    </ul>
    <p>Prefer Settings for shop-operable configuration. Use the file path only when ops mounts secrets outside the database.</p>

    <h3>Setup script (preferred)</h3>
    <p><code>infra/scripts/firebase-mobile-push-setup.sh</code> — see <code>docs/mobile/firebase-mobile-push-setup-doctrine-v1.md</code>.</p>

    <h3>Firebase console checklist</h3>
    <ol>
        <li>Create or reuse a Firebase project for ARK Mobile (iOS + Android apps registered in FlutterFire when native builds ship).</li>
        <li>Project settings → Service accounts → Generate new private key → paste JSON in Settings or deploy to server path above.</li>
        <li>iOS: upload APNs key in Firebase Cloud Messaging settings before iOS push works on devices.</li>
        <li>Enable push in Settings → Communications → Mobile and save.</li>
    </ol>

    <h3>Verify</h3>
    <p>Advisor signs into ARK Mobile → device registration returns <code>push_enabled: true</code> when push is fully configured.</p>
    <p>Send inbound test SMS to shop line → operations advisors with registered devices should receive push (queued job + FCM HTTP v1).</p>
    <p>Check Laravel logs for FCM warnings if delivery fails — stale tokens are cleared automatically.</p>

    <h3>Sync ARKademy to BookStack</h3>
    <p>After catalog changes deploy, run on production app container:</p>
    <p><code>php artisan ark:arkademy:import-bookstack --force</code></p>
    <p>Imports Blade catalog into <a href="https://learn.demo-auto.test">learn.demo-auto.test</a> and updates <code>arkademy_content_registry</code>.</p>

    <h3>Related</h3>
    <p>Install app on devices first: <a href="{{ route('operations.learn.show', ['role' => 'admin', 'article' => 'ark-mobile-android-deploy']) }}">ARK Mobile Android deploy</a>.</p>
    <p>Advisor mobile usage: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'ark-mobile-attention']) }}">ARK Mobile Attention</a>, <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'ark-mobile-check-in']) }}">Mobile check-in and OBD</a>.</p>
    <p>Comms health: <a href="{{ route('operations.learn.show', ['role' => 'admin', 'article' => 'comms-health-check']) }}">Communications health check</a>.</p>
</div>
