<?php

/**
 * Knowledge Centre featured photography.
 *
 * Files live in public/images/blog/featured/{file}.webp (and .jpg fallback).
 * Photos are professional 16:9 technology/business photography — not illustrations.
 */
return [
    'width' => 1200,
    'height' => 675,
    'directory' => 'images/blog/featured',

    /*
    | Per-article assignments. Each slug gets a unique file so cards never
    | share the same photograph.
    */
    'slugs' => [
        'refurbished-macbook-air-vs-new-laptop-value' => [
            'file' => 'macbook-vs-business-laptop',
            'alt' => 'A Dell Windows laptop beside a MacBook on an office desk',
        ],
        'microsoft-365-business-plans-explained' => [
            'file' => 'topic-software-office',
            'alt' => 'Colleagues using laptops for office productivity software',
        ],
        'microsoft-365-licensing-south-africa-guide' => [
            'file' => 'office-productivity-software',
            'alt' => 'Business professional using a laptop for office productivity work',
        ],
        'ubiquiti-supplier-south-africa-buyers-guide' => [
            'file' => 'public-wifi-infrastructure',
            'alt' => 'Ubiquiti UniFi wireless access point and PoE injector',
        ],
        'mikrotik-distributor-south-africa-isp-guide' => [
            'file' => 'network-ethernet-cables',
            'alt' => 'MikroTik cloud router switch with ethernet cables in a network rack',
        ],
        'best-networking-equipment-small-businesses' => [
            'file' => 'small-business-router',
            'alt' => 'Business Wi-Fi router and network equipment on a desk',
        ],
        'wifi-6-business-networks-explained' => [
            'file' => 'office-wifi-access',
            'alt' => 'UniFi wireless access point installed on a commercial ceiling',
        ],
        'fibre-networking-solutions-business-guide' => [
            'file' => 'fibre-network-optics',
            'alt' => 'Fibre optic cables lighting a data network connection',
        ],
        'business-laptops-south-africa-procurement-guide' => [
            'file' => 'business-laptop-desk',
            'alt' => 'Business laptop open on a clean office desk',
        ],
        'best-business-laptops-south-africa-2026' => [
            'file' => 'modern-business-notebook',
            'alt' => 'Modern business notebook photographed on a workspace',
        ],
        'lenovo-vs-dell-business-laptops-comparison' => [
            'file' => 'laptop-keyboard-workspace',
            'alt' => 'Close-up of a business laptop keyboard on an office desk',
        ],
        'dell-vs-hp-business-laptops' => [
            'file' => 'laptop-coding-desk',
            'alt' => 'Open laptop on a wooden desk in a professional workspace',
        ],
        'laptop-buying-guide-students-south-africa' => [
            'file' => 'students-with-laptops',
            'alt' => 'Students using laptops together at a study table',
        ],
        'cctv-equipment-supplier-south-africa-guide' => [
            'file' => 'outdoor-security-camera',
            'alt' => 'Outdoor security camera mounted on a commercial building',
        ],
        'poe-security-cameras-business-buying-guide' => [
            'file' => 'indoor-dome-camera',
            'alt' => 'PoE-style security camera mounted on a commercial brick wall',
        ],
        'cybersecurity-essentials-south-african-businesses' => [
            'file' => 'cybersecurity-operations',
            'alt' => 'Padlock on a laptop illustrating business cybersecurity',
        ],
        'cybersecurity-tips-every-business-should-follow' => [
            'file' => 'laptop-security-lock',
            'alt' => 'Padlock resting on a computer keyboard',
        ],
        'bulk-it-procurement-south-africa' => [
            'file' => 'procurement-documents',
            'alt' => 'Business documents and a laptop used for IT procurement',
        ],
        'source-it-equipment-government-corporate-tenders' => [
            'file' => 'tender-meeting-table',
            'alt' => 'Professionals reviewing paperwork around a meeting table',
        ],
        'education-technology-schools-south-africa-guide' => [
            'file' => 'school-classroom-computers',
            'alt' => 'Students using computers in a library study hall',
        ],
        'interactive-whiteboards-schools-buying-guide' => [
            'file' => 'classroom-interactive-display',
            'alt' => 'Teacher presenting on a large classroom display',
        ],
        'business-technology-strategy-south-africa-smes' => [
            'file' => 'strategy-meeting',
            'alt' => 'Business team in a strategy meeting around laptops',
        ],
        'essential-it-equipment-small-business-needs' => [
            'file' => 'open-plan-office-it',
            'alt' => 'Open-plan office with people working at computers',
        ],
        'how-to-reduce-it-costs-in-your-business' => [
            'file' => 'it-budget-review',
            'alt' => 'Calculator and financial documents used to review IT spend',
        ],
        'best-office-printers-south-african-businesses' => [
            'file' => 'office-multifunction-printer',
            'alt' => 'Office multifunction printer in a workplace copy area',
        ],
        'best-monitors-office-productivity' => [
            'file' => 'dual-office-monitors',
            'alt' => 'Laptop and external monitor on a wooden office desk',
        ],
        'best-all-in-one-pcs-business' => [
            'file' => 'all-in-one-desktop',
            'alt' => 'All-in-one desktop computer on a wooden office desk',
        ],
        'ups-backup-power-load-shedding-business' => [
            'file' => 'ups-power-equipment',
            'alt' => 'APC backup UPS unit for office and server equipment',
        ],
        'docking-stations-explained-business-workstation' => [
            'file' => 'docked-laptop-workstation',
            'alt' => 'Laptop connected to an external monitor on a workstation desk',
        ],
        'what-to-look-for-buying-refurbished-it-equipment' => [
            'file' => 'refurbished-laptop-inspection',
            'alt' => 'Open laptop being inspected on a technician’s bench',
        ],
        'eskom-breaks-ground-on-r12-billion-lethabo-solar-plant' => [
            'file' => 'solar-power-plant',
            'alt' => 'Rows of solar panels at a utility-scale solar plant',
        ],
        'mobile-operators-locked-out-as-icasa-opens-900mhz-of-spectrum' => [
            'file' => 'mobile-cell-tower',
            'alt' => 'Mobile network cell tower against the sky',
        ],
        'serious-legal-risk-for-south-africas-police-drones' => [
            'file' => 'commercial-drone',
            'alt' => 'Commercial drone in flight for aerial operations',
        ],
        'south-africas-right-to-repair-vacuum' => [
            'file' => 'electronics-repair-bench',
            'alt' => 'Technician repairing electronics at a workbench',
        ],
        'the-former-telkom-and-cell-c-ceo-who-is-now-a-director-at-a-bank-in-italy' => [
            'file' => 'executive-boardroom',
            'alt' => 'Executives in a corporate boardroom discussion',
        ],
        '4sight-earnings-leap-led-by-back-office-it-sales' => [
            'file' => 'it-sales-analytics',
            'alt' => 'Laptop showing business analytics in an office',
        ],
        'leaner-telkom-flags-sharply-higher-earnings' => [
            'file' => 'telecom-city-offices',
            'alt' => 'Corporate office buildings in a city skyline',
        ],
        'r65-billion-stolen-from-south-africans-in-fraudulent-scheme-and-nobody-is-behin' => [
            'file' => 'fraud-cyber-alert',
            'alt' => 'Hands using a laptop with a security warning on screen',
        ],
        'serious-blow-to-south-africas-oldest-state-owned-company' => [
            'file' => 'corporate-headquarters',
            'alt' => 'Glass corporate headquarters building exterior',
        ],
        'south-africa-to-target-childrens-screen-time' => [
            'file' => 'child-using-tablet',
            'alt' => 'Child using a tablet computer at a table',
        ],
        'south-africas-leap-to-modern-wi-fi-has-barely-begun' => [
            'file' => 'enterprise-network-cabling',
            'alt' => 'Enterprise network racks with structured cabling',
        ],
        'spam-calls-out-of-control-in-south-africa' => [
            'file' => 'business-smartphone-call',
            'alt' => 'Smartphone on a desk used for business calls',
        ],
    ],

    /*
    | Topic pools used when a slug is not listed above. Variants are unique
    | files; the article slug picks one deterministically.
    */
    'topics' => [
        'laptops' => [
            ['file' => 'topic-laptop-silver', 'alt' => 'Silver business laptop on a clean desk'],
            ['file' => 'topic-laptop-hands', 'alt' => 'Hands typing on a laptop in an office'],
        ],
        'networking' => [
            ['file' => 'topic-network-rack', 'alt' => 'Network switch with patch cables in a comms rack'],
            ['file' => 'topic-server-cables', 'alt' => 'IT technician beside server racks in a comms room'],
        ],
        'cctv' => [
            ['file' => 'topic-cctv-wall', 'alt' => 'Security camera covering a commercial building'],
        ],
        'software' => [
            ['file' => 'office-collaboration-laptops', 'alt' => 'Colleagues working together on laptops in a modern office'],
        ],
        'cybersecurity' => [
            ['file' => 'topic-cyber-screens', 'alt' => 'Person using a 2-in-1 laptop in an office'],
        ],
        'procurement' => [
            ['file' => 'topic-procurement-desk', 'alt' => 'Laptop and paperwork for a business purchasing process'],
        ],
        'education' => [
            ['file' => 'topic-education-lab', 'alt' => 'Learners working at classroom tables'],
        ],
        'business' => [
            ['file' => 'topic-business-office', 'alt' => 'Modern office with computers at workstations'],
        ],
        'guides' => [
            ['file' => 'topic-it-workspace', 'alt' => 'Organised IT workspace with a laptop and accessories'],
        ],
        'news' => [
            ['file' => 'topic-news-city-tech', 'alt' => 'City technology and communications infrastructure'],
            ['file' => 'topic-news-office-team', 'alt' => 'Office team collaborating around computers'],
            ['file' => 'topic-news-datacentre', 'alt' => 'Rows of servers in a data centre'],
        ],
        'ups' => [
            ['file' => 'ups-power-equipment', 'alt' => 'Uninterruptible power supply for business equipment'],
        ],
        'printers' => [
            ['file' => 'office-multifunction-printer', 'alt' => 'Office printer in a workplace'],
        ],
        'signage' => [
            ['file' => 'classroom-interactive-display', 'alt' => 'Large commercial display in a teaching space'],
        ],
        'servers' => [
            ['file' => 'topic-news-datacentre', 'alt' => 'Rows of servers in a data centre'],
        ],
        'solar' => [
            ['file' => 'solar-power-plant', 'alt' => 'Solar panels at a power generation site'],
        ],
        'telecom' => [
            ['file' => 'mobile-cell-tower', 'alt' => 'Telecommunications tower for mobile networks'],
        ],
        'drones' => [
            ['file' => 'commercial-drone', 'alt' => 'Unmanned aerial drone in operation'],
        ],
    ],

    'keyword_topics' => [
        ['pattern' => 'macbook|refurbished mac', 'topic' => 'laptops'],
        ['pattern' => 'microsoft 365|office 365|\bm365\b', 'topic' => 'software'],
        ['pattern' => 'ubiquiti|unifi|mikrotik', 'topic' => 'networking'],
        ['pattern' => 'wi-?fi|wifi|access point', 'topic' => 'networking'],
        ['pattern' => 'fibre|fiber|\bsfp\b', 'topic' => 'networking'],
        ['pattern' => 'poe|cctv|nvr|surveillance|security camera|ip camera', 'topic' => 'cctv'],
        ['pattern' => 'ups|load shedding|backup power', 'topic' => 'ups'],
        ['pattern' => 'printer|mfp|toner', 'topic' => 'printers'],
        ['pattern' => 'monitor|dual.screen', 'topic' => 'guides'],
        ['pattern' => 'interactive whiteboard|digital signage|smart board', 'topic' => 'signage'],
        ['pattern' => 'docking station|thunderbolt', 'topic' => 'guides'],
        ['pattern' => 'all-in-one|\baio\b', 'topic' => 'business'],
        ['pattern' => 'cybersecurity|firewall|phishing|ransomware|fraud', 'topic' => 'cybersecurity'],
        ['pattern' => 'tender|rfq|procurement|bulk it', 'topic' => 'procurement'],
        ['pattern' => 'school|campus|education|classroom|children', 'topic' => 'education'],
        ['pattern' => 'drone', 'topic' => 'drones'],
        ['pattern' => 'solar|eskom', 'topic' => 'solar'],
        ['pattern' => 'spectrum|icasa|telkom|cell-c|spam call|mobile operator', 'topic' => 'telecom'],
        ['pattern' => 'server|data.?cent(?:re|er)|rack', 'topic' => 'servers'],
        ['pattern' => 'laptop|notebook', 'topic' => 'laptops'],
        ['pattern' => 'software|licen[cs]e', 'topic' => 'software'],
        ['pattern' => 'network|switch|router|ethernet', 'topic' => 'networking'],
    ],
];
