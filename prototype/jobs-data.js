/*
 * Demo job data. Replace with an API response later.
 * Company names are fictional. Salaries are monthly, in UGX.
 * daysAgo: 0 = posted today.
 */
window.JOBS = [
  {
    id: 'j01', title: 'Frontend Developer (React)', company: 'Savanna Tech Labs',
    location: 'Kampala', remote: true, type: 'Full-time', category: 'Technology', exp: 'Mid',
    salaryMin: 3500000, salaryMax: 5000000, daysAgo: 0,
    skills: ['React', 'JavaScript', 'CSS', 'REST APIs'],
    summary: 'Build fast, accessible interfaces for fintech and logistics products used across East Africa.',
    responsibilities: [
      'Build and maintain React components and a shared design system',
      'Integrate with backend REST APIs alongside the engineering team',
      'Optimise pages for slow mobile networks'
    ],
    requirements: [
      '2+ years of React or a similar component framework',
      'Solid HTML, CSS and modern JavaScript',
      'Comfortable working in a remote, asynchronous team'
    ]
  },
  {
    id: 'j02', title: 'Senior Accountant', company: 'Nile Crest Ltd',
    location: 'Kampala', remote: false, type: 'Full-time', category: 'Finance', exp: 'Senior',
    salaryMin: 4800000, salaryMax: 6200000, daysAgo: 1,
    skills: ['IFRS', 'QuickBooks', 'Financial reporting', 'VAT & PAYE'],
    summary: 'Lead month-end close, statutory reporting and tax compliance for a mid-sized trading group.',
    responsibilities: [
      'Run month-end and year-end close processes',
      'Prepare management accounts and board packs',
      'Manage URA filings and external audit support'
    ],
    requirements: [
      'ACCA or CPA (Uganda) qualification, or equivalent',
      '5+ years in a finance role, ideally in trading or manufacturing',
      'Advanced Excel skills'
    ]
  },
  {
    id: 'j03', title: 'Registered Nurse', company: 'Mukono Health Partners',
    location: 'Mukono', remote: false, type: 'Full-time', category: 'Healthcare', exp: 'Entry',
    salaryMin: 1200000, salaryMax: 1600000, daysAgo: 2,
    skills: ['Patient care', 'Triage', 'Vital signs', 'Record keeping'],
    summary: 'Provide inpatient and outpatient nursing care at a community health facility serving Mukono district.',
    responsibilities: [
      'Deliver patient care across general wards',
      'Support maternal and child health clinics',
      'Maintain accurate patient records'
    ],
    requirements: [
      'Diploma or degree in nursing, with a valid UNMC registration',
      'Compassionate, calm under pressure',
      'Willing to work rotating shifts'
    ]
  },
  {
    id: 'j04', title: 'Sales Executive', company: 'Pearl Logistics',
    location: 'Entebbe', remote: false, type: 'Full-time', category: 'Sales & Marketing', exp: 'Entry',
    salaryMin: 1000000, salaryMax: 2000000, daysAgo: 3,
    skills: ['B2B sales', 'Negotiation', 'CRM', 'Customer relations'],
    summary: 'Grow freight and warehousing accounts across Entebbe and Kampala. Base salary plus commission.',
    responsibilities: [
      'Prospect and close new B2B freight accounts',
      'Maintain client relationships and renewals',
      'Report pipeline activity weekly'
    ],
    requirements: [
      'Diploma in business, sales or marketing',
      'Proven track record in B2B sales is an advantage',
      'Own transport or boda access is a plus'
    ]
  },
  {
    id: 'j05', title: 'Agronomist', company: 'Rift Valley Agro',
    location: 'Mbarara', remote: false, type: 'Full-time', category: 'Agriculture', exp: 'Mid',
    salaryMin: 2000000, salaryMax: 2800000, daysAgo: 4,
    skills: ['Crop science', 'Extension services', 'Farmer training', 'Data collection'],
    summary: 'Support smallholder farmers in the western region with crop advisory, training and field monitoring.',
    responsibilities: [
      'Run field visits and farmer training sessions',
      'Collect and report crop performance data',
      'Advise on pest and soil management'
    ],
    requirements: [
      'Bachelor\'s degree in agriculture or agronomy',
      '2+ years in extension or field work',
      'Fluent in Runyankole or Rukiga is a strong advantage'
    ]
  },
  {
    id: 'j06', title: 'Graphic Designer (Part-time)', company: 'Bugolobi Digital',
    location: 'Kampala', remote: true, type: 'Part-time', category: 'Design', exp: 'Entry',
    salaryMin: 800000, salaryMax: 1200000, daysAgo: 5,
    skills: ['Figma', 'Illustrator', 'Brand identity', 'Social media design'],
    summary: 'Create social graphics, pitch visuals and brand assets for a small agency serving clients across Uganda.',
    responsibilities: [
      'Design social media posts and campaign assets',
      'Maintain brand guidelines for client accounts',
      'Prepare print-ready files for events and signage'
    ],
    requirements: [
      'Strong portfolio of brand and digital work',
      'Proficiency in Figma and Adobe Illustrator',
      'Able to meet tight turnaround times'
    ]
  },
  {
    id: 'j07', title: 'Front Office Supervisor', company: 'Lake Victoria Hotels',
    location: 'Entebbe', remote: false, type: 'Full-time', category: 'Hospitality', exp: 'Mid',
    salaryMin: 1800000, salaryMax: 2200000, daysAgo: 6,
    skills: ['Guest relations', 'Reservations', 'Opera PMS', 'Team leadership'],
    summary: 'Lead the front desk team at a 120-room lakeside hotel, ensuring smooth check-ins and guest satisfaction.',
    responsibilities: [
      'Supervise daily front office operations',
      'Handle guest complaints and escalations',
      'Train and roster reception staff'
    ],
    requirements: [
      'Diploma in hospitality or tourism management',
      '2+ years in hotel front office',
      'Strong spoken English and customer focus'
    ]
  },
  {
    id: 'j08', title: 'Mathematics Teacher (Secondary)', company: 'Jinja Heights Academy',
    location: 'Jinja', remote: false, type: 'Full-time', category: 'Education', exp: 'Entry',
    salaryMin: 900000, salaryMax: 1300000, daysAgo: 7,
    skills: ['Lesson planning', 'UNEB curriculum', 'Mathematics', 'Classroom management'],
    summary: 'Teach O-Level and A-Level mathematics to a motivated group of students in a boarding school.',
    responsibilities: [
      'Plan and deliver lessons aligned to the UNEB syllabus',
      'Set and mark assessments',
      'Support exam preparation and extracurricular clubs'
    ],
    requirements: [
      'Bachelor\'s degree in education with mathematics',
      'Teaching certificate or diploma in education',
      'Patient and organised'
    ]
  },
  {
    id: 'j09', title: 'Data Analyst', company: 'Kampala Insights',
    location: 'Kampala', remote: true, type: 'Full-time', category: 'Technology', exp: 'Mid',
    salaryMin: 3000000, salaryMax: 4500000, daysAgo: 8,
    skills: ['SQL', 'Excel', 'Power BI', 'Python'],
    summary: 'Turn operational and survey data into dashboards that help NGO and private-sector clients make decisions.',
    responsibilities: [
      'Write SQL queries and build Power BI dashboards',
      'Clean and validate data from field surveys',
      'Present findings to non-technical stakeholders'
    ],
    requirements: [
      '2+ years of analytics experience',
      'Strong SQL and spreadsheet skills',
      'Clear written and verbal communication'
    ]
  },
  {
    id: 'j10', title: 'Backend Engineer (PHP / Laravel)', company: 'Gulu Digital Works',
    location: 'Gulu', remote: true, type: 'Full-time', category: 'Technology', exp: 'Mid',
    salaryMin: 2500000, salaryMax: 4000000, daysAgo: 9,
    skills: ['PHP', 'Laravel', 'MySQL', 'REST APIs', 'Git'],
    summary: 'Build and maintain APIs for government-facing digital services that run on low-bandwidth networks.',
    responsibilities: [
      'Design and build Laravel APIs and background jobs',
      'Optimise database queries and caching',
      'Write tests and review teammates\' code'
    ],
    requirements: [
      '2+ years of PHP with Laravel',
      'Experience with MySQL or PostgreSQL',
      'Familiarity with Git and code reviews'
    ]
  },
  {
    id: 'j11', title: 'Customer Support Agent (Contract)', company: 'Savanna Tech Labs',
    location: 'Kampala', remote: false, type: 'Contract', category: 'Customer Service', exp: 'Entry',
    salaryMin: 900000, salaryMax: 1100000, daysAgo: 10,
    skills: ['Customer support', 'Mobile money', 'Ticketing tools', 'English & Luganda'],
    summary: 'Handle customer queries for a mobile payments product. Six-month contract with a possibility of renewal.',
    responsibilities: [
      'Respond to customer calls, chats and emails',
      'Resolve mobile money transaction issues',
      'Log and escalate tickets accurately'
    ],
    requirements: [
      'Excellent written and spoken English; Luganda is a plus',
      'Comfortable using ticketing or CRM software',
      'Available for shift work'
    ]
  },
  {
    id: 'j12', title: 'Procurement Officer', company: 'Kasese Mining Services',
    location: 'Kasese', remote: false, type: 'Full-time', category: 'Operations', exp: 'Senior',
    salaryMin: 4000000, salaryMax: 6000000, daysAgo: 12,
    skills: ['Procurement', 'Supplier management', 'Contract negotiation', 'PPDA rules'],
    summary: 'Manage supplier sourcing and purchase contracts for a site with heavy equipment and consumables needs.',
    responsibilities: [
      'Source suppliers and negotiate purchase contracts',
      'Ensure compliance with PPDA procurement rules',
      'Track delivery performance and costs'
    ],
    requirements: [
      'Degree in supply chain, business or a related field',
      '5+ years in procurement, ideally in mining or construction',
      'Knowledge of PPDA regulations'
    ]
  },
  {
    id: 'j13', title: 'Digital Marketing Intern', company: 'Kampala Startup Hub',
    location: 'Kampala', remote: true, type: 'Internship', category: 'Sales & Marketing', exp: 'Entry',
    salaryMin: null, salaryMax: null, daysAgo: 14,
    skills: ['Social media', 'Canva', 'Content writing', 'Google Analytics'],
    summary: 'Three-month internship supporting campaigns for early-stage startups in the Kampala ecosystem.',
    responsibilities: [
      'Plan and schedule social media content',
      'Help run email and campaign reporting',
      'Support community events'
    ],
    requirements: [
      'Currently enrolled in a degree or recently graduated',
      'Interest in digital marketing and startups',
      'Basic familiarity with Canva and social platforms'
    ]
  },
  {
    id: 'j14', title: 'Operations Manager', company: 'Pearl Logistics',
    location: 'Kampala', remote: false, type: 'Full-time', category: 'Operations', exp: 'Senior',
    salaryMin: 6000000, salaryMax: 8000000, daysAgo: 15,
    skills: ['Fleet management', 'Logistics', 'KPIs', 'Team leadership'],
    summary: 'Oversee daily dispatch, fleet utilisation and warehouse operations across three hubs.',
    responsibilities: [
      'Own daily dispatch and route planning',
      'Improve fleet utilisation and cost per trip',
      'Lead supervisors across three depots'
    ],
    requirements: [
      '7+ years in logistics or operations management',
      'Track record of hitting delivery KPIs',
      'Strong analytical and people-management skills'
    ]
  }
];
