<?php

declare(strict_types=1);

use App\Models\Partner;
use App\Models\PartnerAlias;
use App\Models\VirtualAccount;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

describe('Partner model', function () {
    it('creates a partner with UUID primary key', function () {
        $partner = Partner::factory()->create();

        expect($partner->id)->toBeString();
        expect(Str::isUuid($partner->id))->toBeTrue();
    });

    it('preserves leading zeros in partner_no_id', function () {
        $partner = Partner::factory()->create([
            'partner_no_id' => '0001234567',
            'partner_no_id_normalized' => Partner::normalizePartnerNoId('0001234567'),
        ]);

        $retrieved = Partner::find($partner->id);

        expect($retrieved->partner_no_id)->toBe('0001234567');
    });

    it('stores partner_no_id and nik as separate string columns', function () {
        $partner = Partner::factory()->create([
            'partner_no_id' => '0001234567',
            'partner_no_id_normalized' => Partner::normalizePartnerNoId('0001234567'),
            'nik' => '0012345678901234',
            'nik_normalized' => Partner::normalizeNik('0012345678901234'),
        ]);

        $retrieved = Partner::find($partner->id);

        expect($retrieved->partner_no_id)->toBe('0001234567');
        expect($retrieved->nik)->toBe('0012345678901234');
        expect($retrieved->partner_no_id)->not->toBe($retrieved->nik);
    });

    it('enforces unique constraint on partner_no_id_normalized', function () {
        Partner::factory()->create([
            'partner_no_id' => '0001234567',
            'partner_no_id_normalized' => '0001234567',
        ]);

        Partner::factory()->create([
            'partner_no_id' => '0001234567',
            'partner_no_id_normalized' => '0001234567',
        ]);
    })->throws(QueryException::class);

    it('allows multiple null partner_no_id_normalized', function () {
        $first = Partner::factory()->staging()->create();
        $second = Partner::factory()->staging()->create();

        expect($first->partner_no_id_normalized)->toBeNull();
        expect($second->partner_no_id_normalized)->toBeNull();
        expect(Partner::whereNull('partner_no_id_normalized')->count())->toBeGreaterThanOrEqual(2);
    });

    it('defaults version to 1', function () {
        $partner = Partner::factory()->create();

        expect($partner->version)->toBe(1);
    });

    it('defaults verification_state to unverified', function () {
        $partner = Partner::factory()->create();

        expect($partner->verification_state)->toBe('unverified');
    });
});

describe('Partner normalization', function () {
    it('normalizes partner_no_id preserving leading zeros', function () {
        expect(Partner::normalizePartnerNoId('  0001234567  '))->toBe('0001234567');
        expect(Partner::normalizePartnerNoId('abc-001'))->toBe('ABC-001');
        expect(Partner::normalizePartnerNoId(null))->toBeNull();
        expect(Partner::normalizePartnerNoId(''))->toBeNull();
        expect(Partner::normalizePartnerNoId('   '))->toBeNull();
    });

    it('normalizes nik preserving leading zeros', function () {
        expect(Partner::normalizeNik('  0012345678901234  '))->toBe('0012345678901234');
        expect(Partner::normalizeNik(null))->toBeNull();
        expect(Partner::normalizeNik(''))->toBeNull();
    });
});

describe('Partner leading-zero round-trip (gate test)', function () {
    it('performs exact-match lookup on partner_no_id_normalized', function () {
        $noId = '0001234567';

        Partner::factory()->create([
            'partner_no_id' => $noId,
            'partner_no_id_normalized' => Partner::normalizePartnerNoId($noId),
            'name' => 'Synthetic Partner A',
        ]);

        Partner::factory()->create([
            'partner_no_id' => '9999999999',
            'partner_no_id_normalized' => Partner::normalizePartnerNoId('9999999999'),
            'name' => 'Synthetic Partner B',
        ]);

        $result = Partner::where('partner_no_id_normalized', Partner::normalizePartnerNoId($noId))->first();

        expect($result)->not->toBeNull();
        expect($result->partner_no_id)->toBe('0001234567');
        expect($result->name)->toBe('Synthetic Partner A');
    });
});

describe('PartnerAlias model', function () {
    it('belongs to a partner', function () {
        $partner = Partner::factory()->create();
        $alias = PartnerAlias::factory()->create(['partner_id' => $partner->id]);

        expect($alias->partner->id)->toBe($partner->id);
    });

    it('has name_raw and name_normalized as distinct fields', function () {
        $alias = PartnerAlias::factory()->create([
            'name_raw' => 'Budi Santoso',
            'name_normalized' => PartnerAlias::normalizeName('Budi Santoso'),
        ]);

        $retrieved = PartnerAlias::find($alias->id);

        expect($retrieved->name_raw)->toBe('Budi Santoso');
        expect($retrieved->name_normalized)->toBe('budi santoso');
    });

    it('normalizes name for search', function () {
        expect(PartnerAlias::normalizeName('  BUDI Santoso  '))->toBe('budi santoso');
    });

    it('defaults state to unreviewed', function () {
        $alias = PartnerAlias::factory()->create();

        expect($alias->state)->toBe('unreviewed');
    });
});

describe('VirtualAccount model', function () {
    it('belongs to a partner', function () {
        $partner = Partner::factory()->create();
        $va = VirtualAccount::factory()->create(['partner_id' => $partner->id]);

        expect($va->partner->id)->toBe($partner->id);
    });

    it('stores va_number as string preserving leading zeros', function () {
        $vaNumber = '0000000012345678';
        $va = VirtualAccount::factory()->create([
            'va_number' => $vaNumber,
            'va_number_normalized' => VirtualAccount::normalizeVaNumber($vaNumber),
        ]);

        $retrieved = VirtualAccount::find($va->id);

        expect($retrieved->va_number)->toBe('0000000012345678');
    });

    it('has va_number and va_number_normalized as distinct fields', function () {
        $va = VirtualAccount::factory()->create([
            'va_number' => '  0000000012345678  ',
            'va_number_normalized' => VirtualAccount::normalizeVaNumber('  0000000012345678  '),
        ]);

        $retrieved = VirtualAccount::find($va->id);

        expect($retrieved->va_number)->toBe('  0000000012345678  ');
        expect($retrieved->va_number_normalized)->toBe('0000000012345678');
    });

    it('normalizes va_number by trimming', function () {
        expect(VirtualAccount::normalizeVaNumber('  0001234567890123  '))->toBe('0001234567890123');
    });
});

describe('Partner relationships', function () {
    it('has many aliases', function () {
        $partner = Partner::factory()->create();
        PartnerAlias::factory()->count(3)->create(['partner_id' => $partner->id]);

        expect($partner->aliases)->toHaveCount(3);
        expect($partner->aliases->first())->toBeInstanceOf(PartnerAlias::class);
    });

    it('has many virtual accounts', function () {
        $partner = Partner::factory()->create();
        VirtualAccount::factory()->count(2)->create(['partner_id' => $partner->id]);

        expect($partner->virtualAccounts)->toHaveCount(2);
        expect($partner->virtualAccounts->first())->toBeInstanceOf(VirtualAccount::class);
    });

    it('cascades delete to aliases and virtual accounts', function () {
        $partner = Partner::factory()->create();
        PartnerAlias::factory()->count(2)->create(['partner_id' => $partner->id]);
        VirtualAccount::factory()->count(2)->create(['partner_id' => $partner->id]);

        $partnerId = $partner->id;
        $partner->delete();

        expect(PartnerAlias::where('partner_id', $partnerId)->count())->toBe(0);
        expect(VirtualAccount::where('partner_id', $partnerId)->count())->toBe(0);
    });
});
