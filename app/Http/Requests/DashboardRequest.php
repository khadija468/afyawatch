<?php

namespace App\Http\Requests;

use App\Support\DashboardFilters;
use Illuminate\Foundation\Http\FormRequest;

class DashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'condition_id' => ['nullable', 'integer', 'exists:conditions,id'],
            'region_id' => ['nullable', 'integer', 'exists:regions,id'],
            'facility_id' => ['nullable', 'integer', 'exists:facilities,id'],
        ];
    }

    public function filters(): DashboardFilters
    {
        return DashboardFilters::make(self::normalise($this->validated()), $this->user());
    }

    /** Drops empty values and turns id parameters into integers. */
    public static function normalise(array $validated): array
    {
        $input = array_filter($validated, fn ($value) => $value !== null && $value !== '');

        foreach (['condition_id', 'region_id', 'facility_id'] as $key) {
            if (isset($input[$key])) {
                $input[$key] = (int) $input[$key];
            }
        }

        return $input;
    }
}
