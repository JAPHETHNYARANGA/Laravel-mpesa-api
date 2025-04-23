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
use Carbon\Carbon;


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
        Log::channel('mpesa')->info('B2C Result: ' . json_encode($request->all()));

        try {
            $data = $request->json('Result');

            // Save successful transaction
            if ($data['ResultCode'] == 0) {
                $params = $data['ResultParameters']['ResultParameter'];

                $transaction = success_b2c_transactions::create([
                    'conversation_id' => $data['ConversationID'],
                    'transaction_id' => $data['TransactionID'],
                    'originator_conversation_id' => $data['OriginatorConversationID'],
                    'result_code' => $data['ResultCode'],
                    'result_desc' => $data['ResultDesc'],
                    'amount' => $this->getResultParameter($params, 'TransactionAmount'),
                    'receiver_name' => $this->getResultParameter($params, 'ReceiverPartyPublicName'),
                    'receiver_phone' => $this->extractPhoneNumber($this->getResultParameter($params, 'ReceiverPartyPublicName')),
                    'transaction_date' => Carbon::createFromFormat('d.m.Y H:i:s', $this->getResultParameter($params, 'TransactionCompletedDateTime')),
                ]);

                return response()->json(['status' => 'success']);
            }

            // Log failed transaction
            Log::channel('mpesa')->error('B2C Failed: ' . $data['ResultDesc']);

            return response()->json(['status' => 'error', 'message' => $data['ResultDesc']]);
        } catch (\Exception $e) {
            Log::channel('mpesa')->error('B2C Error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    private function getResultParameter(array $params, string $key)
    {
        foreach ($params as $param) {
            if ($param['Key'] == $key) {
                return $param['Value'];
            }
        }
        return null;
    }

    private function extractPhoneNumber(string $publicName)
    {
        // Format: "254708374149 - John Doe"
        return trim(explode('-', $publicName)[0]);
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
