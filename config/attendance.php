<?php

return [

    // Require GPS coordinates within a branch geofence to clock in/out.
    // Tenants override via settings.attendance.geofence_required.
    'geofence_required' => (bool) env('ATTENDANCE_GEOFENCE_REQUIRED', false),

    'default_geofence_radius_meters' => (int) env('ATTENDANCE_GEOFENCE_RADIUS', 200),

    // Allow more than one clock-in per employee per day.
    'allow_multiple_clock_ins' => false,

    // Safety cap: an open attendance older than this is auto-closed by the
    // scheduler at the clock-in + this many hours.
    'auto_clock_out_hours' => (int) env('ATTENDANCE_AUTO_CLOCKOUT_HOURS', 16),

];
