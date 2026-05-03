<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppVersion;
use Illuminate\Http\Request;

class AppVersionController extends Controller
{
    public function check(Request $request)
    {
        $request->validate([
            'version'  => 'required|string',
            'platform' => 'required|string|in:android,ios',
        ]);

        $appVersion = AppVersion::where('platform', $request->platform)
            ->latest()
            ->first();

        if (!$appVersion) {
            return response()->json([
                'force_update'    => false,
                'latest_version'  => $request->version,
                'update_message'  => null,
                'update_url'      => null,
            ]);
        }

        // Compare versions
        $forceUpdate = version_compare(
            $request->version,
            $appVersion->minimum_version,
            '<'
        );

        return response()->json([
            'force_update'    => $forceUpdate || $appVersion->force_update,
            'latest_version'  => $appVersion->latest_version,
            'minimum_version' => $appVersion->minimum_version,
            'update_message'  => $appVersion->update_message,
            'update_url'      => $appVersion->update_url,
            'current_version' => $request->version,
        ]);
    }
}