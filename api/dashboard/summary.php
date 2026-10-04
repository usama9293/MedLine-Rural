<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/response.php';

requireRole('Admin', 'Dispatcher');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(
        false,
        'Method Not Allowed',
        null,
        405
    );
}

try {

    /*
    |--------------------------------------------------------------------------
    | REQUEST SUMMARY
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT
            COUNT(*) AS total,
            SUM(status = 'Pending') AS pending,
            SUM(status = 'Approved') AS approved,
            SUM(status = 'Completed') AS completed
        FROM supply_requests
    ");

    $requests = $stmt->fetch();


    /*
    |--------------------------------------------------------------------------
    | SHIPMENT SUMMARY
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT
            COUNT(*) AS total,
            SUM(status = 'Pending') AS pending,
            SUM(status = 'Dispatched') AS dispatched,
            SUM(status = 'In Transit') AS in_transit,
            SUM(status = 'Delivered') AS delivered
        FROM shipments
    ");

    $shipments = $stmt->fetch();


    /*
    |--------------------------------------------------------------------------
    | ALERT SUMMARY
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT
            COUNT(*) AS total,
            SUM(status = 'Open') AS open,
            SUM(status = 'Acknowledged') AS acknowledged,
            SUM(status = 'Resolved') AS resolved
        FROM telemetry_alerts
    ");

    $alerts = $stmt->fetch();


    /*
    |--------------------------------------------------------------------------
    | VEHICLE SUMMARY
    |--------------------------------------------------------------------------
    |
    | Vehicle statuses used by the system:
    |
    | Available
    | Inactive
    |
    */

    $stmt = $pdo->query("
        SELECT
            COUNT(*) AS total,
            SUM(status = 'Available') AS available,
            SUM(status = 'Inactive') AS inactive
        FROM vehicles
    ");

    $vehicles = $stmt->fetch();


    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    jsonResponse(
        true,
        'Dashboard summary retrieved successfully.',
        [
            'requests' => [
                'total' => (int) $requests['total'],
                'pending' => (int) $requests['pending'],
                'approved' => (int) $requests['approved'],
                'completed' => (int) $requests['completed']
            ],

            'shipments' => [
                'total' => (int) $shipments['total'],
                'pending' => (int) $shipments['pending'],
                'dispatched' => (int) $shipments['dispatched'],
                'in_transit' => (int) $shipments['in_transit'],
                'delivered' => (int) $shipments['delivered']
            ],

            'alerts' => [
                'total' => (int) $alerts['total'],
                'open' => (int) $alerts['open'],
                'acknowledged' => (int) $alerts['acknowledged'],
                'resolved' => (int) $alerts['resolved']
            ],

            'vehicles' => [
                'total' => (int) $vehicles['total'],
                'available' => (int) $vehicles['available'],
                'inactive' => (int) $vehicles['inactive']
            ]
        ]
    );

} catch (PDOException $e) {

    error_log($e->getMessage());

    jsonResponse(
        false,
        'Unable to retrieve dashboard summary.',
        null,
        500
    );
}