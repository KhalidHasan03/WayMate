<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\PublicProfileResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user()->load('profile', 'roles'));
    }

    public function update(UpdateProfileRequest $request): UserResource
    {
        $data = $request->validated();
        $user = $request->user();

        $user->fill(Arr::only($data, ['name', 'email', 'gender', 'dob', 'locale']));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $profile = $user->profile()->firstOrCreate([]);
        $profile->fill(Arr::only($data, [
            'bio',
            'preferred_vehicle_type',
            'women_only_opt_in',
            'notify_prefs',
        ]));

        if ($request->boolean('accept_terms')) {
            $profile->terms_accepted_at = now();
        }

        if ($request->boolean('accept_cost_share_disclaimer')) {
            $profile->cost_share_disclaimer_at = now();
        }

        $profile->save();

        return new UserResource($user->refresh()->load('profile', 'roles'));
    }

    public function showPublic(User $user): PublicProfileResource
    {
        return new PublicProfileResource($user->loadMissing('profile'));
    }

    public function updateAvatar(Request $request): UserResource|JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $user = $request->user();
        $previous = $user->avatar_path;
        $path = $request->file('avatar')->store('avatars', 'public');

        $user->forceFill(['avatar_path' => $path])->save();

        if ($previous !== null && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        return new UserResource($user->refresh()->load('profile', 'roles'));
    }
}
