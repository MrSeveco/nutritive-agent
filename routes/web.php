<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\AppointmentController;
use App\Models\Appointment;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('SFHJTUFS'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Rutas de registro encriptadas/secretas
Route::get('/SFHJTUFS', [\Laravel\Fortify\Http\Controllers\RegisteredUserController::class, 'create'])
    ->middleware(['guest:'.config('fortify.guard')])
    ->name('SFHJTUFS.view');

Route::post('/SFHJTUFS', [\Laravel\Fortify\Http\Controllers\RegisteredUserController::class, 'store'])
    ->middleware(['guest:'.config('fortify.guard')])
    ->name('SFHJTUFS');

// Rutas públicas para pacientes (sin autenticación)
Route::get('/appointments/select-doctor', [AppointmentController::class, 'selectDoctor'])->name('appointments.select-doctor');
Route::get('/appointments/calendar', [AppointmentController::class, 'calendar'])->name('appointments.calendar');
Route::get('/appointments/available-slots', [AppointmentController::class, 'getAvailableSlots'])->name('appointments.available-slots');

// Rutas protegidas (solo para doctores autenticados)
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/home', function () {
        $user = auth()->user();

        $currentMonthAppointments = Appointment::query()
            ->where('user_id', $user->id)
            ->whereBetween('appointment_date', [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ])->count();

        $completedAppointments = Appointment::query()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->count();

        $nextAppointment = Appointment::query()
            ->where('user_id', $user->id)
            ->where('appointment_date', '>=', now())
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->orderBy('appointment_date')
            ->first();

        return Inertia::render('Dashboard', [
            'stats' => [
                'currentMonthAppointments' => $currentMonthAppointments,
                'completedAppointments' => $completedAppointments,
                'nextAppointment' => $nextAppointment ? [
                    'id' => $nextAppointment->id,
                    'patient_name' => $nextAppointment->patient_name,
                    'appointment_date' => $nextAppointment->appointment_date,
                    'status' => $nextAppointment->status,
                ] : null,
            ],
        ]);
    })->name('home');

    // Gestión de citas del doctor
    Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
    Route::get('/appointments/create', [AppointmentController::class, 'create'])->name('appointments.create');
    Route::post('/appointments/create', [AppointmentController::class, 'storeDoctor'])->name('appointments.storeDoctor');
    Route::get('/appointments/{appointment}', [AppointmentController::class, 'show'])->name('appointments.show');

    // Acciones sobre citas (cancelar, rechazar, confirmar)
    Route::patch('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])->name('appointments.cancel');
    Route::patch('/appointments/{appointment}/reject', [AppointmentController::class, 'reject'])->name('appointments.reject');
    Route::patch('/appointments/{appointment}/confirm', [AppointmentController::class, 'confirm'])->name('appointments.confirm');
});
