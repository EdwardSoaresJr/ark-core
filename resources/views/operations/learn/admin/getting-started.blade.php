<div class="ops-learn-prose">
    <h3>Admin role in ARK</h3>
    <p>Admins own shop configuration, staff access, and financial authority. Day-to-day RO work can still happen on an admin account, but your primary job is keeping ARK truthful for the shop.</p>

    <h3>Settings areas</h3>
    <ul>
        <li><strong>General</strong> — shop identity, logo, contact info.</li>
        <li><strong>Labor</strong> — default rate, labor categories, modifiers.</li>
        <li><strong>Tax and shop fees</strong> — authoritative calculation inputs.</li>
        <li><strong>Parts matrices</strong> — markup policy for part sell pricing.</li>
        <li><strong>Customer types</strong> — billing posture and disclaimer behavior.</li>
        <li><strong>Document settings</strong> — disclaimers, validity, authorization language, optional <strong>Require customer signature on portal authorization</strong> for digital sign-off on remote sell.</li>
        <li><strong>Workflow defaults</strong> — visit mode, default recommendation intent, note privacy.</li>
        <li><strong>Printing</strong> — QZ tray, key tags, oil stickers.</li>
        <li><strong>Communications</strong> — telephony health, ring group (cells + SIP), SMS webhook URLs, optional Facebook Messenger. Owner guide: <a href="{{ route('operations.learn.show', ['role' => 'owner', 'article' => 'communications-setup']) }}">Communications setup</a>. Desk phones: <a href="{{ route('operations.learn.show', ['role' => 'admin', 'article' => 'telephony-sip-setup']) }}">Telephony and SIP desk phones</a>. Messenger: <a href="{{ route('operations.learn.show', ['role' => 'admin', 'article' => 'messenger-setup']) }}">Facebook Messenger setup</a>.</li>
        <li><strong>Payments</strong> — Record external payments on the repair order. See <a #>Payment recording</a>.</li>
        <li><strong>Shop Overhead</strong> — fixed cost worksheet and shop overhead / billed hr. See <a href="{{ route('operations.learn.show', ['role' => 'admin', 'article' => 'shop-overhead-setup']) }}">Shop overhead and loaded labor cost</a>.</li>
        <li><strong>Staff</strong> — users, roles, active status.</li>
    </ul>

    <p>Open <a href="{{ route('operations.settings.shop.edit') }}">Settings</a> from the left menu. Changes here affect every new line, PDF, and total — not just display.</p>

    <h3>Training staff</h3>
    <p>Point advisors to the <strong>Advisor</strong> guides and technicians to the <strong>Technician</strong> guides in {{ \App\Support\Branding\Branding::learnName() }}. Admins can read all sections for coaching and QA.</p>
    <p>On any guide page, open <strong>Guide media</strong> to upload screenshots, MP4 walkthroughs, or YouTube embeds. Slot names must match the guide markup (figure filename or <code>video:main</code> for walkthrough video).</p>
</div>
