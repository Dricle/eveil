<?php

namespace App\Http\Controllers;

use App\Actions\InboxFolders;
use App\Http\Resources\ConversationResource;
use App\Models\Campaign;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Everyone who actually answered, across every mailbox this project sends
 * from, filed into one folder per status plus `sent` - a mail client's
 * mailbox list, not a single "here is everything" feed.
 *
 * `Replied` is the default folder and the one a fresh answer lands in.
 * Everything from there is the user filing a conversation somewhere once
 * they have decided what it is - won, lost, already a client, not a fit, or
 * opted out - which is what keeps `Replied` itself from filling up with
 * every conversation ever answered.
 *
 * `sent` answers a different question, "did anything actually go out and
 * what did it say", not filtered by status: a mail nobody answered belongs
 * here regardless of where its lead currently stands. See `InboxFolders` for
 * the query both this screen's list and its folder counts share.
 */
class InboxController extends Controller
{
    public function __construct(private InboxFolders $folders) {}

    public function index(Request $request, ?string $folder = null): Response
    {
        $folder ??= 'replied';

        abort_unless(in_array($folder, InboxFolders::FOLDERS, true), 404);

        $conversations = $this->folders->query($request, $folder)
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Inbox', [
            'conversations' => ConversationResource::collection($conversations),
            'campaigns' => Campaign::query()->orderBy('name')->get(['id', 'name']),
            // Every folder's shape, always: the strip has to say whether a
            // folder the user is not currently on holds anything, or
            // switching to it reads as the screen being broken rather than
            // empty.
            'folders' => $this->folders->summaries($request),
            'filters' => [
                'campaign' => $request->integer('campaign') ?: null,
                'folder' => $folder,
            ],
        ]);
    }
}
