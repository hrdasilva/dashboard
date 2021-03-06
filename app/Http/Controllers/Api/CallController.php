<?php
namespace App\Http\Controllers\Api;

use App\Services\CallService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class CallController
{
    public function index(DataTables $dataTables, Request $request, CallService $service){

        $user = $request->user();
        $user = (!$user->is_admin)? $user : null;
        $query = $dataTables->query($service->find($user));

        $query->addIndexColumn()
            ->addColumn('action', function ($call) {
                if ($call->uuid) {
                    return
                    "<button type='button' class='btn btn-link' data-original-title='' title='' onclick='showPlayerModal(\"$call->uuid\")'>
                        <i class='material-icons'>play_circle_outline</i>
                        <div class='ripple-container'></div>
                    </button>
                    <button type='button' class='btn btn-link' data-original-title='' title='' onclick='downloadAudio(\"$call->uuid\")'>
                        <i class='material-icons'>cloud_download</i>
                        <div class='ripple-container'></div>
                    </button>";
                }
            })
            ->filterColumn('start_time', function(Builder $builder, $keyword) {
                $keyword = explode('|', $keyword);
                if ($keyword[0]) {
                    $builder->where('start_time', '>=', $keyword[0]);
                }
                if ($keyword[1]) {
                    $builder->where('start_time', '<=', $keyword[0]);
                }
            })
            ->filterColumn('client', function(Builder $builder, $keyword) {
                $builder->where('customer_id', '=', $keyword);
            });

        return $query->make(true);
    }
}
