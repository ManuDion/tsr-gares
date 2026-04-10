<?php

namespace App\Notifications;

use App\Models\DailyControl;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DailyControlAlertNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $concernedDate,
        protected int $anomalyCount,
        protected array $gares
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Alerte de contrôle journalier TSR')
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line("Le contrôle journalier du {$this->concernedDate} a détecté {$this->anomalyCount} gare(s) en anomalie.")
            ->line('Gares concernées : '.implode(', ', $this->gares))
            ->action('Ouvrir le dashboard', url('/dashboard'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'daily_control_alert',
            'concerned_date' => $this->concernedDate,
            'anomaly_count' => $this->anomalyCount,
            'gares' => $this->gares,
        ];
    }
}
