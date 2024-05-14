<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Customer;
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

    public function store2(Request $request)
    {
        $data = $request->except('file');
        $delivery = Delivery::firstOrNew(['reference_no' => $data['reference_no'] ]);
        $document = $request->file;
        if ($document) {
            $ext = pathinfo($document->getClientOriginalName(), PATHINFO_EXTENSION);
            $documentName = $data['reference_no'] . '.' . $ext;
            $document->move('public/documents/delivery', $documentName);
            $delivery->file = $documentName;
        }
        $delivery->sale_id = $data['sale_id'];
        $delivery->user_id = Auth::id();
        $delivery->address = $data['address'];
        $delivery->delivered_by = $data['delivered_by'];
        $delivery->recieved_by = $data['recieved_by'];
        $delivery->status = $data['status'];
        $delivery->note = $data['note'];
        $delivery->save();
        $lims_sale_data = Sale::find($data['sale_id']);
        $lims_customer_data = Customer::find($lims_sale_data->customer_id);
        $message = 'Delivery created successfully';
        if($lims_customer_data->email && $data['status'] != 1){
            $mail_data['email'] = $lims_customer_data->email;
            $mail_data['customer'] = $lims_customer_data->name;
            $mail_data['sale_reference'] = $lims_sale_data->reference_no;
            $mail_data['delivery_reference'] = $delivery->reference_no;
            $mail_data['status'] = $data['status'];
            $mail_data['address'] = $data['address'];
            $mail_data['delivered_by'] = $data['delivered_by'];
            //return $mail_data;
            try{
                Mail::to($mail_data['email'])->send(new DeliveryDetails($mail_data));
            }
            catch(\Exception $e){
                $message = 'Delivery created successfully. Please setup your <a href="setting/mail_setting">mail setting</a> to send mail.';
            }
        }
        return redirect('delivery')->with('message', $message);
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

        $biller = Auth::id();
        $gudang = request('warehouse_id');
        $file   = $request->file('excel_upload');


        $array= Excel::toArray(new BarangImport, $file);

        $data = [];
        foreach($array as $key => $val){

            foreach ($val as $key2 => $val2){

                // if(isset($val2['jenis'])){
                //     $slug_jenis = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $val2['jenis'])));
                //     $jenis_data = JenisBarangModel::firstOrCreate(['jenisbarang_nama' => $val2['jenis'], 'jenisbarang_slug' => $slug_jenis, 'jenisbarang_ket' => '']);
                //     $jenis_id   = $jenis_data->jenisbarang_id;
                // }else{
                //     $jenis_id = null;
                // }

                // dd($val2);


                $product        = Product::firstOrNew([ 'name' => $val2['nama_produk'] ]);
                $no_resi        = $val2['no_resi'];
                $no_pesanan     = $val2['no_pesanan'];

                // dd($no_resi, $no_pesanan);


                if(!empty($no_pesanan)){
                    $random = Str::random(13);
                    $codeTrn    = 'TRN-'.$random;
                    // $dataOlshop = array(
                    //     'reference_no' =>  $no_pesanan,
                    //     'user_id'      => $biller,
                    //     'warehouse_id' => $gudang
                    // );

                    $olshop                    = Olshop::firstorNew();
                    $olshop->reference_no      = $no_pesanan;
                    $olshop->user_id           = $biller;
                    $olshop->warehouse_id      = $gudang;
                    $olshop->save();
                    dd($olshop);
                }




            }
        }


        // Excel::import(new AdjustmentStokExcelImport, $request->file('file'));

        return redirect('admin/barang')->with('create_message', 'Product imported successfully');
    }
}
