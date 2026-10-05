<?php

declare(strict_types=1);

use App\Services\MaskingService;

describe('MaskingService VA Masking', function () {
    beforeEach(function () {
        $this->service = new MaskingService;
    });

    it('returns null for null or empty va', function () {
        expect($this->service->maskVa(null))->toBeNull()
            ->and($this->service->maskVa(''))->toBeNull();
    });

    it('masks short va numbers <= 8 characters with asterisks', function () {
        expect($this->service->maskVa('12345678'))->toBe('********')
            ->and($this->service->maskVa('1234'))->toBe('****');
    });

    it('masks standard va preserving first 4 and last 4 characters', function () {
        expect($this->service->maskVa('880012345678'))->toBe('8800****5678')
            ->and($this->service->maskVa('0000111122223333'))->toBe('0000********3333');
    });

    it('ensures maskVa produces identical output to maskVaNumber', function () {
        $va = '9876543210123456';
        expect($this->service->maskVa($va))->toBe($this->service->maskVaNumber($va));
    });
});
