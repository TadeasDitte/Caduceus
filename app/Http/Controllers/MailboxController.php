<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MailboxController extends Controller
{
    public function store(StoreMailboxRequest $request, MailboxService $mailboxService)
    {
        $mailbox = $mailboxService->create(
            user: $request->user(),
            domainId: $request->validated('domain_id'),
            localPart: $request->validated('local_part'),
        );

        return redirect()->route('mailboxes.index')->with('status', 'Mailbox created.');
    }
}
