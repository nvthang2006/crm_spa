<?php

namespace App\Services;

use App\Models\CommissionRule;
use Illuminate\Support\Collection;

class CommissionRuleService
{
    /**
     * @param  Collection<int, CommissionRule>  $rules
     * @return Collection<int, CommissionRule>
     */
    public function forPerformer(Collection $rules, ?int $employeeId): Collection
    {
        $personal = $rules->filter(fn (CommissionRule $rule): bool => $rule->beneficiary_type === 'performer_employee'
            && $employeeId !== null && (int) $rule->beneficiary_employee_id === $employeeId)
            ->sortByDesc(fn (CommissionRule $rule): string => $rule->starts_on->toDateString().'-'.str_pad((string) $rule->id, 20, '0', STR_PAD_LEFT))->first();

        return $rules->filter(fn (CommissionRule $rule): bool => match ($rule->beneficiary_type) {
            'performer_employee' => $personal !== null && $rule->id === $personal->id,
            'primary_technician' => $personal === null,
            default => true,
        })->values();
    }
}
