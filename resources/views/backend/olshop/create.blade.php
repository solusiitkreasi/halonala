

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
var oFileIn;

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

    /* Create Fuction input event */
    function createEvent(kd_brg) {
        /* restrict text input number only */
        var input_qty;
        $('#qty_' + kd_brg).keypress(function(event) {
            var kd_brg = $(this).attr('data-barang');
            var keycode = event.which;
            if (!(event.shiftKey == false && (keycode == 8 || keycode == 37 || (keycode >= 48 && keycode <= 57)))) {
                event.preventDefault();
            }
            input_qty = this.value;

        });
        /* Count barang yang diinput */
        $('#qty_' + kd_brg).keyup(function(event) {
            var qty = this.value;
            if (qty == '') qty = 0;

            var keycode = event.which;
            if (keycode == 13) {
                    saveTempData($('#urut_' + kd_brg).val(), kd_brg);
                    loadTempData();
                    // console.log('#ket_'+kd_brg);
                    clearTimeout(timer);
                    timer = setTimeout(function(event) {
                        $('#ket_' + kd_brg).focus();
                    }, 100); //Delay 1 second
            }
        });


    }

    /* Save Temp Data */
    function saveTempData(no_urut, kd_brg) {
        var urut = $('input[name="kd_brg[]"]').length;
        var data = {
            kd_doc_trans: kd_doc_temp,
            urut: no_urut,
            kd_brg: kd_brg,
            qty: $('#qty_' + kd_brg).val(),
        }
            data.ket = $('#ket_' + kd_brg).val();
        $.ajax({
            url: "saveTempData",
            type: "POST",
            data: data,
            dataType: 'json',
            success: function(response) {}
        });
        return false;

    }

    /* Delete Row Data */
    function deleteRow(no_urut, kd_brg) {
        tableListBKB.row(no_urut - 1).remove().draw();
        deleteTempData(kd_brg);

        clearTimeout(timer);
        timer = setTimeout(function(event) {
            var no_urut = 1;
            $("input[name='kd_brg[]']").each(function() {
                saveTempData(no_urut, $(this).val());
                no_urut++;
            });
            loadTempData();
        }, 100); //Delay 1 second
        subAmount();
    }

    /* Delete Temp Data */
    function deleteTempData(kd_brg) {
        var data = {
            kd_doc_trans: kd_doc_temp,
            kd_brg: kd_brg,
        }
        if ($('#jenis_gudang').val() != 'N') {
            data.kd_jenis_rusak = $('#kd_jenis_rusak_' + kd_brg).val();
        }
        $.ajax({
            url: "deleteTempData",
            type: "POST",
            data: data,
            dataType: 'json',
            success: function(response) {}
        });
    }

    /* Load Temp Data */
    function loadTempData() {
        var data = {
            kd_doc_trans: kd_doc_temp,
        }
        clearTimeout(timer);
        timer = setTimeout(function(event) {
            $.ajax({
                url: "fetchDataTemp",
                type: "POST",
                data: data,
                dataType: 'json',
                success: function(response) {
                    if (response.success === true) {
                        // response success
                        /* Set Data Temp */
                        tableListBKB.clear().draw();
                        $.each(response.data, function(index, value) {
                            var urut = $('input[name="kd_brg[]"]').length + 1;
                                var index = $('#tableListBKB').DataTable().row.add([
                                    "<input value='" + urut + "' type='hidden' name='urut[]' id='urut_" + value['kd_brg'] + "'/>" + urut,
                                    "<input  value='" + value['kd_brg'] + "'type='hidden' name='kd_brg[]' id='" + value['kd_brg'] + "' class='form-control' style='width:100%'/>" + value['kd_brg'],
                                    "<input  value='" + value['nm_barang'] + "' type='hidden' name='nm_barang[]' id='" + value['kd_brg'] + "' class='form-control' style='width:100%'/>" + value['nm_barang'] ,
                                    "<input value='" + value['stok'] + "' type='hidden' name='max_qty[]' id='max_qty_" + value['kd_brg'] + "' class='form-control' style='width:100%'  autocomplete='off'/>" +
                                    "<input value='" + value['jb'] + "' type='number' name='qty[]' id='qty_" + value['kd_brg'] + "' data-max-qty='"+value['stok']+"' class='form-control qty' style='width:100%'   autocomplete='off'/>",
                                    "<input value='" + value['ket'] + "' type='text' name='ket[]' id='ket_" + value['kd_brg'] + "' class='form-control' style='width:100%'  autocomplete='off'/>",
                                    '<button type="button" class="btn btn-danger" onclick="deleteRow(\'' + urut + '\',\'' + value['kd_brg'] + '\')"><i class="fa fa-trash"></i></button>',
                                ]).draw(false);
                            createEvent(value['kd_brg']);
                        });
                        /* End Set Data Temp */
                    } else {
                        // response failed
                        $('#btn-information').removeAttr('href');
                        $('#btn-information').attr('data-dismiss', 'modal');
                        $("#messages-alert").html('<div class="alert alert-warning alert-dismissible" role="alert">' +
                            '<strong> <span class="glyphicon glyphicon-exclamation-sign"></span> </strong>' + response.messages +
                            '</div>');
                        $("#modalAlert").modal();
                    }
                }
            });
        }, 100); //Delay 1 second

    }

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
