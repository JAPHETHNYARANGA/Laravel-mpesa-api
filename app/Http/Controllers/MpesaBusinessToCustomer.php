<?php

namespace App\Http\Controllers;

use App\Services\MpesaAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use function response;
use Illuminate\Http\JsonResponse;

class MpesaBusinessToCustomer extends Controller
{
    // Method to initiate the B2C payment request
    public function initiateB2C(Request $request)
    {
        try {
            // Static values to be added to the requestData array
            $requestData['transaction_type'] = 'CustomerPayBillOnline';
            $requestData['organization_code'] = env('SHORTCODE');
            $requestData['account_reference'] = $request->userId;
            $requestData['consumer_key'] = env('CONSUMER_KEY');
            $requestData['consumer_secret'] = env('CONSUMER_SECRET');
            $requestData['shortcode'] = env('SHORTCODE');
            $requestData['passkey'] = env('PASSKEY');

            $consumer_key = $requestData['consumer_key'];
            $consumer_secret = $requestData['consumer_secret'];

            // Validate the incoming request data (for security and correctness)
            $validated = $request->validate([
                'amount' => 'required|numeric|min:1',
                'party_b' => 'required|numeric|digits:12', // Customer phone number (12 digits, including the country code)
                'remarks' => 'nullable|string',
                'occasion' => 'nullable|string',
            ]);

            // Static values from environment (defined in .env file)
            $initiatorName = 'testapi';  // Initiator username
            $securityCredential = '';    // This should be encrypted Initiator password (see encryption method below)
            $commandID = 'BusinessPayment';  // The command ID for business payments
            $queueTimeoutURL = env('TIMEOUT_URL'); // URL to be called if the transaction times out
            $resultURL = env('RESULT_URL');  // URL to be called when the result is returned

            // Use the shortcode directly for party_a (Business shortcode)
            $partyA = $requestData['shortcode'];  // The business shortcode

            // Sanitize and format msisdn (phone number) to ensure it's in the correct format
            $partyB = $this->sanitizeAndFormatMobile($validated['party_b']);

            // Access token needed to authenticate with the Safaricom API
            $accessToken = $this->getAccessToken($consumer_key, $consumer_secret);

            if (!$accessToken) {
                return response()->json(['error' => 'Access token is required'], 400);
            }

            // Use the access token string
            $accessTokenString = $accessToken;  // If it's already a string, use it directly

            // Prepare the data to be sent to Safaricom API
            $requestData = [
                "InitiatorName" => $initiatorName,
                "SecurityCredential" => $securityCredential, // SecurityCredential needs to be encrypted
                "CommandID" => $commandID,
                "Amount" => $validated['amount'],
                "PartyA" => $partyA,  // The business shortcode
                "PartyB" => $partyB,  // The customer's phone number
                "Remarks" => $validated['remarks'] ?? "Payment",  // Custom remarks or default to "Payment"
                "QueueTimeOutURL" => $queueTimeoutURL,  // URL for timeout
                "ResultURL" => $resultURL,  // URL for result
                "Occasion" => $validated['occasion'] ?? '',  // Optional occasion info
            ];

            // Make the API request to initiate the B2C payment
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessTokenString,
                'Content-Type' => 'application/json',
            ])->post('https://api.safaricom.co.ke/mpesa/b2c/v3/paymentrequest', [
                "InitiatorName" => $initiatorName,
                "SecurityCredential" => $securityCredential, // Encrypted password
                "CommandID" => $commandID,
                "Amount" => $validated['amount'],
                "PartyA" => $partyA,  // Business shortcode
                "PartyB" => $partyB,  // Customer's phone number
                "Remarks" => $validated['remarks'] ?? "Payment",
                "QueueTimeOutURL" => $queueTimeoutURL,
                "ResultURL" => $resultURL,
                "Occasion" => $validated['occasion'] ?? '',
            ]);
            
            // Log the response for debugging
            Log::channel('mpesa')->info('Response from Safaricom API: ' . json_encode($response->json()));
            
            return response()->json($response->json());
            

        } catch (\Exception $e) {
            Log::channel('mpesa')->error('Error fetching transaction: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching transaction: ' . $e->getMessage()
            ], 500);
        }
    }

    // Helper method to sanitize and format phone numbers
    public function sanitizeAndFormatMobile(string $mobile): string
    {
        $mobile = preg_replace('/[^0-9]/', '', $mobile);

        if (substr($mobile, 0, 1) === '0') {
            $mobile = '254' . substr($mobile, 1);
        }

        return $mobile;
    }

    // Method to fetch the access token for API authentication
    private function getAccessToken(string $consumerKey, string $consumerSecret): string|JsonResponse
    {
        $url = env('SAF_AUTH_URL');
        $mpesaAuthService = new MpesaAuthService;

        $response = $mpesaAuthService->generateAccessToken($url, $consumerKey, $consumerSecret);

        if (isset($response['error'])) {
            $errorMessage = $response['error'];

            Log::channel('mpesa')->error("STK- Failed to fetch access token: $errorMessage");

            return response()->json(['error' => $errorMessage], 400);
        }

        // Assuming response contains 'access_token'
        return $response['access_token'] ?? null;
    }
}
