<?php

namespace App\Http\Controllers;

use App\Actions\SendContactMessage;
use App\Http\Requests\ContactMessageRequest;
use Illuminate\Http\RedirectResponse;

class ContactFormController extends Controller
{
    public function store(ContactMessageRequest $request, SendContactMessage $action): RedirectResponse
    {
        $action->handle(
            $request->string('name')->toString(),
            $request->string('email')->toString(),
            $request->string('topic')->toString(),
            $request->string('message')->toString(),
        );

        return back()->with('status', "Message sent. We'll get back to you soon.");
    }
}
