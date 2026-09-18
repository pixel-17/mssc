<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Http\Livewire\UpdateProfileInformationForm;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileInformationTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_profile_information_is_available(): void
    {
        $this->actingAs($user = User::factory()->create());

        $component = Livewire::test(UpdateProfileInformationForm::class);

        $this->assertEquals($user->name, $component->state['name']);
        $this->assertEquals($user->email, $component->state['email']);
    }

    public function test_email_can_be_updated(): void
    {
        $this->actingAs($user = User::factory()->create());

        Livewire::test(UpdateProfileInformationForm::class)
            ->set('state', ['name' => 'Test Name', 'email' => 'test@example.com'])
            ->call('updateProfileInformation');

        $this->assertEquals('test@example.com', $user->fresh()->email);
    }

    public function test_name_cannot_be_updated(): void
    {
        $this->actingAs($user = User::factory()->create(['name' => 'Nombre Original']));

        Livewire::test(UpdateProfileInformationForm::class)
            ->set('state', ['name' => 'Nombre Cambiado', 'email' => $user->email])
            ->call('updateProfileInformation');

        $this->assertEquals('Nombre Original', $user->fresh()->name);
    }
}
