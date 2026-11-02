<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\DeviceTokenController;
use App\Http\Controllers\Api\V1\FareController;
use App\Http\Controllers\Api\V1\GeoController;
use App\Http\Controllers\Api\V1\JoinRequestController;
use App\Http\Controllers\Api\V1\MatchController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RideOfferController;
use App\Http\Controllers\Api\V1\RideRequestController;
use App\Http\Controllers\Api\V1\RideSearchController;
use App\Http\Controllers\Api\V1\SavedLocationController;
use App\Http\Controllers\Api\V1\TripController;
use App\Http\Controllers\Api\V1\TripTrackingController;
use App\Http\Controllers\Api\V1\VehicleController;
use App\Http\Controllers\Api\V1\VehicleDocumentController;
use App\Http\Controllers\Api\V1\VerificationDocumentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/otp/request', [AuthController::class, 'requestOtp']);
    Route::post('auth/otp/verify', [AuthController::class, 'verifyOtp']);

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('me', [ProfileController::class, 'show']);
        Route::get('users/{user}', [ProfileController::class, 'showPublic'])->whereNumber('user');
        Route::patch('me/profile', [ProfileController::class, 'update']);
        Route::post('me/avatar', [ProfileController::class, 'updateAvatar']);

        Route::get('geo/search', [GeoController::class, 'search']);

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead']);
        Route::delete('notifications/{notification}', [NotificationController::class, 'destroy']);

        Route::post('device-tokens', [DeviceTokenController::class, 'store']);
        Route::delete('device-tokens/{token}', [DeviceTokenController::class, 'destroy']);

        Route::get('reports', [ReportController::class, 'index']);
        Route::post('reports', [ReportController::class, 'store']);

        Route::prefix('admin')->group(function () {
            Route::get('stats', [AdminController::class, 'stats']);

            Route::get('verification-documents', [AdminController::class, 'verificationDocuments']);
            Route::post('verification-documents/{document}/review', [AdminController::class, 'reviewVerification'])
                ->whereNumber('document');
            Route::get('verification-documents/{document}/file', [AdminController::class, 'verificationFile'])
                ->whereNumber('document');

            Route::get('vehicle-documents', [AdminController::class, 'vehicleDocuments']);
            Route::post('vehicle-documents/{document}/review', [AdminController::class, 'reviewVehicleDocument'])
                ->whereNumber('document');
            Route::get('vehicle-documents/{document}/file', [AdminController::class, 'vehicleDocumentFile'])
                ->whereNumber('document');

            Route::get('vehicles', [AdminController::class, 'vehicles']);
            Route::post('vehicles/{vehicle}/review', [AdminController::class, 'reviewVehicle'])
                ->whereNumber('vehicle');

            Route::get('users', [AdminController::class, 'users']);
            Route::post('users/{user}/status', [AdminController::class, 'setUserStatus'])
                ->whereNumber('user');

            Route::get('trips', [AdminController::class, 'trips']);
            Route::get('activity', [AdminController::class, 'activity']);
            Route::get('reports', [AdminController::class, 'reports']);
            Route::post('reports/{report}/resolve', [AdminController::class, 'resolveReport'])
                ->whereNumber('report');
        });

        Route::get('me/documents', [VerificationDocumentController::class, 'index']);
        Route::post('me/documents', [VerificationDocumentController::class, 'store']);
        Route::delete('me/documents/{document}', [VerificationDocumentController::class, 'destroy'])
            ->whereNumber('document');

        Route::apiResource('vehicles', VehicleController::class)->except(['create', 'edit']);
        Route::post('vehicles/{vehicle}/documents', [VehicleDocumentController::class, 'store']);
        Route::delete('vehicle-documents/{document}', [VehicleDocumentController::class, 'destroy'])
            ->whereNumber('document');

        Route::apiResource('saved-locations', SavedLocationController::class)
            ->except(['create', 'edit', 'show'])
            ->parameters(['saved-locations' => 'location']);

        Route::get('ride-requests', [RideRequestController::class, 'index']);
        Route::post('ride-requests', [RideRequestController::class, 'store']);
        Route::get('ride-requests/{rideRequest}/matches', [MatchController::class, 'forRequest'])
            ->whereNumber('rideRequest');
        Route::get('ride-requests/{rideRequest}', [RideRequestController::class, 'show'])
            ->whereNumber('rideRequest');
        Route::patch('ride-requests/{rideRequest}', [RideRequestController::class, 'update'])
            ->whereNumber('rideRequest');
        Route::post('ride-requests/{rideRequest}/cancel', [RideRequestController::class, 'cancel'])
            ->whereNumber('rideRequest');

        Route::get('ride-offers/search', [RideSearchController::class, 'index']);
        Route::get('ride-offers', [RideOfferController::class, 'index']);
        Route::post('ride-offers', [RideOfferController::class, 'store']);
        Route::get('ride-offers/{rideOffer}/matches', [MatchController::class, 'forOffer'])
            ->whereNumber('rideOffer');
        Route::get('ride-offers/{rideOffer}', [RideOfferController::class, 'show'])
            ->whereNumber('rideOffer');
        Route::patch('ride-offers/{rideOffer}', [RideOfferController::class, 'update'])
            ->whereNumber('rideOffer');
        Route::post('ride-offers/{rideOffer}/cancel', [RideOfferController::class, 'cancel'])
            ->whereNumber('rideOffer');

        Route::post('ride-offers/{rideOffer}/join', [JoinRequestController::class, 'storeForOffer'])
            ->whereNumber('rideOffer');
        Route::post('ride-requests/{rideRequest}/join', [JoinRequestController::class, 'storeForRequest'])
            ->whereNumber('rideRequest');

        Route::get('joins/incoming', [JoinRequestController::class, 'incoming']);
        Route::get('joins/outgoing', [JoinRequestController::class, 'outgoing']);
        Route::post('joins/{join}/accept', [JoinRequestController::class, 'accept'])
            ->whereNumber('join');
        Route::post('joins/{join}/decline', [JoinRequestController::class, 'decline'])
            ->whereNumber('join');
        Route::post('joins/{join}/withdraw', [JoinRequestController::class, 'withdraw'])
            ->whereNumber('join');

        Route::get('conversations', [ConversationController::class, 'index']);
        Route::get('conversations/{conversation}/messages', [ConversationController::class, 'messages'])
            ->whereNumber('conversation');
        Route::post('conversations/{conversation}/messages', [ConversationController::class, 'send'])
            ->whereNumber('conversation');
        Route::post('conversations/{conversation}/read', [ConversationController::class, 'read'])
            ->whereNumber('conversation');
        Route::get('trips/{trip}/conversation', [ConversationController::class, 'forTrip'])
            ->whereNumber('trip');

        Route::get('trips', [TripController::class, 'index']);
        Route::get('trips/{trip}/tracking', [TripTrackingController::class, 'show'])
            ->whereNumber('trip');
        Route::post('trips/{trip}/tracking/start', [TripTrackingController::class, 'start'])
            ->whereNumber('trip');
        Route::post('trips/{trip}/tracking/points', [TripTrackingController::class, 'point'])
            ->whereNumber('trip');
        Route::post('trips/{trip}/tracking/stop', [TripTrackingController::class, 'stop'])
            ->whereNumber('trip');
        Route::post('trips/{trip}/start', [TripController::class, 'start'])
            ->whereNumber('trip');
        Route::post('trips/{trip}/complete', [TripController::class, 'complete'])
            ->whereNumber('trip');
        Route::post('trips/{trip}/cancel', [TripController::class, 'cancel'])
            ->whereNumber('trip');
        Route::get('trips/{trip}/events', [TripController::class, 'events'])
            ->whereNumber('trip');
        Route::get('trips/{trip}/fare', [FareController::class, 'show'])
            ->whereNumber('trip');
        Route::put('trips/{trip}/fare', [FareController::class, 'update'])
            ->whereNumber('trip');
        Route::post('trips/{trip}/fare/agree', [FareController::class, 'agree'])
            ->whereNumber('trip');
        Route::post('trips/{trip}/fare/adjustments', [FareController::class, 'proposeAdjustment'])
            ->whereNumber('trip');
        Route::post('trips/{trip}/fare/adjustments/{adjustment}/vote', [FareController::class, 'voteAdjustment'])
            ->whereNumber(['trip', 'adjustment']);
        Route::post('trips/{trip}/fare/contributions/{contribution}/settle', [FareController::class, 'settle'])
            ->whereNumber(['trip', 'contribution']);
        Route::get('trips/{trip}', [TripController::class, 'show'])
            ->whereNumber('trip');
    });
});
