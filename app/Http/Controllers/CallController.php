<?php

namespace App\Http\Controllers;

use App\Call;
use App\Services\CallService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CallController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $userId = Auth::id();
        return view('calls.index', compact('userId'));
    }

    public function play(Request $request) {
        $products = DB::select(
            DB::raw('SELECT path_s3 FROM '.env('DB_DATABASE_VOIP').'.gravacoes_s3 gs WHERE uuid = ?'),
            [$request->query('uuid')]
        );
        if (!$products) {
            return;
        }
        $file = Cache::store('audio')->get($request->query('uuid'));
        if (!$file) {
            $uri = parse_url($products[0]->path_s3);
            $s3 = Storage::createS3Driver([
                'driver' => 's3',
                'key'    => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
                'region' => env('AWS_DEFAULT_REGION'),
                'bucket' => $uri['host']
            ]);
            $file = $s3->get($uri['path']);
            $expiresAt = now()->addSeconds(getenv('AWS_CACHE_EXPIRE'));
            Cache::store('audio')->put($request->query('uuid'), $file, $expiresAt);
        }

        return response()->stream(function() use ($file) {
            echo $file;
        }, 200, [
            'Cache-Control'         => 'must-revalidate, post-check=0, pre-check=0',
            'Content-Type'          => 'audio/mpeg',
            'Content-Length'        => strlen($file),
            'Content-Disposition'   => 'attachment; filename="' . basename($products[0]->path_s3) . '"',
            'Pragma'                => 'public',
        ]);
    }
}
