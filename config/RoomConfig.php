<?php
/**
 * Centralized Room and Cottage Configuration
 * This file serves as the single source of truth for all room and cottage data
 * Use this instead of hardcoding data across multiple files
 */

// Room Configuration (Database canonical data)
// Inclusions sourced from Villa Soledad brochure cards
const DEFAULT_ROOMS = [
    [
        'name' => 'Standard Room',
        'description' => 'Comfortable rooms perfect for couples with resort access for 2',
        'capacity' => 4,
        'price' => 2800.00,
        'image' => 'images/standard.jpg',
        'available' => 1,
        'promo' => 'Free Breakfast',
        'inclusions' => [
            'Good for 2pax (maximum of 4 pax)',
            'Resort Access for 2',
            'Aircon Room',
            '2 Full Sized Bed',
            'Shower w/heater',
            'Extra person with Breakfast Php 800'
        ]
    ],
    [
        'name' => 'Deluxe Room',
        'description' => 'Spacious rooms with premium amenities and resort access for 2-4',
        'capacity' => 4,
        'price' => 2800.00,
        'image' => 'images/deluxe.jpg',
        'available' => 1,
        'promo' => 'Free Breakfast',
        'inclusions' => [
            'Good for 2pax (maximum of 4 pax)',
            'Resort Access for 2',
            'Aircon Room',
            '2 Full Sized Bed',
            'Shower w/heater',
            'Extra person with Breakfast Php 800'
        ]
    ],
    [
        'name' => 'Family Room',
        'description' => 'Room for families with resort access for 4-6 and plenty of space',
        'capacity' => 6,
        'price' => 3500.00,
        'image' => 'images/family-room.svg',
        'available' => 1,
        'promo' => 'Free Breakfast for 4',
        'inclusions' => [
            'Good for 4pax (maximum of 6 pax)',
            'Resort Access for 4',
            'Aircon Room',
            '1 Full Sized Bed',
            '1 Double Deck',
            'Shower w/heater',
            'Extra person with Breakfast Php 800'
        ]
    ],
    [
        'name' => 'Family Deluxe Room',
        'description' => 'Large family deluxe room with extra comfort and resort access for 6-8',
        'capacity' => 8,
        'price' => 5500.00,
        'image' => 'images/family-deluxe-room.svg',
        'available' => 1,
        'promo' => 'Free Breakfast for 6',
        'inclusions' => [
            'Good for 6pax (maximum of 8 pax)',
            'Resort Access for 6',
            'Aircon Room',
            '4 Full Sized Bed',
            '2 Toilet & Bath',
            'Shower w/heater',
            'Extra person with Breakfast Php 800'
        ]
    ]
];

// Cottage Configuration (Database canonical data)
const DEFAULT_COTTAGES = [
    [
        'name' => 'Cottage A',
        'description' => 'Modern pavilion perfect for poolside gatherings',
        'capacity' => 10,
        'price' => 750.00,
        'image' => 'images/cottage a.png',
        'available' => 1
    ],
    [
        'name' => 'Cottage B',
        'description' => 'Traditional nipa hut for authentic experience',
        'capacity' => 14,
        'price' => 1000.00,
        'image' => 'images/kubo cottage.jpg',
        'available' => 1
    ],
    [
        'name' => 'Kubo Cottage',
        'description' => 'Rustic cottage with comfortable room amenities',
        'capacity' => 22,
        'price' => 1500.00,
        'image' => 'images/kubo with room cottage.jpg',
        'available' => 1
    ]
];

// Reservation Limits (Daily booking limits per item)
const RESERVATION_LIMITS = [
    'Standard Room' => 3,
    'Deluxe Room' => 2,
    'Family Room' => 3,
    'Family Deluxe Room' => 2,
    'Cottage A' => 5,
    'Cottage B' => 3,
    'Kubo Cottage' => 2
];

/**
 * Helper function: Get all rooms
 */
function getDefaultRooms() {
    return DEFAULT_ROOMS;
}

/**
 * Helper function: Get all cottages
 */
function getDefaultCottages() {
    return DEFAULT_COTTAGES;
}

/**
 * Helper function: Get reservation limit for an item
 */
function getReservationLimit($itemName) {
    return RESERVATION_LIMITS[$itemName] ?? 1;
}

/**
 * Helper function: Get all reservation limits
 */
function getAllReservationLimits() {
    return RESERVATION_LIMITS;
}

/**
 * Get brochure display details for a room by name
 */
function getRoomDisplayDetails($roomName) {
    foreach (DEFAULT_ROOMS as $room) {
        if (strcasecmp($room['name'], (string)$roomName) === 0) {
            return $room;
        }
    }
    return null;
}

/**
 * Build a short summary line from room inclusions
 */
function getRoomInclusionsSummary($roomName) {
    $details = getRoomDisplayDetails($roomName);
    if (!$details) {
        return '';
    }
    $promo = $details['promo'] ?? '';
    $first = $details['inclusions'][0] ?? '';
    return trim($promo . ($promo && $first ? ' • ' : '') . $first);
}

/**
 * Seed brochure defaults only for rooms that do not exist yet.
 * Never overwrites admin-edited description, price, or capacity.
 */
function syncRoomBrochureData($conn) {
    if (!$conn) {
        return;
    }

    $checkStmt = $conn->prepare('SELECT id FROM rooms WHERE name = ? LIMIT 1');
    $insertStmt = $conn->prepare(
        'INSERT INTO rooms (name, description, capacity, price_per_night, image_url, available) VALUES (?, ?, ?, ?, ?, ?)'
    );
    if (!$checkStmt || !$insertStmt) {
        return;
    }

    foreach (DEFAULT_ROOMS as $room) {
        $name = $room['name'];
        $checkStmt->bind_param('s', $name);
        $checkStmt->execute();
        $exists = $checkStmt->get_result()->fetch_assoc();
        if ($exists) {
            continue;
        }

        $description = $room['description'];
        $capacity = (int)$room['capacity'];
        $price = (float)$room['price'];
        $image = $room['image'];
        $available = (int)($room['available'] ?? 1);
        $insertStmt->bind_param('ssidsi', $name, $description, $capacity, $price, $image, $available);
        $insertStmt->execute();
    }

    $checkStmt->close();
    $insertStmt->close();
}

/**
 * Render brochure inclusions HTML for room cards / modals
 */
function renderRoomInclusionsHtml($roomName, $compact = false) {
    $details = getRoomDisplayDetails($roomName);
    if (!$details) {
        return '';
    }

    $fontSize = $compact ? '0.95rem' : '1.05rem';
    $lineHeight = $compact ? '1.45' : '1.6';
    $promoSize = $compact ? '0.82rem' : '0.9rem';
    $html = '';

    if (!empty($details['promo'])) {
        $html .= '<div style="display:inline-block; background:#fff7ed; color:#c2410c; font-weight:700; font-size:' . $promoSize . '; padding:0.25rem 0.6rem; border-radius:999px; margin-bottom:0.5rem;">'
            . htmlspecialchars($details['promo'])
            . '</div>';
    }

    $items = $details['inclusions'];
    $items[] = 'Check-in time: 3-PM';
    $items[] = 'Check-out time: 12 Noon';

    $html .= '<ul style="margin:0; padding-left:1.15rem; color:#334155; font-size:' . $fontSize . '; line-height:' . $lineHeight . '; font-weight:500;">';
    foreach ($items as $item) {
        $html .= '<li style="margin-bottom:0.22rem;">' . htmlspecialchars($item) . '</li>';
    }
    $html .= '</ul>';

    return $html;
}
