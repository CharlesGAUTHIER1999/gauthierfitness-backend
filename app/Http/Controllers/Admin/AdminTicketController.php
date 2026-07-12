<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'Admin - Support', weight: 12)]
class AdminTicketController extends Controller
{
    /**
     * Paginated list of support tickets (admin).
     *
     * @queryParam status string Filter by status: open, pending, closed.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Ticket::with(['user:id,firstname,lastname,email'])
            ->withCount('messages')
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return response()->json($query->paginate(20));
    }

    /** Ticket detail with its messages (admin). */
    public function show(Ticket $ticket): JsonResponse
    {
        $ticket->load([
            'user:id,firstname,lastname,email',
            'messages' => fn ($q) => $q->orderBy('created_at'),
            'messages.sender:id,firstname,lastname,email',
        ]);

        return response()->json($ticket);
    }
}
