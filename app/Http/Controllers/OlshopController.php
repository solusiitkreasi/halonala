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
use Illuminate\Support\Facades\Validator;
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

        $lims_olshop_all = OlshopDetail::orderBy('created_at', 'desc')->get();
        return view('backend.olshop.index', compact('lims_olshop_all'));

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



    public function store(Request $request)
    {

        //get the file
        $upload = $request->file('excel_upload');
        $ext = pathinfo($upload->getClientOriginalName(), PATHINFO_EXTENSION);
        //checking if this is a CSV file
        if($ext != 'xls')
            return redirect()->back()->with('error', 'Please upload a .XLS file');

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


                if(!empty($val2['nama_produk'])){
                    $cekproduct        = Product::where('name','LIKE',"%{$val2['nama_produk']}%")->first();
                }

                if(!empty($cekproduct)){
                    #-- Olshop data
                        # Header
                        $olshop                             = Olshop::firstorNew([ 'no_trn' => $codeTrn ]);
                        if(isset($olshop)){
                            $olshop->no_trn                 = $codeTrn;
                            $olshop->user_id                = $biller;
                            $olshop->warehouse_id           = $gudang;
                            $olshop->save();
                        }

                        $product        = Product::where('name','LIKE',"%{$val2['nama_produk']}%")->first(); //Product::firstOrNew([ 'name' => $val2['nama_produk'] ]);

                        $no_resi        = $val2['no_resi'];
                        $no_pesanan     = $val2['no_pesanan'];
                        $jumlah         = $val2['jumlah'];
                        $product_id     = $product['id'];

                        # Detail
                        $olshopDetail['olshop_id']         = $olshop->id;
                        $olshopDetail['product_id']        = $product_id;
                        $olshopDetail['no_resi']           = $no_resi;
                        $olshopDetail['no_pesanan']        = $no_pesanan;
                        $olshopDetail['harga']             = $val2['total_harga_produk'];
                        $olshopDetail['variasi']           = $val2['nama_variasi'];
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
                                $penjualan->total_qty              = $val2['jumlah_produk_di_pesan'];
                                $penjualan->total_discount         = $val2['total_diskon'];
                                $penjualan->total_tax              = '0';
                                $penjualan->total_price            = $val2['total_pembayaran'];
                                $penjualan->grand_total            = $val2['total_pembayaran'];
                                $penjualan->sale_status            = '1';
                                $penjualan->payment_status         = '4';
                                $penjualan->sale_note              = 'Import Excel Olshop';
                                $penjualan->save();
                            }

                            $productBatch        = ProductBatch::where('product_id',$product_id)->first();
                            $productVariant      = ProductVariant::where('product_id',$product_id)->first();

                            if(!empty($productBatch)){
                                $productBatchId      = $productBatch['id'];
                            }else{
                                $productBatchId      = null;
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
                            $penjualanDetail['net_unit_price']    = $val2['harga_awal'];
                            $penjualanDetail['discount']          = $val2['total_diskon'];
                            $penjualanDetail['tax_rate']          = '0';
                            $penjualanDetail['tax']               = '0';
                            $penjualanDetail['total']             = $val2['total_harga_produk'];
                            Product_Sale::create($penjualanDetail);

                            $delivery                   = Delivery::firstorNew(['reference_no' => $no_resi]);
                            $delivery->reference_no     = $no_resi;
                            $delivery->sale_id          = $penjualan->id;
                            $delivery->user_id          = $user_id;
                            $delivery->address          = $val2['alamat_pengiriman'];
                            $delivery->delivered_by     = $val2['opsi_pengiriman'];
                            $delivery->recieved_by      = $val2['nama'];
                            $delivery->file             = '';
                            $delivery->note             = 'Import Excel Olshop';
                            $delivery->status           = '3';
                            $delivery->save();
                        }
                    #-- End Penjualan data


                    #-- Warehouse potong stok
                        # Data
                        $product_data       = Product::where('name','LIKE',"%{$val2['nama_produk']}%")->first();
                        $product_data_id    = $product_data['id'];

                        if (!empty($product_data_id)){

                            //deduct product variant quantity if exist
                            if($variant_id) {
                                $product_variant_data = ProductVariant::where('item_code','LIKE',"%{$val2['nama_variasi']}%")->first();
                                //deduct product variant quantity
                                $product_variant_data->qty -= $jumlah;
                                $product_variant_data->save();

                                $product_warehouse_data = Product_Warehouse::FindProductWithVariant($product_data_id, $product_variant_data->variant_id, $gudang)->first();

                            }elseif($productBatchId){
                                $product_warehouse_data = Product_Warehouse::where([
                                    ['product_id', $product_data_id],
                                    ['product_batch_id', $productBatchId ],
                                    ['warehouse_id', $gudang ]
                                ])->first();

                                $product_batch_data = ProductBatch::find($productBatchId);
                                //deduct product batch quantity
                                $product_batch_data->qty -= $jumlah;
                                $product_batch_data->save();


                            }else{
                                $product_warehouse_data = Product_Warehouse::FindProductWithoutVariant($product_data_id, $gudang)->first();
                            }

                            //deduct quantity from warehouse
                            if(isset($product_warehouse_data->qty)){
                                $qty = $product_warehouse_data->qty - $jumlah;

                                $product_warehouse_data->update([
                                    'qty'   => $qty,
                                ]);
                            }

                            $product_data->qty -= $jumlah;
                            $product_data->save();
                        }
                    #-- END Warehouse potong stok
                }

            }
        }

        return redirect('olshop')->with('message', 'Product imported successfully');
    }


    public function delete($id)
    {
        $olshop_data = OlshopDetail::find($id);
        $olshop_data->delete();


        return redirect('olshop')->with('message', 'Data deleted succesfully');
    }
}
