<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /**
     * Show the profile edit page.
     */
    public function edit()
    {
        return view('pages.profile', [
            'title' => 'Profile',
        ]);
    }

    /**
     * Update user profile information.
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $rules = [];
        if ($request->has('first_name') || $request->has('email')) {
            $rules['first_name'] = ['required', 'string', 'max:50'];
            $rules['last_name'] = ['nullable', 'string', 'max:50'];
            $rules['email'] = ['required', 'email', 'max:255', 'unique:users,email,' . $user->id];
        }
        if ($request->has('phone')) $rules['phone'] = ['nullable', 'string', 'max:50'];
        if ($request->has('bio')) $rules['bio'] = ['nullable', 'string', 'max:1000'];
        if ($request->has('facebook')) $rules['facebook'] = ['nullable', 'string', 'max:255'];
        if ($request->has('twitter')) $rules['twitter'] = ['nullable', 'string', 'max:255'];
        if ($request->has('linkedin')) $rules['linkedin'] = ['nullable', 'string', 'max:255'];
        if ($request->has('instagram')) $rules['instagram'] = ['nullable', 'string', 'max:255'];
        if ($request->has('country')) $rules['country'] = ['nullable', 'string', 'max:100'];
        if ($request->has('city_state')) $rules['city_state'] = ['nullable', 'string', 'max:100'];
        if ($request->has('postal_code')) $rules['postal_code'] = ['nullable', 'string', 'max:20'];
        if ($request->has('tax_id')) $rules['tax_id'] = ['nullable', 'string', 'max:50'];
        if ($request->hasFile('avatar')) $rules['avatar'] = ['nullable', 'image', 'max:2048'];

        $request->validate($rules);

        $data = [];
        if ($request->has('first_name')) {
            $data['name'] = trim(($request->first_name ?? '') . ' ' . ($request->last_name ?? ''));
        }
        if ($request->has('email')) {
            $data['email'] = $request->email;
        }

        foreach (['phone', 'bio', 'facebook', 'twitter', 'linkedin', 'instagram', 'country', 'city_state', 'postal_code', 'tax_id'] as $field) {
            if ($request->has($field)) {
                $data[$field] = $request->input($field);
            }
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar && file_exists(public_path($user->avatar))) {
                @unlink(public_path($user->avatar));
            }
            $file = $request->file('avatar');
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            
            if (!file_exists(public_path('uploads/avatars'))) {
                mkdir(public_path('uploads/avatars'), 0777, true);
            }
            
            $file->move(public_path('uploads/avatars'), $filename);
            $data['avatar'] = 'uploads/avatars/' . $filename;
        }

        $user->update($data);

        return back()->with('success', 'Profil Anda berhasil diperbarui.');
    }

    /**
     * Update user password.
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Kata sandi saat ini salah.']);
        }

        $user->update([
            'password' => $request->password,
        ]);

        return back()->with('success', 'Kata sandi Anda berhasil diperbarui.');
    }
}
