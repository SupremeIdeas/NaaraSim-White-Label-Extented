<?php

namespace App\Services\Updater;

use App\Models\WhiteLabelInstance;
use App\Models\WhiteLabelProjectIntake;
use App\Notifications\WhiteLabelDeploymentReadyNotification;
use App\Support\Auditor;
use App\Support\Mailer;

/**
 * Prompt 21-EXT2 §3 — the project-commencement brief a merchant files once
 * their license is active, and the admin review/deploy-timeline actions on
 * top of it. Kept as its own service (not folded into WhiteLabelLicenseService)
 * since none of this touches money, keys, or tokens — it's a separate concern
 * that only happens to hang off the same instance.
 */
class WhiteLabelProjectIntakeService
{
    /**
     * Merchant-facing: file (or refile, before it's been reviewed) the
     * commencement brief. Only reachable once the instance actually has a
     * live license — a request that hasn't been paid for has nothing to
     * commence yet.
     *
     * @param  array{desired_brand_name:string,whatsapp_number:string,hosting_choice:string,brand_primary_color?:?string,brand_accent_color?:?string,logo_url?:?string,logo_design_reference?:?string,banner_reference_url?:?string,banner_design_request?:?string,hosting_disclaimer_acknowledged?:bool,hosting_host?:?string,hosting_username?:?string,hosting_password?:?string,hosting_notes?:?string,additional_notes?:?string}  $data
     */
    public function submit(WhiteLabelInstance $instance, array $data): WhiteLabelProjectIntake
    {
        if (! $instance->hasLiveLicense()) {
            throw new WhiteLabelProjectIntakeException('not_licensed');
        }

        $selfHosted = in_array($data['hosting_choice'], [
            WhiteLabelInstance::HOSTING_OWN_VPS, WhiteLabelInstance::HOSTING_OWN_SHARED,
        ], true);

        $intake = WhiteLabelProjectIntake::updateOrCreate(
            ['white_label_instance_id' => $instance->id],
            [
                'desired_brand_name' => trim($data['desired_brand_name']),
                'whatsapp_number' => trim($data['whatsapp_number']),
                'brand_primary_color' => $data['brand_primary_color'] ?? null,
                'brand_accent_color' => $data['brand_accent_color'] ?? null,
                'logo_url' => $data['logo_url'] ?? null,
                'logo_design_reference' => $data['logo_design_reference'] ?? null,
                'banner_reference_url' => $data['banner_reference_url'] ?? null,
                'banner_design_request' => $data['banner_design_request'] ?? null,
                'hosting_choice' => $data['hosting_choice'],
                'hosting_disclaimer_acknowledged_at' => $selfHosted && ! empty($data['hosting_disclaimer_acknowledged']) ? now() : null,
                'hosting_host' => $selfHosted ? ($data['hosting_host'] ?? null) : null,
                'hosting_username' => $selfHosted ? ($data['hosting_username'] ?? null) : null,
                'hosting_password' => $selfHosted ? ($data['hosting_password'] ?? null) : null,
                'hosting_notes' => $selfHosted ? ($data['hosting_notes'] ?? null) : null,
                'additional_notes' => $data['additional_notes'] ?? null,
                'status' => WhiteLabelProjectIntake::STATUS_PENDING,
            ],
        );

        Auditor::log('white_label.project_intake_submitted', WhiteLabelInstance::class, $instance->id, [
            'hosting_choice' => $intake->hosting_choice,
        ]);

        return $intake;
    }

    /** Admin-facing: acknowledge receipt of the brief before scheduling a deploy. */
    public function markSeen(WhiteLabelProjectIntake $intake, int $reviewerId): WhiteLabelProjectIntake
    {
        $intake->forceFill([
            'status' => WhiteLabelProjectIntake::STATUS_SEEN,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ])->save();

        Auditor::log('white_label.project_intake_seen', WhiteLabelProjectIntake::class, $intake->id, []);

        return $intake;
    }

    /**
     * Admin-facing: start the autopilot deploy timeline. Requires the intake
     * to already be Seen — scheduling a deploy on a brief nobody reviewed
     * would be premature.
     */
    public function setDeployTimeline(WhiteLabelProjectIntake $intake, int $days): WhiteLabelProjectIntake
    {
        if ($intake->status !== WhiteLabelProjectIntake::STATUS_SEEN) {
            throw new WhiteLabelProjectIntakeException('not_seen_yet');
        }
        if ($days <= 0) {
            throw new WhiteLabelProjectIntakeException('invalid_timeline');
        }

        $intake->forceFill([
            'status' => WhiteLabelProjectIntake::STATUS_IN_PROGRESS,
            'deploy_days' => $days,
            'deploy_started_at' => now(),
            'deploy_completed_at' => null,
        ])->save();

        Auditor::log('white_label.project_deploy_timeline_set', WhiteLabelProjectIntake::class, $intake->id, ['days' => $days]);

        return $intake;
    }

    /** The single source of truth both the merchant dashboard and the admin
     *  panel read deploy progress from. Null until a timeline is set. */
    public function progressPercent(WhiteLabelProjectIntake $intake): ?int
    {
        if ($intake->deploy_started_at === null || $intake->deploy_days === null) {
            return null;
        }

        $totalSeconds = $intake->deploy_days * 86400;
        $elapsedSeconds = now()->diffInSeconds($intake->deploy_started_at, absolute: true);

        return (int) min(100, round($elapsedSeconds / $totalSeconds * 100));
    }

    /** Whole elapsed/total days, for "Day X of Y" text — same inputs as
     *  progressPercent(), never a second computation of elapsed time. */
    public function dayOf(WhiteLabelProjectIntake $intake): ?array
    {
        if ($intake->deploy_started_at === null || $intake->deploy_days === null) {
            return null;
        }

        $elapsedDays = (int) min($intake->deploy_days, floor(now()->diffInSeconds($intake->deploy_started_at, absolute: true) / 86400) + 1);

        return ['day' => $elapsedDays, 'of' => $intake->deploy_days];
    }

    /**
     * Prompt 21-EXT2 §6 — flip an elapsed `in_progress` intake to `completed`
     * and notify the merchant. Idempotent: a no-op if it isn't `in_progress`
     * or hasn't actually reached 100% yet, so the scheduled command can call
     * this on every in-progress row every day without double-completing or
     * double-notifying.
     */
    public function completeIfElapsed(WhiteLabelProjectIntake $intake): bool
    {
        if ($intake->status !== WhiteLabelProjectIntake::STATUS_IN_PROGRESS) {
            return false;
        }
        if (($this->progressPercent($intake) ?? 0) < 100) {
            return false;
        }

        $intake->forceFill([
            'status' => WhiteLabelProjectIntake::STATUS_COMPLETED,
            'deploy_completed_at' => now(),
        ])->save();

        Auditor::log('white_label.project_deploy_completed', WhiteLabelProjectIntake::class, $intake->id, []);

        $owner = $intake->instance?->owner;
        if ($owner !== null) {
            Mailer::notify($owner, new WhiteLabelDeploymentReadyNotification($intake));
        }

        return true;
    }
}
