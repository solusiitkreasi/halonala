@extends('backend.layout.main') @section('content')
@if(session()->has('message'))
  <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{!! session()->get('message') !!}</div>
@endif
@if(session()->has('not_permitted'))
  <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
@endif

<section>
    <div class="container-fluid">
        <a href="{{route('olshop.create')}}" class="btn btn-info add-sale-btn"><i class="dripicons-copy"></i> {{trans('Import Data')}}</a>&nbsp;
    </div>
    <div class="table-responsive">
        <table id="olshop-table" class="table">
            <thead>
                <tr>
                    <th class="not-exported"></th>
                    <th>{{trans('file.Delivery Reference')}}</th>
                    <th>{{trans('file.Sale Reference')}}</th>
                    <th>{{trans('file.customer')}}</th>
                    <th>{{trans('file.Address')}}</th>
                    <th>{{trans('file.Products')}}</th>
                    <th>{{trans('file.grand total')}}</th>
                    <th>{{trans('file.Status')}}</th>
                    <th class="not-exported">{{trans('file.action')}}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lims_olshop_all as $key=>$olshop)
                <?php
                    $customer_sale = DB::table('sales')
                                    ->join('customers', 'sales.customer_id', '=', 'customers.id')
                                    ->where('sales.id', $olshop->sale_id)
                                    ->select('sales.reference_no','customers.name', 'customers.phone_number', 'customers.city', 'sales.grand_total')
                                    ->get();
                    $product_names = DB::table('sales')
                                        ->join('product_sales', 'sales.id', '=', 'product_sales.sale_id')
                                        ->join('products', 'products.id', '=', 'product_sales.product_id')
                                        ->where('sales.id', $olshop->sale_id)
                                        ->pluck('products.name')
                                        ->toArray();

                    if($olshop->status == 1)
                        $status = trans('file.Packing');
                    elseif($olshop->status == 2)
                        $status = trans('file.Delivering');
                    else
                        $status = trans('file.Delivered');

                    $barcode = \DNS2D::getBarcodePNG($olshop->reference_no, 'QRCODE');
                ?>
                <tr class="olshop-link" data-barcode="{{$barcode}}" data-olshop='["{{date($general_setting->date_format, strtotime($olshop->created_at->toDateString()))}}", "{{$olshop->reference_no}}", "{{$olshop->sale->reference_no}}", "{{$status}}", "{{$olshop->id}}", "{{$olshop->sale->customer->name}}", "{{$olshop->sale->customer->phone_number}}", "{{$olshop->sale->customer->address}}", "{{$olshop->sale->customer->city}}", "{{$olshop->note}}", "{{$olshop->user->name}}", "{{$olshop->delivered_by}}", "{{$olshop->recieved_by}}"]'>
                    <td>{{$key}}</td>
                    <td>{{ $olshop->reference_no }}</td>
                    <td>{{ $customer_sale[0]->reference_no }}</td>
                    <td>{!!$customer_sale[0]->name .'<br>'. $customer_sale[0]->phone_number!!}</td>
                    <td>{{ $olshop->address }}</td>
                    <td>{{implode(",", $product_names)}}</td>
                    <td>{{number_format($customer_sale[0]->grand_total, 2)}}</td>
                    @if($olshop->status == 1)
                    <td><div class="badge badge-info">{{$status}}</div></td>
                    @elseif($olshop->status == 2)
                    <td><div class="badge badge-primary">{{$status}}</div></td>
                    @else
                    <td><div class="badge badge-success">{{$status}}</div></td>
                    @endif
                    <td>
                        <div class="btn-group">
                            <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">{{trans('file.action')}}
                              <span class="caret"></span>
                              <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default" user="menu">
                                <li>
                                    <button type="button" data-id="{{$olshop->id}}" class="open-EditCategoryDialog btn btn-link"><i class="dripicons-document-edit"></i> {{trans('file.edit')}}</button>
                                </li>
                                <li class="divider"></li>
                                {{ Form::open(['route' => ['olshop.delete', $olshop->id], 'method' => 'post'] ) }}
                                <li>
                                  <button type="submit" class="btn btn-link" onclick="return confirmDelete()"><i class="dripicons-trash"></i> {{trans('file.delete')}}</button>
                                </li>
                                {{ Form::close() }}
                            </ul>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</seaction>

