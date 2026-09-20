<?php

namespace App\Ark\Operations\Learn\Catalog;

use App\Ark\Runtime\Authorization\ArkRole;
use App\Support\Branding\Branding;

final class LearnArkAdminArticles
{
    /**
     * @return list<array{slug: string, title: string, summary: string, view: string}>
     */
    public static function all(): array
    {
        return [
            [
                'slug' => 'getting-started',
                'title' => 'Admin overview',
                'summary' => 'Shop settings, staff roles, and operational authority.',
                'view' => 'operations.learn.admin.getting-started',
            ],
            [
                'slug' => 'roles-and-access',
                'title' => 'Roles and access',
                'summary' => 'What advisors, technicians, and admins can do in ARK.',
                'view' => 'operations.learn.admin.roles-and-access',
            ],
            [
                'slug' => 'staff-onboarding',
                'title' => 'Onboarding new staff',
                'summary' => 'Create accounts, assign roles, required '.Branding::learnName().', team progress.',
                'view' => 'operations.learn.admin.staff-onboarding',
            ],
            [
                'slug' => 'financial-rules',
                'title' => 'Financial rules',
                'summary' => 'Labor categories, tax, fees, matrices, billing classes.',
                'view' => 'operations.learn.admin.financial-rules',
            ],
            [
                'slug' => 'workflow-defaults',
                'title' => 'Workflow defaults',
                'summary' => 'Recommendation intent, note privacy, visit mode labels.',
                'view' => 'operations.learn.admin.workflow-defaults',
            ],
            [
                'slug' => 'telephony-sip-setup',
                'title' => 'Telephony and SIP desk phones',
                'summary' => 'Twilio webhooks, SIP domains, ring groups, and Poly desk phone registration.',
                'view' => 'operations.learn.admin.telephony-sip-setup',
            ],
            [
                'slug' => 'comms-health-check',
                'title' => 'Communications health check',
                'summary' => 'Webhooks, ring group, SMS interrupt, Reverb, gate, and escalation.',
                'view' => 'operations.learn.admin.comms-health-check',
            ],
            [
                'slug' => 'ark-mobile-push-setup',
                'title' => 'ARK Mobile push setup',
                'summary' => 'Firebase FCM env, service account paths, and BookStack sync.',
                'view' => 'operations.learn.admin.ark-mobile-push-setup',
            ],
            [
                'slug' => 'ark-mobile-android-deploy',
                'title' => 'ARK Mobile Android deploy',
                'summary' => 'Release APK sideload, USB floor test, Play Store signing, OBD requirements.',
                'view' => 'operations.learn.admin.ark-mobile-android-deploy',
            ],
            [
                'slug' => 'messenger-setup',
                'title' => 'Facebook Messenger setup',
                'summary' => 'Meta app, Page webhook, tokens, 24-hour window, and verification.',
                'view' => 'operations.learn.admin.messenger-setup',
            ],
            [
                'slug' => 'shop-overhead-setup',
                'title' => 'Shop overhead and loaded labor cost',
                'summary' => 'Overhead worksheet and technician loaded cost.',
                'view' => 'operations.learn.admin.shop-overhead-setup',
            ],
            [
                'slug' => 'printing-qz',
                'title' => 'Label printing with QZ Tray',
                'summary' => 'Printer routing, key tags, oil stickers, sign health.',
                'view' => 'operations.learn.admin.printing-qz',
            ],
            [
                'slug' => 'email-delivery',
                'title' => 'Shop email delivery',
                'summary' => 'ARK Email settings; estimate email with PDF and portal link.',
                'view' => 'operations.learn.admin.email-delivery',
            ],
            [
                'slug' => 'owner-targets',
                'title' => 'Owner excellence targets',
                'summary' => 'Margin health bands and quarterly review flag.',
                'view' => 'operations.learn.admin.owner-targets',
            ],
        ];
    }

    public static function roleKey(): string
    {
        return ArkRole::Admin->value;
    }
}
