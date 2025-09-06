<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Eager load prayer requests count to potentially show user activity
        $users = User::withCount('prayerRequests')->get();
        return response()->json($users);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = User::find($id);

        if (is_null($user)) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'admin_sn' => 'sometimes|required|boolean',
            // Add other fields that can be updated by an admin here
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        // Prevent admin from accidentally removing their own admin status
        if ($request->user()->id == $user->id && $request->has('admin_sn') && !$request->admin_sn) {
            return response()->json(['message' => 'You cannot remove your own admin status.'], 400);
        }

        $user->update($request->only(['admin_sn']));

        return response()->json($user);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = User::find($id);

        if (is_null($user)) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Prevent admin from deleting themselves
        if (auth()->user()->id == $id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 400);
        }

        $user->delete();

        return response()->json(null, 204);
    }
}
