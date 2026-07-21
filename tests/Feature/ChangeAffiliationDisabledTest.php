<?php

namespace Tests\Feature;

use App\Filament\Actions\ChangeAffiliationAction;
use Tests\TestCase;

class ChangeAffiliationDisabledTest extends TestCase
{
    public function test_affiliation_change_is_disabled_by_default(): void
    {
        config(['hopeworks.tenant_migration.affiliation_change_enabled' => false]);

        $action = ChangeAffiliationAction::make();

        $this->assertTrue($action->isDisabled());
        $this->assertSame(
            ChangeAffiliationAction::DISABLED_TOOLTIP,
            $action->getTooltip(),
        );
    }

    public function test_affiliation_change_can_be_reenabled_via_config(): void
    {
        config(['hopeworks.tenant_migration.affiliation_change_enabled' => true]);

        $action = ChangeAffiliationAction::make();

        $this->assertFalse($action->isDisabled());
        $this->assertNull($action->getTooltip());
    }
}
