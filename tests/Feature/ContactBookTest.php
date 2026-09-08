<?php

namespace Tests\Feature;

use App\Livewire\Contacts;
use App\Livewire\Dialer;
use App\Models\Contact;
use App\Models\User;
use App\Support\ContactImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Live Voice — Part C (in-app contact book). Auth-scoped CRUD + CSV/vCard bulk
 * import that feeds the dialer. No feature gate (it isn't provider-billed); it
 * works identically on every browser (the Android-Chrome picker is a bonus only).
 */
class ContactBookTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_add_a_contact(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Contacts::class)
            ->set('name', 'Ada Obi')
            ->set('phone', '+2348012345678')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('contacts', [
            'user_id' => $user->id, 'name' => 'Ada Obi', 'phone_number' => '+2348012345678',
        ]);
    }

    public function test_a_messy_number_is_normalised_on_save(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Contacts::class)
            ->set('name', 'Ben')
            ->set('phone', '+234 801-234-5678')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('contacts', ['phone_number' => '+2348012345678']);
    }

    public function test_saving_the_same_number_updates_rather_than_duplicates(): void
    {
        $user = User::factory()->create();
        Contact::create(['user_id' => $user->id, 'name' => 'Old Name', 'phone_number' => '+2348012345678']);

        Livewire::actingAs($user)->test(Contacts::class)
            ->set('name', 'New Name')
            ->set('phone', '+2348012345678')
            ->call('save');

        $this->assertSame(1, Contact::where('user_id', $user->id)->count());
        $this->assertSame('New Name', Contact::where('user_id', $user->id)->first()->name);
    }

    public function test_a_user_can_edit_and_delete_a_contact(): void
    {
        $user = User::factory()->create();
        $c = Contact::create(['user_id' => $user->id, 'name' => 'X', 'phone_number' => '+15551110000']);

        Livewire::actingAs($user)->test(Contacts::class)
            ->call('edit', $c->id)
            ->assertSet('name', 'X')
            ->set('name', 'Renamed')
            ->call('save')
            ->call('delete', $c->id);

        $this->assertDatabaseMissing('contacts', ['id' => $c->id]);
    }

    public function test_a_user_can_toggle_a_contact_as_favourite(): void
    {
        $user = User::factory()->create();
        $c = Contact::create(['user_id' => $user->id, 'name' => 'Ada Obi', 'phone_number' => '+2348012345678']);

        Livewire::actingAs($user)->test(Contacts::class)->call('toggleFavorite', $c->id);
        $this->assertTrue($c->fresh()->is_favorite);

        Livewire::actingAs($user)->test(Contacts::class)->call('toggleFavorite', $c->id);
        $this->assertFalse($c->fresh()->is_favorite);
    }

    public function test_a_user_cannot_favourite_another_users_contact(): void
    {
        $owner = User::factory()->create();
        $c = Contact::create(['user_id' => $owner->id, 'name' => 'Secret', 'phone_number' => '+15551110000']);
        $attacker = User::factory()->create();

        // The auth-scoped lookup refuses a foreign row outright (404), so the
        // favourite flag can never be flipped by anyone but the owner.
        try {
            Livewire::actingAs($attacker)->test(Contacts::class)->call('toggleFavorite', $c->id);
            $this->fail('Expected the foreign contact to be unreachable.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // expected
        }

        $this->assertFalse($c->fresh()->is_favorite); // untouched
    }

    public function test_a_user_cannot_touch_another_users_contact(): void
    {
        $owner = User::factory()->create();
        $c = Contact::create(['user_id' => $owner->id, 'name' => 'Secret', 'phone_number' => '+15551110000']);
        $attacker = User::factory()->create();

        Livewire::actingAs($attacker)->test(Contacts::class)->call('delete', $c->id);

        $this->assertDatabaseHas('contacts', ['id' => $c->id]); // untouched
    }

    public function test_csv_import_creates_contacts_scoped_to_the_user(): void
    {
        $user = User::factory()->create();
        $csv = "Name,Phone\nAda Obi,+2348012345678\nBen Roy,08087654321\n";
        $file = UploadedFile::fake()->createWithContent('book.csv', $csv);

        Livewire::actingAs($user)->test(Contacts::class)
            ->set('upload', $file)
            ->call('import')
            ->assertHasNoErrors();

        $this->assertSame(2, Contact::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('contacts', ['user_id' => $user->id, 'name' => 'Ada Obi', 'phone_number' => '+2348012345678']);
    }

    public function test_vcard_import_creates_contacts(): void
    {
        $user = User::factory()->create();
        $vcf = "BEGIN:VCARD\nVERSION:3.0\nFN:Chidi Eze\nTEL;TYPE=CELL:+2349011122233\nEND:VCARD\n"
            ."BEGIN:VCARD\nVERSION:3.0\nFN:Zara\nTEL:+15552223333\nEND:VCARD\n";
        $file = UploadedFile::fake()->createWithContent('book.vcf', $vcf);

        Livewire::actingAs($user)->test(Contacts::class)
            ->set('upload', $file)
            ->call('import')
            ->assertHasNoErrors();

        $this->assertSame(2, Contact::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('contacts', ['name' => 'Chidi Eze', 'phone_number' => '+2349011122233']);
    }

    public function test_the_android_picker_shortcut_imports_into_the_same_book(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Contacts::class)
            ->call('importPicked', [
                ['name' => 'From Phone', 'phone' => '+2348012345678'],
                ['name' => '', 'phone' => 'not-a-number'], // dropped
            ]);

        $this->assertSame(1, Contact::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('contacts', ['name' => 'From Phone', 'phone_number' => '+2348012345678']);
    }

    public function test_import_parser_dedupes_and_drops_unusable_rows(): void
    {
        $rows = ContactImport::fromCsv("Ada,+2348012345678\nAda Again,+234 801 234 5678\nNoPhone,\n");

        // The two Ada rows collapse to one (same normalised number); the empty one drops.
        $this->assertCount(1, $rows);
        $this->assertSame('+2348012345678', $rows[0]['phone']);
    }

    public function test_tapping_a_contact_prefills_the_dialer(): void
    {
        config(['services.twilio.account_sid' => 'AC_test', 'services.twilio.auth_token' => 'secret_token']);
        $user = User::factory()->create();

        Livewire::actingAs($user)->withQueryParams(['to' => '+2348012345678'])
            ->test(Dialer::class)
            ->assertSet('destination', '+2348012345678');
    }
}