<!-- Modal -->
<div id="olshop-details" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog">
      <div class="modal-content">
        <div class="container mt-3 pb-2 border-bottom">
            <div class="row">
                <div class="col-md-6 d-print-none">
                    <button id="print-btn" type="button" class="btn btn-default btn-sm d-print-none"><i class="dripicons-print"></i> {{trans('file.Print')}}</button>

                </div>
                <div class="col-md-6">
                    <button type="button" id="close-btn" data-dismiss="modal" aria-label="Close" class="close d-print-none"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
                </div>
                <div class="col-md-12">
                    <h3 id="exampleModalLabel" class="modal-title text-center container-fluid">
                        {{$general_setting->site_title}}
                    </h3>
                </div>
                <div class="col-md-12 text-center">
                    <i style="font-size: 15px;">{{trans('file.Delivery Details')}}</i>
                </div>
            </div>
        </div>
        <div class="modal-body">
            <table class="table table-bordered" id="olshop-content">
                <tbody></tbody>
            </table>
            <br>
            <table class="table table-bordered product-olshop-list">
                <thead>
                    <th>No</th>
                    <th>Code</th>
                    <th>Description</th>
                    <th>{{trans('file.Batch No')}}</th>
                    <th>{{trans('file.Expired Date')}}</th>
                    <th>Qty</th>
                </thead>
                <tbody>
                </tbody>
            </table>
            <div id="olshop-footer" class="row">
            </div>
        </div>
      </div>
    </div>
</div>

<div id="edit-olshop" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 id="exampleModalLabel" class="modal-title">{{trans('file.Update Delivery')}}</h5>
                <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
            </div>
            <div class="modal-body">
                {!! Form::open(['route' => 'olshop.update', 'method' => 'post', 'files' => true]) !!}
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>{{trans('file.Delivery Reference')}}</label>
                        <p id="dr"></p>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>{{trans('file.Sale Reference')}}</label>
                        <p id="sr"></p>
                    </div>
                    <div class="col-md-12 form-group">
                        <label>{{trans('file.Status')}} *</label>
                        <select name="status" required class="form-control selectpicker">
                            <option value="1">{{trans('file.Packing')}}</option>
                            <option value="2">{{trans('file.Delivering')}}</option>
                            <option value="3">{{trans('file.Delivered')}}</option>
                        </select>
                    </div>
                    <div class="col-md-6 mt-2 form-group">
                        <label>{{trans('file.Delivered By')}}</label>
                        <input type="text" name="delivered_by" class="form-control">
                    </div>
                    <div class="col-md-6 mt-2 form-group">
                        <label>{{trans('file.Recieved By')}}</label>
                        <input type="text" name="recieved_by" class="form-control">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>{{trans('file.customer')}} *</label>
                        <p id="customer"></p>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>{{trans('file.Attach File')}}</label>
                        <input type="file" name="file" class="form-control">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>{{trans('file.Address')}} *</label>
                        <textarea rows="3" name="address" class="form-control" required></textarea>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>{{trans('file.Note')}}</label>
                        <textarea rows="3" name="note" class="form-control"></textarea>
                    </div>
                </div>
                <input type="hidden" name="reference_no">
                <input type="hidden" name="olshop_id">
                <button type="submit" class="btn btn-primary">{{trans('file.submit')}}</button>
                {{ Form::close() }}
            </div>
        </div>
    </div>
</div>


@endsection

