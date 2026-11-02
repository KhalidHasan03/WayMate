<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Admin\Services\ModerationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\ModerationDecisionRequest;
use App\Http\Requests\ResolveReportRequest;
use App\Http\Requests\UserStatusRequest;
use App\Http\Resources\AdminVehicleDocumentResource;
use App\Http\Resources\AdminVerificationDocumentResource;
use App\Http\Resources\ReportResource;
use App\Http\Resources\TripResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\VehicleResource;
use App\Models\Report;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Models\VerificationDocument;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $this->allow($request, 'reports.view');

        return response()->json([
            'data' => [
                'users' => [
                    'total' => User::query()->count(),
                    'active' => User::query()->where('status', User::STATUS_ACTIVE)->count(),
                    'suspended' => User::query()->where('status', User::STATUS_SUSPENDED)->count(),
                ],
                'pending_verifications' => VerificationDocument::query()
                    ->where('status', VerificationDocument::STATUS_PENDING)->count(),
                'pending_vehicle_documents' => VehicleDocument::query()
                    ->where('status', VehicleDocument::STATUS_PENDING)->count(),
                'pending_vehicles' => Vehicle::query()->where('status', Vehicle::STATUS_PENDING)->count(),
                'open_reports' => Report::query()
                    ->whereIn('status', [Report::STATUS_OPEN, Report::STATUS_REVIEWING])->count(),
                'trips' => [
                    'total' => Trip::query()->count(),
                    'in_progress' => Trip::query()->where('status', Trip::STATUS_IN_PROGRESS)->count(),
                    'completed' => Trip::query()->where('status', Trip::STATUS_COMPLETED)->count(),
                    'cancelled' => Trip::query()->where('status', Trip::STATUS_CANCELLED)->count(),
                ],
                'tracking_active' => Trip::query()->where('tracking_enabled', true)->count(),
            ],
        ]);
    }

    public function verificationDocuments(Request $request): AnonymousResourceCollection
    {
        $this->allow($request, 'verification.review');

        $status = $request->string('status', VerificationDocument::STATUS_PENDING)->toString();

        $documents = VerificationDocument::query()
            ->with(['user', 'reviewer'])
            ->where('status', $status)
            ->latest()
            ->paginate(30);

        return AdminVerificationDocumentResource::collection($documents);
    }

    public function reviewVerification(
        ModerationDecisionRequest $request,
        VerificationDocument $document,
        ModerationService $moderation,
    ): AdminVerificationDocumentResource {
        $this->allow($request, 'verification.review');

        $status = $request->string('decision')->toString() === 'approve'
            ? VerificationDocument::STATUS_APPROVED
            : VerificationDocument::STATUS_REJECTED;

        $document = $moderation->reviewVerification(
            $document,
            $request->user(),
            $status,
            $request->input('reason'),
        );

        return new AdminVerificationDocumentResource($document);
    }

    public function verificationFile(Request $request, VerificationDocument $document)
    {
        $this->allow($request, 'verification.review');

        return $this->fileResponse($document->file_path);
    }

    public function vehicleDocuments(Request $request): AnonymousResourceCollection
    {
        $this->allow($request, 'verification.review');

        $status = $request->string('status', VehicleDocument::STATUS_PENDING)->toString();

        $documents = VehicleDocument::query()
            ->with(['vehicle.user', 'reviewer'])
            ->where('status', $status)
            ->latest()
            ->paginate(30);

        return AdminVehicleDocumentResource::collection($documents);
    }

    public function reviewVehicleDocument(
        ModerationDecisionRequest $request,
        VehicleDocument $document,
        ModerationService $moderation,
    ): AdminVehicleDocumentResource {
        $this->allow($request, 'verification.review');

        $status = $request->string('decision')->toString() === 'approve'
            ? VehicleDocument::STATUS_APPROVED
            : VehicleDocument::STATUS_REJECTED;

        $document = $moderation->reviewVehicleDocument(
            $document,
            $request->user(),
            $status,
            $request->input('reason'),
        );

        return new AdminVehicleDocumentResource($document);
    }

    public function vehicleDocumentFile(Request $request, VehicleDocument $document)
    {
        $this->allow($request, 'verification.review');

        return $this->fileResponse($document->file_path);
    }

    public function vehicles(Request $request): AnonymousResourceCollection
    {
        $this->allow($request, 'verification.review');

        $status = $request->string('status', Vehicle::STATUS_PENDING)->toString();

        $vehicles = Vehicle::query()
            ->with(['user', 'documents'])
            ->where('status', $status)
            ->latest()
            ->paginate(30);

        return VehicleResource::collection($vehicles);
    }

    public function reviewVehicle(
        ModerationDecisionRequest $request,
        Vehicle $vehicle,
        ModerationService $moderation,
    ): VehicleResource {
        $this->allow($request, 'verification.review');

        $status = $request->string('decision')->toString() === 'approve'
            ? Vehicle::STATUS_ACTIVE
            : Vehicle::STATUS_REJECTED;

        $vehicle = $moderation->reviewVehicle(
            $vehicle,
            $request->user(),
            $status,
            $request->input('reason'),
        );

        return new VehicleResource($vehicle);
    }

    public function users(Request $request): AnonymousResourceCollection
    {
        $this->allow($request, 'user.suspend');

        $query = User::query()->with('roles')->withCount('vehicles');

        if ($search = $request->string('q')->trim()->toString()) {
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%"));
        }

        if ($status = $request->string('status')->trim()->toString()) {
            $query->where('status', $status);
        }

        return UserResource::collection($query->latest()->paginate(30));
    }

    public function setUserStatus(
        UserStatusRequest $request,
        User $user,
        ModerationService $moderation,
    ): UserResource {
        $this->allow($request, 'user.suspend');

        abort_if($user->id === $request->user()->id, 422, 'You cannot change your own account status.');

        $user = $moderation->setUserStatus(
            $user,
            $request->user(),
            $request->string('status')->toString(),
            $request->input('reason'),
        );

        return new UserResource($user->load('roles', 'profile'));
    }

    public function trips(Request $request): AnonymousResourceCollection
    {
        $this->allow($request, 'reports.view');

        $trips = Trip::query()
            ->with([
                'coordinator',
                'provider',
                'vehicle',
                'participants.user',
                'rideRequest',
                'rideOffer.provider',
                'rideOffer.vehicle',
            ])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->trim()->toString();

                $query->where(function ($builder) use ($search) {
                    $builder->where('id', $search)
                        ->orWhereHas('coordinator', fn ($user) => $user
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%"))
                        ->orWhereHas('provider', fn ($user) => $user
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(30);

        return TripResource::collection($trips);
    }

    public function activity(Request $request): JsonResponse
    {
        $this->allow($request, 'reports.view');

        $logs = Activity::query()
            ->with(['causer', 'subject'])
            ->when($request->filled('log'), fn ($query) => $query->where('log_name', $request->string('log')))
            ->latest()
            ->paginate(50);

        return response()->json([
            'data' => collect($logs->items())->map(fn (Activity $activity) => [
                'id' => $activity->id,
                'log' => $activity->log_name,
                'event' => $activity->event,
                'description' => $activity->description,
                'properties' => $activity->properties,
                'causer' => $activity->causer ? [
                    'id' => $activity->causer->id,
                    'name' => $activity->causer->name,
                ] : null,
                'subject' => [
                    'type' => $activity->subject_type,
                    'id' => $activity->subject_id,
                ],
                'created_at' => $activity->created_at?->toIso8601String(),
            ])->all(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    public function reports(Request $request): AnonymousResourceCollection
    {
        $this->allow($request, 'reports.view');

        $status = $request->string('status')->trim()->toString();

        $reports = Report::query()
            ->with(['reporter', 'reportedUser', 'reviewer'])
            ->when(
                $status !== '',
                fn ($query) => $query->where('status', $status),
                fn ($query) => $query->whereIn('status', [Report::STATUS_OPEN, Report::STATUS_REVIEWING]),
            )
            ->latest()
            ->paginate(30);

        return ReportResource::collection($reports);
    }

    public function resolveReport(
        ResolveReportRequest $request,
        Report $report,
        ModerationService $moderation,
    ): ReportResource {
        $this->allow($request, 'reports.view');

        $report = $moderation->resolveReport(
            $report,
            $request->user(),
            $request->string('status')->toString(),
            $request->input('note'),
        );

        return new ReportResource($report);
    }

    private function allow(Request $request, string $permission): void
    {
        abort_unless($request->user()?->can($permission), 403, 'You do not have permission for this action.');
    }

    private function fileResponse(string $path)
    {
        $disk = Storage::disk('local');

        abort_unless($disk->exists($path), 404, 'Document file not found.');

        return response()->file($disk->path($path));
    }
}
