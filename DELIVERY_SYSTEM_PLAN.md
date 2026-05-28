# Delivery Partner System Implementation Plan

## 1. Overview
We will implement a complete Delivery Partner Ecosystem within the existing PHP project. This includes a mobile-responsive Web Portal for delivery partners and an enhanced Admin Panel for real-time tracking and management.

## 2. Database Enhancements
We need to update the `delivery_persons` table and create new tables for tracking and earnings.

### Updates to `delivery_persons`
- `password_hash`: For login authentication.
- `otp`: For login verification.
- `documents`: JSON or Text field to store paths of uploaded ID proofs.
- `current_lat`, `current_lng`: For real-time location.
- `wallet_balance`: To store current earnings.
- `is_verified`: Boolean to check if admin approved documents.
- `fcm_token`: For Push Notifications (Firebase).

### New Table: `delivery_earnings`
- `id`, `delivery_person_id`, `order_id`, `amount`, `type` (Credit/Debit), `description`, `created_at`.

### New Table: `delivery_location_logs` (Optional for history)
- `id`, `delivery_person_id`, `lat`, `lng`, `timestamp`.

## 3. Delivery Partner Web App (`/delivery`)
A Mobile-First Web Application (PWA style) located in `c:\xampp\htdocs\major\delivery`.

- **Login (`login.php`)**: Mobile Number based login.
- **Registration (`register.php`)**: Upload Name, Vehicle Info, Documents.
- **Dashboard (`index.php`)**:
  - Toggle Online/Offline.
  - View "New Orders" (assigned but not accepted).
  - View "Active Orders".
- **Order View (`order.php`)**:
  - Customer Details.
  - "Navigate" button (opens Google Maps).
  - Status Buttons: "Picked Up", "Delivered".
- **Wallet (`wallet.php`)**: View daily/weekly earnings.

## 4. Admin Panel Enhancements (`/admin`)
- **Delivery Persons**:
  - Add "Documents Verification" status.
  - Add "Live Map View" to see all active partners.
- **Orders**:
  - Auto-assign logic (optional, initially manual).

## 5. Technology Stack
- **Frontend**: HTML5, Vanilla CSS (Mobile Responsive), Google Maps JS API (or Leaflet/OpenStreetMap for free alternative).
- **Backend**: Native PHP (consistent with existing project).
- **Database**: MySQL.
- **Real-time**: Ajax Polling (simplest for PHP) or basic WebSockets if supported.

## 6. Integrations
- **Maps**: Leaflet.js (Free) or Google Maps API.
- **Notifications**: Firebase Cloud Messaging (FCM) via JS.

---
**Next Steps**:
1. Execute Database Migration script.
2. Scaffolding the `/delivery` directory.
