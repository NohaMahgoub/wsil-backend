<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppVersion;
use Illuminate\Http\Request;

class AppVersionController extends Controller
{
    public function update(Request $request)
    {
        $request->validate([
            'platform'        => 'required|in:android,ios',
            'minimum_version' => 'required|string',
            'latest_version'  => 'required|string',
            'force_update'    => 'boolean',
            'update_message'  => 'nullable|string',
            'update_url'      => 'nullable|string',
        ]);

        $version = AppVersion::updateOrCreate(
            ['platform' => $request->platform],
            [
                'minimum_version' => $request->minimum_version,
                'latest_version'  => $request->latest_version,
                'force_update'    => $request->force_update ?? false,
                'update_message'  => $request->update_message,
                'update_url'      => $request->update_url,
            ]
        );

        return response()->json([
            'message' => 'تم تحديث إعدادات الإصدار.',
            'version' => $version,
        ]);
    }

    public function show()
    {
        $version = AppVersion::where('platform', 'android')->first();
        return response()->json($version);
    }
}