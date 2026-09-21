<?php

namespace App\Actions;

use App\Mail\ContactFormMail;
use Illuminate\Support\Facades\Mail;

class SendContactMessage
{
    public function handle(string $name, string $email, string $topic, string $body): void
    {
        Mail::to('support@eveil.cloud')->send(
            new ContactFormMail($name, $email, $topic, $body),
        );
    }
}
