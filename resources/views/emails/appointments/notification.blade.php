<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $intro }}</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            background-color: #f4f4f5;
            color: #111827;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 24px;
        }
        .card {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 32px;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
        }
        .btn {
            display: inline-block;
            margin-top: 16px;
            padding: 12px 24px;
            border-radius: 9999px;
            background-color: #059669;
            color: #ffffff;
            text-decoration: none;
            font-weight: bold;
        }
        .meta {
            margin-top: 24px;
            border-top: 1px solid #e5e7eb;
            padding-top: 16px;
            font-size: 14px;
            color: #374151;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <p>Hola {{ $patientName }},</p>
        <p>{{ $intro }}</p>

        <p><strong>Médico:</strong> {{ $doctorName }}</p>
        <p><strong>Estado:</strong> {{ $statusText }}</p>
        <p><strong>Fecha y hora:</strong> {{ $appointmentDate }}</p>

        <p class="meta">{{ $outro }}</p>
        <p class="meta">Este es un mensaje automático, por favor no lo respondas.</p>
    </div>
</div>
</body>
</html>
