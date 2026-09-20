<div class="ops-learn-prose">
    <h3>Staff before software tricks</h3>
    <p>New hire onboarding in ARK: create user in <a href="{{ route('operations.settings.shop.edit') }}">Settings → Staff</a>, assign role (advisor, technician, admin, owner), resend invitation if email missed.</p>
    <p>Role drives {{ \App\Support\Branding\Branding::learnName() }} curriculum and operational permissions — wrong role means wrong guides and wrong Settings access on day one.</p>
    <p>Deactivate instead of delete when someone leaves — history stays attached to real users for audit and messaging.</p>

    <x-operations.learn.figure
        role="admin"
        article="staff-onboarding"
        file="staff-settings.png"
        alt="Staff settings with role assignment and invitation status"
        caption="Confirm phone/SIP endpoint mapping separately for advisors who answer shop line."
    />

    <h3>First-week curriculum</h3>
    <p>Advisors: {{ \App\Support\Branding\Branding::learnName() }} advisor track starting with <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'getting-started']) }}">Advisor basics</a>, then intake, workboard, <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'remote-sell']) }}">remote sell</a>, authorization.</p>
    <p>Technicians: technician getting started, reading estimates, findings, MPI, production sheet.</p>
    <p>Admins read admin getting started plus financial rules, workflow defaults, comms health — you cannot coach what you have not configured.</p>

    <h3>Floor pairing</h3>
    <p>Shadow counter with senior advisor before solo intake — scope naming mistakes are expensive to unwind.</p>
    <p>Telephony: verify ring group includes new advisor endpoint before first live shift. See <a href="{{ route('operations.learn.show', ['role' => 'admin', 'article' => 'telephony-sip-setup']) }}">Telephony and SIP</a>.</p>
    <p>Team progress view: <a href="{{ route('operations.learn.team-progress') }}">{{ \App\Support\Branding\Branding::learnName() }} team progress</a> for checkpoint completion without nagging.</p>

    <x-operations.learn.video
        role="admin"
        article="staff-onboarding"
        file="walkthrough.mp4"
        video-key="main"
        title="Create staff, assign role, point to {{ \App\Support\Branding\Branding::learnName() }}"
        poster-file="poster.jpg"
    />

    <h3>Related guides</h3>
    <p>Roles detail: <a href="{{ route('operations.learn.show', ['role' => 'admin', 'article' => 'roles-and-access']) }}">Roles and access</a>.</p>
</div>
