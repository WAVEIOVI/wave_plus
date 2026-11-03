<?php

declare(strict_types=1);

namespace App\Modules\Hr\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Concerns\AuthorizesHrMutations;
use App\Modules\Hr\Models\PublicHoliday;
use App\Modules\Hr\Support\TranslatableFields;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicHolidayController extends Controller
{
    use AuthorizesHrMutations;

    public function index(Request $request): JsonResponse
    {
        if (! $request->user()?->can('hr.leave.request')) {
            $this->authorizeHrRead('hr.workforce.view');
        }

        $query = PublicHoliday::with('department');

        if ($year = $request->input('year')) {
            $query->forYear((int) $year);
        }

        if ($departmentId = $request->input('department_id')) {
            $query->where(function ($q) use ($departmentId) {
                $q->whereNull('department_id')->orWhere('department_id', $departmentId);
            });
        }

        if ($request->boolean('active_only', true)) {
            $query->active();
        }

        $holidays = $query->orderBy('date')->get();

        return response()->json([
            'data' => $holidays,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeHrMutation('hr.workforce.assign');
        TranslatableFields::normalizeRequest($request, ['name']);

        $data = $request->validate(array_merge([
            'date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:date'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'is_recurring' => ['nullable', 'boolean'],
            'is_half_day' => ['nullable', 'boolean'],
            'applies_to_all' => ['nullable', 'boolean'],
            'country_code' => ['nullable', 'string', 'max:10'],
            'department_id' => ['nullable', 'ulid', 'exists:hr_departments,id'],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ], TranslatableFields::rules('name', true, 150)));

        if (empty($data['year'])) {
            $data['year'] = (int) date('Y', strtotime($data['date']));
        }

        $holiday = PublicHoliday::create($data);

        return response()->json([
            'message' => 'Public holiday created successfully.',
            'data' => $holiday->load('department'),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $this->authorizeHrRead('hr.workforce.view');
        $holiday = PublicHoliday::with('department')->findOrFail($id);

        return response()->json([
            'data' => $holiday,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.workforce.assign');
        $holiday = PublicHoliday::findOrFail($id);
        TranslatableFields::normalizeRequest($request, ['name']);

        $data = $request->validate(array_merge([
            'date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:date'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'is_recurring' => ['nullable', 'boolean'],
            'is_half_day' => ['nullable', 'boolean'],
            'applies_to_all' => ['nullable', 'boolean'],
            'country_code' => ['nullable', 'string', 'max:10'],
            'department_id' => ['nullable', 'ulid', 'exists:hr_departments,id'],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ], TranslatableFields::rules('name', true, 150)));

        if (empty($data['year'])) {
            $data['year'] = (int) date('Y', strtotime($data['date']));
        }

        $holiday->update($data);

        return response()->json([
            'message' => 'Public holiday updated successfully.',
            'data' => $holiday->fresh()->load('department'),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.workforce.assign');
        $holiday = PublicHoliday::findOrFail($id);
        $holiday->delete();

        return response()->json([
            'message' => 'Public holiday deleted successfully.',
        ]);
    }
}
