

@extends('backend.layout.main') @section('content')
@if(session()->has('not_permitted'))
    <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
@endif
<section class="forms">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h4>{{trans('Import From Ol-shop')}}</h4>
                    </div>
                    <div class="card-body">
                        <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                        {!! Form::open(['route' => 'olshop.store', 'method' => 'post', 'files' => true, 'class' => 'payment-form']) !!}
                        <div class="row">
                            <div class="col-md-12">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{trans('file.Warehouse')}} *</label>
                                            <select required name="warehouse_id" class="selectpicker form-control" data-live-search="true" data-live-search-style="begins" title="Select warehouse...">
                                                @foreach($lims_warehouse_list as $warehouse)
                                                <option value="{{$warehouse->id}}">{{$warehouse->name}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{trans('file.Biller')}} *</label>
                                            <select required name="biller_id" class="selectpicker form-control" data-live-search="true" data-live-search-style="begins" title="Select Biller...">
                                                @foreach($lims_biller_list as $biller)
                                                <option value="{{$biller->id}}">{{$biller->name . ' (' . $biller->company_name . ')'}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>{{trans('file.Upload CSV File')}} *</label>
                                            <input required type="file" class="form-control-file" id="excel_upload" name='excel_upload' onchange="filePicked(event)">
                                            <p>{{trans('file.The correct column order is')}} (product_code, quantity, sale_unit, product_price, discount, tax_name) {{trans('file.and you must follow this')}}. {{trans('file.For Digital product sale_unit will be n/a')}}. {{trans('file.All columns are required')}}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label></label><br>
                                            <a download href="../sample_file/import_olshop.xls" class="btn btn-primary btn-block btn-lg"><i class="dripicons-download"></i> {{trans('file.Download Sample File')}}</a>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mt-5">
                                    <div class="col-md-12">
                                        <h5>{{trans('file.Order Table')}} *</h5>
                                        <div class="table-responsive mt-3">
                                            <table id="tableOlshop" class="table table-hover order-list">
                                                <thead>
                                                    <tr>
                                                        <th>{{trans('file.Code')}}</th>
                                                        <th>{{trans('file.name')}}</th>
                                                        <th>{{trans('file.Quantity')}}</th>
                                                        <th>{{trans('file.Batch No')}}</th>
                                                        <th><i class="dripicons-trash"></i></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <input type="submit" value="{{trans('file.submit')}}" class="btn btn-primary" id="submit-button">
                                </div>
                            </div>
                        </div>
                        {!! Form::close() !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid">
        <table class="table table-bordered table-condensed totals">

        </table>
    </div>


</section>

@endsection

@push('scripts')
<script type="text/javascript">
var timer;
var tableOlshop;

$(document).ready(function() {
    $("ul#sale").siblings('a').attr('aria-expanded','true');
    $("ul#sale").addClass("show");
    $("ul#sale #olshop-menu").addClass("active");

    /* Inisialisasi Table Item BKB */
    tableOlshop = $('#tableOlshop').DataTable({
        'scrollCollapse': true,
        'orderCellsTop' : true,
        'fixedHeader'   : true,
        'processing'    : true,
        'ordering'      : false,
        'bPaginate'     : false,
        'bLengthChange' : false,
        'bFilter'       : false,
        'bInfo'         : false,
        'bAutoWidth'    : false,
        'columnDefs'    : [
            {targets: 0,className: 'text-center'},
            {targets: 1,className: 'text-left'},
            {targets: 2,className: 'text-left'},
            {targets: 3,className: 'text-center'},
            {targets: 4,className: 'text-left'},
        ]
    });
    /* End Inisialisasi Table Item BKB */

});

    var oFileIn;

    $(function() {
        oFileIn = document.getElementById('excel_upload');

    });

    function filePicked(oEvent) {

            clearTimeout(timer);
            timer = setTimeout(function(event) {
                tableOlshop.columns.adjust().draw();
            }, 10); //Delay 1 second
            var urut = $('input[name="nama_produk[]"]').length + 1;
            var count_kd_double = 0;
                // Get The File From The Input
            var oFile = oEvent.target.files[0];
            console.log(oFile);
            if (typeof(oFile) != "undefined") {
                var sFilename = oFile.name;
                // Create A File Reader HTML5
                var reader = new FileReader();

                // Ready The Event For When A File Gets Selected
                reader.onload = function(e) {
                    var data = e.target.result;
                    var cfb = XLS.CFB.read(data, {
                        type: 'binary'
                    });
                    var wb = XLS.parse_xlscfb(cfb);
                    // Loop Over Each Sheet
                    var error = [];
                    // var check_merk = true;
                    var result_data = [];
                    tableOlshop.clear().draw();
                    wb.SheetNames.forEach(function(sheetName) {
                        // Obtain The Current Row As CSV
                        var sCSV = XLS.utils.make_csv(wb.Sheets[sheetName]);

                        var oJS = XLS.utils.sheet_to_row_object_array(wb.Sheets[sheetName]);
                        var row = 1;

                        if (oJS[0]) {
                            var header_excel = Object.keys(oJS[0]);
                            if (header_excel[4] != 'No. Resi') {
                                dialog_warning('Notification', 'Pastikan Anda Tidak Merubah Baris Pertama dan data tidak kosong, Cek Kembali Header No. Resi Pada Colom A4');
                            } else if (header_excel[13] != 'Nama Produk') {
                                dialog_warning('Notification', 'Pastikan Anda Tidak Merubah Baris Pertama dan data tidak kosong atau 0, Cek Kembali Header Qty Pada Colom A13');
                            }  else {
                                oJS.forEach(function(data) {
                                    if (data['No. Resi'] == null) {
                                        error.push(row + 1);
                                    } else {
                                        if (!Number.isInteger(parseInt(data['Jumlah'])) && parseInt(data['Jumlah']) < 0) {
                                            error.push(row + 1);
                                        } else {

                                            if (count_kd_double == 0) {

                                                    var index = $('#tableOlshop').DataTable().row.add([
                                                        "<input type='hidden' name='urut[]' id='urut_" + urut + "' value='" + urut + "' />" + urut,
                                                        "<input type='hidden' name='nama_produk[]' id='" + data['No. Resi'] + "' class='form-control' style='width:100%' value='" + data['Nama Produk'] + "' />" + data['Nama Produk'],
                                                        "<input type='number' name='qty[]' id='qty_" + data['No. Resi'] + "' class='form-control qty' style='width:100%' value='" + data['Jumlah'] + "' autocomplete='off'/>",
                                                        "<input type='text' name='no_resi[]' id='no_resi" + data['No. Resi'] + "' class='form-control' style='width:100%' value='" + data['No. Resi'] + "' autocomplete='off'/>",
                                                        '<button type="button" class="btn btn-danger" onclick="deleteRow(\'' + urut + '\',\'' + data['No. Resi'] + '\')"><i class="fa fa-trash"></i></button>',
                                                    ]).draw(false);

                                                // createEvent(data['Nama Produk']);

                                                /* add id in row */
                                                var row = $('#tableOlshop').dataTable().fnGetNodes(index);
                                                $(row).attr('id', "rowID_" + urut);

                                                clearTimeout(timer);
                                                timer = setTimeout(function(event) {
                                                    var no_urut = 1;
                                                    $("input[name='nama_produk[]']").each(function() {
                                                        saveTempData(no_urut, $(this).val());
                                                        no_urut++;
                                                    });
                                                    loadTempData();
                                                }, 100); //Delay 1 second
                                            } else {
                                                $("#messages-alert").html('<div class="alert alert-warning alert-dismissible" role="alert">' +
                                                    '<strong> <span class="glyphicon glyphicon-exclamation-sign"></span> </strong> Barang Sudah Ditambahkan' +
                                                    '</div>');
                                                $('#modalAlert').modal();
                                            }

                                        }
                                    }
                                    row++;
                                });
                            }
                        } else {
                            dialog_warning('Notification', 'Cek Kembali File Yang Anda Pilih !');
                        }
                    });
                    if (error.length) {
                        dialog_warning('Notification', 'Pastikan Anda Tidak Merubah Baris Pertama, Cek Kembali data Pada Baris ( ' + error.join() +
                            ' )');
                    } else {
                        tableOlshop.rows.add(result_data).draw();
                    }
                };
                // Tell JS To Start Reading The File.. You could delay this if desired
                reader.readAsBinaryString(oFile);
            }

    }

</script>
@endpush
