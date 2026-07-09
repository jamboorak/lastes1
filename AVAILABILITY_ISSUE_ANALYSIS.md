# Cottage Availability Issue - Root Cause Analysis

## Executive Summary
The booking system displays "0 available" for cottages because of **name mismatches** between database tables. The `cottages` table, `reservation_limits` table, and `reservation_items` table use different cottage names, causing availability lookups to fail.

---

## 1. Database Tables Structure

### Table: `cottages`
**Source**: `views/booking.php` (lines 90-96)
**Insertion Logic**: Only inserted if table is empty (SQL executed in booking view)
**Current Cottages**:
```
- Cottage A (capacity: 8-12, price: ₱750)
- Cottage B (capacity: 12-16, price: ₱1000)
- Kubo Cottage (capacity: 20-25, price: ₱1500)
```

### Table: `reservation_limits`
**Source**: `setup_reservation_limits.php` (lines 15-30)
**Purpose**: Defines daily booking limits for each room/cottage
**Current Limits**:
```
Cottages:
- Cottage A: 5 per day
- Cottage B: 3 per day
- Kubo Cottage: 2 per day

Rooms:
- Standard Room: 3 per day
- Deluxe Room: 2 per day
- Family Room: 3 per day
- Family Deluxe Room: 2 per day
```

### Table: `reservation_items`
**Source**: `setup_database.php` (lines 100-118)
**Purpose**: Stores individual booked items
**Structure**:
- `reservation_id` (FK to reservations)
- `item_type` ('room' or 'cottage')
- `item_id` (ID reference)
- **`item_name` VARCHAR(100)** ← KEY FIELD for availability matching
- `price`, `capacity`, `nights`

### Table: `reservations`
**Structure**:
- `id` (PRIMARY KEY)
- `user_id` (FK)
- `check_in` DATE
- `check_out` DATE
- `status` ('pending', 'approved', 'cancelled', etc.)
- (Used to filter active reservations)

---

## 2. How Availability Checking Works

### Location: `models/Booking.php` (lines 233-280)

```php
public function checkDailyAvailability($date, $roomName = null) {
    // Step 1: Load all item names and limits from reservation_limits
    $itemLimits = []; 
    foreach (DB results) {
        $itemLimits[$row['item_name']] = (int)$row['daily_limit'];
    }
    
    // Step 2: For each item, count reservations from reservation_items
    $sql = "SELECT ri.item_name, COUNT(*) as booked_count
            FROM reservation_items ri
            JOIN reservations r ON ri.reservation_id = r.id
            WHERE r.status IN ('pending', 'approved')
              AND ? >= r.check_in
              AND ? < r.check_out
              AND ri.item_name IN ($placeholders)
            GROUP BY ri.item_name";
    
    // Step 3: Calculate available = limit - booked
    $availability[$itemName] = [
        'limit' => $limit,
        'booked' => $booked,
        'available' => max(0, $limit - $booked)
    ];
}
```

**KEY POINT**: The availability lookup uses `item_name` field:
1. Loads names from `reservation_limits` table
2. Queries `reservation_items` for matching `item_name`
3. If a cottage name doesn't exist in `reservation_limits`, it won't be found and shows **0 available**

---

## 3. The Problem: Name Mismatches

### Issue #1: Database Initialization Files Use Different Names

**In `setup_database.php` (lines 89-97):**
```php
INSERT INTO cottages (...) VALUES
('Family Cottage', 'Spacious cottage perfect for families...', 6, 1500.00, ...),
('Barkada Cottage', 'Ideal for group of friends...', 8, 2000.00, ...),
('Romantic Cottage', 'Cozy cottage for couples', 2, 800.00, ...)
```

**In `setup_reservation_limits.php` (lines 14-30):**
```php
$allData = [
    ['cottage', 'Cottage A', 5],
    ['cottage', 'Cottage B', 3],
    ['cottage', 'Kubo Cottage', 2]
];
```

