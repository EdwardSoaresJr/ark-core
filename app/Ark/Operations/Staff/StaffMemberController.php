<?php

namespace App\Ark\Operations\Staff;

use App\Ark\Operations\Labor\RecordTechnicianCompensationAgreementAction;
use App\Ark\Operations\Labor\TechnicianFloorWageSuggestion;
use App\Ark\Operations\Labor\TechnicianLaborPayBasis;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Ark\Runtime\Preferences\DisplayTheme;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffMemberController
{
    public function __construct(
        private readonly StaffInvitationIssuer $invitations,
        private readonly RecordTechnicianCompensationAgreementAction $compensationAgreements,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateStaff($request, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:32'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', Rule::in($this->staffRoleValues())],
            'labor_cost' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'labor_pay_basis' => ['nullable', Rule::enum(TechnicianLaborPayBasis::class)],
            'flag_rate' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'floor_rate' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'workday_hours' => ['nullable', 'numeric', 'min:1', 'max:24'],
        ]);

        $roles = $this->normalizeRoles($data['roles']);
        $payBasis = $this->laborPayBasisFromInput($data, $roles);

        $user = User::query()->forceCreate([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => filled($data['phone'] ?? null) ? trim((string) $data['phone']) : null,
            'labor_cost_cents' => $this->laborCostCentsFromInput($data, $roles),
            'labor_pay_basis' => $payBasis,
            'flag_rate_cents' => $this->flagRateCentsFromInput($data, $roles, $payBasis),
            'floor_rate_cents' => $this->floorRateCentsFromInput($data, $roles, $payBasis),
            'workday_hours' => $this->workdayHoursFromInput($data, $roles),
            'password' => StaffInvitationIssuer::placeholderPassword(),
            'is_active' => true,
            'password_set_at' => null,
            'display_theme' => DisplayTheme::default()->value,
        ]);

        $user->syncRoles($roles);

        $this->syncCompensationAgreementHistory($user, $roles, $request->user());

        $this->invitations->send($user);

        return $this->redirectToStaffSettings($user->name.' invited. A setup email was sent to '.$user->email.'.');
    }

    public function resendInvitation(User $user): RedirectResponse
    {
        $this->ensureStaffMember($user);

        if ($user->hasPasswordSet()) {
            return $this->redirectToStaffSettings($user->name.' has already set a password.');
        }

        abort_unless($user->isActive(), 422);

        $this->invitations->send($user);

        return $this->redirectToStaffSettings('Setup email resent to '.$user->email.'.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->ensureStaffMember($user);

        $data = $this->validateStaff($request, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', Rule::in($this->staffRoleValues())],
            'labor_cost' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'labor_pay_basis' => ['nullable', Rule::enum(TechnicianLaborPayBasis::class)],
            'flag_rate' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'floor_rate' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'workday_hours' => ['nullable', 'numeric', 'min:1', 'max:24'],
            'operator_pin' => ['nullable', 'string', 'size:4', 'regex:/^\d{4}$/'],
        ]);

        $roles = $this->normalizeRoles($data['roles']);
        $this->guardRoleChange($user, $roles, $request->user());
        $payBasis = $this->laborPayBasisFromInput($data, $roles, $user);

        $user->forceFill([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => filled($data['phone'] ?? null) ? trim((string) $data['phone']) : null,
            'labor_cost_cents' => $this->laborCostCentsFromInput($data, $roles, $user),
            'labor_pay_basis' => $payBasis,
            'flag_rate_cents' => $this->flagRateCentsFromInput($data, $roles, $payBasis, $user),
            'floor_rate_cents' => $this->floorRateCentsFromInput($data, $roles, $payBasis, $user),
            'workday_hours' => $this->workdayHoursFromInput($data, $roles, $user),
        ]);

        if (filled($data['password'] ?? null)) {
            $user->forceFill([
                'password' => Hash::make($data['password']),
                'password_set_at' => now(),
            ]);
        }

        if (array_key_exists('operator_pin', $data) && filled($data['operator_pin'])) {
            $user->setOperatorPin((string) $data['operator_pin']);
        }

        $user->save();
        $user->syncRoles($roles);

        $this->syncCompensationAgreementHistory($user, $roles, $request->user());

        return $this->redirectToStaffSettings($user->name.' updated.');
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        $this->ensureStaffMember($user);
        $this->guardAccountChange($user, $request->user());

        if (! $user->isActive()) {
            return $this->redirectToStaffSettings($user->name.' is already disabled.');
        }

        $user->forceFill(['is_active' => false])->save();

        return $this->redirectToStaffSettings($user->name.' disabled. They can no longer sign in.');
    }

    public function activate(User $user): RedirectResponse
    {
        $this->ensureStaffMember($user);

        if ($user->isActive()) {
            return $this->redirectToStaffSettings($user->name.' is already active.');
        }

        $user->forceFill(['is_active' => true])->save();

        return $this->redirectToStaffSettings($user->name.' re-enabled.');
    }

    /**
     * @return Collection<int, User>
     */
    public static function list(): Collection
    {
        $roleValues = collect(ArkRole::staffAssignable())
            ->map(fn (ArkRole $role): string => $role->value)
            ->all();

        return User::query()
            ->whereHas('roles', function ($query) use ($roleValues): void {
                $query->whereIn('name', $roleValues);
            })
            ->with('roles:id,name')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function validateStaff(Request $request, array $rules): array
    {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray())
                ->redirectTo(route('operations.settings.shop.edit', ['section' => 'staff']));
        }

        return $validator->validated();
    }

    private function redirectToStaffSettings(string $message): RedirectResponse
    {
        return redirect()
            ->route('operations.settings.shop.edit', ['section' => 'staff'])
            ->with('status', $message);
    }

    private function ensureStaffMember(User $user): void
    {
        abort_unless(
            $user->roles->pluck('name')->intersect($this->staffRoleValues())->isNotEmpty(),
            404,
        );
    }

    private function guardRoleChange(User $user, array $newRoles, User $actor): void
    {
        if ($user->isMasterAdmin() && ! in_array(ArkRole::Admin->value, $newRoles, true)) {
            throw ValidationException::withMessages([
                'roles' => 'The master admin account must keep the admin role.',
            ])->redirectTo(route('operations.settings.shop.edit', ['section' => 'staff']));
        }

        if (! $user->hasRole(ArkRole::Admin->value) || in_array(ArkRole::Admin->value, $newRoles, true)) {
            return;
        }

        if ($this->activeAdminCountExcluding($user->id) === 0) {
            throw ValidationException::withMessages([
                'roles' => 'At least one active admin account must remain.',
            ])->redirectTo(route('operations.settings.shop.edit', ['section' => 'staff']));
        }

        if ($user->is($actor)) {
            throw ValidationException::withMessages([
                'roles' => 'Assign another admin before removing admin from your own account.',
            ])->redirectTo(route('operations.settings.shop.edit', ['section' => 'staff']));
        }
    }

    private function guardAccountChange(User $user, User $actor, bool $allowSelf = false): void
    {
        if ($allowSelf && $user->is($actor)) {
            return;
        }

        if ($user->is($actor)) {
            throw ValidationException::withMessages([
                'staff' => 'You cannot disable your own account.',
            ])->redirectTo(route('operations.settings.shop.edit', ['section' => 'staff']));
        }

        if (! $user->hasRole(ArkRole::Admin->value) || ! $user->isActive()) {
            return;
        }

        if ($this->activeAdminCountExcluding($user->id) === 0) {
            throw ValidationException::withMessages([
                'staff' => 'At least one active admin account must remain.',
            ])->redirectTo(route('operations.settings.shop.edit', ['section' => 'staff']));
        }
    }

    private function activeAdminCountExcluding(int $exceptUserId): int
    {
        return User::role(ArkRole::Admin->value)
            ->active()
            ->whereKeyNot($exceptUserId)
            ->count();
    }

    /**
     * @return list<string>
     */
    private function staffRoleValues(): array
    {
        return collect(ArkRole::staffAssignable())
            ->map(fn (ArkRole $role): string => $role->value)
            ->all();
    }

    /**
     * @param  list<string>  $roles
     * @return list<string>
     */
    private function normalizeRoles(array $roles): array
    {
        return collect($roles)
            ->filter(fn (mixed $role): bool => is_string($role) && $role !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $roles
     */
    private function laborCostCentsFromInput(array $data, array $roles, ?User $existing = null): ?int
    {
        if (! in_array(ArkRole::Technician->value, $roles, true)) {
            return null;
        }

        if (! array_key_exists('labor_cost', $data) || $data['labor_cost'] === null || $data['labor_cost'] === '') {
            return $existing?->labor_cost_cents;
        }

        return (int) round(((float) $data['labor_cost']) * 100);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $roles
     */
    private function laborPayBasisFromInput(array $data, array $roles, ?User $existing = null): string
    {
        if (! in_array(ArkRole::Technician->value, $roles, true)) {
            return $existing?->labor_pay_basis ?? TechnicianLaborPayBasis::Hourly->value;
        }

        if (! array_key_exists('labor_pay_basis', $data) || $data['labor_pay_basis'] === null || $data['labor_pay_basis'] === '') {
            return $existing?->labor_pay_basis ?? TechnicianLaborPayBasis::Hourly->value;
        }

        return TechnicianLaborPayBasis::from((string) $data['labor_pay_basis'])->value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $roles
     */
    private function workdayHoursFromInput(array $data, array $roles, ?User $existing = null): ?float
    {
        if (! in_array(ArkRole::Technician->value, $roles, true)) {
            return null;
        }

        if (! array_key_exists('workday_hours', $data) || $data['workday_hours'] === null || $data['workday_hours'] === '') {
            return $existing?->workday_hours;
        }

        return round((float) $data['workday_hours'], 2);
    }

    /**
     * History is authority; users.* columns remain current-state projection.
     *
     * @param  list<string>  $roles
     */
    private function syncCompensationAgreementHistory(User $user, array $roles, ?User $actor): void
    {
        if (! in_array(ArkRole::Technician->value, $roles, true)) {
            return;
        }

        $user->refresh();

        $this->compensationAgreements->syncCurrent(
            $user,
            (string) ($user->labor_pay_basis ?? TechnicianLaborPayBasis::Hourly->value),
            $user->flag_rate_cents,
            $user->floor_rate_cents,
            $actor,
        );
    }

    /**
     * Compensation agreement — preserved when pay basis is Hourly (fields omitted from form).
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $roles
     */
    private function flagRateCentsFromInput(array $data, array $roles, string $payBasis, ?User $existing = null): ?int
    {
        if (! in_array(ArkRole::Technician->value, $roles, true)) {
            return $existing?->flag_rate_cents;
        }

        // Hourly UI omits flag_rate — never destroy a Flag agreement by toggling basis.
        if ($payBasis !== TechnicianLaborPayBasis::Flag->value) {
            return $existing?->flag_rate_cents;
        }

        if (! array_key_exists('flag_rate', $data)) {
            return $existing?->flag_rate_cents;
        }

        if ($data['flag_rate'] === null || $data['flag_rate'] === '') {
            return null;
        }

        return (int) round(((float) $data['flag_rate']) * 100);
    }

    /**
     * Compensation agreement — seed suggestion on create only; never silent overwrite on config change.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $roles
     */
    private function floorRateCentsFromInput(array $data, array $roles, string $payBasis, ?User $existing = null): ?int
    {
        if (! in_array(ArkRole::Technician->value, $roles, true)) {
            return $existing?->floor_rate_cents;
        }

        if ($payBasis !== TechnicianLaborPayBasis::Flag->value) {
            return $existing?->floor_rate_cents;
        }

        if (! array_key_exists('floor_rate', $data)) {
            if ($existing === null) {
                return TechnicianFloorWageSuggestion::amountCents();
            }

            return $existing->floor_rate_cents;
        }

        if ($data['floor_rate'] === null || $data['floor_rate'] === '') {
            if ($existing === null) {
                return TechnicianFloorWageSuggestion::amountCents();
            }

            return null;
        }

        return (int) round(((float) $data['floor_rate']) * 100);
    }
}
