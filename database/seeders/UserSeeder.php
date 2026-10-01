<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed synthetic users representing all PRD §3 roles.
     */
    public function run(): void
    {
        // 1. System Admin
        $admin = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'System Administrator (Synthetic)',
                'role' => Role::SystemAdmin,
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ]
        );
        $admin->role = Role::SystemAdmin;
        $admin->save();

        // 2. Operator
        $operator = User::firstOrCreate(
            ['email' => 'operator@example.test'],
            [
                'name' => 'Operator Staf TJSL (Synthetic)',
                'role' => Role::Operator,
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ]
        );
        $operator->role = Role::Operator;
        $operator->grantPermission(Permission::PartnerView);
        $operator->grantPermission(Permission::AgreementView);
        $operator->grantPermission(Permission::PaymentStage);
        $operator->grantPermission(Permission::PaymentPost);
        $operator->grantPermission(Permission::DocumentView);
        $operator->save();

        // 3. Reconciliation Reviewer
        $reviewer = User::firstOrCreate(
            ['email' => 'reviewer@example.test'],
            [
                'name' => 'Reconciliation Reviewer (Synthetic)',
                'role' => Role::ReconciliationReviewer,
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ]
        );
        $reviewer->role = Role::ReconciliationReviewer;
        $reviewer->grantPermission(Permission::PartnerView);
        $reviewer->grantPermission(Permission::AgreementView);
        $reviewer->grantPermission(Permission::AllocationApprove);
        $reviewer->save();

        // 4. Auditor
        $auditor = User::firstOrCreate(
            ['email' => 'auditor@example.test'],
            [
                'name' => 'Auditor TJSL (Synthetic)',
                'role' => Role::Auditor,
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ]
        );
        $auditor->role = Role::Auditor;
        $auditor->grantPermission(Permission::PartnerView);
        $auditor->grantPermission(Permission::AgreementView);
        $auditor->save();

        // 5. Process Owner
        $processOwner = User::firstOrCreate(
            ['email' => 'process_owner@example.test'],
            [
                'name' => 'Process Owner TJSL (Synthetic)',
                'role' => Role::ProcessOwner,
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ]
        );
        $processOwner->role = Role::ProcessOwner;
        $processOwner->grantPermission(Permission::PartnerView);
        $processOwner->grantPermission(Permission::AgreementView);
        $processOwner->grantPermission(Permission::AgreementActivate);
        $processOwner->grantPermission(Permission::PolicyDefine);
        $processOwner->save();
    }
}
