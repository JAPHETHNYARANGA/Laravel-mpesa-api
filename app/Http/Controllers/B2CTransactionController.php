<?php

namespace App\Http\Controllers;

use App\Models\success_b2c_transactions;
use Illuminate\Http\Request;

class B2CTransactionController extends Controller
{
    public function checkTransaction(Request $request)
    {
        $request->validate(['conversation_id' => 'required']);

        $transaction = success_b2c_transactions::where('conversation_id', $request->conversation_id)->first();

        if ($transaction) {
            return response()->json([
                'status' => 'success',
                'transaction_id' => $transaction->transaction_id,
                'result_code' => $transaction->result_code,
                'result_desc' => $transaction->result_desc,
                'amount' => $transaction->amount,
                'receiver_phone' => $transaction->receiver_phone,
                'transaction_date' => $transaction->transaction_date,
            ]);
        }

        return response()->json([
            'status' => 'not_found',
            'message' => 'Transaction not found'
        ], 404);
    }
}
