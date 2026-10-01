<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Models\Agreement;
use App\Models\Partner;
use App\Models\PartnerAlias;
use App\Models\User;
use App\Models\VirtualAccount;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->authorizedUser = User::factory()->operator()->create();
    $this->authorizedUser->grantPermission(Permission::PartnerView);

    $this->unauthorizedUser = User::factory()->operator()->create();
});

describe('Partner Search Authentication & Authorization', function () {
    it('redirects unauthenticated guests to login', function () {
        $response = $this->get('/partners');
        $response->assertRedirect('/login');

        $partner = Partner::factory()->create();
        $detailResponse = $this->get("/partners/{$partner->id}");
        $detailResponse->assertRedirect('/login');
    });

    it('forbids users without partner.view permission', function () {
        $response = $this->actingAs($this->unauthorizedUser)->get('/partners');
        $response->assertForbidden();

        $partner = Partner::factory()->create();
        $detailResponse = $this->actingAs($this->unauthorizedUser)->get("/partners/{$partner->id}");
        $detailResponse->assertForbidden();
    });

    it('allows users with partner.view permission to view search index and show page', function () {
        $response = $this->actingAs($this->authorizedUser)->get('/partners');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Partners/Index')
            ->has('partners.data')
            ->has('filters')
        );

        $partner = Partner::factory()->create();
        $detailResponse = $this->actingAs($this->authorizedUser)->get("/partners/{$partner->id}");
        $detailResponse->assertOk();
        $detailResponse->assertInertia(fn (Assert $page) => $page
            ->component('Partners/Show')
            ->where('partner.id', $partner->id)
        );
    });
});

