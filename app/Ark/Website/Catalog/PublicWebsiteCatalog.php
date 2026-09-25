<?php

namespace App\Ark\Website\Catalog;

/**
 * Assembles the publication document imported from the pre-extraction public site.
 *
 * Shop name, phone, email, address, hours, logo, and google_reviews_url are not
 * included. Those stay on ShopSettings and are read at render time.
 *
 * Headline, reviews, FAQs, legal copy, financing links, and SEO defaults are the
 * Foundry PHP defaults. public_surface_settings was dropped, so this is not a
 * database export. Publishing this document is the explicit decision to keep
 * that visible copy as website_publications content.
 */
final class PublicWebsiteCatalog
{
    /**
     * @return array<string, mixed>
     */
    public static function document(): array
    {
        /** @var list<array<string, mixed>> $problems */
        $problems = require __DIR__.'/common_problems.php';

        return [
            'source' => 'foundry-public-catalog-and-php-defaults',
            'headline' => 'Accurate Diagnostics. Honest Repairs.',
            'positioning_lede' => 'We find the real problem first. You get a clear estimate before we do any repairs.',
            'local_tagline' => 'Family owned in Colorado Springs.',
            'google_rating' => '4.9',
            'google_review_count' => 56,
            'reviews' => [
                [
                    'quote' => 'Expecting the worst but Edward and Caleb were great and found it only needed a proper trans service that another shop said they did but left seriously underfilled.',
                    'attribution' => 'Richard Conti',
                ],
                [
                    'quote' => 'Edward is exceptionally meticulous and methodical in his approach to vehicle repair, while also prioritizing a truly comfortable and transparent customer experience.',
                    'attribution' => 'Greg Powell',
                ],
                [
                    'quote' => 'Edward is an amazing mechanic and his shop is meticulously clean and organized. He offered various OE and OEM part selections to help fit my budget.',
                    'attribution' => 'Eric',
                ],
                [
                    'quote' => 'The most trustworthy shop in town - won\'t go anywhere else.',
                    'attribution' => 'Bradley Vogleman',
                ],
            ],
            'contact_faqs' => [
                [
                    'question' => 'Do I need an appointment?',
                    'answer' => 'Appointments help us save bay time for diagnostics. Same-day help is sometimes possible - call or text and we will tell you the next open slot.',
                ],
                [
                    'question' => 'What is the difference between the shop warranty and RepairPal?',
                    'answer' => 'The shop warranty is 24 months / 24,000 miles on qualifying parts and labor, where applicable. RepairPal Certified warranty is 12 months / 12,000 miles nationwide on qualifying repairs.',
                ],
                [
                    'question' => 'Do you accept customer-supplied parts?',
                    'answer' => 'Yes, in most cases. There is an extra labor fee, and the part itself is not covered by our parts warranty. Ask us before buying the part so we can make sure it will work for the repair.',
                ],
                [
                    'question' => 'Do you offer towing?',
                    'answer' => 'We can help arrange a tow to the shop. Call or text with where you are and what you are driving.',
                ],
                [
                    'question' => 'Do you perform inspections?',
                    'answer' => 'Yes. We can inspect the vehicle and tell you what we find. If the cause of a problem is not clear, we can diagnose it too.',
                ],
                [
                    'question' => 'What forms of payment do you accept?',
                    'answer' => 'Major cards, and financing when the repair qualifies. Ask about Wisetack or Synchrony Car Care on the estimate.',
                ],
            ],
            'financing' => [
                'lede' => 'Big repairs do not wait for a good month. If the job qualifies, Wisetack and Synchrony Car Care can help you pay over time.',
                'programs' => [
                    [
                        'name' => 'Wisetack',
                        'body' => 'Pay over time on repairs that qualify. During estimate review, we can text you a link to see if you are approved.',
                        'url' => 'https://wisetack.us/#/uz8sh8e/prequalify',
                    ],
                    [
                        'name' => 'Synchrony Car Care',
                        'body' => 'A credit card made for auto repair and maintenance at shops that take Synchrony.',
                        'url' => 'https://www.synchrony.com/financing/car-care/prospecting',
                    ],
                ],
            ],
            'seo' => [
                'home' => [
                    'title' => 'Auto Repair Colorado Springs | Verified Diagnostics',
                    'description' => 'Colorado Springs auto repair with testing and live data before parts. 24-month shop warranty on qualifying work. Book an appointment online.',
                ],
                'book' => [
                    'title' => 'Book an Appointment',
                    'description' => 'Request an appointment. We verify the problem, provide a clear estimate, and repair only what you approve.',
                ],
                'contact' => [
                    'title' => 'Contact',
                    'description' => 'Call, text, or send a message to the shop.',
                ],
                'financing' => [
                    'title' => 'Financing for Auto Repair',
                    'description' => 'Repair financing through Wisetack and Synchrony Car Care when the job qualifies.',
                ],
                'warranty' => [
                    'title' => 'Repair Warranty',
                    'description' => '24 month / 24,000 mile shop warranty on qualifying parts and labor.',
                ],
                'privacy' => [
                    'title' => 'Privacy policy',
                    'description' => 'What the shop collects through the website and My Account, and how it is used.',
                ],
                'terms' => [
                    'title' => 'Terms of use',
                    'description' => 'Basic terms for using the shop website and My Account.',
                ],
                'common_problems' => [
                    'title' => 'Common problems',
                    'description' => 'What a symptom can mean, whether you can keep driving, and how the shop finds the cause.',
                ],
            ],
            'pages' => [
                'privacy' => [
                    'title' => 'Privacy policy',
                    'lede' => 'This page explains what we collect through the website and My Account, and how we use it.',
                    'sections' => [
                        [
                            'heading' => 'Information we collect',
                            'body' => 'When you contact the shop, sign in to My Account, or approve work online, we may collect your name, phone number, email address, vehicle information, and messages you send us.',
                        ],
                        [
                            'heading' => 'How we use it',
                            'body' => 'We use this information to respond to your request, schedule service, perform repairs, send estimates and invoices, and communicate about your vehicle. We do not sell your personal information.',
                        ],
                        [
                            'heading' => 'Contact',
                            'body' => 'Questions about this policy? Call or text the shop using the number on this website, or send a message through the contact form.',
                        ],
                    ],
                ],
                'terms' => [
                    'title' => 'Terms of use',
                    'lede' => 'By using this website and My Account, you agree to these basic terms.',
                    'sections' => [
                        [
                            'heading' => 'Website content',
                            'body' => 'Repair guides and symptom pages describe common patterns we see in the shop. They are educational, not a diagnosis of your specific vehicle. Have a technician inspect the car before driving when safety is in question.',
                        ],
                        [
                            'heading' => 'Online requests',
                            'body' => 'Submitting the contact form does not guarantee an appointment time. A service advisor will review your message and follow up during business hours.',
                        ],
                        [
                            'heading' => 'Changes',
                            'body' => 'We may update this page as our online services change. Continued use of the site after changes means you accept the updated terms.',
                        ],
                    ],
                ],
                'warranty' => [
                    'title' => 'Repair warranty',
                    'lede' => 'Qualifying repairs are covered by the shop warranty for 24 months / 24,000 miles on parts and labor, where applicable, whichever comes first. RepairPal Certified warranty coverage is separate: 12 months / 12,000 miles nationwide on qualifying repairs.',
                    'sections' => [
                        [
                            'heading' => 'What it covers',
                            'body' => 'When we do a covered repair, the parts and our workmanship are covered for 24 months or 24,000 miles, whichever comes first. If something related to that repair fails within the warranty period, bring the vehicle back and we will look at it with you.',
                        ],
                        [
                            'heading' => 'How to use it',
                            'body' => 'Keep your invoice. If something related to the repair goes wrong, call or text the shop and tell us what changed. An advisor will help you schedule a follow-up look.',
                        ],
                        [
                            'heading' => 'Questions',
                            'body' => 'Coverage can depend on the type of repair and the parts used. Your advisor confirms what is covered on your estimate before work starts.',
                        ],
                    ],
                ],
            ],
            'common_problems' => $problems,
        ];
    }
}
