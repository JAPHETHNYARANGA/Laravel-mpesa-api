<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MpesaStkPayments;
use App\Models\MpesaConfirmation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use function response;


class MpesaDataFetchController extends Controller
{
        /**
     * Fetches a specific customer transaction using billref_no.
     *
     * @param \Illuminate\Http\Request $request The incoming HTTP request.
     * 
     * @return \Illuminate\Http\JsonResponse A JSON response containing the fetched record or an error message.
     */
    public function fetchCustomerTransaction(Request $request): JsonResponse
    {
        try {
            // Validate that billref_no is passed in the request
            $validatedData = Validator::make($request->all(), [
                'billref_no' => 'required'
            ]);

            if ($validatedData->fails()) {
                return response()->json($validatedData->errors(), 422);
            }

            // Access validated data
            $requestData = $validatedData->validated();
            $billref_no = $requestData['billref_no'];

            // Fetch the MpesaStkPayments record using the provided billref_no
            $mpesapayments = MpesaStkPayments::where('user_id', $billref_no)->get();

            // If no mpesa payments found for the given billref_no
            if ($mpesapayments->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No transactions found for the given billref_no'
                ], 404);
            }

            // Collect all account references from the retrieved payments
            $accountReferences = $mpesapayments->pluck('account_reference')->toArray();

            // Fetch corresponding MpesaConfirmation transactions using the account references
            $transactions = MpesaConfirmation::whereIn('billref_no', $accountReferences)->get();

            // If no matching confirmation transactions found
            if ($transactions->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No confirmation transactions found for the given billref_no'
                ], 404);
            }

            // Convert the transactions to an array
            $transactionsArray = $transactions->toArray();

            return response()->json([
                'status' => 'success',
                'data' => $transactionsArray
            ], 200);

        } catch (\Exception $e) {
            Log::channel('mpesa')->error('Error fetching transactions: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching transactions: ' . $e->getMessage()
            ], 500);
        }
    }

    public function fetchSuccessTransaction(Request $request): JsonResponse
    {
        try {
            // Validate that billref_no is passed in the request
            $validatedData = Validator::make($request->all(), [
                'transaction_id' => 'required'
            ]);

            if ($validatedData->fails()) {
                return response()->json($validatedData->errors(), 422);
            }

            // Access validated data
            $requestData = $validatedData->validated();

            $transactionId = $requestData['transaction_id'];

            // Fetch all transactions using the billref_no
            $transactions = MpesaConfirmation::where('billref_no', $transactionId)->get();

            // If no transactions are found
            if ($transactions->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No transactions found for the given billref_no'
                ], 404);
            }

        

            return response()->json([
                'status' => 'success',
                'data' => $transactions
            ], 200);
        } catch (\Exception $e) {
            Log::channel('mpesa')->error('Error fetching transactions: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching transactions: ' . $e->getMessage()
            ], 500);
        }
    }


    public function fetchAllTransactions(Request $request): JsonResponse
    {
        try {
            // Fetch all transactions from the relevant model, e.g., MpesaConfirmation
            // You can change this to MpesaStkPayments if you want to fetch STK payments instead

            $transactions = MpesaConfirmation::all();  // Fetching all transactions from MpesaConfirmation

            if ($transactions->isNotEmpty()) {
                // If transactions are found, return them as a JSON response
                return response()->json([
                    'status' => 'success',
                    'data' => $transactions
                ], 200);
            } else {
                // If no transactions are found
                return response()->json([
                    'status' => 'success',
                    'message' => 'No transactions found',
                    'data' => []
                ], 200);
            }
        } catch (\Exception $e) {
            // Catch any exceptions and log the error
            Log::channel('mpesa')->error('Error fetching all transactions: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching transactions: ' . $e->getMessage()
            ], 500);
        }
    }




    /**
     * Fetches Mpesa confirmation records that have not been previously fetched.
     *
     * @param \Illuminate\Http\Request $request The incoming HTTP request.
     * 
     * @return \Illuminate\Http\JsonResponse A JSON response containing the fetched records or an error message.
     * 
     */

    public function fetchC2bPayments(Request $request):JsonResponse
    {
        try {

            $shortcode = $request->query('shortcode');

            $validatedData = Validator::make($request->all(), [
                'shortcode' => 'required|numeric|regex:/^[0-9]+$/'
            ]);

            if ($validatedData->fails()) {
                return response()->json($validatedData->errors(), 422);
            }

            $requestData = $validatedData->validated();


            $shortcode = $requestData['shortcode'];


            $cacheKey = 'fetched_record_ids';

            $lastFetchedId = Cache::get($cacheKey, 0);

            $records = MpesaConfirmation::where('id', '>', $lastFetchedId)
                ->where('business_shortcode', $shortcode)
                ->get(['id', 'transaction_type', 'transaction_id', 'transaction_amount', 'business_shortcode', 'mobile_number', 'first_name']);

            if ($records->isNotEmpty()) {

                $newLastFetchedId = $records->max('id');

                Cache::put($cacheKey, $newLastFetchedId);

                Log::channel('mpesa')->info('C2B_Data_Fetch_Initiated:  ' . $records->max('id'));

                return response()->json([
                    'status' => 'success',
                    'data' => $records
                ], 200);
            }

            Log::channel('mpesa')->info('C2B_Data_Fetch_Initiated: No records found for' . $shortcode);

            return response()->json([
                'status' => 'success',
                'message' => 'No records found',
                'data' => []
            ], 200);
        } catch (\Exception $e) {

            $errorMessage = $e->getMessage();

            Log::channel('mpesa')->error('Error fetching records: ' . $errorMessage);

            return response()->json([
                'status' => 'error',
                'message' => $errorMessage
            ], 500);
        }
    }


    /**
     * Fetch all M-PESA STK payments from the database.
     *
     * @return \Illuminate\Http\JsonResponse 
     */

    public function fetchStkPayments(Request $request):JsonResponse
    {
        try {

            $shortcode = $request->query('shortcode');

            $validatedData = Validator::make($request->all(), [
                'shortcode' => 'required|numeric|regex:/^[0-9]+$/'
            ]);

            if ($validatedData->fails()) {
                return response()->json($validatedData->errors(), 422);
            }

            $requestData = $validatedData->validated();


            $shortcode = $requestData['shortcode'];

            $records = MpesaStkPayments::where('business_shortcode', $shortcode)
                ->get();


            if ($records->isNotEmpty()) {
                Log::channel('mpesa')->info('STK_Data_Fetch_Initiated:  ' . $shortcode);
                return response()->json([
                    'status' => 'success',
                    'data' => $records
                ], 200);
            } else {
                Log::channel('mpesa')->info('STK_Data_Fetch_Initiated: No records found for: ' . $shortcode);

                return response()->json([
                    'status' => 'success',
                    'message' => 'No records found',
                    'data' => []
                ], 200);
            }
        } catch (\Exception $e) {
            Log::channel('mpesa')->error('Error fetching STK payments: ' . $e->getMessage());

            $errorMessage = $e->getMessage();
            return response()->json([
                'status' => 'error',
                'message' => $errorMessage
            ], 500);
        }
    }
}
