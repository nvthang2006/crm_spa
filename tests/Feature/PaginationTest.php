<?php

namespace Tests\Feature;

use App\Livewire\Admin\CustomersPage;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_pagination_renders_correctly_and_handles_page_switching(): void
    {
        $user = User::factory()->create();
        Customer::factory()->count(25)->create();

        Livewire::actingAs($user)
            ->test(CustomersPage::class)
            ->assertSee('spa-pagination-controls')
            ->assertSee('spa-pagination-btn-active')
            ->assertSee('Hiển thị')
            ->assertSee('khách hàng')
            ->assertDontSee('Showing 1 to')
            ->assertDontSee('results')
            ->call('gotoPage', 2)
            ->assertSet('paginators.page', 2)
            ->assertSee('spa-pagination-btn-active');
    }
}
