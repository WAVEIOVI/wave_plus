<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\OperationalHub\Models\ChecklistTemplate;
use App\Modules\OperationalHub\Models\ChecklistTemplateItem;
use App\Modules\OperationalHub\Resources\ChecklistTemplateResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ChecklistTemplateController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ChecklistTemplate::class);

        $query = ChecklistTemplate::query()->with('items')->orderBy('sort_order');

        if ($request->filled('applicable_mode')) {
            $mode = $request->input('applicable_mode');
            $query->where(function ($q) use ($mode) {
                $q->whereNull('applicable_mode')->orWhere('applicable_mode', $mode);
            });
        }

        if ($request->boolean('active_only', true)) {
            $query->where('is_active', true);
        }

        return ChecklistTemplateResource::collection($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ChecklistTemplate::class);

        $validated = $request->validate([
            'code'            => ['required', 'string', 'unique:checklist_templates,code'],
            'name'            => ['required', 'array'],
            'description'     => ['nullable', 'array'],
            'applicable_mode' => ['nullable', 'string', 'in:field_service,workshop'],
            'category'        => ['nullable', 'string'],
            'is_active'       => ['boolean'],
            'sort_order'      => ['integer'],
            'items'           => ['nullable', 'array'],
            'items.*.title'   => ['required', 'array'],
            'items.*.answer_type' => ['nullable', 'string'],
            'items.*.is_required' => ['boolean'],
        ]);

        $template = DB::transaction(function () use ($validated, $request) {
            $template = ChecklistTemplate::create([
                'code'            => $validated['code'],
                'name'            => $validated['name'],
                'description'     => $validated['description'] ?? null,
                'applicable_mode' => $validated['applicable_mode'] ?? null,
                'category'        => $validated['category'] ?? null,
                'is_active'       => $validated['is_active'] ?? true,
                'sort_order'      => $validated['sort_order'] ?? 0,
                'created_by'      => $request->user()?->id,
                'updated_by'      => $request->user()?->id,
            ]);

            if (!empty($validated['items'])) {
                foreach ($validated['items'] as $idx => $item) {
                    $template->items()->create([
                        'title'       => $item['title'],
                        'description' => $item['description'] ?? null,
                        'answer_type' => $item['answer_type'] ?? 'yes_no',
                        'is_required' => $item['is_required'] ?? true,
                        'is_active'   => $item['is_active'] ?? true,
                        'sort_order'  => $item['sort_order'] ?? $idx,
                    ]);
                }
            }

            return $template->fresh('items');
        });

        return response()->json([
            'message' => 'Checklist template created successfully.',
            'data'    => new ChecklistTemplateResource($template),
        ], 201);
    }

    public function show(ChecklistTemplate $template): ChecklistTemplateResource
    {
        $this->authorize('view', $template);

        $template->loadMissing('items');
        return new ChecklistTemplateResource($template);
    }

    public function update(Request $request, ChecklistTemplate $template): JsonResponse
    {
        $this->authorize('update', $template);

        $validated = $request->validate([
            'name'            => ['sometimes', 'array'],
            'description'     => ['nullable', 'array'],
            'applicable_mode' => ['nullable', 'string'],
            'category'        => ['nullable', 'string'],
            'is_active'       => ['boolean'],
            'sort_order'      => ['integer'],
            'items'           => ['nullable', 'array'],
        ]);

        DB::transaction(function () use ($template, $validated, $request) {
            $template->update([
                'name'            => $validated['name'] ?? $template->name,
                'description'     => $validated['description'] ?? $template->description,
                'applicable_mode' => $validated['applicable_mode'] ?? $template->applicable_mode,
                'category'        => $validated['category'] ?? $template->category,
                'is_active'       => $validated['is_active'] ?? $template->is_active,
                'sort_order'      => $validated['sort_order'] ?? $template->sort_order,
                'updated_by'      => $request->user()?->id,
            ]);

            if (isset($validated['items']) && is_array($validated['items'])) {
                $template->items()->delete();
                foreach ($validated['items'] as $idx => $item) {
                    $template->items()->create([
                        'title'       => $item['title'],
                        'description' => $item['description'] ?? null,
                        'answer_type' => $item['answer_type'] ?? 'yes_no',
                        'is_required' => $item['is_required'] ?? true,
                        'is_active'   => $item['is_active'] ?? true,
                        'sort_order'  => $item['sort_order'] ?? $idx,
                    ]);
                }
            }
        });

        return response()->json([
            'message' => 'Checklist template updated successfully.',
            'data'    => new ChecklistTemplateResource($template->fresh('items')),
        ]);
    }

    public function destroy(ChecklistTemplate $template): JsonResponse
    {
        $this->authorize('delete', $template);

        $template->delete();
        return response()->json(['message' => 'Checklist template deleted successfully.']);
    }
}
