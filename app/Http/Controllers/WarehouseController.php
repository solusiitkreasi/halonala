<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Warehouse;
use Illuminate\Validation\Rule;
use Keygen;
use App\Traits\CacheForget;

class WarehouseController extends Controller
{
    use CacheForget;
    public function index()
    {
        $lims_warehouse_all = Warehouse::where('is_active', true)->get();
        $numberOfWarehouse = Warehouse::where('is_active', true)->count();
        return view('backend.warehouse.create', compact('lims_warehouse_all', 'numberOfWarehouse'));
    }


    public function edit($id)
    {
        $lims_warehouse_data = Warehouse::findOrFail($id);
        return $lims_warehouse_data;
    }

    public function store(Request $request)
    {
        // $this->validate($request, [
        //     'name' => [
        //         'max:255',
        //             Rule::unique('warehouses')->where(function ($query) {
        //             return $query->where('is_active', 1);
        //         }),
        //     ],
        //     'site_logo' => 'image|mimes:jpg,jpeg,png,gif|max:100000',
        // ]);

        // $data = $request->except('site_logo');

        $input = $request->all();
        $input['is_active'] = true;

        // $logo = $request->site_logo;
        // if ($logo) {
        //     $ext = pathinfo($logo->getClientOriginalName(), PATHINFO_EXTENSION);
        //     $logoName = date("Ymdhis") . '.' . $ext;
        //     $logo->move('public/logo', $logoName);
        //     $input['site_logo'] = $logoName;
        // }
        Warehouse::create($input);
        $this->cacheForget('warehouse_list');
        return redirect('warehouse')->with('message', 'Data inserted successfully');
    }

    public function update(Request $request, $id)
    {
        // $this->validate($request, [
        //     'name' => [
        //         'max:255',
        //             Rule::unique('warehouses')->ignore($request->warehouse_id)->where(function ($query) {
        //             return $query->where('is_active', 1);
        //         }),
        //     ],
        //     'site_logo' => 'image|mimes:jpg,jpeg,png,gif|max:100000',
        // ]);

        // $data = $request->except('site_logo');

        $input = $request->all();


        // if ($logo) {
        //     $ext = pathinfo($logo->getClientOriginalName(), PATHINFO_EXTENSION);
        //     $logoName = date("Ymdhis") . '.' . $ext;
        //     $logo->move('public/logo', $logoName);
        //     $input['site_logo'] = $logoName;
        // }

        $lims_warehouse_data = Warehouse::find($input['warehouse_id']);
        $lims_warehouse_data->update($input);
        $this->cacheForget('warehouse_list');
        return redirect('warehouse')->with('message', 'Data updated successfully');
    }

    public function importWarehouse(Request $request)
    {
        //get file
        $upload=$request->file('file');
        $ext = pathinfo($upload->getClientOriginalName(), PATHINFO_EXTENSION);
        if($ext != 'csv')
            return redirect()->back()->with('not_permitted', 'Please upload a CSV file');
        $filename =  $upload->getClientOriginalName();
        $upload=$request->file('file');
        $filePath=$upload->getRealPath();
        //open and read
        $file=fopen($filePath, 'r');
        $header= fgetcsv($file);
        $escapedHeader=[];
        //validate
        foreach ($header as $key => $value) {
            $lheader=strtolower($value);
            $escapedItem=preg_replace('/[^a-z]/', '', $lheader);
            array_push($escapedHeader, $escapedItem);
        }
        //looping through othe columns
        while($columns=fgetcsv($file))
        {
            if($columns[0]=="")
                continue;
            foreach ($columns as $key => $value) {
                $value=preg_replace('/\D/','',$value);
            }
           $data= array_combine($escapedHeader, $columns);

           $warehouse = Warehouse::firstOrNew([ 'name'=>$data['name'], 'is_active'=>true ]);
           $warehouse->name = $data['name'];
           $warehouse->phone = $data['phone'];
           $warehouse->email = $data['email'];
           $warehouse->address = $data['address'];
           $warehouse->is_active = true;
           $warehouse->save();
        }
        $this->cacheForget('warehouse_list');
        return redirect('warehouse')->with('message', 'Warehouse imported successfully');
    }

    public function deleteBySelection(Request $request)
    {
        $warehouse_id = $request['warehouseIdArray'];
        foreach ($warehouse_id as $id) {
            $lims_warehouse_data = Warehouse::find($id);
            $lims_warehouse_data->is_active = false;
            $lims_warehouse_data->save();
        }
        $this->cacheForget('warehouse_list');
        return 'Warehouse deleted successfully!';
    }

    public function destroy($id)
    {
        $lims_warehouse_data = Warehouse::find($id);
        $lims_warehouse_data->is_active = false;
        $lims_warehouse_data->save();
        $this->cacheForget('warehouse_list');
        return redirect('warehouse')->with('not_permitted', 'Data deleted successfully');
    }
}
