<?php

declare(strict_types=1);

/*
 * Wording for the About us page (/about). Edit freely: each section has a heading and one or
 * more paragraphs of plain text (no HTML). Placeholders are filled in from Settings:
 *   {site}  site name        {price}  CV service price, e.g. "UGX 100,000"
 */
return [
    'intro' => '{site} is a job board for Uganda. We bring together current vacancies from employers across the country, from Kampala and Entebbe to Jinja, Mbarara, Gulu and beyond, so job seekers can find and apply for work in one place.',
    'sections' => [
        [
            'heading' => 'What we do',
            'paragraphs' => [
                'Every job on {site} shows what matters before you apply: the employer, the location, the type of role, the salary in Ugandan shillings where the employer shares it, the requirements, how to apply and the application deadline.',
                'Jobs are removed when the deadline passes or the vacancy closes, so what you see is open now.',
            ],
        ],
        [
            'heading' => 'For job seekers',
            'paragraphs' => [
                'Search by keyword, city, category, job type, experience level and salary. Save the jobs you like in your browser, and subscribe to our email job alerts to hear about new vacancies first.',
                'Using {site} is free for job seekers. We never ask you to pay to apply for a job.',
            ],
        ],
        [
            'heading' => 'Help with your CV',
            'paragraphs' => [
                'A clear CV gets more interviews. Our CV fix service reviews and improves your CV for {price}, so it presents your experience well to employers in Uganda.',
            ],
        ],
        [
            'heading' => 'For employers',
            'paragraphs' => [
                'Want to reach job seekers across Uganda? Contact us to advertise a vacancy. Tell us about the role, how candidates should apply and your deadline, and we will publish it.',
            ],
        ],
    ],
];
