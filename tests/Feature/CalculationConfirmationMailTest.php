<?php

namespace Tests\Feature;

use App\Mail\CalculationConfirmed;
use App\Models\Calculation;
use App\Models\CalculationItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CalculationConfirmationMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirming_a_calculation_emails_its_author(): void
    {
        Mail::fake();

        $author = User::factory()->create(['email' => 'author@example.com']);
        $calculation = Calculation::factory()->create(['user_id' => $author->id]);
        $item = CalculationItem::factory()->forCalculation($calculation)->create();

        $this->post("/c/{$calculation->access_token}/confirm", [
            'accepted_items' => [$item->id],
        ])->assertRedirect();

        Mail::assertQueued(CalculationConfirmed::class, function (CalculationConfirmed $mail) use ($calculation, $author) {
            return $mail->calculation->is($calculation) && $mail->hasTo($author->email);
        });
    }

    public function test_no_mail_is_sent_when_calculation_has_no_author(): void
    {
        Mail::fake();

        $calculation = Calculation::factory()->create(['user_id' => null]);
        $item = CalculationItem::factory()->forCalculation($calculation)->create();

        $this->post("/c/{$calculation->access_token}/confirm", [
            'accepted_items' => [$item->id],
        ])->assertRedirect();

        Mail::assertNothingQueued();
    }
}