@push('scripts')
<script type="text/javascript">

    $("ul#sale").siblings('a').attr('aria-expanded','true');
    $("ul#sale").addClass("show");
    $("ul#sale #olshop-menu").addClass("active");

    var olshop_id = [];
    var user_verified = <?php echo json_encode(env('USER_VERIFIED')) ?>;

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    $("#print-btn").on("click", function(){
        var divContents = document.getElementById("olshop-details").innerHTML;
        var a = window.open('');
        a.document.write('<html>');
        a.document.write('<body><style>body{font-family: sans-serif;line-height: 1.15;-webkit-text-size-adjust: 100%;}.d-print-none{display:none}.text-center{text-align:center}.row{width:100%;margin-right: -15px;margin-left: -15px;}.col-md-12{width:100%;display:block;padding: 5px 15px;}.col-md-6{width: 50%;float:left;padding: 5px 15px;}table{width:100%;margin-top:30px;}th{text-aligh:left}td{padding:10px}table,th,td{border: 1px solid black; border-collapse: collapse;}#olshop-footer{margin-left:10px}</style><style>@media print {.modal-dialog { max-width: 1000px;} }</style>');
        a.document.write(divContents);
        a.document.write('</body></html>');
        a.document.close();
        setTimeout(function(){a.close();},10);
        a.print();
    });

    function confirmDelete() {
        if (confirm("Are you sure want to delete?")) {
            return true;
        }
        return false;
    }

    $("tr.olshop-link td:not(:first-child, :last-child)").on("click", function() {
        var olshop = $(this).parent().data('olshop');
        var barcode = $(this).parent().data('barcode');
        olshopDetails(olshop, barcode);
    });

    function olshopDetails(olshop, barcode) {
        $('input[name="olshop_id"]').val(olshop[4]);
        $("#olshop-content tbody").remove();
        var newBody = $("<tbody>");
        var rows = '';
        rows += '<tr><td>Date</td><td>'+olshop[0]+'</td></tr>';
        rows += '<tr><td>Delivery Reference</td><td>'+olshop[1]+'</td></tr>';
        rows += '<tr><td>Sale Reference</td><td>'+olshop[2]+'</td></tr>';
        rows += '<tr><td>Status</td><td>'+olshop[3]+'</td></tr>';
        rows += '<tr><td>Customer Name</td><td>'+olshop[5]+'</td></tr>';
        rows += '<tr><td>Address</td><td>'+olshop[7]+', '+olshop[8]+'</td></tr>';
        rows += '<tr><td>Phone Number</td><td>'+olshop[6]+'</td></tr>';
        rows += '<tr><td>Note</td><td>'+olshop[9]+'</td></tr>';

        newBody.append(rows);
        $("table#olshop-content").append(newBody);

        $.get('olshop/product_olshop/' + olshop[4], function(data) {
            $(".product-olshop-list tbody").remove();
            var code = data[0];
            var description = data[1];
            var batch_no = data[2];
            var expired_date = data[3];
            var qty = data[4];
            var newBody = $("<tbody>");
            $.each(code, function(index) {
                var newRow = $("<tr>");
                var cols = '';
                cols += '<td><strong>' + (index+1) + '</strong></td>';
                cols += '<td>' + code[index] + '</td>';
                cols += '<td>' + description[index] + '</td>';
                cols += '<td>' + batch_no[index] + '</td>';
                cols += '<td>' + expired_date[index] + '</td>';
                cols += '<td>' + qty[index] + '</td>';
                newRow.append(cols);
                newBody.append(newRow);
            });
            $("table.product-olshop-list").append(newBody);
        });

        var htmlfooter = '<div class="col-md-4 form-group"><p>Prepared By: '+olshop[10]+'</p></div>';
        htmlfooter += '<div class="col-md-4 form-group"><p>Delivered By: '+olshop[11]+'</p></div>';
        htmlfooter += '<div class="col-md-4 form-group"><p>Recieved By: '+olshop[12]+'</p></div>';
        htmlfooter += '<br><br>';
        htmlfooter += '<div class="col-md-2 offset-md-5"><img style="max-width:850px;height:100%;max-height:130px" src="data:image/png;base64,'+barcode+'" alt="barcode" /></div>';

        $('#olshop-footer').html(htmlfooter);
        $('#olshop-details').modal('show');
    }

    $(document).ready(function() {
        $('.open-EditCategoryDialog').on('click', function(){
        var url ="olshop/"
        var id = $(this).data('id').toString();
        url = url.concat(id).concat("/edit");

        $.get(url, function(data){
                $('#dr').text(data[0]);
                $('#sr').text(data[1]);
                $('select[name="status"]').val(data[2]);
                $('.selectpicker').selectpicker('refresh');
                $('input[name="delivered_by"]').val(data[3]);
                $('input[name="recieved_by"]').val(data[4]);
                $('#customer').text(data[5]);
                $('textarea[name="address"]').val(data[6]);
                $('textarea[name="note"]').val(data[7]);
                $('input[name="reference_no"]').val(data[0]);
                $('input[name="olshop_id"]').val(id);
        });
        $("#edit-olshop").modal('show');
        });
    });

    $('#olshop-table').DataTable( {
        "order": [],
        'language': {
            'lengthMenu': '_MENU_ {{trans("file.records per page")}}',
            "info":      '<small>{{trans("file.Showing")}} _START_ - _END_ (_TOTAL_)</small>',
            "search":  '{{trans("file.Search")}}',
            'paginate': {
                    'previous': '<i class="dripicons-chevron-left"></i>',
                    'next': '<i class="dripicons-chevron-right"></i>'
            }
        },
        'columnDefs': [
            {
                "orderable": false,
                'targets': [0, 6]
            },
            {
                'render': function(data, type, row, meta){
                    if(type === 'display'){
                        data = '<div class="checkbox"><input type="checkbox" class="dt-checkboxes"><label></label></div>';
                    }

                    return data;
                },
                'checkboxes': {
                    'selectRow': true,
                    'selectAllRender': '<div class="checkbox"><input type="checkbox" class="dt-checkboxes"><label></label></div>'
                },
                'targets': [0]
            }
        ],
        'select': { style: 'multi',  selector: 'td:first-child'},
        'lengthMenu': [[10, 25, 50, -1], [10, 25, 50, "All"]],
        dom: '<"row"lfB>rtip',
        buttons: [
            {
                extend: 'pdf',
                text: '<i title="export to pdf" class="fa fa-file-pdf-o"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported)',
                    rows: ':visible'
                },
            },
            {
                extend: 'excel',
                text: '<i title="export to excel" class="dripicons-document-new"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported)',
                    rows: ':visible'
                },
            },
            {
                extend: 'csv',
                text: '<i title="export to csv" class="fa fa-file-text-o"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported)',
                    rows: ':visible'
                },
            },
            {
                extend: 'print',
                text: '<i title="print" class="fa fa-print"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported)',
                    rows: ':visible'
                },
            },
            {
                text: '<i title="delete" class="dripicons-cross"></i>',
                className: 'buttons-delete',
                action: function ( e, dt, node, config ) {
                    if(user_verified == '1') {
                        olshop_id.length = 0;
                        $(':checkbox:checked').each(function(i){
                            if(i){
                                olshop_id[i-1] = $(this).closest('tr').data('id');
                            }
                        });
                        if(olshop_id.length && confirm("Are you sure want to delete?")) {
                            $.ajax({
                                type:'POST',
                                url:'olshop/deletebyselection',
                                data:{
                                    olshopIdArray: olshop_id
                                },
                                success:function(data){
                                    alert(data);
                                }
                            });
                            dt.rows({ page: 'current', selected: true }).remove().draw(false);
                        }
                        else if(!olshop_id.length)
                            alert('Nothing is selected!');
                    }
                    else
                        alert('This feature is disable for demo!');
                }
            },
            {
                extend: 'colvis',
                text: '<i title="column visibility" class="fa fa-eye"></i>',
                columns: ':gt(0)'
            },
        ],
    } );
</script>
@endpush
