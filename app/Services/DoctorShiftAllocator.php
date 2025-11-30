<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class DoctorShiftAllocator
{
    private const PATTERN_FULL = [
        ['08:00', '18:00'],
    ];

    private const PATTERN_TWO = [
        ['08:00', '13:00'],
        ['13:00', '18:00'],
    ];

    private const PATTERN_ROTATING = [
        ['08:00', '12:00'],
        ['11:00', '15:00'],
        ['14:00', '18:00'],
    ];

    private static array $cache = [];

    public static function getShiftForDate(int $doctorId, Carbon $date): ?array
    {
        $definition = self::resolveShift($doctorId);

        if (!$definition) {
            return null;
        }

        return [
            'start' => $date->copy()->setTimeFromTimeString($definition['start']),
            'end' => $date->copy()->setTimeFromTimeString($definition['end']),
        ];
    }

    public static function isWithinShift(int $doctorId, Carbon $dateTime): bool
    {
        $shift = self::getShiftForDate($doctorId, $dateTime->copy());

        if (!$shift) {
            return false;
        }

        return $dateTime->greaterThanOrEqualTo($shift['start']) && $dateTime->lt($shift['end']);
    }

    private static function resolveShift(int $doctorId): ?array
    {
        if (isset(self::$cache[$doctorId])) {
            return self::$cache[$doctorId];
        }

        $specialtyColumn = Schema::hasColumn('users', 'speciality') ? 'speciality' : 'specialty';

        $doctor = User::query()
            ->select('id', $specialtyColumn . ' as speciality')
            ->find($doctorId);

        if (!$doctor || !$doctor->speciality) {
            return null;
        }

        $doctors = User::query()
            ->select('id')
            ->where($specialtyColumn, $doctor->speciality)
            ->whereIn('role', ['doctor', 'doctor_s'])
            ->orderBy('id')
            ->get()
            ->pluck('id')
            ->values();

        $index = $doctors->search($doctorId);
        $count = $doctors->count();

        if ($index === false) {
            return null;
        }

        if ($count === 1) {
            $definition = self::PATTERN_FULL[0];
        } elseif ($count === 2) {
            $definition = self::PATTERN_TWO[$index];
        } else {
            $definition = self::PATTERN_ROTATING[$index % 3];
        }

        return self::$cache[$doctorId] = [
            'start' => $definition[0],
            'end' => $definition[1],
        ];
    }
}
