<?php

namespace Database\Seeders;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Database\Seeder;

class SupportSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()->take(5)->get();

        foreach ($users as $user) {

            $ticket = Ticket::create([
                'user_id' => $user->id,
                'subject' => 'Question sur ma commande',
                'status' => 'open',
            ]);

            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_type' => 'user',
                'sender_id' => $user->id,
                'message' => 'Bonjour, j’ai une question concernant ma commande.',
            ]);
        }
    }
}

