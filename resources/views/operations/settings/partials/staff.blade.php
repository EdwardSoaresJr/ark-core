@php
    use App\Ark\Runtime\Authorization\ArkRole;

    $defaultInviteRoles = [ArkRole::Advisor->value];
    $roleBadge = [
        ArkRole::Admin->value => 'bg-secondary-lt',
        ArkRole::Advisor->value => 'bg-orange-lt',
        ArkRole::Technician->value => 'bg-blue-lt',
        ArkRole::Customer->value => 'bg-secondary-lt',
    ];
@endphp

<div class="card-body" data-settings-staff-invite>
    @if ($errors->any() && (old('_member') || old('roles') || old('password') || old('labor_pay_basis') || old('labor_cost') || old('flag_rate') || old('floor_rate')))
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <h3 class="mb-1">Add team member</h3>
    <p class="text-secondary small">Sends a setup email with a signed link (valid {{ \App\Ark\Operations\Staff\StaffInvitationIssuer::INVITE_VALID_DAYS }} days).</p>

    <form
        method="POST"
        action="{{ route('operations.settings.staff.store') }}"
        x-data="{ roles: @js(array_values(old('roles', $defaultInviteRoles))) }"
        style="max-width: 48rem"
    >
        @csrf
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label for="staff-name" class="form-label required">Name</label>
                <input id="staff-name" name="name" value="{{ old('name') }}" required class="form-control" autocomplete="name">
            </div>
            <div class="col-12 col-md-6">
                <label for="staff-email" class="form-label required">Email</label>
                <input id="staff-email" name="email" type="email" value="{{ old('email') }}" required class="form-control" autocomplete="username">
            </div>
            <div class="col-12 col-md-6">
                <div class="d-flex align-items-center gap-1 mb-2">
                    <label for="staff-phone" class="form-label mb-0">Cell phone</label>
                    <x-tabler.help-tip
                        text="Used for inbound call ringing when this person is selected in Communications settings."
                        label="Cell phone help"
                    />
                </div>
                <input id="staff-phone" name="phone" type="text" value="{{ old('phone') }}" class="form-control" autocomplete="tel" placeholder="(719) 555-0100">
            </div>
            <fieldset class="col-12 col-md-6">
                <legend class="form-label">Roles</legend>
                <div class="d-flex flex-wrap gap-3">
                    @foreach ($staffRoles as $role)
                        <label class="form-check mb-0">
                            <input
                                type="checkbox"
                                name="roles[]"
                                value="{{ $role->value }}"
                                x-model="roles"
                                class="form-check-input"
                            >
                            <span class="form-check-label">{{ $role->label() }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <div class="col-12" x-show="roles.includes(@js(ArkRole::Technician->value))" x-cloak>
                <label for="staff-labor-cost" class="form-label">Estimated labor cost / hr</label>
                <input id="staff-labor-cost" name="labor_cost" type="number" min="0" step="0.01" value="{{ old('labor_cost') }}" class="form-control" style="max-width: 12rem" placeholder="35.00">
                <div class="form-hint">Cost per billed hour for labor GP on closed ROs - not a paycheck. Set shop overhead first under Settings → Shop Overhead.</div>
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
            <div class="col-12 col-md-6" x-show="roles.includes(@js(ArkRole::Technician->value))" x-cloak>
                <label for="staff-workday-hours" class="form-label">Workday hours</label>
                <input id="staff-workday-hours" name="workday_hours" type="number" min="1" max="24" step="0.25" value="{{ old('workday_hours') }}" class="form-control" placeholder="8">
                <div class="form-hint">Defaults to 8 hours for efficiency math when left blank.</div>
            </div>
        </div>
        <div class="d-flex justify-content-end border-top pt-3 mt-3">
            <button type="submit" class="btn btn-primary">Invite member</button>
        </div>
    </form>
</div>

<div class="card-body" data-settings-staff-list>
    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
        <h3 class="mb-0">Team</h3>
        <span class="badge bg-secondary-lt">{{ $staff->count() }} members</span>
    </div>

    <div class="row g-2 px-3 mb-1 d-none d-md-flex subheader" aria-hidden="true">
        <div class="col-md-5">Member</div>
        <div class="col-md-3">Roles</div>
        <div class="col-md-2">Status</div>
        <div class="col-md-2 text-end">Actions</div>
    </div>

    <div class="list-group">
        @forelse ($staff as $member)
            @php
                $memberRoles = $member->roles->pluck('name')->all();
            @endphp

            <div
                x-data="{ editing: {{ $errors->any() && old('_member') == $member->id ? 'true' : 'false' }}, roles: @js(array_values(old('roles', $memberRoles))) }"
                class="list-group-item"
                data-staff-member="{{ $member->id }}"
                @unless ($member->isActive()) data-staff-member-inactive @endunless
            >
                <div x-show="! editing" class="row g-2 align-items-center">
                    <div class="col-12 col-md-5">
                        <div @class(['fw-medium', 'text-secondary' => ! $member->isActive()])>{{ $member->name }}</div>
                        <div class="small text-secondary text-break">{{ $member->email }}</div>
                        @if ($member->display_phone)
                            <div class="small text-secondary">{{ $member->display_phone }}</div>
                        @endif
                        @if ($member->isMasterAdmin())
                            <div class="subheader">Master admin</div>
                        @endif
                        @if ($member->worksAsTechnician())
                            <div class="small text-secondary">
                                @if ($member->labor_cost_cents !== null)
                                    ${{ number_format($member->labor_cost_cents / 100, 2) }}/hr est. cost · {{ $member->laborPayBasis()->label() }}
                                @else
                                    {{ $member->laborPayBasis()->label() }} · set estimated labor cost
                                @endif
                            </div>
                            @if ($member->laborPayBasis() === \App\Ark\Operations\Labor\TechnicianLaborPayBasis::Flag)
                                <div class="small text-secondary">
                                    @if ($member->flag_rate_cents !== null)
                                        Flag ${{ number_format($member->flag_rate_cents / 100, 2) }}
                                    @else
                                        Flag -
                                    @endif
                                    ·
                                    @if ($member->floor_rate_cents !== null)
                                        Floor ${{ number_format($member->floor_rate_cents / 100, 2) }}
                                    @else
                                        Floor -
                                    @endif
                                    @if ($member->floorWageNeedsReview())
                                        <span class="text-warning">· Floor may need review</span>
                                    @endif
                                </div>
                            @endif
                        @endif
                        @if ($member->worksAsTechnician())
                            <div class="small text-secondary">{{ number_format($member->effectiveWorkdayHours(), 1) }} hr workday</div>
                        @endif
                        @if (auth()->user()?->canAccessOwnerWorkspace())
                            <a
                                href="{{ route('operations.owner.staff.coaching', $member) }}"
                                class="small"
                            >Coaching history</a>
                        @endif
                    </div>

                    <div class="col-6 col-md-3">
                        <div class="d-flex flex-wrap gap-1">
                            @forelse ($memberRoles as $memberRoleName)
                                @php($memberRole = ArkRole::tryFrom($memberRoleName))
                                @if ($memberRole)
                                    <span class="badge {{ $roleBadge[$memberRole->value] ?? 'bg-secondary-lt' }}">{{ $memberRole->label() }}</span>
                                @endif
                            @empty
                                <span class="small text-secondary">No roles</span>
                            @endforelse
                        </div>
                    </div>

                    <div class="col-6 col-md-2">
                        @if (! $member->isActive())
                            <span class="badge bg-secondary-lt" data-staff-status="disabled">Disabled</span>
                        @elseif ($member->needsPasswordSetup())
                            <span class="badge bg-yellow-lt" data-staff-status="pending">Invite pending</span>
                        @else
                            <span class="badge bg-green-lt" data-staff-status="active">Active</span>
                        @endif
                    </div>

                    <div class="col-12 col-md-2">
                        <div class="btn-list justify-content-md-end">
                            <button type="button" class="btn btn-sm" @click="editing = true">Edit</button>
                            @if ($member->isActive() && $member->needsPasswordSetup())
                                <form method="POST" action="{{ route('operations.settings.staff.resend-invitation', $member) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm">Resend invite</button>
                                </form>
                            @endif
                            @if ($member->isActive())
                                @if ($member->id !== auth()->id())
                                    <form method="POST" action="{{ route('operations.settings.staff.deactivate', $member) }}" class="d-inline" onsubmit="return confirm('Disable {{ $member->name }}? They will not be able to sign in.')">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-ghost-danger">Disable</button>
                                    </form>
                                @endif
                            @else
                                <form method="POST" action="{{ route('operations.settings.staff.activate', $member) }}" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm">Enable</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>

                <form
                    x-show="editing"
                    x-cloak
                    method="POST"
                    action="{{ route('operations.settings.staff.update', $member) }}"
                    data-staff-member-form
                >
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="_member" value="{{ $member->id }}">

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label for="staff-name-{{ $member->id }}" class="form-label required">Name</label>
                            <input id="staff-name-{{ $member->id }}" name="name" value="{{ old('name', $member->name) }}" required class="form-control">
                        </div>
                        <fieldset class="col-12 col-md-6">
                            <legend class="form-label">Roles</legend>
                            @if ($member->isMasterAdmin())
                                <input type="hidden" name="roles[]" value="{{ ArkRole::Admin->value }}">
                            @endif
                            <div class="d-flex flex-wrap gap-3">
                                @foreach ($staffRoles as $role)
                                    <label class="form-check mb-0">
                                        <input
                                            type="checkbox"
                                            name="roles[]"
                                            value="{{ $role->value }}"
                                            x-model="roles"
                                            @disabled($member->isMasterAdmin() && $role === ArkRole::Admin)
                                            class="form-check-input"
                                        >
                                        <span class="form-check-label">{{ $role->label() }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @if ($member->isMasterAdmin())
                                <div class="form-hint">Master admin keeps the admin role permanently.</div>
                            @endif
                        </fieldset>
                        <div class="col-12 col-md-6">
                            <label for="staff-email-{{ $member->id }}" class="form-label required">Email</label>
                            <input id="staff-email-{{ $member->id }}" name="email" type="email" value="{{ old('email', $member->email) }}" required class="form-control">
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="staff-phone-{{ $member->id }}" class="form-label">Cell phone</label>
                            <input id="staff-phone-{{ $member->id }}" name="phone" type="text" value="{{ old('phone', $member->phone) }}" class="form-control" autocomplete="tel" placeholder="(719) 555-0100">
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="d-flex align-items-center gap-1 mb-2">
                                <label for="staff-operator-pin-{{ $member->id }}" class="form-label mb-0">Workstation PIN</label>
                                <x-tabler.help-tip
                                    text="Used to unlock shared workstations - not the ARK login password."
                                    label="Workstation PIN help for {{ $member->name }}"
                                />
                            </div>
                            <input
                                id="staff-operator-pin-{{ $member->id }}"
                                name="operator_pin"
                                type="password"
                                inputmode="numeric"
                                pattern="\d{4}"
                                maxlength="4"
                                class="form-control"
                                placeholder="{{ $member->hasOperatorPin() ? '•••• (leave blank to keep)' : '4 digits' }}"
                                autocomplete="off"
                            >
                        </div>
                        <div class="col-12" x-show="roles.includes(@js(ArkRole::Technician->value))" x-cloak>
                            <label for="staff-labor-cost-{{ $member->id }}" class="form-label">Estimated labor cost / hr</label>
                            <input
                                id="staff-labor-cost-{{ $member->id }}"
                                name="labor_cost"
                                type="number"
                                min="0"
                                step="0.01"
                                value="{{ old('labor_cost', $member->labor_cost_cents !== null ? number_format($member->labor_cost_cents / 100, 2, '.', '') : '') }}"
                                class="form-control"
                                style="max-width: 12rem"
                                placeholder="35.00"
                            >
                            <div class="form-hint">Margin cost per billed hour - not a paycheck. Set shop overhead first under Settings → Shop Overhead.</div>
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
                        <div class="col-12 col-md-6" x-show="roles.includes(@js(ArkRole::Technician->value))" x-cloak>
                            <label for="staff-workday-hours-{{ $member->id }}" class="form-label">Workday hours</label>
                            <input
                                id="staff-workday-hours-{{ $member->id }}"
                                name="workday_hours"
                                type="number"
                                min="1"
                                max="24"
                                step="0.25"
                                value="{{ old('workday_hours', $member->workday_hours !== null ? number_format((float) $member->workday_hours, 2, '.', '') : '') }}"
                                class="form-control"
                                placeholder="8"
                            >
                        </div>
                        @if ($member->hasPasswordSet())
                            <div class="col-12 col-md-6">
                                <label for="staff-password-{{ $member->id }}" class="form-label">New password</label>
                                <input id="staff-password-{{ $member->id }}" name="password" type="password" placeholder="Leave blank to keep" class="form-control">
                            </div>
                        @else
                            <div class="col-12 col-md-6">
                                <p class="small text-secondary mb-0">Password setup is pending. Use <strong>Resend invite</strong> to send another email.</p>
                            </div>
                        @endif
                    </div>

                    <div class="btn-list justify-content-end border-top pt-3 mt-3">
                        <button type="button" class="btn btn-ghost-secondary" @click="editing = false">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </div>
                </form>
            </div>
        @empty
            <div class="list-group-item text-secondary">
                No staff accounts yet. Add your first team member above.
            </div>
        @endforelse
    </div>
</div>

<div class="card-footer small" data-settings-staff-legend>
    <p class="mb-1"><span class="badge {{ $roleBadge[ArkRole::Admin->value] }}">Admin</span> settings, staff, and full shop control.</p>
    <p class="mb-1"><span class="badge {{ $roleBadge[ArkRole::Advisor->value] }}">Advisor</span> customers, estimates, approvals, and closeout.</p>
    <p class="mb-1"><span class="badge {{ $roleBadge[ArkRole::Technician->value] }}">Technician</span> assignable on ROs and estimated labor cost for report GP.</p>
    <p class="text-secondary mb-0">The <strong>master admin</strong> account keeps admin permanently. Shop owners can also check <strong>Advisor</strong> + <strong>Technician</strong> to show up in technician assignment and labor reporting.</p>
</div>
