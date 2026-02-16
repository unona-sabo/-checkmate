<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        $slug = Str::slug($user->name.'-workspace');
        $baseSlug = $slug;
        $counter = 1;
        while (Workspace::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        $workspace = Workspace::create([
            'name' => $user->name."'s Workspace",
            'slug' => $slug,
            'owner_id' => $user->id,
        ]);

        $workspace->members()->attach($user->id, ['role' => WorkspaceRole::Owner->value]);
        $user->update(['current_workspace_id' => $workspace->id]);

        return $user;
    }
}