**In `views/booking.php` (lines 90-96):**
```php
INSERT INTO cottages (...) VALUES
('Cottage A', 'Modern pavilion perfect for poolside gatherings', ...),
('Cottage B', 'Traditional nipa hut for authentic experience', ...),
('Kubo Cottage', 'Rustic cottage with comfortable room amenities', ...)
```

### Issue #2: Display Logic Assumes Names Will Match

**In `views/booking.php` (lines ~915-945):**
```php
<?php 
$cottageName = $cottage['name'];  // Gets name from cottages table
$availabilityInfo = isset($availabilityData[$cottageName]) 
    ? $availabilityData[$cottageName] 
    : ['limit' => 0, 'booked' => 0, 'available' => 0];  // DEFAULTS TO 0!
?>
```

**In `views/booking.php` (line ~935):**
```php
<?php echo $isAvailable ? $availabilityInfo['available'] . ' available' : 'Unavailable'; ?>
```

**Result**: If cottage name doesn't exist in `$availabilityData`, it shows "0 available"

---

## 4. Data Flow Diagram

```
Database Initialization:
┌─────────────────────────────────────────┐
│ setup_database.php                      │
│ - Creates cottages table                │
│ - Inserts: Family Cottage,              │
│   Barkada Cottage, Romantic Cottage     │
└────────────────────┬────────────────────┘
                     │
                     v
            ┌────────────────┐
            │ cottages table │
            └────────────────┘

Booking View Initialization:
┌─────────────────────────────────────────┐
│ views/booking.php                       │
│ (Runs when cottageCount == 0)           │
│ - Overwrites cottages with:             │
│   Cottage A, Cottage B, Kubo Cottage    │
└────────────────────┬────────────────────┘
                     │
                     v
            ┌────────────────┐
            │ cottages table │ ← NOW contains Cottage A, B, Kubo
            └────────────────┘

Reservation Limits Setup:
┌─────────────────────────────────────────┐
│ setup_reservation_limits.php            │
│ - Creates reservation_limits table      │
│ - Inserts: Cottage A, Cottage B,        │
│   Kubo Cottage with daily limits        │
└────────────────────┬────────────────────┘
                     │
                     v
         ┌──────────────────────────┐
         │ reservation_limits table │
         │ - Cottage A: 5           │
         │ - Cottage B: 3           │
         │ - Kubo Cottage: 2        │
         └──────────────────────────┘

Availability Check Flow:
User selects date → BookingController::checkDailyAvailability()
    ↓
Loads from reservation_limits:
- Cottage A, Cottage B, Kubo Cottage
    ↓
Queries reservation_items for matching item_name
    ↓
Display in booking.php:
- Shows cottage names from cottages table
- Looks up availability by name in availabilityData
- IF NAME MATCHES → Shows count
- IF NO MATCH → Shows "0 available" (default)
```

---

## 5. Specific Problems Identified

### Problem A: Static Versus Dynamic Insertion
- `setup_database.php` is run once during setup → inserts "Family Cottage", "Barkada Cottage", "Romantic Cottage"
- `views/booking.php` is run every page load → if `cottageCount == 0`, it re-inserts with "Cottage A", "Cottage B", "Kubo Cottage"
- The `setup_reservation_limits.php` expects "Cottage A", "Cottage B", "Kubo Cottage"

### Problem B: SQL Syntax Error in views/booking.php
**Lines 93-96**:
```php
INSERT INTO cottages (...) VALUES
('Cottage A', '...', 8-12, 750.00, ...),  // ← 8-12 is arithmetic, not text!
('Cottage B', '...', 12-16, 1000.00, ...),
('Kubo Cottage', '...', 20-25, 1500.00, ...)
```

The capacity values `8-12`, `12-16`, `20-25` are treated as SQL math expressions:
- `8-12` evaluates to `-4`
- `12-16` evaluates to `-4`
- `20-25` evaluates to `-5`

These should be fixed to single values like `10`, `14`, `22`.

### Problem C: No Room Names in reservation_limits
The `reservation_limits` table contains room names:
- Standard Room, Deluxe Room, Family Room, Family Deluxe Room

