<div class="ops-learn-prose">
    <h3>What you are deploying</h3>
    <p><strong>ARK Mobile</strong> is the Flutter staff app for advisors and technicians on the lot. Package ID: <code>com.arksms.ark_mobile</code>. It talks to the same ARK backend as desktop — default API host <code>https://app.demo-auto.test</code>.</p>
    <p>This guide covers getting a <strong>native Android build</strong> onto shop devices. OBD / iCar Pro scanning requires the installed app — it does not work in a mobile browser.</p>

    <h3>Recommended path — sideload release APK</h3>
    <p>Use this for floor rollout before Play Store signing is configured.</p>
    <ol>
        <li>On a build machine with Flutter installed, clone or open the <code>ark-mobile</code> repo.</li>
        <li>Build: <code>flutter build apk --release</code></li>
        <li>Install file: <code>build/app/outputs/flutter-apk/app-release.apk</code></li>
        <li>Transfer APK to each Android device (AirDrop, email, USB, shared drive).</li>
        <li>On the device: allow installs from unknown sources for your file app, open the APK, install.</li>
        <li>Sign in with a staff account and confirm <strong>My Work</strong> (tech) or <strong>Check-in</strong> / <strong>Attention</strong> (advisor) loads.</li>
    </ol>
    <p>Reinstall the APK when a new build ships — there is no auto-update channel until Play Store or an MDM feed is wired.</p>

    <h3>Floor test from USB (developers)</h3>
    <p>For same-day validation on a tethered device:</p>
    <ol>
        <li>Enable <strong>Developer options</strong> and <strong>USB debugging</strong> on the Android device.</li>
        <li>Connect USB → run <code>flutter devices</code> to confirm the device ID.</li>
        <li>Run <code>flutter run --release -d &lt;device-id&gt;</code> from the <code>ark-mobile</code> project root.</li>
    </ol>
    <p>Release mode matches production performance; use this before handing APKs to the shop.</p>

    <h3>Play Store (later)</h3>
    <p>Production Play Store builds need a release keystore — the repo currently signs release APKs with debug keys (floor testing only).</p>
    <ol>
        <li>Create a keystore: <code>keytool -genkey -v -keystore upload-keystore.jks -keyalg RSA -keysize 2048 -validity 10000 -alias upload</code></li>
        <li>Add <code>android/key.properties</code> (gitignored) with store path, passwords, and alias.</li>
        <li>Wire signing in <code>android/app/build.gradle.kts</code> per Flutter release docs.</li>
        <li>Build bundle: <code>flutter build appbundle --release</code> → upload to Google Play Console.</li>
    </ol>
    <p>Keep the keystore backed up outside git — losing it blocks future updates under the same package ID.</p>

    <h3>OBD / iCar Pro</h3>
    <ul>
        <li>Requires Bluetooth LE on the device and the iCar Pro adapter paired through the app.</li>
        <li>Advisor check-in can scan DTCs and attach a summary to the new RO.</li>
        <li>Technicians can refresh DTC context on a concern from the production workspace.</li>
        <li>Grant location / nearby-devices permissions when Android prompts — required for BLE on modern Android versions.</li>
    </ul>

    <h3>Push notifications</h3>
    <p>Push is optional and currently deferred for floor observation. When enabled, configure Firebase per <a href="{{ route('operations.learn.show', ['role' => 'admin', 'article' => 'ark-mobile-push-setup']) }}">ARK Mobile push setup</a>.</p>

    <h3>Publish this guide to BookStack</h3>
    <p>After catalog changes deploy to production, post-deploy runs:</p>
    <p><code>php artisan ark:arkademy:import-bookstack --force</code></p>
    <p>Staff can then read this on <a href="https://learn.demo-auto.test">learn.demo-auto.test</a> under <strong>Shop In A Box → Admin</strong>.</p>

    <h3>Related</h3>
    <p>Advisor lot workflows: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'ark-mobile-check-in']) }}">Mobile check-in and OBD</a>, <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'ark-mobile-attention']) }}">ARK Mobile Attention</a>.</p>
    <p>Technician production: <a href="{{ route('operations.learn.show', ['role' => 'technician', 'article' => 'ark-mobile-concern-workspace']) }}">Concern workspace on mobile</a>.</p>
</div>
