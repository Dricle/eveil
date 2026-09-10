<?php

use App\Http\Resources\ReplyResource;
use App\Models\Message;

it('does not crash when the lead relation is missing', function () {
    $message = Message::factory()->make();
    $message->setRelation('lead', null);

    $data = (new ReplyResource($message))->toArray(request());

    expect($data['lead']['name'])->toBeNull()
        ->and($data['lead']['company'])->toBeNull();
});
