<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminTicketControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'admin'], ['label' => 'Administrateur']);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($role->id);

        $this->customer = User::factory()->create();
    }

    public function test_non_admin_cannot_list_tickets(): void
    {
        Sanctum::actingAs($this->customer);

        $this->getJson('/api/admin/tickets')->assertForbidden();
    }

    public function test_guest_cannot_list_tickets(): void
    {
        $this->getJson('/api/admin/tickets')->assertUnauthorized();
    }

    public function test_admin_can_list_tickets(): void
    {
        Sanctum::actingAs($this->admin);

        Ticket::create(['user_id' => $this->customer->id, 'subject' => 'Question livraison', 'status' => 'open']);
        Ticket::create(['user_id' => $this->customer->id, 'subject' => 'Retour produit', 'status' => 'closed']);

        $response = $this->getJson('/api/admin/tickets')->assertOk();

        $this->assertCount(2, $response->json('data'));
    }

    public function test_admin_can_filter_tickets_by_status(): void
    {
        Sanctum::actingAs($this->admin);

        Ticket::create(['user_id' => $this->customer->id, 'subject' => 'Question livraison', 'status' => 'open']);
        Ticket::create(['user_id' => $this->customer->id, 'subject' => 'Retour produit', 'status' => 'closed']);

        $response = $this->getJson('/api/admin/tickets?status=open')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Question livraison', $response->json('data.0.subject'));
    }

    public function test_admin_can_view_ticket_with_messages(): void
    {
        Sanctum::actingAs($this->admin);

        $ticket = Ticket::create(['user_id' => $this->customer->id, 'subject' => 'Question livraison', 'status' => 'open']);
        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_type' => 'user',
            'sender_id' => $this->customer->id,
            'message' => 'Bonjour, où en est ma commande ?',
        ]);
        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_type' => 'admin',
            'sender_id' => $this->admin->id,
            'message' => 'Elle est en cours de préparation.',
        ]);

        $response = $this->getJson("/api/admin/tickets/{$ticket->id}")->assertOk();

        $this->assertCount(2, $response->json('messages'));
        $this->assertEquals('user', $response->json('messages.0.sender_type'));
        $this->assertEquals('admin', $response->json('messages.1.sender_type'));
    }
}
