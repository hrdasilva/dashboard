<?php

namespace App\Http\Controllers;

use App\Customer;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->is_admin) {
            foreach (Customer::all() as $customer) {
                $data['customers'][$customer->id] = $customer->name;
            }
        }

        $data['status'] = (new InvoiceService())->status();
        $data['userId'] = $user->id;
        return view('invoices.index', $data);
    }

    public function show($id, InvoiceService $service){
        $invoice = DB::connection('legacy')
            ->table('tblinvoices')
            ->where('id', '=', $id)
            ->get()
            ->first();

        $customer =  Customer::where('code', '=', $invoice->userid)->get()->first();

        $itens = DB::connection('legacy')
            ->table('tblinvoiceitems')
            ->where('invoiceid', '=', $id)
            ->get();

        $products = $service->products($id);

        return view('invoices.show', compact('invoice', 'itens', 'products', 'customer'));
    }

    public function csv($id, InvoiceService $service)
    {
        $headers = array(
            'Content-type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=fatura-'.date('Ymd').'.csv',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        );

        $products = $service->products($id);
        $columns = ['datahora', 'origem', 'destino', 'tarifa', 'duracao', 'duracao_faturado', 'valor_faturado'];

        $callback = function() use ($products, $columns)
        {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($products as $product){
                foreach($product->details as $detail) {
                    fputcsv($file, [
                        $detail->datahora, $detail->origem, $detail->destino, $detail->tarifa,
                        $detail->duracao, $detail->duracao_faturado,
                        number_format($detail->valor_faturado, 2, ',', '.')
                    ]);
                }
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    public function billet($id){
        $billet = DB::connection('voip')
            ->table('autourlfaturas')
            ->where('invoiceid', $id)
            ->orderBy('datahora','DESC')
            ->limit(1)
            ->get()
            ->first();
        return response()->redirectTo($billet->url);
    }
}
