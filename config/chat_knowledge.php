<?php

/*
| Urban Focus chat knowledge.
|
| This is the maintainable source for facts the customer chat may say about
| the business. Edit the lines here, then ship the change through GitHub.
| cPanel must Deploy HEAD Commit afterwards so a cached config picks this up.
|
| Do not invent prices, stock, bank details or policies in this file.
| Phone, email, hours and the street address are read from config/business.php
| (the BUSINESS_* environment values on the server). Courier rates are read
| from config/shipping.php. Published FAQ answers are read from config/seo.php.
| Returns and warranty wording is read from the published policy text.
|
| Company registration, B-BBEE level and sister-brand names below were supplied
| by the owner. They are not on the About page. Change them here if they change.
*/

return [
    'refusal' => 'I can only help with Urban Focus: our products, services, orders and policies. I cannot answer that. Ask about the catalogue, a quote, delivery or an order, or use Talk to the team.',

    'greeting' => 'Hello. I can help with Urban Focus products, quotes, delivery, orders and policies. What do you need?',

    'topics' => [
        'company' => [
            'title' => 'Company',
            'triggers' => [
                'who are you', 'who is urban', 'about you', 'about urban', 'your company',
                'the company', 'what is urban', 'holdings', 'bbbee', 'b-bbee', 'bbee',
                'sister brand', 'it guy', 'cyber developers', 'registration', 'pty',
            ],
            'lines' => [
                'Urban Focus (www.urbanfocus.co.za) is a South African IT hardware, electronics and technology supplier. The About page says it operates from Centurion, Gauteng, and delivers nationwide across South Africa.',
                'Urban Focus is a brand of R Murulana Holdings (Pty) Ltd, registration 2024/736593/07. The About page shows the trading name Urban Focus.',
                'B-BBEE Level 1 (EME).',
                'Sister brands: IT Guy Solutions and Cyber Developers. No other published detail about those brands is available in this chat.',
                'The About page says typical customers are corporates, systems integrators, ISPs, schools, resellers and public-sector buyers. The focus is business and enterprise infrastructure, not general consumer electronics.',
            ],
        ],
        'services' => [
            'title' => 'Services',
            'triggers' => [
                'service', 'services', 'what do you do', 'what do you offer', 'what can you do',
                'what do you sell', 'what can you help', 'how can you help',
            ],
            'lines' => [
                'Urban Focus sells networking equipment, business laptops, desktops, servers, storage, CCTV, fibre, VoIP systems, software licensing and specialist IT, including hardware security keys, industrial IoT and private-cloud software.',
                'Around those products it also provides VAT invoices, courier delivery, quotes, RFQ upload, and procurement support for organisations.',
                'The About page lists these ranges: enterprise and SMB networking (wireless, switching, routing, fibre and PoE); business laptops, desktops, servers and storage; CCTV, access control and related security electronics; specialist technology including hardware security keys, industrial IoT and licensing.',
                'Published solution pages cover corporate IT supply, Ubiquiti supply, MikroTik supply, business laptops, CCTV equipment and bulk IT procurement.',
                'A price, a discount or a stock level is confirmed only from the live catalogue for a specific product. This services answer does not claim that any particular model is in stock.',
            ],
        ],
        'b2b' => [
            'title' => 'Quotes and B2B',
            'triggers' => [
                'quote', 'quotation', 'rfq', 'procurement', 'tender', 'bulk', 'b2b',
                'government', 'source a product', 'sourcing', 'account',
            ],
            'lines' => [
                'A quote can be requested on the website. The quote page says the team responds within one business day. That sentence is about the quote form, not a promise that every chat message is answered in one day.',
                'A formal RFQ document can be uploaded on the Upload RFQ page.',
                'Corporate and government procurement enquiries are accepted on the Corporate & Government Procurement page (formal quotes, vendor registration and supply).',
                'The FAQ says Urban Focus supports corporate, government, education and installer customers with bulk pricing, formal quotes, RFQ processing and account support. Bulk prices are not quoted in this chat.',
                'If a product is not in the catalogue, the Source a Product page asks the team to search the supplier network. No price and no delivery date is promised for a sourcing request.',
            ],
        ],
        'delivery' => [
            'title' => 'Delivery',
            'triggers' => [
                'deliver', 'delivery', 'shipping', 'courier', 'dispatch', 'collection',
                'collect', 'how long will', 'arrive', 'nationwide',
            ],
            'lines' => [
                'Standard courier delivery across South Africa. The shipping page names Johannesburg, Pretoria, Cape Town, Durban, Centurion and regional centres. The FAQ also names those cities and regional areas.',
                'Collection can be arranged by contacting the team. Large or bulk orders can be given a manual courier quote.',
                'The Courier Guy logo is shown on the site as a delivery partner. The FAQ also says delivery uses trusted couriers.',
                'The shipping page says orders are processed within 1–2 business days after payment confirmation. The FAQ says in-stock items typically ship within 1–3 business days and that delivery time depends on location and the courier. Neither figure is a guaranteed delivery date. Tracking details are sent once an order has been dispatched.',
            ],
        ],
        'payment' => [
            'title' => 'Payment',
            'triggers' => [
                'payment', 'pay', 'paid', 'eft', 'paystack', 'card', 'vat', 'invoice',
                'apple pay', 'google pay', 'snapscan', 'capitec',
            ],
            'lines' => [
                'The FAQ says Urban Focus accepts Paystack secure checkout, including credit and debit card, Instant EFT, Apple Pay, Google Pay and manual EFT.',
                'The terms page says Paystack (card, instant EFT) and manual EFT are accepted, and that orders are processed after payment verification.',
                'All website prices are in South African Rand (ZAR) and include VAT where applicable. The terms page says prices include VAT unless stated otherwise, and that pricing errors may be corrected. An order is confirmed once payment is received or approved.',
                'The site also displays Visa, Mastercard, SnapScan and Capitec Pay marks.',
                'VAT invoices are supplied for business, corporate, government and registered customer orders. Company details can be included at checkout or on a B2B quote.',
                'Bank account numbers are not published in this chat. Manual EFT details are shown on an order only when the store has them configured. Otherwise contact the team.',
            ],
        ],
        'tracking' => [
            'title' => 'Order tracking',
            'triggers' => [
                'track my', 'track an order', 'order status', 'where is my order', 'dispatched',
            ],
            'lines' => [
                'Tracking in this chat needs both the order number and the email used at checkout. The order number alone is not enough. A wrong pair gets the same reply as an unknown order.',
                'The reply is the status stored on the order, including payment status. It is not a new delivery date.',
            ],
        ],
        'privacy' => [
            'title' => 'Privacy',
            'triggers' => [
                'privacy', 'popia', 'personal information', 'personal data', 'my data',
            ],
            'lines' => [
                'The privacy page says personal information is used to process orders, provide support, improve services and comply with legal obligations. Urban Focus does not sell personal data.',
                'Personal information is processed under the Protection of Personal Information Act (POPIA). A customer may request access or correction by emailing the sales address. A POPIA page is published on the site.',
            ],
        ],
        'terms' => [
            'title' => 'Terms',
            'triggers' => [
                'terms', 'conditions', 'liability', 'pricing error',
            ],
            'lines' => [
                'Using www.urbanfocus.co.za means the customer agrees to the published terms.',
                'Product images are for illustration. Specifications may change. Stock is shown on the product page.',
                'The terms page says Urban Focus is not liable for indirect losses, and liability is limited to the value of the goods purchased.',
            ],
        ],
        'brands' => [
            'title' => 'Brands',
            'triggers' => [
                'which brands', 'what brands', 'brands do you', 'brands you', 'stock brands',
                'genuine',
            ],
            'lines' => [
                'The About page says Urban Focus supplies genuine Ubiquiti, MikroTik, Dell, HP, Hikvision and other leading IT brands through trusted distribution channels.',
                'A brand logo on the website is not a promise that every model is in stock. Check a specific product in the catalogue.',
            ],
        ],
        'careers' => [
            'title' => 'Careers',
            'triggers' => [
                'career', 'careers', 'job', 'jobs', 'vacancy', 'vacancies', 'work with you',
            ],
            'lines' => [
                'A Work With Us page is published. Career messages use the published careers email, which defaults to the sales email. This chat does not list open jobs and does not accept a job application.',
            ],
        ],
    ],
];
