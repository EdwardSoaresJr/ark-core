<div class="ops-learn-prose">
    <h3>Staff roles</h3>
    <table class="ops-learn-table">
        <thead>
            <tr>
                <th>Role</th>
                <th>Primary access</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Advisor</td>
                <td>Check In, customers, full estimate builder, documents, pricing override, reports view, closeout.</td>
            </tr>
            <tr>
                <td>Technician</td>
                <td>Workboard and repair order <strong>view</strong> — read sold work, no estimate editing.</td>
            </tr>
            <tr>
                <td>Admin</td>
                <td>All advisor capabilities plus settings, staff management, and financial manage.</td>
            </tr>
        </tbody>
    </table>

    <h3>Why roles are separated</h3>
    <ul>
        <li>Financial and pricing authority stay with advisors and admins.</li>
        <li>Technicians see operational truth without estimate-editing risk.</li>
        <li>{{ \App\Support\Branding\Branding::learnName() }} shows each role only its own training path.</li>
    </ul>

    <h3>Assigning roles</h3>
    <p>Manage staff in <strong>Settings → Staff</strong>. Each user should have one primary staff role matching their job. Mixed roles are supported — they will see multiple {{ \App\Support\Branding\Branding::learnName() }} sections.</p>

    <h3>Master admin</h3>
    <p>Platform-level accounts may exist outside normal shop roles. Keep those limited to owners and IT.</p>
</div>
