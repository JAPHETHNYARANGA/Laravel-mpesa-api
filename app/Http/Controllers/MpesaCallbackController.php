<?php

namespace App\Http\Controllers;

use App\Models\success_b2c_transactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Services\MpesaCallBackService;
use Illuminate\Support\Facades\Validator;
use App\Services\MpesaCallbackRegistrationService;
use function response;


class MpesaCallbackController extends Controller
{
    //

    /**
     * Handle the registration of M-PESA callback URLs.
     *
     * @param \Illuminate\Http\Request $request The incoming request containing callback URL data.
     * 
     * @return \Illuminate\Http\JsonResponse A JSON response with the result of the registration.
     */


    public function registerCallback(Request $request): JsonResponse
    {


        $validateData = Validator::make($request->all(), [
            'confirmation_url' => 'required|url',
            'validation_url' => 'required|url',
            'consumer_key' => 'required|string',
            'consumer_secret' => 'required|string',
            'shortcode' => 'required|numeric'
        ]);

        if ($validateData->fails()) {
            return response()->json($validateData->errors(), 422);
        }

        $requestData = $validateData->validated();

        $callbackUrlData = [
            'confirmation_url' => $requestData['confirmation_url'],
            'validation_url' => $requestData['validation_url'],
            'consumer_key' => $requestData['consumer_key'],
            'consumer_secret' => $requestData['consumer_secret'],
            'shortcode' => $requestData['shortcode'],
        ];

        $mpesaCallbackRegistration = new MpesaCallbackRegistrationService;


        $response = $mpesaCallbackRegistration->registerCallBackUrl($callbackUrlData);

        if (isset($response['error'])) {
            Log::channel('mpesa')->error('Callback URL registration failed: ' . $response['error']);

            return response()->json([
                'status' => 'error',
                'message' => $response['error']
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'message' => $response['success'] ?? 'Callback URL registered successfully'
        ], 200);
    }


    /**
     * Handles the M-PESA C2B callback.
 
     * @param \Illuminate\Http\Request $request The incoming HTTP request containing the callback data.
     * 
     * @return \Illuminate\Http\JsonResponse The response from the `MpesaCallBackService` after processing the callback data.
     */

    public function handlec2bCallback(Request $request): JsonResponse
    {

        Log::channel('app')->info('CallBack_Initiated: ' . json_encode($request->all()));

        $mpesaData = $request->all();

        $mpesaCallBackService = new MpesaCallBackService();

        $response = $mpesaCallBackService->handleCallBackData($mpesaData);

        if (isset($response['error'])) {
            return response()->json([
                'status' => 'error',
                'message' => $response['error']
            ], 400);
        }

        return response()->json([
            'status' => 'success',
            'message' => $response['success']
        ], 200);
    }


    /**
     * Handles the M-PESA C2B validation callback.
 
     * @param \Illuminate\Http\Request $request The incoming HTTP request containing the validation data.
     * 
     * @return \Illuminate\Http\JsonResponse A JSON response indicating that the validation was accepted.
     */

    public function handlec2bvalidation(Request $request): JsonResponse
    {

        Log::channel('app')->info('Validation_Initiated: ' . json_encode($request->all()));

        $response = [
            //use ResultCode C2B00011 and ResultDesc Rejected to reject transactions
            "ResultCode" => "0",
            "ResultDesc" => "Accepted"

        ];

        return response()->json($response, 200);
    }

    public function handleB2CResult(Request $request)
    {


        // Check if the response is successful
        if ($request->input('ResultCode') == 0) {
            // Store the successful transaction in the database
            success_b2c_transactions::create([
                'conversation_id' => $request->input('ConversationID'),
                'transaction_id' => $request->input('TransactionID'),
                'originator_conversation_id' => $request->input('OriginatorConversationID'),
                'result_code' => $request->input('ResultCode'),
                'result_desc' => $request->input('ResultDesc'),
                // 'amount' => $request->input('Amount'),
                // 'receiver_name' => $request->input('ReceiverPartyPublicName'),
                // 'transaction_date' => $request->input('TransactionCompletedDateTime'),
            ]);

            // Return a success response to M-Pesa (you can adjust this based on M-Pesa's expected format)
            return response()->json([
                'ResponseCode' => '0',
                'ResponseDescription' => 'Success'
            ]);
        } else {


            // Optionally, you can store the failed transaction details in a separate table for future reference

            return response()->json([
                'ResponseCode' => '1',
                'ResponseDescription' => 'Failure'
            ]);
        }
    }

    public function handleB2CTimeout(Request $request)
    {
        Log::channel('mpesa')->info('B2C Timeout Callback: ' . json_encode($request->all()));

        return response()->json([
            'ResponseCode' => '0',
            'ResponseDescription' => 'Timeout received'
        ]);
    }
}
