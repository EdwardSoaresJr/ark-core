@php
    use App\Ark\Runtime\Authorization\ArkRole;

    $defaultInviteRoles = [ArkRole::Advisor->value];
@endphp

<section x-show="active === 'staff'" x-cloak class="ops-staff-settings space-y-4">
    <div class="border-b border-slate-200 pb-2">
        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Staff Access</p>
        <h2 class="text-base font-black text-slate-950">Team logins and roles</h2>
        <p class="mt-0.5 text-xs text-slate-500">Add name, email, and roles — ARK emails a secure setup link so they choose their own password. Select every hat someone wears: admin, advisor, and/or technician.</p>
    </div>

    @if ($errors->any() && (old('_member') || old('roles') || old('password') || old('labor_pay_basis') || old('labor_cost') || old('flag_rate') || old('floor_rate')))
        <p class="border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-800">{{ $errors->first() }}</p>
    @endif

    <div class="ops-staff-invite border border-slate-200">
        <div class="ops-staff-panel-head">
            <p class="ops-staff-panel-title">Add team member</p>
            <p class="ops-staff-panel-hint">Sends a setup email with a signed link (valid {{ \App\Ark\Operations\Staff\StaffInvitationIssuer::INVITE_VALID_DAYS }} days).</p>
        </div>

        <form
            method="POST"
            action="{{ route('operations.settings.staff.store') }}"
            class="ops-staff-invite-form"
            x-data="{ roles: @js(array_values(old('roles', $defaultInviteRoles))) }"
        >
            @csrf
            <div class="ops-staff-field">
                <label for="staff-name" class="ops-index-field-label">Name</label>
                <input id="staff-name" name="name" value="{{ old('name') }}" required class="ops-index-field" autocomplete="name">
            </div>
            <div class="ops-staff-field">
                <label for="staff-email" class="ops-index-field-label">Email</label>
                <input id="staff-email" name="email" type="email" value="{{ old('email') }}" required class="ops-index-field" autocomplete="username">
            </div>
            <div class="ops-staff-field">
                <label for="staff-phone" class="ops-index-field-label">Cell phone</label>
                <input id="staff-phone" name="phone" type="text" value="{{ old('phone') }}" class="ops-index-field" autocomplete="tel" placeholder="(719) 555-0100">
                <p class="mt-1 text-xs text-slate-500">Used for inbound call ringing when this person is selected in Communications settings.</p>
            </div>
            <div class="ops-staff-field ops-staff-field--role">
                <span class="ops-index-field-label">Roles</span>
                <div class="mt-1 flex flex-wrap gap-3">
                    @foreach ($staffRoles as $role)
                        <label class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-800">
                            <input
                                type="checkbox"
                                name="roles[]"
                                value="{{ $role->value }}"
                                x-model="roles"
                                class="rounded-sm border-slate-300"
                            >
                            {{ $role->label() }}
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="ops-staff-field" x-show="roles.includes(@js(ArkRole::Technician->value))" x-cloak>
                <label for="staff-labor-cost" class="ops-index-field-label">Estimated labor cost / hr</label>
                <input id="staff-labor-cost" name="labor_cost" type="number" min="0" step="0.01" value="{{ old('labor_cost') }}" class="ops-index-field" placeholder="35.00">
                <p class="mt-1 text-xs text-slate-500">Cost per billed hour for labor GP on closed ROs — not a paycheck. Set shop overhead first under Settings → Shop Overhead.</p>
                @include('operations.settings.partials.loaded-labor-cost-calculator', [
                    'targetInputId' => 'staff-labor-cost',
                    'workdayHours' => old('workday_hours') ?: 8,
                    'shopOverheadPerHour' => $settings->shopOverheadPerHour(),
                    'laborPayBasis' => old('labor_pay_basis', \App\Ark\Operations\Labor\TechnicianLaborPayBasis::Hourly->value),
                    'flagRate' => old('flag_rate'),
                    'floorRate' => old('floor_rate'),
                    'seedFloorSuggestion' => true,
                ])
            </div>
            <div class="ops-staff-field" x-show="roles.includes(@js(ArkRole::Technician->value))" x-cloak>
                <label for="staff-workday-hours" class="ops-index-field-label">Workday hours</label>
                <input id="staff-workday-hours" name="workday_hours" type="number" min="1" max="24" step="0.25" value="{{ old('workday_hours') }}" class="ops-index-field" placeholder="8">
                <p class="mt-1 text-xs text-slate-500">Defaults to 8 hours for efficiency math when left blank.</p>
            </div>
            <div class="ops-staff-field ops-staff-field--action">
                <button type="submit" class="ops-index-btn ops-index-btn--primary">Invite member</button>
            </div>
        </form>
    </div>

    <div class="ops-board-shell">
        <div class="ops-index-results-head">
            <span>Team</span>
            <span class="tabular-nums">{{ $staff->count() }} members</span>
        </div>

        <div class="ops-index-results-columns ops-index-results-columns--staff">
            <span>Member</span>
            <span>Roles</span>
            <span>Status</span>
            <span class="text-right">Actions</span>
        </div>

        <div class="ops-staff-list">
            @forelse ($staff as $member)
                @php
                    $memberRoles = $member->roles->pluck('name')->all();
                @endphp

                <div
                    x-data="{ editing: {{ $errors->any() && old('_member') == $member->id ? 'true' : 'false' }}, roles: @js(array_values(old('roles', $memberRoles))) }"
                    class="ops-staff-member {{ $member->isActive() ? '' : 'ops-staff-member--inactive' }}"
                >
                    <div x-show="! editing" class="ops-index-results-row ops-index-results-row--staff">
                        <div class="ops-staff-member-identity">
                            <p class="ops-staff-member-name">{{ $member->name }}</p>
                            <p class="ops-staff-member-email">{{ $member->email }}</p>
                            @if ($member->display_phone)
                                <p class="text-[11px] font-medium text-slate-500">{{ $member->display_phone }}</p>
                            @endif
                            @if ($member->isMasterAdmin())
                                <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Master admin</p>
                            @endif
                            @if ($member->worksAsTechnician())
                                <p class="text-[11px] font-medium text-slate-500">
                                    @if ($member->labor_cost_cents !== null)
                                        ${{ number_format($member->labor_cost_cents / 100, 2) }}/hr est. cost · {{ $member->laborPayBasis()->label() }}
                                    @else
                                        {{ $member->laborPayBasis()->label() }} · set estimated labor cost
                                    @endif
                                </p>
                                @if ($member->laborPayBasis() === \App\Ark\Operations\Labor\TechnicianLaborPayBasis::Flag)
                                    <p class="text-[11px] font-medium text-slate-500">
                                        @if ($member->flag_rate_cents !== null)
                                            Flag ${{ number_format($member->flag_rate_cents / 100, 2) }}
                                        @else
                                            Flag —
                                        @endif
                                        ·
                                        @if ($member->floor_rate_cents !== null)
                                            Floor ${{ number_format($member->floor_rate_cents / 100, 2) }}
                                        @else
                                            Floor —
                                        @endif
                                        @if ($member->floorWageNeedsReview())
                                            <span class="text-amber-700">· Floor may need review</span>
                                        @endif
                                    </p>
                                @endif
                            @endif
                            @if ($member->worksAsTechnician())
                                <p class="text-[11px] font-medium text-slate-500">{{ number_format($member->effectiveWorkdayHours(), 1) }} hr workday</p>
                            @endif
                            @if (auth()->user()?->canAccessOwnerWorkspace())
                                <a
                                    href="{{ route('operations.owner.staff.coaching', $member) }}"
                                    class="mt-1 inline-block text-[11px] font-bold text-slate-700 underline decoration-slate-300 hover:text-slate-950"
                                >Coaching history</a>
                            @endif
                        </div>

                        <div class="ops-staff-member-role flex flex-wrap gap-1">
                            @forelse ($memberRoles as $memberRoleName)
                                @php($memberRole = ArkRole::tryFrom($memberRoleName))
                                @if ($memberRole)
                                    <span class="ops-role-chip {{ $memberRole->chipClass() }}">{{ $memberRole->label() }}</span>
                                @endif
                            @empty
                                <span class="text-xs text-slate-400">No roles</span>
                            @endforelse
                        </div>

                        <div class="ops-staff-member-status">
                            @if (! $member->isActive())
                                <span class="ops-staff-status ops-staff-status--disabled">Disabled</span>
                            @elseif ($member->needsPasswordSetup())
                                <span class="ops-staff-status ops-staff-status--pending">Invite pending</span>
                            @else
                                <span class="ops-staff-status ops-staff-status--active">Active</span>
                            @endif
                        </div>

                        <div class="ops-staff-member-actions">
                            <button type="button" class="ops-staff-link" @click="editing = true">Edit</button>
                            @if ($member->isActive() && $member->needsPasswordSetup())
                                <form method="POST" action="{{ route('operations.settings.staff.resend-invitation', $member) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="ops-staff-link">Resend invite</button>
                                </form>
                            @endif
                            @if ($member->isActive())
                                @if ($member->id !== auth()->id())
                                    <form method="POST" action="{{ route('operations.settings.staff.deactivate', $member) }}" class="inline" onsubmit="return confirm('Disable {{ $member->name }}? They will not be able to sign in.')">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="ops-staff-link ops-staff-link--danger">Disable</button>
                                    </form>
                                @endif
                            @else
                                <form method="POST" action="{{ route('operations.settings.staff.activate', $member) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="ops-staff-link">Enable</button>
                                </form>
                            @endif
                        </div>
                    </div>

                    <form
                        x-show="editing"
                        x-cloak
                        method="POST"
                        action="{{ route('operations.settings.staff.update', $member) }}"
                        class="ops-staff-member-form"
                    >
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="_member" value="{{ $member->id }}">

                        <div class="ops-staff-form-grid">
                            <div class="ops-staff-field">
                                <label for="staff-name-{{ $member->id }}" class="ops-index-field-label">Name</label>
                                <input id="staff-name-{{ $member->id }}" name="name" value="{{ old('name', $member->name) }}" required class="ops-index-field">
                            </div>
                            <div class="ops-staff-field ops-staff-field--role">
                                <span class="ops-index-field-label">Roles</span>
                                @if ($member->isMasterAdmin())
                                    <input type="hidden" name="roles[]" value="{{ ArkRole::Admin->value }}">
                                @endif
                                <div class="mt-1 flex flex-wrap gap-3">
                                    @foreach ($staffRoles as $role)
                                        <label class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-800">
                                            <input
                                                type="checkbox"
                                                name="roles[]"
                                                value="{{ $role->value }}"
                                                x-model="roles"
                                                @disabled($member->isMasterAdmin() && $role === ArkRole::Admin)
                                                class="rounded-sm border-slate-300"
                                            >
                                            {{ $role->label() }}
                                        </label>
                                    @endforeach
                                </div>
                                @if ($member->isMasterAdmin())
                                    <p class="mt-1 text-xs text-slate-500">Master admin keeps the admin role permanently.</p>
                                @endif
                            </div>
                            <div class="ops-staff-field">
                                <label for="staff-email-{{ $member->id }}" class="ops-index-field-label">Email</label>
                                <input id="staff-email-{{ $member->id }}" name="email" type="email" value="{{ old('email', $member->email) }}" required class="ops-index-field">
                            </div>
                            <div class="ops-staff-field">
                                <label for="staff-phone-{{ $member->id }}" class="ops-index-field-label">Cell phone</label>
                                <input id="staff-phone-{{ $member->id }}" name="phone" type="text" value="{{ old('phone', $member->phone) }}" class="ops-index-field" autocomplete="tel" placeholder="(719) 555-0100">
                            </div>
                            <div class="ops-staff-field">
                                <label for="staff-operator-pin-{{ $member->id }}" class="ops-index-field-label">Workstation PIN</label>
                                <input
                                    id="staff-operator-pin-{{ $member->id }}"
                                    name="operator_pin"
                                    type="password"
                                    inputmode="numeric"
                                    pattern="\d{4}"
                                    maxlength="4"
                                    class="ops-index-field"
                                    placeholder="{{ $member->hasOperatorPin() ? '•••• (leave blank to keep)' : '4 digits' }}"
                                    autocomplete="off"
                                >
                                <p class="mt-1 text-xs text-slate-500">Used to unlock shared workstations — not the ARK login password.</p>
                            </div>
                            <div class="ops-staff-field" x-show="roles.includes(@js(ArkRole::Technician->value))" x-cloak>
                                <label for="staff-labor-cost-{{ $member->id }}" class="ops-index-field-label">Estimated labor cost / hr</label>
                                <input
                                    id="staff-labor-cost-{{ $member->id }}"
                                    name="labor_cost"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value="{{ old('labor_cost', $member->labor_cost_cents !== null ? number_format($member->labor_cost_cents / 100, 2, '.', '') : '') }}"
                                    class="ops-index-field"
                                    placeholder="35.00"
                                >
                                <p class="mt-1 text-xs text-slate-500">Margin cost per billed hour — not a paycheck. Set shop overhead first under Settings → Shop Overhead.</p>
                                @include('operations.settings.partials.loaded-labor-cost-calculator', [
                                    'targetInputId' => 'staff-labor-cost-'.$member->id,
                                    'workdayHours' => $member->effectiveWorkdayHours(),
                                    'shopOverheadPerHour' => $settings->shopOverheadPerHour(),
                                    'laborPayBasis' => old('labor_pay_basis', $member->laborPayBasis()->value),
                                    'flagRate' => old('flag_rate', $member->flag_rate_cents !== null ? number_format($member->flag_rate_cents / 100, 2, '.', '') : ''),
                                    'floorRate' => old('floor_rate', $member->floor_rate_cents !== null ? number_format($member->floor_rate_cents / 100, 2, '.', '') : ''),
                                    'seedFloorSuggestion' => false,
                                ])
                            </div>
                            <div class="ops-staff-field" x-show="roles.includes(@js(ArkRole::Technician->value))" x-cloak>
                                <label for="staff-workday-hours-{{ $member->id }}" class="ops-index-field-label">Workday hours</label>
                                <input
                                    id="staff-workday-hours-{{ $member->id }}"
                                    name="workday_hours"
                                    type="number"
                                    min="1"
                                    max="24"
                                    step="0.25"
                                    value="{{ old('workday_hours', $member->workday_hours !== null ? number_format((float) $member->workday_hours, 2, '.', '') : '') }}"
                                    class="ops-index-field"
                                    placeholder="8"
                                >
                            </div>
                            @if ($member->hasPasswordSet())
                                <div class="ops-staff-field">
                                    <label for="staff-password-{{ $member->id }}" class="ops-index-field-label">New password</label>
                                    <input id="staff-password-{{ $member->id }}" name="password" type="password" placeholder="Leave blank to keep" class="ops-index-field">
                                </div>
                            @else
                                <div class="ops-staff-field ops-staff-field--hint">
                                    <p class="text-xs text-slate-500">Password setup is pending. Use <strong>Resend invite</strong> to send another email.</p>
                                </div>
                            @endif
                        </div>

                        <div class="ops-staff-form-actions">
                            <button type="button" class="ops-index-btn ops-index-btn--ghost" @click="editing = false">Cancel</button>
                            <button type="submit" class="ops-index-btn ops-index-btn--primary">Save changes</button>
                        </div>
                    </form>
                </div>
            @empty
                <div class="ops-staff-empty">
                    No staff accounts yet. Add your first team member above.
                </div>
            @endforelse
        </div>
    </div>

    <div class="ops-staff-legend">
        <p><span class="ops-role-chip ops-role-chip--admin">Admin</span> settings, staff, and full shop control.</p>
        <p><span class="ops-role-chip ops-role-chip--advisor">Advisor</span> customers, estimates, approvals, and closeout.</p>
        <p><span class="ops-role-chip ops-role-chip--technician">Technician</span> assignable on ROs and estimated labor cost for report GP.</p>
        <p class="text-slate-500">The <strong>master admin</strong> account keeps admin permanently. Shop owners can also check <strong>Advisor</strong> + <strong>Technician</strong> to show up in technician assignment and labor reporting.</p>
    </div>
</section>
