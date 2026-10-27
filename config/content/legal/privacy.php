<?php

declare(strict_types=1);

/*
 * Privacy Policy shown at /privacy. Written to match how the site actually handles data; if you
 * change what the site collects, update this text. Have it reviewed by a lawyer before relying on it.
 * Placeholders: {site} {url} {contact} {address} {price}. Plain text only (no HTML).
 * Change 'updated' whenever you change the wording.
 */
return [
    'title' => 'Privacy Policy',
    'updated' => '2026-10-27',
    'summary' => 'This policy explains what personal data {site} collects, why, how long we keep it, who we share it with and the rights you have under the Data Protection and Privacy Act, 2019 of Uganda. In short: we collect very little, we never sell your data, and you can ask us to see, correct or delete it at any time.',
    'sections' => [
        [
            'heading' => 'Who we are',
            'paragraphs' => [
                '{site} ({url}) is a job board for Uganda. For the personal data described in this policy, {site} is the data controller: we decide why and how it is used.',
                'You can reach us {contact}. Our address is {address}.',
            ],
        ],
        [
            'heading' => 'The information we collect',
            'paragraphs' => ['We only collect what we need for the services you choose to use:'],
            'items' => [
                'Job alert subscriptions: your email address, your name if you give it, the date, time and IP address from which you agreed to receive job alerts and confirmed your subscription, whether you are subscribed or have unsubscribed, and a record of which job alert emails we sent you and whether they were delivered.',
                'Contact and CV help forms: your name, email address, phone or WhatsApp number if you give it, and your message. These are sent to us by email and are not stored in the website’s database.',
                'CV help service: the CV and other details you send us by email or WhatsApp so we can work on your CV.',
                'Messages you send us directly by email or WhatsApp.',
                'Website usage statistics: which pages and jobs are viewed, which application links or email addresses are clicked, the words typed into the job search and the filters used, the website or campaign link that brought you to us (only the site name, never the full address), and whether you use a phone, tablet or computer. We do not use cookies for this and we do not store your IP address or browser details: they are combined with a random code that changes every day into an anonymous visitor number, and the code is deleted at the end of the day, so we cannot recognise you on a later day or work out who you are. Visits are not counted at all if your browser sends a Do Not Track or Global Privacy Control signal.',
                'Security information: when a form is submitted we briefly record the IP address it came from, to stop spam and abuse (for example, limiting how many messages can be sent from one address). Our hosting provider also keeps standard technical logs of requests to the website, which include IP addresses.',
            ],
            'after' => [
                'We do not use third-party analytics, advertising or tracking tools, cookies or tracking pixels, and our job alert emails do not track whether you open them or click their links. Our fonts and icons are hosted on our own server, so simply viewing our pages does not send your data to other companies.',
                'Jobs you save are stored only in your own browser on your device. We cannot see them unless you open the Saved jobs page, which sends us the list of saved job numbers so we can show those jobs.',
                'You do not need an account to use {site}, and we do not collect information to apply for jobs: when you apply, you do so directly with the employer.',
            ],
        ],
        [
            'heading' => 'How we use your information and why we are allowed to',
            'items' => [
                'To send you job alerts, only after you have agreed and confirmed your email address. Our legal basis is your consent, which you can withdraw at any time with the unsubscribe link in every email.',
                'To answer your messages and to provide the CV help service you ask for. Our legal basis is that you asked us to, and, for the CV service, our agreement with you.',
                'To understand how the website is used, for example which jobs people look at and apply for and what they search for but cannot find, so we can improve it and show employers how their adverts perform (only as totals, never about individual visitors). Our legal basis is our legitimate interest in running and improving the service; because the statistics are anonymous and you can switch them off with Do Not Track or Global Privacy Control, this has very little impact on you.',
                'To keep the website secure and protect it from spam, fraud and abuse. Our legal basis is our legitimate interest, and our duty, in keeping personal data and the service secure.',
                'To meet our legal obligations, for example if a court or authority lawfully requires information.',
            ],
            'after' => [
                'We do not sell or rent your personal data, we do not use it for advertising, and we do not make decisions about you by automated means.',
            ],
        ],
        [
            'heading' => 'Who we share it with',
            'paragraphs' => ['We share personal data only with the service providers we need to run the website, and only for that purpose:'],
            'items' => [
                'Our hosting provider, which runs the website and its database.',
                'Our email provider, which delivers confirmation emails, job alerts and our replies.',
                'WhatsApp (operated by Meta), if you choose to contact us there. WhatsApp’s own privacy policy applies to your use of WhatsApp.',
                'Professional advisers or public authorities, only where the law requires it.',
            ],
            'after' => [
                'We do not pass your details to employers. When you apply for a job you send your application directly to the employer, using the link or email address in the job advert, and the employer’s own privacy policy applies to what they do with it.',
            ],
        ],
        [
            'heading' => 'Storing data outside Uganda',
            'paragraphs' => [
                'Our hosting and email providers may store and process data on servers outside Uganda. Where personal data leaves Uganda we only use providers that keep it secure and protect it to a standard at least equivalent to the Data Protection and Privacy Act, 2019, as section 19 of the Act requires.',
            ],
        ],
        [
            'heading' => 'How long we keep it',
            'items' => [
                'Job alert subscriptions: for as long as you stay subscribed. After you unsubscribe we keep your email address and the date you unsubscribed so that we do not email you again by mistake; ask us and we will delete it completely.',
                'Sign-ups that are never confirmed receive no job alerts. The confirmation link stops working after 48 hours, and we will delete the record if you ask.',
                'Contact and CV help messages: only as long as we need them to deal with your request and any follow-up, after which we delete them.',
                'CV files you send us: until the CV work is finished, after which we delete them unless you ask us to keep them for future updates.',
                'Security records of IP addresses used to limit spam: deleted automatically within 2 days.',
                'Website usage statistics: the anonymous record of each page view is deleted automatically after 1 year; after that we keep only daily totals (for example “120 views of this job on 3 March”), which say nothing about any visitor. The daily code used to make visitor numbers is deleted at the end of each day.',
            ],
        ],
        [
            'heading' => 'Your rights',
            'paragraphs' => ['Under the Data Protection and Privacy Act, 2019 you have the right to:'],
            'items' => [
                'be told whether we hold personal data about you and get a copy of it;',
                'have inaccurate or out-of-date data corrected;',
                'have your data deleted when we no longer have a valid reason to keep it;',
                'object to us using your data, and withdraw your consent at any time (for job alerts, use the unsubscribe link or contact us);',
                'not be subject to decisions made only by automated means; and',
                'complain to the Personal Data Protection Office (PDPO) of Uganda.',
            ],
            'after' => [
                'To use any of these rights, contact us {contact}. We may ask you to confirm your identity before acting on a request, and we will respond within 30 days. Using your rights is free.',
            ],
        ],
        [
            'heading' => 'How we protect your data',
            'paragraphs' => [
                'The website is served over an encrypted connection (HTTPS). Access to the back office is limited to our administrator, protected by a strong password and automatic sign-out, and every administrative action is logged. Stored secrets such as email account passwords are encrypted. No system is perfectly secure, but we take reasonable technical and organisational measures to protect your data, and we will notify the Personal Data Protection Office and affected people of a data breach as the law requires.',
            ],
        ],
        [
            'heading' => 'Children',
            'paragraphs' => [
                '{site} is meant for job seekers aged 18 and over. We do not knowingly collect personal data from children. If you believe a child has given us their details, contact us and we will delete them.',
            ],
        ],
        [
            'heading' => 'Changes to this policy',
            'paragraphs' => [
                'If we change how we handle personal data we will update this page and the “Last updated” date at the top. If a change significantly affects job alert subscribers, we will tell them by email.',
            ],
        ],
        [
            'heading' => 'Contact and complaints',
            'paragraphs' => [
                'Questions or concerns about your privacy? Contact us {contact}, or write to us at {address}.',
                'If you are not satisfied with our response, you can complain to the Personal Data Protection Office of Uganda (www.pdpo.go.ug).',
            ],
        ],
    ],
];
