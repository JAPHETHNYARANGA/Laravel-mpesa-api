<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\MpesaStkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Traits\MobileFormattingTrait;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class mpesaStkController extends Controller
{
    //

    use MobileFormattingTrait;

    /**
     * Initiates an M-PESA STK transaction.
     *
     * @param Request $request The incoming request containing STK data.
     * @return \Illuminate\Http\JsonResponse JSON response with API result or error message.
     */

     public function initiateStkRequest(Request $request): JsonResponse
     {
         // Validate only the dynamic values
         $validatedData = Validator::make($request->all(), [
             'amount' => 'required|numeric',
             'msisdn' => 'required|numeric',
             'userId' => 'required',
         ]);
     
         if ($validatedData->fails()) {
             return response()->json($validatedData->errors(), 422);
         }
     
         // Access validated data
         $requestData = $validatedData->validated();

         // Generate a random account reference
            $randomAccountReference = Str::random(10); 
    
     
         // Add static values to the requestData array
         $requestData['transaction_type'] = 'CustomerPayBillOnline';
         $requestData['organization_code'] = env('SHORTCODE');
         $requestData['stk_callback'] = 'https://payment.cityrealtykenya.com/api/mpesa/callback/register';
         $requestData['account_reference'] = $randomAccountReference;
         $requestData['consumer_key'] = env('CONSUMER_KEY');
         $requestData['consumer_secret'] = env('CONSUMER_SECRET');
         $requestData['shortcode'] =env('SHORTCODE');
         $requestData['passkey'] = env('PASSKEY');
     
         // Sanitize and format msisdn
         $requestData['msisdn'] = $this->sanitizeAndFormatMobile($requestData['msisdn']);
     
         // Call the service to initiate the STK push
         $mpesaStkService = new MpesaStkService;
         $response = $mpesaStkService->lipaNaMpesaStk($requestData);
         $userId =$request->userId;

         $accountReference = $requestData['account_reference'];
     
         if (isset($response['error'])) {
             return response()->json([
                 'status' => 'error',
                 'message' => $response['error']
             ], 500);
         }
     
         if (isset($response['ResponseCode']) && $response['ResponseCode'] === '0') {
             Log::channel('mpesa')->info('STK Request successful: ' . json_encode($response));
     
             // Save payment
             $result = $mpesaStkService->saveStkPayment($response, env('SHORTCODE'), $accountReference, $userId);
     
             if (isset($result['error'])) {
                 return response()->json([
                     'status' => 'error',
                     'message' => $result['error']
                 ], 500);
             }
     
             return response()->json([
                 'status' => 'success',
                 'message' => $response['ResponseDescription'],
                 'data' => $response,
                 'account_reference' => $accountReference, 
             ], 200);
         }
     
         Log::channel('mpesa')->error('STK Request failed: ' . json_encode($response));
     
         return response()->json([
             'status' => 'error',
             'message' => $response['ResponseDescription'] ?? 'An error occurred',
             'data' => $response
         ], 500);
     }
     


    /**
     * Handle M-PESA STK callback data.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */

    public function handleStkCallback(Request $request):JsonResponse
    {

        Log::channel('mpesa')->info('Received STK Callback data: ' . json_encode($request->all()));

        $callbackData = $request->input('Body.stkCallback', []);

        
        $merchantRequestId = $callbackData['MerchantRequestID'];
        $checkoutRequestId = $callbackData['CheckoutRequestID'];
        $resultCode = $callbackData['ResultCode'];
        $resultDesc = $callbackData['ResultDesc'];

        if ($resultCode !== 0) {
            Log::channel('mpesa')->error('STK Callback failed with ResultCode: ' . $resultCode . ' - ' . $resultDesc);
            return response()->json(['status' => 'error', 'message' => $resultDesc], 500);
        }

        $callbackMetadata = $callbackData['CallbackMetadata']['Item'];

        $amount = null;
        $transactionDate = null;
        $msisdn = null;
        $transactionId = null;

        foreach ($callbackMetadata as $item) {
            switch ($item['Name']) {
                case 'Amount':
                    $amount = $item['Value'];
                    break;
                case 'MpesaReceiptNumber':
                    $transactionId = $item['Value'];
                    break;
                case 'TransactionDate':
                    $transactionDate = $item['Value'];
                    break;
                case 'PhoneNumber':
                    $msisdn = $this->sanitizeAndFormatMobile($item['Value']);
                    break;
                default:
                    //Do nothing
                    break;
            }
        }


        $data = [
            'merchant_request_id' => $merchantRequestId,
            'checkout_request_id' => $checkoutRequestId,
            'transaction_id' => $transactionId,
            'transaction_date' => $transactionDate,
            'amount' => $amount,
            'msisdn' => $msisdn,
        ];

        $mpesaStkService = new MpesaStkService();

        $response = $mpesaStkService->handleStkCallbackData($data);

        if (isset($response['error'])) {
            Log::channel('mpesa')->error('STK Callback handling failed: ' . $response['error']);

            return response()->json([
                'status' => 'error',
                'message' => $response['error']
            ], 500);
        }

        Log::channel('mpesa')->info('STK Callback handled successfully: ' . json_encode($response));

        return response()->json([
            'status' => 'success',
            'message' => $response['success']
        ], 200);
    }
}
