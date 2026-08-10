<?php

namespace App\Notifications;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Dispatched by ApplicationObserver whenever an application changes state.
 * Queued so a slow mail server never blocks an admin's decision request.
 */
class ApplicationStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Application $application,
        public readonly ?ApplicationStatus $previousStatus = null,
    ) {}

    public function via(object $notifiable): array
    {
        // 'database' would require the notifications table; mail keeps the
        // dependency surface small for this build.
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $status = $this->application->status;

        $message = (new MailMessage())
            ->subject("AidBridge: your application is now {$status->label()}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your application for \"{$this->application->aidProgram->title}\" is now **{$status->label()}**.")
            ->line("Reference: {$this->application->reference}");

        $message = match ($status) {
            ApplicationStatus::Approved => $message
                ->line('Your aid has been approved. Payment will be scheduled shortly.')
                ->action('View your application', route('applications.show', $this->application)),
            ApplicationStatus::Rejected => $message
                ->line('Unfortunately this application was not successful.')
                ->line($this->application->notes ? "Reviewer note: {$this->application->notes}" : '')
                ->action('View details', route('applications.show', $this->application)),
            default => $message->action('Track your application', route('applications.show', $this->application)),
        };

        return $message->line('Thank you for using AidBridge.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'reference' => $this->application->reference,
            'status' => $this->application->status->value,
            'previous_status' => $this->previousStatus?->value,
        ];
    }
}
