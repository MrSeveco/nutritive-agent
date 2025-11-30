<?php

namespace App\Http\Controllers;

use App\Mail\AppointmentNotificationMail;
use App\Models\Appointment;
use App\Models\User;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use App\Services\DoctorShiftAllocator;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class AppointmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Filtrar solo las citas del doctor logueado
        return Inertia::render('Appointments/Index', [
            'appointments' => Appointment::with('user')
                ->where('user_id', auth()->id())
                ->orderBy('appointment_date', 'desc')
                ->get()
        ]);
    }

    /**
     * Display the calendar view for appointments.
     */
    public function calendar()
    {
        $daysAvailable = $this->getAvailableDays();
        $specialtyColumn = $this->getSpecialtyColumn();
        $referenceDate = Carbon::now('America/Bogota');

        $doctors = User::query()
            ->select('id', 'name', $specialtyColumn . ' as speciality')
            ->whereIn('role', ['doctor', 'doctor_s'])
            ->orderBy('speciality')
            ->orderBy('name')
            ->get()
            ->map(function ($doctor) use ($referenceDate) {
                $shift = DoctorShiftAllocator::getShiftForDate($doctor->id, $referenceDate->copy());

                $doctor->shift = $shift ? [
                    'start' => $shift['start']->format('H:i'),
                    'end' => $shift['end']->format('H:i'),
                ] : null;

                return $doctor;
            });

        $doctorsBySpecialty = $doctors
            ->groupBy('speciality')
            ->map(function ($group) {
                return $group->values();
            })
            ->toArray();

        return Inertia::render('Appointments/Calendar', [
            'daysAvailable' => $daysAvailable,
            // CORRECCIÓN 1: Forzar (int) para evitar el error de prop en Vue
            'appointmentDuration' => (int) env('APPOINTMENT_DURATION_MINUTES', 20),
            'doctors' => $doctors->values()->toArray(),
            'doctorsBySpecialty' => $doctorsBySpecialty,
        ]);
    }

    /**
     * Get available appointment slots for the calendar.
     */
    public function getAvailableSlots(Request $request)
    {
        $validated = $request->validate([
            'start' => 'required|date',
            'end' => 'required|date|after_or_equal:start',
            'user_id' => 'required|exists:users,id',
        ]);

        $userId = (int) $validated['user_id'];

        // CORRECCIÓN 3: Try-catch para capturar el error 500 y mostrarlo
        try {
            $start = Carbon::parse($validated['start'], 'UTC')->setTimezone('America/Bogota');
            $end = Carbon::parse($validated['end'], 'UTC')->setTimezone('America/Bogota');

            $allSlots = $this->generateAllSlotsWithStatus($start, $end, $userId);

            return response()->json($allSlots);
        } catch (\Exception $e) {
            Log::error('Error generando slots: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Generate all time slots and determine their status (available, booked, etc.).
     */
    private function generateAllSlotsWithStatus(Carbon $start, Carbon $end, int $userId)
    {
        // LOG 1: Ver qué rango de fechas llega
        Log::info("--- GENERANDO SLOTS ---");
        Log::info("Rango: " . $start->toDateTimeString() . " hasta " . $end->toDateTimeString());

        $slots = [];
        $daysAvailable = $this->getAvailableDays();
        // CORRECCIÓN 1: Forzar (int) aquí también para sumar minutos correctamente
        $duration = (int) env('APPOINTMENT_DURATION_MINUTES', 20);

        // LOG 2: Ver qué días están configurados en el .env
        Log::info("Días disponibles config: " . implode(', ', $daysAvailable));

        $bookedAppointments = Appointment::with('user')
            ->where('user_id', $userId)
            ->where('status', '!=', 'rejected')
            ->whereRaw("appointment_date >= ? AND appointment_date <= ?", [
                $start->format('Y-m-d 00:00:00'),
                $end->format('Y-m-d 23:59:59')
            ])
            ->get()
            ->mapWithKeys(function ($appointment) {
                return [$appointment->appointment_date->format('Y-m-d H:i:s') => $appointment];
            });

        // LOG 3: Cuántas citas encontró en la BD
        Log::info("Citas encontradas en BD: " . $bookedAppointments->count());

        $currentDate = $start->copy()->startOfDay();

        while ($currentDate->lte($end)) {
            $dayNameEnglish = $currentDate->format('l');
            $dayNameLocal = $currentDate->translatedFormat('l');

            // LOG 4: Ver qué día está analizando el bucle
            Log::info("Analizando día: " . $currentDate->format('Y-m-d') . " ($dayNameEnglish / $dayNameLocal)");

            $daysAvailableLower = array_map('strtolower', $daysAvailable);

            if (in_array(strtolower($dayNameEnglish), $daysAvailableLower) ||
                in_array(strtolower($dayNameLocal), $daysAvailableLower)) {

                // LOG 5: ¡Éxito! Entró al IF del día
                Log::info("-> El día es válido. Generando horas...");

                $shift = DoctorShiftAllocator::getShiftForDate($userId, $currentDate);

                if (!$shift) {
                    $currentDate->addDay();
                    continue;
                }

                $slotTime = $shift['start']->copy();
                $endOfDay = $shift['end']->copy();

                while ($slotTime->lt($endOfDay)) {
                    $slotTimeFormatted = $slotTime->format('Y-m-d H:i:s');
                    $endTime = $slotTime->copy()->addMinutes($duration);

                    // 3. Verificación directa en el array en memoria
                    if ($bookedAppointments->has($slotTimeFormatted)) {
                        $appointment = $bookedAppointments->get($slotTimeFormatted);
                        $slots[] = [
                            'id' => $appointment->id,
                            'title' => 'Reservado - ' . $appointment->user->name,
                            'start' => $slotTimeFormatted,
                            'end' => $endTime->format('Y-m-d H:i:s'),
                            'extendedProps' => [
                                'type' => 'booked',
                                'userId' => $appointment->user_id,
                                'status' => $appointment->status,
                                    'doctorId' => $userId,
                            ],
                        ];
                    } else {
                        if ($slotTime->isFuture()) {
                            $slots[] = [
                                'title' => 'Disponible',
                                'start' => $slotTimeFormatted,
                                'end' => $endTime->format('Y-m-d H:i:s'),
                                'extendedProps' => [
                                    'type' => 'available',
                                    'doctorId' => $userId,
                                ],
                            ];
                        }
                    }

                    $slotTime->addMinutes($duration);
                }
            } else {
                // LOG 6: Falló la validación del día
                Log::info("-> Día NO válido o no configurado.");
            }

            $currentDate->addDay();
        }

        Log::info("Total slots generados: " . count($slots));
        return $slots;
    }

    /**
     * Generate available time slots based on configuration.
     */
    private function generateAvailableSlots(Carbon $start, Carbon $end)
{
    $slots = [];
    $daysAvailable = $this->getAvailableDays();
    $duration = env('APPOINTMENT_DURATION_MINUTES', 20);

    $businessHoursStart = 8;
    $businessHoursEnd = 18;

    $currentDate = $start->copy()->startOfDay();

    while ($currentDate->lte($end)) {
        $dayName = $currentDate->format('l');

        if (in_array($dayName, $daysAvailable)) {

            $slotTime = $currentDate->copy()->setTime($businessHoursStart, 0);
            $endOfDay = $currentDate->copy()->setTime($businessHoursEnd, 0);

            while ($slotTime->lt($endOfDay)) {

                $isBooked = Appointment::where('appointment_date', $slotTime->format('Y-m-d H:i:s'))
                    ->exists();

                if (!$isBooked && $slotTime->isFuture()) {
                    $slots[] = [
                        'title' => 'Available',
                        'start' => $slotTime->format('Y-m-d H:i:s'),
                        'end' => $slotTime->copy()->addMinutes($duration)->format('Y-m-d H:i:s'),
                        'backgroundColor' => '#10b981',
                        'borderColor' => '#059669',
                        'classNames' => ['available-slot'],
                        'extendedProps' => [
                            'type' => 'available',
                        ],
                    ];
                }

                $slotTime->addMinutes($duration);
            }
        }

        $currentDate->addDay();
    }

    return $slots;
}



    /**
     * Get booked appointments for the calendar.
     */
    private function getBookedAppointments(Carbon $start, Carbon $end)
{
    $appointments = Appointment::with('user')
        ->whereBetween('appointment_date', [$start, $end])
        ->get();

    $duration = env('APPOINTMENT_DURATION_MINUTES', 20);

    return $appointments->map(function ($appointment) use ($duration) {
        $startTime = Carbon::parse($appointment->appointment_date);

        return [
            'id' => $appointment->id,
            'title' => 'Booked - ' . $appointment->user->name,
            'start' => $startTime->format('Y-m-d H:i:s'),
            'end' => $startTime->copy()->addMinutes($duration)->format('Y-m-d H:i:s'),
            'backgroundColor' => '#ef4444',
            'borderColor' => '#dc2626',
            'extendedProps' => [
                'type' => 'booked',
                'userId' => $appointment->user_id,
                'status' => $appointment->status,
            ],
        ];
    })->toArray();
}



    /**
     * Get the list of available days from environment configuration.
     */
    private function getAvailableDays()
    {
        $daysString = env('DAYS_AVAILABLE', '{Monday,Tuesday,Wednesday,Thursday,Friday}');
        // Remove curly braces and split by comma
        $daysString = trim($daysString, '{}');
        return array_map('trim', explode(',', $daysString));
    }

    /**
     * Determine which specialty column exists in the users table.
     */
    private function getSpecialtyColumn(): string
    {
        static $column = null;

        if ($column === null) {
            $column = Schema::hasColumn('users', 'speciality') ? 'speciality' : 'specialty';
        }

        return $column;
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Appointments/Create', [
            'daysAvailable' => $this->getAvailableDays(),
            'appointmentDuration' => env('APPOINTMENT_DURATION_MINUTES', 20),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAppointmentRequest $request)
    {
        try {
            $appointment = Appointment::create($request->validated());
            $this->sendNotification($appointment, 'created');

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Cita creada exitosamente',
                    'appointment' => $appointment->load('user'),
                ], Response::HTTP_CREATED);
            }
            return redirect()->route('appointments.calendar')
                ->with('success', 'Cita creada exitosamente');

        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al crear la cita: ' . $e->getMessage(),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return back()->withErrors(['error' => 'Error al crear la cita']);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Appointment $appointment)
    {
        if ($appointment->user_id !== auth()->id()) {
            abort(403);
        }

        return Inertia::render('Appointments/View', [
            'appointment' => $appointment->load('user'),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Appointment $appointment)
    {
        return Inertia::render('Appointments/Edit', [
            'appointment' => $appointment->load('user'),
            'daysAvailable' => $this->getAvailableDays(),
            'appointmentDuration' => env('APPOINTMENT_DURATION_MINUTES', 20),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAppointmentRequest $request, Appointment $appointment)
    {
        try {
            $appointment->update($request->validated());

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Cita actualizada exitosamente',
                    'appointment' => $appointment->load('user'),
                ], Response::HTTP_OK);
            }

            return redirect()->route('appointments.index')
                ->with('success', 'Cita actualizada exitosamente');

        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al actualizar la cita: ' . $e->getMessage(),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return back()->withErrors(['error' => 'Error al actualizar la cita']);
        }
    }

    /**
     * Cancel an appointment (delete the record).
     */
    public function cancel(Request $request, Appointment $appointment)
    {
        $this->authorizeDoctor($appointment);

        try {
            $appointment->update(['status' => 'canceled']);
            $appointment->refresh();
            $this->sendNotification($appointment, 'canceled');
            return $this->respondSuccess($request, 'Cita cancelada exitosamente', $appointment);

        } catch (\Exception $e) {
            return $this->respondError($request, 'Error al cancelar la cita: ' . $e->getMessage());
        }
    }

    /**
     * Reject an appointment by changing status to rejected.
     */
    public function reject(Request $request, Appointment $appointment)
    {
        $this->authorizeDoctor($appointment);

        try {
            $appointment->update(['status' => 'rejected']);
            $appointment->refresh();
            $this->sendNotification($appointment, 'rejected');
            return $this->respondSuccess($request, 'Cita rechazada exitosamente', $appointment);

        } catch (\Exception $e) {
            return $this->respondError($request, 'Error al rechazar la cita: ' . $e->getMessage());
        }
    }

    /**
     * Confirm an appointment by changing status to confirmed.
     */
    public function confirm(Request $request, Appointment $appointment)
    {
        $this->authorizeDoctor($appointment);

        try {
            $appointment->update(['status' => 'confirmed']);
            $appointment->refresh();
            $this->sendNotification($appointment, 'confirmed');
            return $this->respondSuccess($request, 'Cita confirmada exitosamente', $appointment);

        } catch (\Exception $e) {
            return $this->respondError($request, 'Error al confirmar la cita: ' . $e->getMessage());
        }
    }

    private function authorizeDoctor(Appointment $appointment): void
    {
        if (auth()->guest() || $appointment->user_id !== auth()->id()) {
            abort(403);
        }
    }

    private function sendNotification(Appointment $appointment, string $eventType): void
    {
        if (!$appointment->patient_email) {
            return;
        }

        Mail::to($appointment->patient_email)
            ->send(new AppointmentNotificationMail($appointment, $eventType));
    }

    private function respondSuccess(Request $request, string $message, Appointment $appointment)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'appointment' => $appointment,
            ], Response::HTTP_OK);
        }

        return redirect()->back()->with('success', $message);
    }

    private function respondError(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return redirect()->back()->withErrors(['error' => $message]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Appointment $appointment)
    {
        try {
            $appointment->delete();

            return redirect()->route('appointments.index')
                ->with('success', 'Cita eliminada exitosamente');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al eliminar la cita']);
        }
    }
}
