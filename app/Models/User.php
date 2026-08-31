<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Ark\Operations\Labor\TechnicianLaborPayBasis;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\ShopExcellence\OwnerWorkspaceAccess;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Ark\Runtime\Authorization\DevRolePretend;
use App\Ark\Runtime\Identity\Oidc\UserProductAccess;
use App\Ark\Runtime\Preferences\AccentColor;
use App\Ark\Runtime\Preferences\AccentTheme;
use App\Ark\Runtime\Preferences\DisplayTheme;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'phone', 'accent_theme', 'accent_color', 'display_theme', 'schedule_board_view', 'labor_cost_cents', 'labor_pay_basis', 'flag_rate_cents', 'floor_rate_cents', 'workday_hours', 'scheduling_hours', 'auto_clock_enabled', 'auto_lunch_minutes'])]
#[Hidden(['password', 'remember_token', 'operator_pin_hash'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_master_admin' => 'boolean',
            'workday_hours' => 'decimal:2',
            'scheduling_hours' => 'array',
            'cloud_funnel_draft' => 'array',
            'password_set_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'auto_clock_enabled' => 'boolean',
            'auto_lunch_minutes' => 'integer',
        ];
    }

    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => PhoneNumber::normalize($value),
        );
    }

    protected function displayPhone(): Attribute
    {
        return Attribute::make(
            get: fn () => PhoneNumber::display($this->attributes['phone'] ?? null),
        );
    }

    public function accentTheme(): AccentTheme
    {
        return AccentTheme::tryFromStored($this->accent_theme);
    }

    public function displayTheme(): DisplayTheme
    {
        return DisplayTheme::tryFromStored($this->display_theme);
    }

    public function prefersDarkDisplay(bool $systemPrefersDark): bool
    {
        return $this->displayTheme()->resolvesToDark($systemPrefersDark);
    }

    public function accentColorHex(): ?string
    {
        return AccentColor::normalize($this->accent_color);
    }

    /**
     * @return array{data-accent: string, style?: string}
     */
    public function accentHtmlAttributes(): array
    {
        $theme = $this->accentTheme();

        if ($theme !== AccentTheme::Custom) {
            return ['data-accent' => $theme->value];
        }

        return [
            'data-accent' => AccentTheme::Custom->value,
            'style' => AccentColor::htmlStyleAttribute($this->accent_color),
        ];
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * Email verification is required on production; skipped on local / demo sites.
     * Override with AUTH_REQUIRE_EMAIL_VERIFICATION=true|false.
     */
    public static function emailVerificationIsRequired(): bool
    {
        $explicit = config('auth.require_email_verification');

        if ($explicit !== null && $explicit !== '') {
            return filter_var($explicit, FILTER_VALIDATE_BOOLEAN);
        }

        if (app()->environment(['local', 'demo'])) {
            return false;
        }

        $host = strtolower((string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: ''));

        if ($host !== '' && (str_starts_with($host, 'demo.') || str_contains($host, '.demo.'))) {
            return false;
        }

        return true;
    }

    public function hasVerifiedEmail(): bool
    {
        if (! static::emailVerificationIsRequired()) {
            return true;
        }

        return ! is_null($this->email_verified_at);
    }

    public function hasPasswordSet(): bool
    {
        return $this->password_set_at !== null;
    }

    public function needsPasswordSetup(): bool
    {
        return ! $this->hasPasswordSet();
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    public function isMasterAdmin(): bool
    {
        if (DevRolePretend::isActive()) {
            return false;
        }

        return (bool) $this->is_master_admin;
    }

    public function canAccessOwnerWorkspace(): bool
    {
        return OwnerWorkspaceAccess::allows($this);
    }

    public function worksAsTechnician(): bool
    {
        if (DevRolePretend::isActive()) {
            return true;
        }

        return $this->hasRole(ArkRole::Technician->value);
    }

    public function worksAsAdvisor(): bool
    {
        if (DevRolePretend::isActive()) {
            return false;
        }

        return $this->hasRole(ArkRole::Advisor->value);
    }

    public function effectiveWorkdayHours(): float
    {
        return $this->workday_hours !== null
            ? (float) $this->workday_hours
            : 8.0;
    }

    public function laborPayBasis(): TechnicianLaborPayBasis
    {
        return TechnicianLaborPayBasis::tryFrom((string) ($this->labor_pay_basis ?? ''))
            ?? TechnicianLaborPayBasis::Hourly;
    }

    public function flagRateDollars(): ?float
    {
        return $this->flag_rate_cents !== null
            ? round($this->flag_rate_cents / 100, 2)
            : null;
    }

    public function floorRateDollars(): ?float
    {
        return $this->floor_rate_cents !== null
            ? round($this->floor_rate_cents / 100, 2)
            : null;
    }

    public function floorWageNeedsReview(): bool
    {
        return \App\Ark\Operations\Labor\TechnicianFloorWageSuggestion::needsReview($this->floor_rate_cents);
    }

    /**
     * @return list<string>
     */
    public function staffRoleLabels(): array
    {
        return $this->roles
            ->pluck('name')
            ->map(fn (string $name): ?string => ArkRole::tryFrom($name)?->label())
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return HasMany<UserProductAccess, $this>
     */
    public function productAccessOverrides(): HasMany
    {
        return $this->hasMany(UserProductAccess::class);
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function hasOperatorPin(): bool
    {
        return filled($this->operator_pin_hash);
    }

    public function setOperatorPin(string $pin): void
    {
        $this->forceFill([
            'operator_pin_hash' => app(\App\Ark\Operations\Workstations\OperatorPinVerifier::class)->hash($pin),
        ])->save();
    }

    public function presenceAvatarInitials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name), 2) ?: [];
        $first = strtoupper(mb_substr($parts[0] ?? '', 0, 1));

        if ($first === '') {
            return '?';
        }

        $second = isset($parts[1]) && $parts[1] !== ''
            ? strtoupper(mb_substr($parts[1], 0, 1))
            : '';

        return $first.$second;
    }

    public function presenceAvatarColor(): string
    {
        return $this->accentHexResolved();
    }

    /**
     * Cloud Funnel M2 — one owned platform Shop. Not operational shop_settings.
     */
    public function ownedShop(): HasOne
    {
        return $this->hasOne(\App\Ark\Platform\Shop::class, 'owner_user_id');
    }

    /**
     * The operator's effective accent color as a hex string — custom override
     * first, otherwise the chosen swatch, otherwise ARK cerulean. One source of
     * truth for every surface (web data-accent, mobile theme, avatars).
     */
    public function accentHexResolved(): string
    {
        $custom = $this->accentColorHex();

        if ($custom !== null) {
            return $custom;
        }

        return match ($this->accentTheme()) {
            AccentTheme::Orange => '#f97316',
            AccentTheme::Blue => '#3b82f6',
            AccentTheme::Emerald => '#10b981',
            AccentTheme::Violet => '#8b5cf6',
            AccentTheme::Rose => '#f43f5e',
            AccentTheme::Amber => '#f59e0b',
            AccentTheme::Sky => '#0ea5e9',
            AccentTheme::Teal => '#14b8a6',
            AccentTheme::Ark2, AccentTheme::Custom => '#0099cc',
        };
    }
}
