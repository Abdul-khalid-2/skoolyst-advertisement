<?php
/**
 * Single source of truth for ad/app data until Section 3 (Backend) and
 * Section 5 (Database) replace this with real repositories reading from
 * MySQL. Every page — and the client-side JS in js/dashboard.js — reads
 * from this one file (injected as JSON in views/partials/scripts.php)
 * instead of each maintaining its own copy.
 *
 * Image paths are stored relative to the project root (no leading "../")
 * — callers prepend $baseHref when rendering.
 */

return [

    'apps' => [
        ['id' => 'sk', 'code' => 'SK', 'name' => 'Skoolyst', 'domain' => 'skoolyst.com', 'apiKey' => 'sk_live_9c1c...4f2a', 'status' => 'active', 'placements' => 3],
        ['id' => 'ss', 'code' => 'SS', 'name' => 'Skoolyst Social', 'domain' => 'social.skoolyst.com', 'apiKey' => 'sk_live_2b7e...9a10', 'status' => 'active', 'placements' => 2],
        ['id' => 'st', 'code' => 'ST', 'name' => 'Skoolyst Teachers', 'domain' => 'teachers.skoolyst.com', 'apiKey' => 'sk_live_af31...c88d', 'status' => 'active', 'placements' => 2],
        ['id' => 'jf', 'code' => 'JF', 'name' => 'Jaans Fabrics', 'domain' => 'jaansfabrics.com', 'apiKey' => 'sk_live_11e0...77bb', 'status' => 'active', 'placements' => 1],
        ['id' => 'sa', 'code' => 'SA', 'name' => 'Safi India Autos', 'domain' => 'safiindiaautos.com', 'apiKey' => 'sk_live_5d90...12ff', 'status' => 'paused', 'placements' => 1],
    ],

    'ads' => [
        [
            'id' => 'ad_1001', 'title' => 'Admissions Open — Build Your Future With Tech',
            'advertiser' => 'Bright Path Computer Academy',
            'description' => 'Hands-on courses in web development, graphic design, and office skills. Evening batches available for working students.',
            'image' => 'assets/img/ad-1.svg', 'cta' => 'Learn More', 'url' => 'https://example.com/computer-academy',
            'app' => 'sk', 'placement' => 'home_top', 'status' => 'active',
            'impressions' => 48210, 'clicks' => 1120, 'startDate' => '2026-07-01', 'endDate' => '2026-09-30',
        ],
        [
            'id' => 'ad_1002', 'title' => 'Speak Confidently in 8 Weeks',
            'advertiser' => 'Fluent English Learning Center',
            'description' => 'Small-group spoken English classes for students and professionals, with weekend batches and certified instructors.',
            'image' => 'assets/img/ad-2.svg', 'cta' => 'Book a Seat', 'url' => 'https://example.com/english-center',
            'app' => 'st', 'placement' => 'teacher_profile_sidebar', 'status' => 'pending',
            'impressions' => 0, 'clicks' => 0, 'startDate' => '2026-08-28', 'endDate' => '2026-10-28',
        ],
        [
            'id' => 'ad_1003', 'title' => 'New Semester, New Books — Up to 20% Off',
            'advertiser' => 'Noor Educational Book Store',
            'description' => 'Textbooks, guides, and stationery for all grades in one place, with home delivery across the city.',
            'image' => 'assets/img/ad-3.svg', 'cta' => 'Shop Now', 'url' => 'https://example.com/book-store',
            'app' => 'sk', 'placement' => 'blog_inline', 'status' => 'active',
            'impressions' => 22870, 'clicks' => 640, 'startDate' => '2026-06-15', 'endDate' => '2026-08-31',
        ],
        [
            'id' => 'ad_1004', 'title' => 'Custom Stitched Uniforms — School Rate Discounts',
            'advertiser' => 'Jaans Fabrics',
            'description' => 'Bulk school uniform stitching with measurement pickup and 10-day delivery across Karachi.',
            'image' => 'assets/img/ad-2.svg', 'cta' => 'Get a Quote', 'url' => 'https://example.com/jaans-fabrics',
            'app' => 'jf', 'placement' => 'feed_inline', 'status' => 'paused',
            'impressions' => 9310, 'clicks' => 145, 'startDate' => '2026-05-01', 'endDate' => '2026-08-01',
        ],
        [
            'id' => 'ad_1005', 'title' => 'Free Career Counselling This Weekend',
            'advertiser' => 'Bright Path Computer Academy',
            'description' => 'One-on-one sessions for matric and intermediate students exploring tech careers.',
            'image' => 'assets/img/ad-1.svg', 'cta' => 'Reserve a Slot', 'url' => 'https://example.com/counselling',
            'app' => 'ss', 'placement' => 'social_feed_inline', 'status' => 'rejected',
            'impressions' => 0, 'clicks' => 0, 'startDate' => '2026-08-20', 'endDate' => '2026-09-05',
            'rejectionReason' => 'Landing page did not match the ad claim. Please update the URL and resubmit.',
        ],
        [
            'id' => 'ad_1006', 'title' => 'Vehicle Inspection Package — 20% Off This Month',
            'advertiser' => 'Safi India Autos',
            'description' => 'Full 40-point inspection before you buy or sell a used vehicle.',
            'image' => 'assets/img/ad-3.svg', 'cta' => 'Book Inspection', 'url' => 'https://example.com/safi-autos',
            'app' => 'sa', 'placement' => 'home_sidebar', 'status' => 'draft',
            'impressions' => 0, 'clicks' => 0, 'startDate' => '', 'endDate' => '',
        ],
        [
            'id' => 'ad_1007', 'title' => 'Parent-Teacher Meet Scheduling Made Easy',
            'advertiser' => 'Skoolyst',
            'description' => 'Let parents pick their own slots — no more back-and-forth on WhatsApp groups.',
            'image' => 'assets/img/ad-1.svg', 'cta' => 'See How It Works', 'url' => 'https://example.com/skoolyst-ptm',
            'app' => 'st', 'placement' => 'teacher_dashboard_banner', 'status' => 'ended',
            'impressions' => 61200, 'clicks' => 2030, 'startDate' => '2026-03-01', 'endDate' => '2026-06-01',
        ],
    ],

    'placementsByApp' => [
        'sk' => [
            ['value' => 'home_top', 'label' => 'Home — Top Banner'],
            ['value' => 'home_sidebar', 'label' => 'Home — Sidebar'],
            ['value' => 'blog_inline', 'label' => 'Blog — Inline'],
        ],
        'ss' => [
            ['value' => 'social_feed_inline', 'label' => 'Feed — Inline Card'],
            ['value' => 'social_sidebar', 'label' => 'Sidebar'],
        ],
        'st' => [
            ['value' => 'teacher_profile_sidebar', 'label' => 'Teacher Profile — Sidebar'],
            ['value' => 'teacher_dashboard_banner', 'label' => 'Teacher Dashboard — Banner'],
        ],
        'jf' => [
            ['value' => 'feed_inline', 'label' => 'Catalog — Inline'],
        ],
        'sa' => [
            ['value' => 'home_sidebar', 'label' => 'Home — Sidebar'],
        ],
    ],

];
