<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PressInquiry;
use Illuminate\Http\Request;

class PressInquiryController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'birth_date' => ['required', 'date'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'media_station' => ['required', 'string', 'max:255'],
            'media_position' => ['required', 'string', 'max:255'],
            'belongs_to_church' => ['required', 'boolean'],
            'church_name' => ['nullable', 'string', 'max:255'],
            'pastor_name' => ['nullable', 'string', 'max:255'],
            'program_slots' => ['required', 'array', 'min:1'],
            'program_slots.*.type' => ['required', 'string', 'max:50'],
            'program_slots.*.value' => ['required', 'string', 'max:255'],
            'program_slots.*.start_time' => ['nullable', 'string', 'max:20'],
            'program_slots.*.end_time' => ['nullable', 'string', 'max:20'],
        ]);

        $inquiry = PressInquiry::create($data);

        return response()->json([
            'message' => 'Inscripcion enviada.',
            'inquiry' => $inquiry,
        ], 201);
    }

    public function index()
    {
        return response()->json(PressInquiry::query()->latest()->get());
    }

    public function destroy(PressInquiry $pressInquiry)
    {
        $pressInquiry->delete();
        return response()->json(null, 204);
    }
}