describe('Leading-Zero NO ID Exact-Match (Gate Test)', function () {
    it('returns exact match for leading-zero NO ID and excludes non-padded variants', function () {
        $partnerPadded = Partner::factory()->create([
            'partner_no_id' => '007',
            'partner_no_id_normalized' => Partner::normalizePartnerNoId('007'),
            'name' => 'Synthetic Agent Padded',
        ]);

        $partnerUnpadded = Partner::factory()->create([
            'partner_no_id' => '7',
            'partner_no_id_normalized' => Partner::normalizePartnerNoId('7'),
            'name' => 'Synthetic Agent Unpadded',
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get('/partners?query=007&type=no_id');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Partners/Index')
            ->has('partners.data', 1)
            ->where('partners.data.0.id', $partnerPadded->id)
            ->where('partners.data.0.partner_no_id', '007')
        );

        // Verify reverse: searching "7" returns the unpadded one only
        $responseReverse = $this->actingAs($this->authorizedUser)
            ->get('/partners?query=7&type=no_id');

        $responseReverse->assertOk();
        $responseReverse->assertInertia(fn (Assert $page) => $page
            ->component('Partners/Index')
            ->has('partners.data', 1)
            ->where('partners.data.0.id', $partnerUnpadded->id)
            ->where('partners.data.0.partner_no_id', '7')
        );
    });
});

describe('Same-Name Separate Candidates (FR-01)', function () {
    it('returns separate candidates for different partners with identical names/aliases', function () {
        $partnerA = Partner::factory()->create([
            'name' => 'Budi Santoso',
        ]);
        PartnerAlias::factory()->create([
            'partner_id' => $partnerA->id,
            'name_raw' => 'Budi Santoso',
            'name_normalized' => PartnerAlias::normalizeName('Budi Santoso'),
        ]);

        $partnerB = Partner::factory()->create([
            'name' => 'Budi Santoso',
        ]);
        PartnerAlias::factory()->create([
            'partner_id' => $partnerB->id,
            'name_raw' => 'Budi Santoso',
            'name_normalized' => PartnerAlias::normalizeName('Budi Santoso'),
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get('/partners?query=Budi+Santoso&type=name');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Partners/Index')
            ->has('partners.data', 2)
            ->where('partners.total', 2)
        );

        // Confirm both distinct partners exist in the response and were not merged
        $partners = Partner::where('name', 'Budi Santoso')->get();
        expect($partners)->toHaveCount(2);
        expect($partners->pluck('id')->unique())->toHaveCount(2);
    });
});

describe('Agreement Number Search (DEC-001)', function () {
    it('returns all partners in the grouping agreement', function () {
        $sharedAgreementNumber = '0042/SP-TJSL/2026';

        $partner1 = Partner::factory()->create(['name' => 'Partner Group Alpha']);
        Agreement::factory()->create([
            'partner_id' => $partner1->id,
            'agreement_number' => $sharedAgreementNumber,
            'agreement_number_normalized' => Agreement::normalizeAgreementNumber($sharedAgreementNumber),
        ]);

        $partner2 = Partner::factory()->create(['name' => 'Partner Group Beta']);
        Agreement::factory()->create([
            'partner_id' => $partner2->id,
            'agreement_number' => $sharedAgreementNumber,
            'agreement_number_normalized' => Agreement::normalizeAgreementNumber($sharedAgreementNumber),
        ]);

        $otherPartner = Partner::factory()->create(['name' => 'Partner Other Group']);
        Agreement::factory()->create([
            'partner_id' => $otherPartner->id,
            'agreement_number' => '9999/SP-TJSL/2026',
            'agreement_number_normalized' => Agreement::normalizeAgreementNumber('9999/SP-TJSL/2026'),
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get("/partners?query={$sharedAgreementNumber}&type=agreement");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Partners/Index')
            ->has('partners.data', 2)
            ->where('partners.total', 2)
        );
    });
});

describe('Virtual Account Search', function () {
    it('returns matching partner by VA number preserving leading zeros', function () {
        $vaNumber = '0000000012345678';

        $partner = Partner::factory()->create(['name' => 'Partner With VA']);
        VirtualAccount::factory()->create([
            'partner_id' => $partner->id,
            'va_number' => $vaNumber,
            'va_number_normalized' => VirtualAccount::normalizeVaNumber($vaNumber),
        ]);

        $otherPartner = Partner::factory()->create(['name' => 'Other Partner']);
        VirtualAccount::factory()->create([
            'partner_id' => $otherPartner->id,
            'va_number' => '0000000087654321',
            'va_number_normalized' => VirtualAccount::normalizeVaNumber('0000000087654321'),
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get("/partners?query={$vaNumber}&type=va");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Partners/Index')
            ->has('partners.data', 1)
            ->where('partners.data.0.id', $partner->id)
        );
    });
});

describe('Server-Side Pagination & Page Size', function () {
    it('paginates results with configurable page size', function () {
        Partner::factory()->count(25)->create();

        $response = $this->actingAs($this->authorizedUser)
            ->get('/partners?per_page=10');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Partners/Index')
            ->where('partners.per_page', 10)
            ->where('partners.total', 25)
            ->where('partners.last_page', 3)
            ->has('partners.data', 10)
        );

        $page2Response = $this->actingAs($this->authorizedUser)
            ->get('/partners?per_page=10&page=2');

        $page2Response->assertOk();
        $page2Response->assertInertia(fn (Assert $page) => $page
            ->component('Partners/Index')
            ->where('partners.current_page', 2)
            ->has('partners.data', 10)
        );

        $page3Response = $this->actingAs($this->authorizedUser)
            ->get('/partners?per_page=10&page=3');

        $page3Response->assertOk();
        $page3Response->assertInertia(fn (Assert $page) => $page
            ->component('Partners/Index')
            ->where('partners.current_page', 3)
            ->has('partners.data', 5)
        );
    });
});

describe('Verification Badges as Text (FR-01)', function () {
    it('provides descriptive text labels for verification states', function () {
        $verified = Partner::factory()->create(['verification_state' => 'verified']);
        $pending = Partner::factory()->create(['verification_state' => 'pending']);
        $unverified = Partner::factory()->create(['verification_state' => 'unverified']);

        $response = $this->actingAs($this->authorizedUser)->get('/partners');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Partners/Index')
            ->has('partners.data', 3)
        );

        // Verify detail endpoints return the badge text label
        $this->actingAs($this->authorizedUser)
            ->get("/partners/{$verified->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('partner.verification_badge_label', 'Terverifikasi')
            );

        $this->actingAs($this->authorizedUser)
            ->get("/partners/{$pending->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('partner.verification_badge_label', 'Menunggu Verifikasi')
            );

        $this->actingAs($this->authorizedUser)
            ->get("/partners/{$unverified->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('partner.verification_badge_label', 'Belum Terverifikasi')
            );
    });
});

describe('Sensitive Field Masking (DEC-004)', function () {
    it('masks NIK and VA numbers in search and detail responses', function () {
        $partner = Partner::factory()->create([
            'nik' => '3512345678900001',
            'nik_normalized' => Partner::normalizeNik('3512345678900001'),
        ]);

        VirtualAccount::factory()->create([
            'partner_id' => $partner->id,
            'va_number' => '0000111122223333',
            'va_number_normalized' => VirtualAccount::normalizeVaNumber('0000111122223333'),
        ]);

        $response = $this->actingAs($this->authorizedUser)->get("/partners/{$partner->id}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('partner.nik', '35**********0001')
            ->where('partner.virtual_accounts.0.va_number', '0000********3333')
            ->where('partner.is_masked', true)
        );
    });
});

describe('Request Validation', function () {
    it('validates search query type and page size', function () {
        $response = $this->actingAs($this->authorizedUser)
            ->get('/partners?query=test&type=invalid_type');

        $response->assertSessionHasErrors(['type']);

        $sizeResponse = $this->actingAs($this->authorizedUser)
            ->get('/partners?per_page=999');

        $sizeResponse->assertSessionHasErrors(['per_page']);
    });
});

describe('JSON API Resource Support', function () {
    it('returns json resource when requested via json accept header', function () {
        $partner = Partner::factory()->create([
            'partner_no_id' => '0001234567',
            'partner_no_id_normalized' => '0001234567',
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->getJson('/partners?query=0001234567&type=no_id');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'partner_no_id',
                    'name',
                    'nik',
                    'phone',
                    'address',
                    'business_type',
                    'region',
                    'verification_state',
                    'verification_badge_label',
                    'is_masked',
                    'aliases',
                    'virtual_accounts',
                    'agreements_count',
                ],
            ],
            'links',
            'meta',
        ]);
        expect($response->json('data.0.partner_no_id'))->toBe('0001234567');
    });
});
