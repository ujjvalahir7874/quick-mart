# Delivery Partner System

## Overview
This folder contains the **Delivery Partner Web Portal**, designed for mobile use by delivery agents.

## Features
- **Login/Register**: Secure signup with Mobile Number and Password.
- **Dashboard**: View assigned and active orders.
- **Live Tracking**: Automatically updates location (Latitude/Longitude) to the server every 10 seconds.
- **Navigation**: One-click Google Maps navigation to customer address.
- **Earnings**: Track earnings per delivery (Current logic: ₹50 per delivered order).

## How to Use
1. **Access**: Go to `http://localhost/major/delivery/` on your mobile phone (connected to same network) or simulator.
   - Note: For Geolocation to work on mobile, you might need HTTPS. On localhost (desktop), it works fine.
2. **Register**: creates a new account.
3. **Admin Approval**: Currently, newly registered partners are 'Offline'. Admin can change status in Admin Panel -> Delivery Staff.
4. **Receiving Orders**:
   - Go to Admin Panel -> Orders.
   - Click "Assign Staff" on any pending order.
   - Select the delivery partner.
   - The partner will see the order appear on their Dashboard.

## Files
- `index.php`: Main Dashboard.
- `login.php` / `register.php`: Auth.
- `api/update_location.php`: Endpoint receiving GPS coordinates.

## Admin Tracking
- Go to Admin Panel -> Delivery Staff -> **Live Tracking**.
- You will see real-time location markers of all active partners.
