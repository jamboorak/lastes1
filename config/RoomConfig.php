<?php
/**
 * Centralized Room and Cottage Configuration
 * This file serves as the single source of truth for all room and cottage data
 * Use this instead of hardcoding data across multiple files
 */

// Room Configuration (Database canonical data)
const DEFAULT_ROOMS = [
    [
        'name' => 'Standard Room',
        'description' => 'Comfortable rooms perfect for couples with resort access for 2',
        'capacity' => 2,
        'price' => 2500.00,
        'image' => 'images/standard.jpg',
        'available' => 1
    ],
    [
        'name' => 'Deluxe Room',
        'description' => 'Spacious rooms with premium amenities and resort access for 2-4',
        'capacity' => 4,
        'price' => 2800.00,
        'image' => 'images/deluxe.jpg',
        'available' => 1
    ],
    [
        'name' => 'Family Room',
        'description' => 'Room for families with resort access for 4-6 and plenty of space',
        'capacity' => 6,
        'price' => 3500.00,
        'image' => 'images/family-room.svg',
        'available' => 1
    ],
    [
        'name' => 'Family Deluxe Room',
        'description' => 'Large family deluxe room with extra comfort and resort access for 6-8',
        'capacity' => 8,
        'price' => 5500.00,
        'image' => 'images/family-deluxe-room.svg',
        'available' => 1
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
