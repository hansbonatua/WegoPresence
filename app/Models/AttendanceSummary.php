<?php

namespace App\Models;

/**
 * Marker class for the simulated attendance summary report.
 *
 * The summary is calculated on demand from the attendance, permission,
 * leave and sick leave tables, so it has no table or Eloquent instance.
 */
class AttendanceSummary {}
