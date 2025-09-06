<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PrayerRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PrayerRequestController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum')->except(['index']);
        $this->middleware('admin')->only(['indexAdmin', 'update', 'destroy']);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $requests = PrayerRequest::where('is_public', true)
                               ->where('is_approved', true)
                               ->with('user:id,name') // Load user name
                               ->orderBy('created_at', 'desc')
                               ->get();

        return response()->json($requests);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'request_text' => 'required|string|max:1000',
            'is_public' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $prayerRequest = PrayerRequest::create([
            'user_id' => Auth::id(),
            'request_text' => $request->request_text,
            'is_public' => $request->is_public,
            'is_approved' => false, // New requests are not approved by default
        ]);

        return response()->json($prayerRequest, 201);
    }

    /**
     * Display the specified resource.
     */
    public function indexAdmin()
    {
        $requests = PrayerRequest::with('user:id,name')
                               ->orderBy('created_at', 'desc')
                               ->get();

        return response()->json($requests);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $prayerRequest = PrayerRequest::find($id);

        if (is_null($prayerRequest)) {
            return response()->json(['message' => 'Prayer Request not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'is_public' => 'sometimes|required|boolean',
            'is_approved' => 'sometimes|required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $prayerRequest->update($request->only(['is_public', 'is_approved']));

        return response()->json($prayerRequest);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $prayerRequest = PrayerRequest::find($id);

        if (is_null($prayerRequest)) {
            return response()->json(['message' => 'Prayer Request not found'], 404);
        }

        $prayerRequest->delete();

        return response()->json(null, 204);
    }
}
