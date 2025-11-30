<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class AppointmentNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Appointment $appointment,
        public string $eventType
    ) {
        $this->appointment->loadMissing('user');
    }

    /**
     * Build the message.
     */
    public function build(): self
    {
        $payload = $this->buildPayload();

        return $this
            ->subject($payload['subject'])
            ->view('emails.appointments.notification')
            ->with($payload['data']);
    }

    private function buildPayload(): array
    {
        $appointment = $this->appointment;
        $doctorName = $appointment->user?->name ?? 'tu especialista';
        $localizedDate = Carbon::parse($appointment->appointment_date)
            ->timezone('America/Bogota')
            ->locale('es')
            ->isoFormat('dddd D [de] MMMM [de] YYYY [a las] hh:mm A');

        $messages = [
            'created' => [
                'subject' => 'Tu cita fue registrada',
                'intro' => 'Hemos recibido tu solicitud de cita.',
                'outro' => 'Si necesitas realizar cambios responde a este correo.',
            ],
            'confirmed' => [
                'subject' => 'Tu cita fue confirmada',
                'intro' => 'Tu médico ha confirmado la cita programada.',
                'outro' => 'Te esperamos en la fecha acordada.',
            ],
            'canceled' => [
                'subject' => 'Tu cita fue cancelada',
                'intro' => 'Tu cita fue cancelada por el consultorio.',
                'outro' => 'Te invitamos a reagendar tu cita cuando lo requieras.',
            ],
            'rejected' => [
                'subject' => 'Tu cita fue rechazada',
                'intro' => 'Tu médico no pudo aceptar la cita solicitada.',
                'outro' => 'El espacio quedó disponible para que puedas elegir otro horario.',
            ],
        ];

        $copy = $messages[$this->eventType] ?? $messages['created'];

        return [
            'subject' => $copy['subject'],
            'data' => [
                'intro' => $copy['intro'],
                'outro' => $copy['outro'],
                'patientName' => $appointment->patient_name,
                'doctorName' => $doctorName,
                'statusText' => $this->mapStatusLabel($appointment->status),
                'appointmentDate' => $localizedDate,
            ],
        ];
    }

    private function mapStatusLabel(string $status): string
    {
        return [
            'scheduled' => 'Programada',
            'confirmed' => 'Confirmada',
            'canceled' => 'Cancelada',
            'rejected' => 'Rechazada',
            'completed' => 'Completada',
        ][$status] ?? ucfirst($status);
    }
}
