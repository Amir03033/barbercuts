<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Exceptions\BookingException;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Review;
use Illuminate\Support\Facades\DB;

/**
 * Reviews (fase 7): achterlaten na een voltooide afspraak, reageren, melden, verbergen en de score bijhouden.
 */
class ReviewService
{
    /** Zoveel "denkbeeldige" reviews met het gemiddelde cijfer tellen mee in de gewogen score. */
    public const int PRIOR_WEIGHT = 5;

    public const float PRIOR_MEAN = 4.0;

    /** Vanaf dit aantal reviews kan een zaak "top beoordeeld" zijn. */
    public const int MIN_REVIEWS_FOR_TOP = 3;

    /**
     * De review van de klant bij deze afspraak. Kan één keer, en alleen na een voltooid bezoek.
     */
    public function submit(Appointment $appointment, int $rating, ?string $comment): Review
    {
        if ($appointment->status !== AppointmentStatus::Completed) {
            throw new BookingException('Je kunt pas een review achterlaten na je bezoek.');
        }

        if ($rating < 1 || $rating > 5) {
            throw new BookingException('Kies 1 tot en met 5 sterren.');
        }

        if (Review::withoutGlobalScopes()->where('appointment_id', $appointment->id)->exists()) {
            throw new BookingException('Je hebt voor deze afspraak al een review achtergelaten.');
        }

        return DB::transaction(function () use ($appointment, $rating, $comment): Review {
            $review = new Review;
            $review->barbershop_id = $appointment->barbershop_id;
            $review->appointment_id = $appointment->id;
            $review->customer_id = $appointment->customer_id;
            $review->staff_member_id = $appointment->staff_member_id;
            $review->rating = $rating;
            $review->comment = $comment !== null && trim($comment) !== '' ? trim($comment) : null;
            $review->save();

            $this->recalculate($review->barbershop_id);

            return $review;
        });
    }

    public function reply(Review $review, ?string $reply): void
    {
        $reply = $reply !== null && trim($reply) !== '' ? trim($reply) : null;

        $review->reply = $reply;
        $review->replied_at = $reply === null ? null : now();
        $review->save();
    }

    /**
     * De eigenaar meldt een review bij het platform. Verbergen kan alleen de admin.
     */
    public function report(Review $review, string $reason): void
    {
        $review->reported_at = now();
        $review->report_reason = trim($reason);
        $review->save();
    }

    /**
     * Door de admin: verbergen of weer tonen. De score van de zaak telt alleen zichtbare reviews.
     */
    public function setHidden(Review $review, bool $hidden): void
    {
        DB::table('reviews')->where('id', $review->id)->update(['hidden_at' => $hidden ? now() : null, 'updated_at' => now()]);
        $this->recalculate($review->barbershop_id);

        ActivityLog::record($hidden ? 'review.hidden' : 'review.shown', $review->barbershop()->first(), $review);
    }

    /**
     * Reken gemiddelde, aantal en gewogen score van de zaak en haar kappers opnieuw uit (in de database).
     */
    public function recalculate(int $shopId): void
    {
        $visible = fn () => DB::table('reviews')->where('barbershop_id', $shopId)->whereNull('hidden_at');

        $totals = $visible()->selectRaw('COUNT(*) as count, COALESCE(SUM(rating), 0) as total')->first();
        $count = (int) $totals->count;
        $sum = (int) $totals->total;

        DB::table('barbershops')->where('id', $shopId)->update([
            'rating_count' => $count,
            'rating_avg' => $count > 0 ? round($sum / $count, 2) : null,
            'rating_score' => $count > 0 ? round((self::PRIOR_WEIGHT * self::PRIOR_MEAN + $sum) / (self::PRIOR_WEIGHT + $count), 3) : 0,
        ]);

        $perStaff = $visible()->groupBy('staff_member_id')->selectRaw('staff_member_id, COUNT(*) as count, AVG(rating) as average')->get()->keyBy('staff_member_id');

        foreach (DB::table('staff_members')->where('barbershop_id', $shopId)->pluck('id') as $staffId) {
            $row = $perStaff->get($staffId);

            DB::table('staff_members')->where('id', $staffId)->update([
                'rating_count' => (int) ($row->count ?? 0),
                'rating_avg' => $row === null ? null : round((float) $row->average, 2),
            ]);
        }
    }
}
