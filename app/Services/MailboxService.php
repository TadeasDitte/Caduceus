<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MailboxService
{
    public function create(User $user, int $domainId, string $localPart): Mailbox
    {
        return DB::transaction(function () use ($user, $domainId, $localPart) {
            $address = Address::create([
                'domain_id' => $domainId,
                'local_part' => $localPart,
                'type' => 'mailbox',
            ]);

            return Mailbox::create([
                'address_id' => $address->id,
                'user_id' => $user->id,
                'password' => Hash::make(Str::random(24)),
            ]);
        });
    }
}
