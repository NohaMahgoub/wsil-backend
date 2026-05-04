<?php
namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use Illuminate\Http\Request;

class DisputeController extends Controller
{
    // Vendor views their disputes
    public function index(Request $request)
    {
        $disputes = Dispute::whereHas('delivery', function ($q) use ($request) {
            $q->where('vendor_id', $request->user()->id);
        })
        ->with([
            'delivery.order',
            'delivery.driver:id,name',
            'resolvedBy:id,name',
        ])
        ->latest()
        ->paginate(15);

        return response()->json($disputes);
    }

    // Vendor views a single dispute
    public function show(Request $request, Dispute $dispute)
    {
        // Make sure vendor owns this dispute
        if ($dispute->delivery->vendor_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $dispute->load([
            'delivery.order',
            'delivery.driver:id,name',
            'resolvedBy:id,name',
        ]);

        return response()->json($dispute);
    }
}