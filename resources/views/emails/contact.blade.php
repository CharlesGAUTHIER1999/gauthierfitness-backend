<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Nouveau message de contact</title>
</head>
<body style="font-family: Arial, sans-serif; color: #222; line-height: 1.6;">
<h2 style="margin-bottom: 4px;">Nouveau message de contact</h2>
<p style="color: #888; margin-top: 0;">Reçu depuis gauthierfitness.fr</p>

<table style="border-collapse: collapse; margin: 16px 0;">
    <tr>
        <td style="font-weight: bold;">Nom</td>
        <td>{{ $data['name'] }}</td>
    </tr>
    <tr>
        <td style="font-weight: bold;">Email</td>
        <td>{{ $data['email'] }}</td>
    </tr>
    <tr>
        <td style="font-weight: bold;">Sujet</td>
        <td>{{ $data['subject'] ?? '—' }}</td>
    </tr>
</table>

<div style="border-top: 1px solid #e5e5e5; padding-top: 12px;">
    {!! nl2br(e($data['message'])) !!}
</div>
</body>
</html>