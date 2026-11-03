<?php

namespace App\Modules\Procurement\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Procurement\Controllers\Concerns\AuthorizesProcurementRequests;
use App\Modules\Procurement\Models\RfqHeader;
use App\Modules\Procurement\Models\RfqResponse;
use App\Modules\Procurement\Models\RfqResponseLine;
use App\Modules\Procurement\Requests\StoreRfqResponseRequest;
use App\Modules\Procurement\Resources\RfqResponseResource;
use App\Modules\Procurement\Services\ProcurementStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RfqResponseController extends Controller
{
    use AuthorizesProcurementRequests;

    public function __construct(private readonly ProcurementStateMachine $stateMachine) {}

    public function store(StoreRfqResponseRequest $request, RfqHeader $rfq): JsonResponse
    {
        $this->authorize('update', $rfq);
        $this->stateMachine->assertRfqAcceptsBids($rfq->status);

        $validated = $request->validated();

        $response = DB::transaction(function () use ($rfq, $validated) {
            // Extract nested lines
            $linesData = Arr::pull($validated, 'lines');

            // Create or update the RFQ Response header
            $response = RfqResponse::updateOrCreate(
                [
                    'rfq_id' => $rfq->id,
                    'supplier_id' => $validated['supplier_id'],
                ],
                [
                    'validity_until' => $validated['validity_until'] ?? null,
                    'can_deliver_by' => $validated['can_deliver_by'] ?? null,
                    'payment_terms_offered' => $validated['payment_terms_offered'] ?? null,
                    'total_ht' => $validated['total_ht'] ?? 0,
                    'supplier_notes' => $validated['supplier_notes'] ?? null,
                    'submitted_at' => now(),
                ]
            );

            // Delete old lines if updating
            $response->responseLines()->delete();

            // Create each response line, computing vs_target_pct when possible
            foreach ($linesData as $lineData) {
                /** @var \App\Modules\Procurement\Models\RfqLine|null $rfqLine */
                $rfqLine = $rfq->lines()->find($lineData['rfq_line_id']);

                $vsTargetPct = null;
                if ($rfqLine && $rfqLine->target_price_ht && $rfqLine->target_price_ht > 0) {
                    $offered = (float) $lineData['offered_unit_price_ht'];
                    $target = (float) $rfqLine->target_price_ht;
                    $vsTargetPct = round((($offered - $target) / $target) * 100, 2);
                }

                RfqResponseLine::create([
                    'rfq_response_id' => $response->id,
                    'rfq_line_id' => $lineData['rfq_line_id'],
                    'offered_unit_price_ht' => $lineData['offered_unit_price_ht'],
                    'offered_qty' => $lineData['offered_qty'],
                    'offered_lead_time_days' => $lineData['offered_lead_time_days'] ?? null,
                    'certifications_confirmed' => $lineData['certifications_confirmed'] ?? null,
                    'vs_target_pct' => $vsTargetPct,
                ]);
            }

            // Update the corresponding invitation status to "responded"
            $rfq->invitations()
                ->where('supplier_id', $response->supplier_id)
                ->update(['status' => 'responded']);

            // Auto-compute the weighted evaluation score based on RFQ criteria
            $this->computeAndSaveScore($rfq, $response);

            return $response->load(['supplier', 'responseLines.rfqLine']);
        });

        return response()->json([
            'message' => 'Supplier bid submitted successfully.',
            'data' => new RfqResponseResource($response),
        ], 201);
    }

    /**
     * Compute and persist the weighted evaluation score for a response,
     * based on the RFQ's evaluation_criteria weights.
     *
     * Scoring strategy:
     *  - Price     : lower is better — cheapest offer = 100, others proportional
     *  - Lead Time : on/before required date = 100; -5 pts per day late
     *  - Quality   : % of required certifications confirmed across all lines
     *  - Technical : defaults to 100 (no current manual input)
     *  - Payment   : tiered by payment terms offered
     */
    private function computeAndSaveScore(RfqHeader $rfq, RfqResponse $response): void
    {
        $criteria = $rfq->evaluation_criteria ?? [];

        $priceWeight = (float) ($criteria['price_weight'] ?? 0);
        $leadTimeWeight = (float) ($criteria['lead_time_weight'] ?? 0);
        $qualityWeight = (float) ($criteria['quality_weight'] ?? 0);
        $technicalWeight = (float) ($criteria['technical_weight'] ?? 0);
        $paymentWeight = (float) ($criteria['payment_weight'] ?? 0);

        $totalWeight = $priceWeight + $leadTimeWeight + $qualityWeight + $technicalWeight + $paymentWeight;

        if ($totalWeight <= 0) {
            return;
        }

        // ── 1. Price Score ──────────────────────────────────────────────────
        $priceScore = 100.0;
        if ($priceWeight > 0) {
            $allTotals = RfqResponse::where('rfq_id', $rfq->id)
                ->where('total_ht', '>', 0)
                ->pluck('total_ht')
                ->map(fn ($v) => (float) $v);

            $minTotal = $allTotals->min() ?: null;
            $thisTotal = (float) $response->total_ht;

            if ($minTotal && $thisTotal > 0) {
                $priceScore = round(($minTotal / $thisTotal) * 100, 2);
            }
        }

        // ── 2. Lead Time Score ──────────────────────────────────────────────
        $leadTimeScore = 100.0;
        if ($leadTimeWeight > 0 && $rfq->required_delivery_date && $response->can_deliver_by) {
            $required = \Carbon\Carbon::parse($rfq->required_delivery_date);
            $offered = \Carbon\Carbon::parse($response->can_deliver_by);
            $daysLate = max(0, $offered->diffInDays($required, false) * -1);
            $leadTimeScore = max(0.0, 100.0 - ($daysLate * 5.0));
        }

        // ── 3. Quality Score ────────────────────────────────────────────────
        $qualityScore = 100.0;
        if ($qualityWeight > 0) {
            $rfqLines = $rfq->lines()->get();
            $confirmedCount = 0;
            $requiredCount = 0;

            foreach ($rfqLines as $rfqLine) {
                $required = $rfqLine->required_certifications ?? [];
                if (empty($required)) {
                    continue;
                }

                $responseLine = $response->responseLines()
                    ->where('rfq_line_id', $rfqLine->id)
                    ->first();

                $confirmed = $responseLine?->certifications_confirmed ?? [];
                foreach ($required as $cert) {
                    $requiredCount++;
                    if (in_array($cert, $confirmed)) {
                        $confirmedCount++;
                    }
                }
            }

            $qualityScore = $requiredCount > 0
                ? round(($confirmedCount / $requiredCount) * 100, 2)
                : 100.0;
        }

        // ── 4. Technical Score ──────────────────────────────────────────────
        // Defaults to 100 — extend this when manual technical scoring is added.
        $technicalScore = 100.0;

        // ── 5. Payment Score ────────────────────────────────────────────────
        $paymentScoreMap = [
            'net_7' => 100,
            'net_15' => 95,
            'net_30' => 90,
            'net_45' => 75,
            'net_60' => 60,
            'net_90' => 40,
            'cod' => 30,
            'upfront' => 10,
        ];
        $paymentScore = (float) ($paymentScoreMap[$response->payment_terms_offered] ?? 50);

        // ── Weighted Final Score ────────────────────────────────────────────
        $weightedSum = 0.0;
        if ($priceWeight > 0) {
            $weightedSum += ($priceScore * $priceWeight);
        }
        if ($leadTimeWeight > 0) {
            $weightedSum += ($leadTimeScore * $leadTimeWeight);
        }
        if ($qualityWeight > 0) {
            $weightedSum += ($qualityScore * $qualityWeight);
        }
        if ($technicalWeight > 0) {
            $weightedSum += ($technicalScore * $technicalWeight);
        }
        if ($paymentWeight > 0) {
            $weightedSum += ($paymentScore * $paymentWeight);
        }

        $finalScore = round($weightedSum / $totalWeight, 2);

        $response->update([
            'evaluation_score' => $finalScore,
            'evaluation_details' => [
                'price_score' => $priceScore,
                'lead_time_score' => $leadTimeScore,
                'quality_score' => $qualityScore,
                'technical_score' => $technicalScore,
                'payment_score' => $paymentScore,
            ],
        ]);
    }
}
