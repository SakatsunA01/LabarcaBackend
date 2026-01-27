<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PromoInquiry;
use Illuminate\Http\Request;

class PromoInquiryController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'company' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $inquiry = PromoInquiry::create($data);

        return response()->json([
            'message' => 'Consulta enviada.',
            'inquiry' => $inquiry,
        ], 201);
    }

    public function index()
    {
        return response()->json(PromoInquiry::query()->latest()->get());
    }

    public function destroy(PromoInquiry $promoInquiry)
    {
        $promoInquiry->delete();
        return response()->json(null, 204);
    }
}
