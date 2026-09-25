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
        /** @var list<array<string, mixed>> $dtcCodes */
        $dtcCodes = require __DIR__.'/common_problem_dtc_codes.php';
        $problems = array_merge($problems, $dtcCodes);

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
                    'description' => 'Colorado Springs auto repair with testing and live data before parts. 24-month shop warranty on qualifying work. Request an appointment online.',
                ],
                'book' => [
                    'title' => 'Request an appointment',
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
                    'title' => 'Problems and codes',
                    'description' => 'What a symptom or code can mean, whether you can keep driving, and how the shop finds the cause.',
                ],
                'repairpal' => [
                    'title' => 'RepairPal',
                    'description' => 'RepairPal Certified shop pages: certification, reviews, and the nationwide warranty.',
                ],
                'repairpal-certified' => [
                    'title' => 'RepairPal Certified',
                    'description' => 'What RepairPal Certified means at this shop, and how to check the listing.',
                ],
                'repairpal-reviews' => [
                    'title' => 'RepairPal Reviews',
                    'description' => 'RepairPal reviews sit on RepairPal. Google reviews remain the local record on this site.',
                ],
                'repairpal-warranty' => [
                    'title' => 'RepairPal Warranty',
                    'description' => 'RepairPal Certified warranty is 12 months / 12,000 miles nationwide. The shop warranty is separate.',
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
                'repairpal' => [
                    'title' => 'RepairPal at this shop',
                    'lede' => 'This shop is a RepairPal Certified auto repair shop in Colorado Springs. These pages explain what that certification means before you leave the site to check it on RepairPal.',
                    'sections' => [
                        [
                            'heading' => 'Start here',
                            'body' => 'RepairPal Certified explains the certification. RepairPal Reviews explains where those reviews live. RepairPal Warranty is the nationwide 12 month / 12,000 mile program. It is not the shop warranty.',
                        ],
                        [
                            'heading' => 'Check it yourself',
                            'body' => 'The official RepairPal profile is the source of record for certification status and RepairPal reviews: https://www.repairpal.com/auto-repair-near-me/auto-repair-in-colorado-springs-colorado/lugs-n-plugs-automotive-auto-repair-in-colorado-springs-co',
                        ],
                    ],
                    'links' => [
                        ['path' => '/repairpal-certified', 'label' => 'RepairPal Certified'],
                        ['path' => '/repairpal-reviews', 'label' => 'RepairPal Reviews'],
                        ['path' => '/repairpal-warranty', 'label' => 'RepairPal Warranty'],
                    ],
                ],
                'repairpal-certified' => [
                    'title' => 'RepairPal Certified',
                    'lede' => 'This shop is a RepairPal Certified shop. RepairPal is an independent network. They review shops for workmanship standards, fair pricing practices, and customer experience.',
                    'sections' => [
                        [
                            'heading' => 'What RepairPal is',
                            'body' => 'RepairPal is an independent auto repair marketplace and certification network. Drivers use it to find shops that meet published quality and pricing standards, compare estimates, and read reviews collected outside any one shop website.',
                        ],
                        [
                            'heading' => 'What RepairPal Certified means',
                            'body' => 'Certification is not a paid marketing sticker. RepairPal evaluates shops against published criteria that include trained technicians, parts practices, pricing transparency, and customer service. When you see RepairPal Certified on this site, you can check the same status on the public RepairPal listing.',
                        ],
                        [
                            'heading' => 'Why this shop chose certification',
                            'body' => 'The shop already finds the problem before recommending a repair. Certification lets you verify that with a third party. If you arrive from RepairPal, or you recognize the badge from elsewhere, you should be able to confirm the shop meets the same bar it claims on its own pages.',
                        ],
                        [
                            'heading' => 'What customers gain',
                            'body' => 'A shop credential you can verify independently. Access to RepairPal review and estimate tools when you use that platform. Nationwide RepairPal Certified warranty (12 months / 12,000 miles) on qualifying repairs done at this shop. The same diagnostic standard used for every Colorado Springs customer: verify the problem before recommending the repair.',
                        ],
                        [
                            'heading' => 'How we diagnose',
                            'body' => 'Codes and check-engine lights are clues, not a parts list. The shop uses diagnostic tools and live data from the car to confirm the fault, then explains what is wrong, what can wait, and what should be repaired now. RepairPal Certification sits beside that promise. It does not replace it.',
                        ],
                        [
                            'heading' => 'Common questions',
                            'body' => 'RepairPal is not the same as Google reviews. Google reviews stay on Google. RepairPal collects its own reviews through its platform. You do not have to book through RepairPal. You can request service on this site, call or text the shop, or use RepairPal if you prefer that estimate flow. The work is done at this shop either way. To verify the shop is still certified, open the official RepairPal profile. That listing is the third-party source of record.',
                        ],
                    ],
                    'links' => [
                        ['path' => '/repairpal', 'label' => 'RepairPal'],
                        ['path' => '/repairpal-warranty', 'label' => 'RepairPal Warranty'],
                    ],
                ],
                'repairpal-reviews' => [
                    'title' => 'RepairPal reviews',
                    'lede' => 'Independent review sites help you check a shop without relying only on the shop website. RepairPal reviews sit alongside Google reviews. They do not replace them.',
                    'sections' => [
                        [
                            'heading' => 'How RepairPal collects reviews',
                            'body' => 'RepairPal invites customers who use its marketplace to leave feedback on the shop listing. Those reviews live on RepairPal, under RepairPal terms. This site does not rewrite them.',
                        ],
                        [
                            'heading' => 'Why independent reviews matter',
                            'body' => 'A shop can choose what to put on its homepage. An independent platform cannot be fully controlled by the shop. When you read RepairPal reviews on RepairPal, you are reading that platform record, the same way Google reviews live on Google.',
                        ],
                        [
                            'heading' => 'Google reviews still matter',
                            'body' => 'Most Colorado Springs drivers find the shop through Google Maps and Search. The homepage shows the Google rating because it is a primary local signal. RepairPal is a second signal for drivers who already use that network, or who arrive from a RepairPal estimate.',
                        ],
                        [
                            'heading' => 'Read RepairPal reviews on RepairPal',
                            'body' => 'This site does not republish RepairPal review text. The live profile is the accurate, up-to-date source, including ratings, recent feedback, and certification status.',
                        ],
                    ],
                    'links' => [
                        ['path' => '/repairpal', 'label' => 'RepairPal'],
                        ['path' => '/repairpal-certified', 'label' => 'RepairPal Certified'],
                    ],
                ],
                'repairpal-warranty' => [
                    'title' => 'RepairPal nationwide warranty',
                    'lede' => 'The RepairPal Certified warranty is 12 months / 12,000 miles on qualifying parts and labor, whichever comes first. That coverage is nationwide through the RepairPal Certified warranty program. The shop also offers a separate shop warranty of 24 months / 24,000 miles on qualifying parts and labor, where applicable.',
                    'sections' => [
                        [
                            'heading' => 'What is covered',
                            'body' => 'Under the RepairPal Certified warranty, qualifying parts and labor are covered for 12 months or 12,000 miles, whichever comes first. The advisor confirms which items on the estimate qualify before work starts.',
                        ],
                        [
                            'heading' => 'Nationwide coverage',
                            'body' => 'This warranty is meant for travel, not only for drivers who stay in Colorado Springs. If a related issue shows up while you are away, participating RepairPal Certified shops can look at warranty work under the program terms.',
                        ],
                        [
                            'heading' => 'If you need warranty help while traveling',
                            'body' => 'Keep your invoice and repair paperwork. Contact the shop first when you can, and tell them what changed. If you need local help on the road, ask for a RepairPal Certified shop in that area and share your invoice so they can coordinate coverage under the program. Exact claim steps depend on the repair and the shop helping you.',
                        ],
                        [
                            'heading' => 'Why this matters on the road',
                            'body' => 'A covered problem should not leave you stuck far from home with no path forward. Nationwide warranty coverage on qualifying work is one reason certification matters. It can extend help beyond this building.',
                        ],
                        [
                            'heading' => 'Shop warranty is separate',
                            'body' => 'The shop warranty is 24 months / 24,000 miles on qualifying parts and labor, where applicable. It is not the RepairPal Certified warranty. Not every repair is covered. Qualifying repairs are confirmed on the estimate. Certification status and program details are published on the official RepairPal profile.',
                        ],
                    ],
                    'links' => [
                        ['path' => '/warranty', 'label' => 'Shop warranty'],
                        ['path' => '/repairpal-certified', 'label' => 'RepairPal Certified'],
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