But `rooms` table might have different names inserted from `views/booking.php`.

---

## 6. Recommended Fixes

### Fix #1: Standardize Cottage Names Across All Files
**Priority: CRITICAL**

**Option A** (Recommended): Use "Cottage A", "Cottage B", "Kubo Cottage"
- Update `setup_database.php` to match
- Keep `views/booking.php` as-is
- Keep `setup_reservation_limits.php` as-is
- Benefit: Simple, cottage names are generic/standardized

**Option B**: Use descriptive names "Family Cottage", "Barkada Cottage", "Romantic Cottage"
- Update `views/booking.php` to match
- Update `setup_reservation_limits.php` to match
- Update `setup_database.php` to match
- Benefit: More descriptive for customers

### Fix #2: Fix SQL Syntax for Capacity Values
**In `views/booking.php` lines 93-96:**
```php
// BEFORE:
INSERT INTO cottages (..., capacity, ...) VALUES
('Cottage A', '...', 8-12, 750.00, ...),  // Wrong: 8-12 = -4

// AFTER:
INSERT INTO cottages (..., capacity, ...) VALUES
('Cottage A', '...', 10, 750.00, ...),    // Correct: use average or specific number
('Cottage B', '...', 14, 1000.00, ...),
('Kubo Cottage', '...', 22, 1500.00, ...)
```

### Fix #3: Ensure Room Names Match
**In `views/booking.php` lines 45-65:**
Already uses correct room names:
- Standard Room ✓
- Deluxe Room ✓
- Family Room ✓
- Family Deluxe Room ✓

These match `setup_reservation_limits.php` (lines 19-22).

### Fix #4: Prevent Duplicate Initialization
**Consider**: Add a check to prevent `views/booking.php` from overwriting cottages table:
```php
// Option 1: Remove cottage insertion from booking.php
if ($cottageCount == 0) {
    // Don't auto-insert, require setup_database.php to run first
}

// Option 2: Redirect to setup page if tables are empty
if ($cottageCount == 0 && $roomCount == 0) {
    header('Location: setup_database.php');
    exit;
}
```

---

## 7. Implementation Verification Checklist

After applying fixes, verify:

- [ ] Run `setup_database.php` to create cottages table
- [ ] Run `setup_reservation_limits.php` to create limits
- [ ] Query cottages table - verify names
- [ ] Query reservation_limits table - verify names match
- [ ] Access booking page and select a date
- [ ] Verify each cottage shows correct availability (not 0)
- [ ] Make a test reservation
- [ ] Verify that reserved items don't show as available on that date

---

## 8. Testing Queries

```sql
-- Check cottages
SELECT id, name, capacity FROM cottages ORDER BY id;

-- Check reservation limits
SELECT item_type, item_name, daily_limit FROM reservation_limits WHERE item_type = 'cottage';

-- Check for mismatches
SELECT c.name FROM cottages c 
LEFT JOIN reservation_limits rl ON c.name = rl.item_name
WHERE rl.id IS NULL AND c.name LIKE '%Cottage%';

-- Check reservations
SELECT ri.item_name, COUNT(*) as reserved_count, r.check_in, r.check_out, r.status
FROM reservation_items ri
JOIN reservations r ON ri.reservation_id = r.id
WHERE ri.item_type = 'cottage'
GROUP BY ri.item_name, r.check_in, r.check_out, r.status;
```

---

## 9. Root Cause Summary

| Issue | Cause | Impact |
|-------|-------|--------|
| Cottage names differ | Multiple files define cottages differently | Availability lookup fails to find matching names |
| Display defaults to 0 | `availabilityInfo = ['available' => 0]` when not found | Shows "0 available" for all cottages |
| SQL math in capacity | `8-12` parsed as expression not text | Negative capacity values (wrong but not shown in UI) |
| Dynamic vs static inserts | booking.php runs every page load | Can overwrite setup data if cottage table empty |

**PRIMARY CAUSE**: **Cottage name mismatch between `cottages` table and `reservation_limits` table** leads to failed availability lookups, which default to showing "0 available".
