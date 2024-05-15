<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Customer;
use App\Product_Warehouse;
use App\Warehouse;
use App\Biller;
use App\Sale;
use App\Product_Sale;
use App\Product;
use App\ProductVariant;
use App\ProductBatch;
use App\Delivery;
use App\PosSetting;
use App\Olshop;
use App\OlshopDetail;
use App\Import\BarangImport;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

use App\Mail\DeliveryDetails;
use App\Mail\DeliveryChallan;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Input;
use File;
use Redirect;
use Excel;
use DB;
use PDF;
use Mail;
use Auth;

class OlshopController extends Controller
{

    public function index()
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('delivery')) {
            if(Auth::user()->role_id > 2 && config('staff_access') == 'own')
                $lims_olshop_all = Delivery::orderBy('id', 'desc')->where('user_id', Auth::id())->get();
            else
                $lims_olshop_all = Delivery::orderBy('id', 'desc')->get();
            return view('backend.olshop.index', compact('lims_olshop_all'));
        }
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }

    public function create(){
        $lims_customer_list = Customer::where('is_active', true)->get();
        if(Auth::user()->role_id > 2) {
            $lims_warehouse_list = Warehouse::where([
                ['is_active', true],
                ['id', Auth::user()->warehouse_id]
            ])->get();
            $lims_biller_list = Biller::where([
                ['is_active', true],
                ['id', Auth::user()->biller_id]
            ])->get();
        }
        else {
            $lims_warehouse_list = Warehouse::where('is_active', true)->get();
            $lims_biller_list = Biller::where('is_active', true)->get();
        }

        $lims_pos_setting_data = PosSetting::latest()->first();
        if($lims_pos_setting_data)
            $options = explode(',', $lims_pos_setting_data->payment_options);
        else
            $options = [];

        $currency_list = DB::table('currencies')->get();
        $numberOfInvoice = Sale::count();
        return view('backend.olshop.create',compact('currency_list',
                'lims_customer_list', 'lims_warehouse_list', 'lims_biller_list', 'lims_pos_setting_data',
                'options', 'numberOfInvoice'));
    }



    public function deleteBySelection(Request $request)
    {
        $delivery_id = $request['deliveryIdArray'];
        foreach ($delivery_id as $id) {
            $olshop_data = Delivery::find($id);
            $olshop_data->delete();
        }
        return 'Delivery deleted successfully';
    }

    public function delete($id)
    {
        $olshop_data = Delivery::find($id);
        $olshop_data->delete();
        return redirect('olshop')->with('not_permitted', 'Delivery deleted successfully');
    }


    public function store(Request $request)
    {

        $user_id    = Auth::id();
        $biller     = request('biller_id');
        $gudang     = request('warehouse_id');
        $file       = $request->file('excel_upload');
        $random     = Str::random(13);
        $codeTrn    = 'TRN-'.$random;

        $array= Excel::toArray(new BarangImport, $file);

        $data = [];
        foreach($array as $key => $val){

            foreach ($val as $key2 => $val2){
                #-- Olshop data
                    # Header
                    $olshop                             = Olshop::firstorNew([ 'no_trn' => $codeTrn ]);
                    if(isset($olshop)){
                        $olshop->no_trn                 = $codeTrn;
                        $olshop->user_id                = $biller;
                        $olshop->warehouse_id           = $gudang;
                        $olshop->save();
                    }

                    $product        = Product::firstOrNew([ 'name' => $val2['nama_produk'] ]);
                    $no_resi        = $val2['no_resi'];
                    $no_pesanan     = $val2['no_pesanan'];
                    $jumlah         = $val2['jumlah'];
                    $product_id     = $product['id'];

                    # Detail
                    $olshopDetail['olshop_id']         = $olshop->id;
                    $olshopDetail['product_id']        = $product_id;
                    $olshopDetail['no_resi']           = $no_resi;
                    $olshopDetail['no_pesanan']        = $no_pesanan;
                    $olshopDetail['qty']               = $jumlah;
                    if (!empty($olshopDetail['product_id'] )){
                        OlshopDetail::create($olshopDetail);
                    }
                #-- End Olshop data

                #-- Penjualan data
                    # Header
                    $reference_no   = $val2['no_pesanan'];
                    $penjualan      = Sale::firstorNew(['reference_no' => $reference_no]);
                    if (!empty($olshopDetail['product_id'] )){
                        if(isset($penjualan)){
                            $penjualan->reference_no           = $reference_no;
                            $penjualan->user_id                = $user_id;
                            $penjualan->customer_id            = '1';
                            $penjualan->warehouse_id           = $gudang;
                            $penjualan->biller_id              = $biller;
                            $penjualan->item                   = '1';
                            $penjualan->total_qty              = '1';
                            $penjualan->total_discount         = '0';
                            $penjualan->total_tax              = '0';
                            $penjualan->total_price            = '0';
                            $penjualan->grand_total            = '0';
                            $penjualan->sale_status            = '1';
                            $penjualan->payment_status         = '4';
                            $penjualan->sale_note              = 'Import Excel Olshop';
                            $penjualan->save();

                            $delivery               = Delivery::firstorNew(['reference_no' => $reference_no]);
                            $delivery->reference_no     = $reference_no;
                            $delivery->sale_id          = $penjualan->id;
                            $delivery->user_id          = $user_id;
                            $delivery->address          = '';
                            $delivery->delivered_by      = '';
                            $delivery->recieved_by      = '';
                            $delivery->file             = '';
                            $delivery->note             = 'Import Excel Olshop';
                            $delivery->status           = '2';
                            $delivery->save();
                        }

                        $productBatch        = ProductBatch::where('product_id',$product_id)->first();
                        $productVariant      = ProductVariant::where('product_id',$product_id)->first();


                        if(!empty($productBatch)){
                            $productBatchId      = $productBatch['id'];
                        }else{
                            $productBatchId      = '';
                        }

                        if(!empty($productVariant)){
                            $variant_id          = $productVariant['variant_id'];
                        }else{
                            $variant_id          = null;
                        }

                        # Detail
                        $penjualanDetail['sale_id']           = $penjualan->id;
                        $penjualanDetail['sale_unit_id']      = '1';
                        $penjualanDetail['product_id']        = $product_id;
                        $penjualanDetail['product_batch_id']  = $productBatchId;
                        $penjualanDetail['variant_id']        = $variant_id;
                        $penjualanDetail['qty']               = $jumlah;
                        $penjualanDetail['net_unit_price']    = '0';
                        $penjualanDetail['discount']    = '0';
                        $penjualanDetail['tax_rate']    = '0';
                        $penjualanDetail['tax']    = '0';
                        $penjualanDetail['total']    = '0';
                        Product_Sale::create($penjualanDetail);
                    }
                #-- End Penjualan data

                #-- Warehouse potong stok
                    # Data
                    $product_data       = Product::firstOrNew([ 'name' => $val2['nama_produk'] ]);
                    $product_data_id    = $product_data['id'];
                    if (!empty($product_data_id)){

                        $product_data->qty = $product_data->qty - $jumlah;
                        //deduct product variant quantity if exist
                        if($variant_id) {
                            $product_variant_data->qty -= $jumlah;
                            $product_variant_data->save();
                            $product_warehouse_data = Product_Warehouse::FindProductWithVariant($product_data_id, $variant_id, $gudang)->first();
                        }elseif($productBatchId){
                            $product_warehouse_data = Product_Warehouse::where([
                                ['product_batch_id', $productBatchId ],
                                ['warehouse_id', $gudang ]
                            ])->first();
                            $lims_product_batch_data = ProductBatch::find($productBatchId);
                            //deduct product batch quantity
                            $lims_product_batch_data->qty -= $jumlah;
                            $lims_product_batch_data->save();
                        }else{
                            $product_warehouse_data = Product_Warehouse::FindProductWithoutVariant($product_data_id, $gudang)->first();
                        }
                        //deduct quantity from warehouse
                        $product_warehouse_data->qty -= $jumlah;
                        $product_warehouse_data->save();
                    }
            }
        }

        return redirect('olshop')->with('message', 'Product imported successfully');
    }
}
