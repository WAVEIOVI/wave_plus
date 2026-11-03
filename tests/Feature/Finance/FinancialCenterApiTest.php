<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\Finance\Models\CostCenter;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Models\FinancialPeriod;
use App\Modules\Finance\Models\TaxRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialCenterApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'finance_api_test@waveplus.local',
        ]);
    }

    public function test_financial_dashboard_endpoint(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/finance/dashboard');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'balances' => [
                        'cash',
                        'bank',
                        'total_liquidity',
                    ],
                    'receivables' => [
                        'total',
                        'overdue',
                        'current',
                        'aging',
                    ],
                    'payables' => [
                        'total',
                        'overdue',
                        'current',
                        'aging',
                    ],
                    'performance',
                    'cash_flow',
                ],
            ]);
    }

    public function test_financial_periods_endpoints(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/finance/financial-periods');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data',
            ]);

        $current = $this->actingAs($this->user)->getJson('/api/finance/financial-periods/current');
        $current->assertOk()->assertJsonStructure(['success', 'data']);
    }

    public function test_chart_of_accounts_and_cost_centers_endpoints(): void
    {
        CostCenter::create([
            'code' => 'OPS-001',
            'name' => 'Operations Division',
            'type' => 'operational',
        ]);

        $ccResponse = $this->actingAs($this->user)->getJson('/api/finance/cost-centers');
        $ccResponse->assertOk()->assertJsonStructure(['success', 'data']);

        $coaResponse = $this->actingAs($this->user)->getJson('/api/finance/accounting/chart-of-accounts');
        $coaResponse->assertOk()->assertJsonStructure(['success', 'data']);
    }

    public function test_financial_accounts_crud(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/finance/financial-accounts', [
            'name'            => 'Main Operations Account',
            'type'            => 'bank',
            'currency'        => 'TND',
            'opening_balance' => 50000,
            'bank_name'       => 'BIAT',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Main Operations Account')
            ->assertJsonPath('data.type', 'bank');

        $list = $this->actingAs($this->user)->getJson('/api/finance/financial-accounts');
        $list->assertOk();
    }

    public function test_profitability_summary_endpoint(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/finance/profitability/overview');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary' => [
                        'total_revenue',
                        'total_cost',
                        'gross_contribution',
                        'margin_percentage',
                    ],
                    'items',
                ],
            ]);
    }
}
