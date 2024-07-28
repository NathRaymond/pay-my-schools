<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Models\School;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Paystack;
use Yansongda\Pay\Pay;

class SchoolController extends Controller
{
    public function showForm()
    {
        return view('school.register');
    }
    public function verifyPayment(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:users,email|unique:schools,email',
            'phone' => 'required|unique:users,phone|unique:schools,phone',
            'address' => 'required',
            'name' => 'required|string|max:255',
            'p_name' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'lga' => 'required|string|max:255',
            'reference' => 'required|string',
        ]);

        try {
            // Save in school table
            $schoolData = $request->all();
            // dd($schoolData);
            $school = School::create($schoolData);

            $reference = $request->reference;

            // Verify the transaction
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('PAYSTACK_SECRET_KEY')
            ])->get('https://api.paystack.co/transaction/verify/' . $reference);

            if ($response->successful()) {
                $data = $response->json();

                if ($data['data']['status'] === 'success') {
                    // Save in user table if payment is successful
                    $userData = $request->all();
                    $userData['name'] = $request->name;
                    $userData['school_id'] = $school->id;
                    User::create($userData);

                    return response()->json(['success' => true, 'message' => 'Payment verified and records saved successfully.']);
                } else {
                    return response()->json(['success' => false, 'message' => 'Payment verification failed: ' . $data['data']['gateway_response']], 400);
                }
            } else {
                return response()->json(['success' => false, 'message' => 'Payment verification failed: Unable to reach payment gateway.'], 400);
            }
        } catch (\Exception $exception) {
            Log::error('Payment verification error: ' . $exception->getMessage());
            return response()->json(['success' => false, 'message' => 'An error occurred: ' . $exception->getMessage()], 400);
        }
    }
}