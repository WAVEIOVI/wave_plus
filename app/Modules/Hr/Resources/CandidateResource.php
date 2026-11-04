<?php

declare(strict_types=1);

namespace App\Modules\Hr\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CandidateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'notes' => $this->notes,
            'applications' => $this->applications->map(fn ($app) => [
                'id' => $app->id,
                'job_opening_id' => $app->job_opening_id,
                'job_title' => $app->jobOpening?->title,
                'job_reference' => $app->jobOpening?->reference,
                'application_date' => $app->application_date?->toDateString(),
                'status' => $app->status,
            ]),
            'interviews' => $this->interviews->map(fn ($int) => [
                'id' => $int->id,
                'job_opening_id' => $int->job_opening_id,
                'job_title' => $int->jobOpening?->title,
                'scheduled_at' => $int->scheduled_at?->toISOString(),
                'duration_minutes' => $int->duration_minutes,
                'interviewer_id' => $int->interviewer_id,
                'interviewer_name' => $int->interviewer?->name,
                'status' => $int->status,
                'rating' => $int->rating,
                'feedback' => $int->feedback,
            ]),
            'resumes' => $this->getMedia('resume')->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->file_name,
                'size' => $m->size,
                'mime_type' => $m->mime_type,
                'collection' => $m->collection_name,
                'url' => url("/api/hr/recruitment/candidates/{$this->id}/media/{$m->id}/download"),
                'download_url' => url("/api/hr/recruitment/candidates/{$this->id}/media/{$m->id}/download"),
                'is_private' => true,
            ]),
            'documents' => $this->getMedia('documents')->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->file_name,
                'size' => $m->size,
                'mime_type' => $m->mime_type,
                'collection' => $m->collection_name,
                'url' => url("/api/hr/recruitment/candidates/{$this->id}/media/{$m->id}/download"),
                'download_url' => url("/api/hr/recruitment/candidates/{$this->id}/media/{$m->id}/download"),
                'is_private' => true,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
