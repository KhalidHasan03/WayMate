<?php

namespace App\Domains\Admin\Services;

use App\Domains\Notifications\Services\NotificationService;
use App\Models\Report;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Models\VerificationDocument;
use Illuminate\Support\Facades\DB;

class ModerationService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function reviewVerification(
        VerificationDocument $document,
        User $reviewer,
        string $decision,
        ?string $reason,
    ): VerificationDocument {
        return DB::transaction(function () use ($document, $reviewer, $decision, $reason) {
            $approved = $decision === VerificationDocument::STATUS_APPROVED;

            $document->update([
                'status' => $decision,
                'reviewer_id' => $reviewer->id,
                'reviewed_at' => now(),
                'rejection_reason' => $approved ? null : $reason,
            ]);

            activity('moderation')
                ->causedBy($reviewer)
                ->performedOn($document)
                ->withProperties(['decision' => $decision, 'reason' => $reason])
                ->event('verification_document_reviewed')->log('verification_document_reviewed');

            $user = $document->user;

            if ($user === null) {
                return $document->fresh(['reviewer']);
            }

            if ($approved) {
                if ($document->type === VerificationDocument::TYPE_NID) {
                    $user->forceFill(['nid_verified_at' => now()])->save();
                }

                if ($document->type === VerificationDocument::TYPE_LICENSE) {
                    $user->forceFill(['license_verified_at' => now()])->save();
                }
            }

            $this->notifications->send(
                $user,
                $approved ? 'verification_approved' : 'verification_rejected',
                $approved ? 'Document approved' : 'Document needs attention',
                $approved
                    ? 'Your '.str_replace('_', ' ', $document->type).' document was approved.'
                    : 'Your '.str_replace('_', ' ', $document->type).' document was rejected: '.($reason ?? 'See profile for details.'),
                '/app/profile',
                ['document_id' => $document->id, 'type' => $document->type],
            );

            return $document->fresh(['user', 'reviewer']);
        });
    }

    public function reviewVehicleDocument(
        VehicleDocument $document,
        User $reviewer,
        string $decision,
        ?string $reason,
    ): VehicleDocument {
        $approved = $decision === VehicleDocument::STATUS_APPROVED;

        $document->update([
            'status' => $decision,
            'reviewer_id' => $reviewer->id,
            'reviewed_at' => now(),
            'rejection_reason' => $approved ? null : $reason,
        ]);

        activity('moderation')
            ->causedBy($reviewer)
            ->performedOn($document)
            ->withProperties(['decision' => $decision, 'reason' => $reason])
            ->event('vehicle_document_reviewed')->log('vehicle_document_reviewed');

        $vehicle = $document->vehicle;
        $owner = $vehicle?->user;

        if ($owner) {
            $this->notifications->send(
                $owner,
                $approved ? 'vehicle_document_approved' : 'vehicle_document_rejected',
                $approved ? 'Vehicle document approved' : 'Vehicle document needs attention',
                $approved
                    ? 'Your '.str_replace('_', ' ', $document->type).' document for '.$vehicle->registration_no.' was approved.'
                    : 'Your '.str_replace('_', ' ', $document->type).' document was rejected: '.($reason ?? 'See your vehicle for details.'),
                '/app/vehicles',
                ['document_id' => $document->id, 'vehicle_id' => $vehicle->id],
            );
        }

        return $document->fresh(['vehicle', 'reviewer']);
    }

    public function reviewVehicle(
        Vehicle $vehicle,
        User $reviewer,
        string $decision,
        ?string $reason,
    ): Vehicle {
        $approved = $decision === Vehicle::STATUS_ACTIVE;

        $vehicle->update([
            'status' => $decision,
            'verified_at' => $approved ? now() : null,
            'reviewer_id' => $reviewer->id,
            'rejection_reason' => $approved ? null : $reason,
        ]);

        activity('moderation')
            ->causedBy($reviewer)
            ->performedOn($vehicle)
            ->withProperties(['decision' => $decision, 'reason' => $reason])
            ->event('vehicle_reviewed')->log('vehicle_reviewed');

        if ($vehicle->user === null) {
            return $vehicle->fresh(['documents']);
        }

        $this->notifications->send(
            $vehicle->user,
            $approved ? 'vehicle_approved' : 'vehicle_rejected',
            $approved ? 'Vehicle approved' : 'Vehicle rejected',
            $approved
                ? "{$vehicle->make} {$vehicle->model} ({$vehicle->registration_no}) is now approved for ride offers."
                : "{$vehicle->registration_no} was rejected: ".($reason ?? 'See your vehicles for details.'),
            '/app/vehicles',
            ['vehicle_id' => $vehicle->id],
        );

        return $vehicle->fresh(['user', 'documents']);
    }

    public function setUserStatus(User $user, User $reviewer, string $status, ?string $reason): User
    {
        $user->forceFill([
            'status' => $status,
            'moderation_note' => $status === User::STATUS_ACTIVE ? null : $reason,
        ])->save();

        if ($status !== User::STATUS_ACTIVE) {
            $user->tokens()->delete();
        }

        activity('moderation')
            ->causedBy($reviewer)
            ->performedOn($user)
            ->withProperties(['status' => $status, 'reason' => $reason])
            ->event('user_status_changed')->log('user_status_changed');

        $this->notifications->send(
            $user,
            $status === User::STATUS_ACTIVE ? 'account_restored' : 'account_suspended',
            $status === User::STATUS_ACTIVE ? 'Account restored' : 'Account suspended',
            $status === User::STATUS_ACTIVE
                ? 'Your WayMate account is active again.'
                : 'Your WayMate account was suspended: '.($reason ?? 'Contact support for details.'),
            '/app/profile',
            ['status' => $status, 'reviewer_id' => $reviewer->id],
        );

        return $user->fresh();
    }

    public function resolveReport(
        Report $report,
        User $reviewer,
        string $status,
        ?string $note,
    ): Report {
        $report->update([
            'status' => $status,
            'reviewer_id' => $reviewer->id,
            'resolution_note' => $note,
            'resolved_at' => now(),
        ]);

        activity('moderation')
            ->causedBy($reviewer)
            ->performedOn($report)
            ->withProperties(['status' => $status, 'note' => $note])
            ->event('report_resolved')->log('report_resolved');

        if ($report->reporter === null) {
            return $report->fresh(['reviewer']);
        }

        $this->notifications->send(
            $report->reporter,
            'report_resolved',
            'Report '.$status,
            'Your report was '.$status.($note ? ": {$note}" : '.'),
            '/app/profile',
            ['report_id' => $report->id, 'status' => $status],
        );

        return $report->fresh(['reporter', 'reportedUser', 'reviewer']);
    }
}
