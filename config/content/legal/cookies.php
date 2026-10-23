<?php

declare(strict_types=1);

/*
 * Cookie Policy shown at /cookies. It lists the cookies the site really sets (see
 * src/Middleware/CsrfCookie.php and StartSession.php, and localStorage in public/assets/js/site.js).
 * If you add analytics, ads or any other tracking, this policy must change and visitors must be
 * asked for consent first. Placeholders: {site} {url} {contact} {address}. Plain text only.
 */
return [
    'title' => 'Cookie Policy',
    'updated' => '2026-10-23',
    'summary' => '{site} uses only a small number of strictly necessary cookies, to keep forms secure and to show messages after you submit a form. We do not use analytics, advertising or tracking cookies, so we do not need to ask for your consent to cookies.',
    'sections' => [
        [
            'heading' => 'What cookies are',
            'paragraphs' => [
                'Cookies are small text files that a website stores in your browser. Websites can also store information in your browser in a similar way using “local storage”. This page explains both.',
            ],
        ],
        [
            'heading' => 'Cookies we use',
            'paragraphs' => ['All of our cookies are set by {site} itself (first-party), cannot be read by scripts on the page, and are only sent over a secure connection on the live website.'],
            'table' => [
                'head' => ['Name', 'Purpose', 'How long it lasts'],
                'rows' => [
                    ['kaziug_csrf', 'Security: proves that forms you submit (job alerts, contact, CV help) really come from our website, which protects you from forged requests.', 'Until you close your browser'],
                    ['kaziug_session', 'Shows one-time messages after you submit a form (for example “Your message has been sent”) and keeps our administrator signed in. It is only set when needed.', 'Deleted after 2 hours without activity, or when you close your browser'],
                ],
            ],
            'after' => [
                'These cookies are strictly necessary for the website to work securely, which is why they do not need your consent.',
            ],
        ],
        [
            'heading' => 'Information stored in your browser',
            'table' => [
                'head' => ['Name', 'Purpose', 'How long it lasts'],
                'rows' => [
                    ['kazi-saved-jobs (local storage)', 'Remembers the jobs you save with the star, so you can find them again. It stays on your device; we only receive the list of saved job numbers when you open the Saved jobs page.', 'Until you remove the jobs or clear your browser data'],
                ],
            ],
        ],
        [
            'heading' => 'What we do not use',
            'items' => [
                'No analytics or statistics cookies.',
                'No advertising, retargeting or social media tracking cookies or pixels.',
                'No third-party cookies: our fonts and icons are hosted on our own server.',
            ],
            'after' => [
                'Websites we link to, such as employers’ application pages and WhatsApp, may set their own cookies when you visit them. Their own cookie policies apply.',
            ],
        ],
        [
            'heading' => 'Managing cookies',
            'paragraphs' => [
                'You can delete or block cookies in your browser settings. If you block the cookies listed above, forms on {site} (job alerts, contact and CV help) will not work, because we cannot confirm that the form came from our website. Clearing your browser data also removes your saved jobs.',
            ],
        ],
        [
            'heading' => 'Changes and contact',
            'paragraphs' => [
                'If we ever start using other cookies we will update this policy first and, where the law requires, ask for your consent. Questions? Contact us {contact}.',
            ],
        ],
    ],
];
